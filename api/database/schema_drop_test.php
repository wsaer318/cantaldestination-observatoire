<?php
/**
 * Admin API: suppression des tables *_test (ULTRA restreint)
 * Fichier : api/database/schema_drop_test.php
 * Méthode : POST (JSON)
 *
 * Garde-fous cumulés :
 *   - Auth admin obligatoire (token ∈ ADMIN_API_TOKENS ou session admin)
 *   - Allowlist IP (API_IP_ALLOWLIST)
 *   - API_SCHEMA_ENABLE=1 requis
 *   - Confirme "confirm":"DROP" dans le body pour exécuter
 *   - Filtrage strict : ne supprime QUE les tables dont le nom finit par "_test"
 *
 * Body JSON (exemples) :
 *   { "type":"all", "dry_run": true }
 *   { "type":"dim", "dry_run": false, "confirm":"DROP" }
 *   { "tables":["dim_pays_test","dim_communes_test"], "confirm":"DROP" }
 *
 * Champs :
 *   - type: "all"|"dim"|"fact"|"temp"|"system"  (défaut: "all") — filtre par famille
 *   - tables: [noms]  (optionnel) — liste explicite ; on ignore tout ce qui n’est pas *_test
 *   - dry_run: bool   (défaut: false) — mode simulation, ne fait pas de DROP
 *   - confirm: "DROP" (requis si dry_run=false) — confirmation explicite
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once dirname(dirname(__DIR__)) . '/classes/Auth.php';
require_once dirname(dirname(__DIR__)) . '/classes/Database.php';
require_once __DIR__ . '/_auth_helpers.php';

// Auth renforcée : admin + IP + rate-limit scope
require_api_auth_or_session([
    'require_admin' => true,
    'restrict_ips'  => true,
    'scope'         => 'schema.drop_test'
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
}

// Garde-fou d’activation globale
if ((getenv('API_SCHEMA_ENABLE') ?: '') !== '1') {
    jsonResponse(['error' => "API Schema désactivée. Activez API_SCHEMA_ENABLE=1 pour autoriser la suppression (_test)."], 403);
}

// Compat PHP<8
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        $len = strlen($needle);
        if ($len === 0) return true;
        return substr($haystack, -$len) === $needle;
    }
}

/** Nom de table simple (lettres/chiffres/underscore, commence par lettre) */
function ok_name(string $name): bool {
    return (bool)preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name);
}

/** Filtre SQL pour "famille" (dim/fact/temp/system/all), en plus du suffixe _test */
function type_filters(string $type): array {
    switch ($type) {
        case 'dim':    return ["table_name LIKE 'dim\\_%'"];
        case 'fact':   return ["table_name LIKE 'fact\\_%'"];
        case 'temp':   return ["table_name LIKE '%\\_temp%'"];
        case 'system': return ["table_name NOT LIKE 'dim\\_%'", "table_name NOT LIKE 'fact\\_%'", "table_name NOT LIKE '%\\_temp%'"];
        case 'all':
        default:       return [];
    }
}

try {
    $cfg = \DatabaseConfig::getConfig();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'],$cfg['port'],$cfg['database'],$cfg['charset']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $payload   = get_json_body();
    $type      = strtolower(trim((string)($payload['type'] ?? 'all')));
    $tablesIn  = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
    $dryRun    = (bool)($payload['dry_run'] ?? false);
    $confirm   = (string)($payload['confirm'] ?? '');

    if (!$dryRun && $confirm !== 'DROP') {
        jsonResponse(['error' => 'Confirmation manquante. Pour exécuter, envoyez "confirm":"DROP".', 'hint'=>'Utilisez dry_run=true pour simuler.'], 400);
    }

    // 1) Récupère la liste des candidates depuis information_schema
    $conds = ["table_schema = :db", "table_name LIKE '%\\_test'"];
    $conds = array_merge($conds, type_filters($type));
    $where = implode(' AND ', $conds);

    $sql = "SELECT table_name FROM information_schema.tables WHERE $where ORDER BY table_name";
    $st  = $pdo->prepare($sql);
    $st->execute([':db' => $cfg['database']]);
    $candidates = array_map(fn($r) => $r['table_name'], $st->fetchAll());

    // 2) Si liste explicite fournie, on intersecte (et on ignore tout ce qui n’est pas *_test)
    $explicit = [];
    foreach ($tablesIn as $t) {
        if (!is_string($t)) continue;
        $t = trim($t);
        if ($t === '') continue;
        if (!ok_name($t)) continue;
        if (!str_ends_with($t, '_test')) continue; // garde-fou principal
        $explicit[] = $t;
    }
    if ($explicit) {
        $whitelist = array_flip($explicit);
        $candidates = array_values(array_filter($candidates, fn($n) => isset($whitelist[$n])));
    }

    // Rien à faire ?
    if (!$candidates) {
        $resp = [
            'success'   => true,
            'dry_run'   => $dryRun,
            'type'      => $type,
            'dropped'   => [],
            'skipped'   => [],
            'failed'    => [],
            'count'     => ['to_drop' => 0, 'dropped' => 0, 'failed' => 0]
        ];
        audit_log('schema.drop_test', ['dry_run'=>$dryRun, 'type'=>$type, 'to_drop'=>0]);
        jsonResponse($resp);
    }

    // 3) Soit on simule, soit on DROP une par une (collecte des résultats)
    $dropped = [];
    $failed  = [];
    $skipped = []; // (pas utilisé ici, on garde pour symétrie)

    if ($dryRun) {
        $resp = [
            'success' => true,
            'dry_run' => true,
            'type'    => $type,
            'to_drop' => $candidates,
            'count'   => ['to_drop' => count($candidates)]
        ];
        audit_log('schema.drop_test', ['dry_run'=>true, 'type'=>$type, 'to_drop'=>count($candidates)]);
        jsonResponse($resp);
    }

    foreach ($candidates as $tbl) {
        // Double garde-fou à l’exécution
        if (!ok_name($tbl) || !str_ends_with($tbl, '_test')) {
            $skipped[] = $tbl;
            continue;
        }
        try {
            $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
            $dropped[] = $tbl;
        } catch (Throwable $e) {
            $failed[] = ['table'=>$tbl, 'error'=>$e->getMessage()];
        }
    }

    $resp = [
        'success' => count($failed) === 0,
        'dry_run' => false,
        'type'    => $type,
        'dropped' => $dropped,
        'skipped' => $skipped,
        'failed'  => $failed,
        'count'   => [
            'requested' => count($candidates),
            'dropped'   => count($dropped),
            'skipped'   => count($skipped),
            'failed'    => count($failed),
        ]
    ];

    audit_log('schema.drop_test', ['dry_run'=>false, 'type'=>$type, 'dropped'=>count($dropped), 'failed'=>count($failed)]);
    jsonResponse($resp);

} catch (JsonException $e) {
    jsonResponse(['error' => 'JSON invalide', 'details' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('SCHEMA DROP_TEST API error: '.$e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
