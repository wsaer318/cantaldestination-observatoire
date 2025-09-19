<?php
/**
 * CantalDestination - Helpers d'authentification API
 * Fichier: api/database/_auth_helpers.php
 * Utilitaires d'authentification et helpers communs API
 * - Auth Bearer (avec détection robuste de l'en-tête)
 * - Fallback X-Api-Key
 * - Session applicative (Auth::isAuthenticated())
 * - Allowlist IP optionnelle
 * - Rôle admin optionnel (ADMIN_API_TOKENS ou Auth::isAdmin())
 * - Rate-limit optionnel (APCu) par token/IP + scope
 * - Audit file logging (opérations sensibles)
 */

declare(strict_types=1);

/**
 * Répond en JSON et termine. Supposé fourni par app.php, mais on garde une
 * fonction de secours si indisponible (no-op headers minimalistes).
 */
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/** Nettoyage + booléens */
function normalize_uc(string $s): string { $s = trim(preg_replace('/\s+/u', ' ', $s)); return mb_strtoupper($s, 'UTF-8'); }
function boolish($v): bool { if ($v===null) return false; $v = strtolower(trim((string)$v)); return in_array($v, ['1','true','yes','on','y'], true); }

/** JSON Body strict (lance JsonException si invalide) */
function get_json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('JSON invalide');
    return $data;
}

/** Test mode via header/query/payload */
function want_test_mode(?array $payload=null): bool {
    if (isset($_SERVER['HTTP_X_TEST_MODE'])) return boolish($_SERVER['HTTP_X_TEST_MODE']);
    if (isset($_GET['test'])) return boolish($_GET['test']);
    if ($payload && isset($payload['options']['test_mode'])) return (bool)$payload['options']['test_mode'];
    if ($payload && isset($payload['test_mode'])) return (bool)$payload['test_mode'];
    return false;
}
function test_suffix(bool $test): string { return $test ? '_test' : ''; }

/** Client IP (optionnellement derrière proxy) */
function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (boolish(getenv('API_TRUST_PROXY') ?: '0')) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) { // prend la première IP
            $parts = array_map('trim', explode(',', $xff));
            if ($parts && filter_var($parts[0], FILTER_VALIDATE_IP)) return $parts[0];
        }
    }
    return $ip;
}

/** CIDR match simple */
function cidr_match(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) return $ip === $cidr;
    [$subnet, $mask] = explode('/', $cidr, 2);
    $mask = (int)$mask;
    $ipLong = ip2long($ip);
    $subLong = ip2long($subnet);
    if ($ipLong === false || $subLong === false) return false;
    $maskLong = -1 << (32 - $mask);
    return ($ipLong & $maskLong) === ($subLong & $maskLong);
}

/** Allowlist IP (si configurée) */
function ip_allowed(?string $ip = null): bool {
    $ip = $ip ?: client_ip();
    $list = trim((string)(getenv('API_IP_ALLOWLIST') ?: ''));
    if ($list === '') return true; // non configurée = tout autorisé
    foreach (array_filter(array_map('trim', explode(',', $list))) as $rule) {
        if ($rule === '*') return true;
        if (cidr_match($ip, $rule)) return true;
    }
    return false;
}

/** Récupération robuste du token API */
function get_bearer_token(): ?string {
    // Sources possibles selon SAPI et reverse proxy
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
    ];
    if (function_exists('apache_request_headers')) {
        $hdrs = apache_request_headers();
        if (!empty($hdrs['Authorization'])) $candidates[] = $hdrs['Authorization'];
    }
    // Fallback X-Api-Key (clé directe sans préfixe)
    $xKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

    foreach ($candidates as $h) {
        if (!$h) continue;
        if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return trim($m[1]);
        // si le header vaut directement la clé
        if (preg_match('/^[A-Za-z0-9_\-\.]{8,}$/', trim($h))) return trim($h);
    }
    if ($xKey && preg_match('/^[A-Za-z0-9_\-\.]{8,}$/', trim($xKey))) return trim($xKey);
    return null;
}

/** Vérifie si un token est admin */
function is_admin_token(?string $token): bool {
    if (!$token) return false;
    $admins = array_filter(array_map('trim', explode(',', getenv('ADMIN_API_TOKENS') ?: '')));
    return in_array($token, $admins, true);
}

/** Rate limit (APCu) — ex: API_RATE_LIMIT="60/60" => 60 req par 60s */
function rate_limit_ok(string $scope, string $subject): bool {
    $cfg = trim((string)(getenv('API_RATE_LIMIT') ?: ''));
    if ($cfg === '') return true; // désactivé
    if (!function_exists('apcu_fetch')) return true; // APCu non dispo

    [$limit, $window] = array_map('intval', array_pad(explode('/', $cfg, 2), 2, 60));
    if ($limit <= 0 || $window <= 0) return true;

    $bucket = (int)floor(time() / $window);
    $key = sprintf('rl:%s:%s:%d', $scope, sha1($subject), $bucket);
    $count = apcu_fetch($key);
    if ($count === false) {
        apcu_store($key, 1, $window + 5);
        return true;
    }
    if ($count < $limit) {
        apcu_inc($key);
        return true;
    }
    return false;
}

/** Audit file logging minimal (append) */
function audit_log(string $action, array $extra = []): void {
    $path = (string)(getenv('API_AUDIT_LOG') ?: '');
    if ($path === '') return; // désactivé
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = json_encode([
        'ts' => date('c'),
        'ip' => client_ip(),
        'action' => $action,
        'extra' => $extra
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
}

/**
 * Authz principale
 * $opts = [
 *   'require_admin' => bool (false),
 *   'restrict_ips'  => bool (false),
 *   'scope'         => string pour rate-limit (ex: 'schema.ensure')
 * ]
 */
function require_api_auth_or_session(array $opts = []): void {
    $requireAdmin = (bool)($opts['require_admin'] ?? false);
    $restrictIPs  = (bool)($opts['restrict_ips'] ?? false);
    $scope        = (string)($opts['scope'] ?? 'api');

    if ($restrictIPs && !ip_allowed()) {
        jsonResponse(['error' => 'Accès refusé depuis cette IP'], 403);
    }

    $subject = client_ip();
    $token = get_bearer_token();
    $validTokens = array_filter(array_map('trim', explode(',', getenv('API_TOKENS') ?: '')));

    // 1) Token API
    if ($token && in_array($token, $validTokens, true)) {
        if (!rate_limit_ok($scope, $token)) {
            jsonResponse(['error' => 'Trop de requêtes (rate-limit)'], 429);
        }
        if ($requireAdmin && !is_admin_token($token)) {
            jsonResponse(['error' => 'Privilèges insuffisants (admin requis)'], 403);
        }
        return;
    }

    // 2) Session applicative
    if (class_exists('Auth') && \Auth::isAuthenticated()) {
        if (!rate_limit_ok($scope, $subject)) {
            jsonResponse(['error' => 'Trop de requêtes (rate-limit)'], 429);
        }
        if ($requireAdmin && method_exists('Auth', 'isAdmin') && !\Auth::isAdmin()) {
            jsonResponse(['error' => 'Privilèges insuffisants (admin requis)'], 403);
        }
        return;
    }

    jsonResponse(['error' => 'Authentification requise (Bearer token ou session).'], 401);
}