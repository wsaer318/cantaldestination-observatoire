<?php

/**
 * Gestionnaire de sécurité avancé pour FluxVision
 * Gère rate limiting, headers de sécurité, et autres mesures de protection
 */
class SecurityManager {
    
    private static $rateLimitStorage = [];
    private static $securityConfig = [
        'rate_limit' => [
            'login_attempts' => 5,        // Tentatives max par IP
            'login_window' => 900,        // Fenêtre de temps (15 min)
            'api_requests' => 100,        // Requêtes API max par IP
            'api_window' => 3600,         // Fenêtre API (1 heure)
        ],
        'session' => [
            'timeout' => 3600,            // Session timeout (1 heure)
            'regenerate_interval' => 300, // Régénération ID session (5 min)
        ]
    ];

    /**
     * Initialise les headers de sécurité HTTP
     */
    public static function setSecurityHeaders(): void {
        // Ne pas envoyer de headers si ils ont déjà été envoyés
        if (headers_sent()) {
            return;
        }
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; media-src 'self' data:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://geo.api.gouv.fr https://raw.githubusercontent.com; frame-ancestors 'none';");
        
        // Sécurité XSS
        header("X-XSS-Protection: 1; mode=block");
        
        // Empêcher le MIME sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Empêcher l'embedding en iframe
        header("X-Frame-Options: DENY");
        
        // Référer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Feature Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        
        // HSTS (si HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
        
        // Cache control pour les pages sensibles
        if (self::isSensitivePage()) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
        }
    }

    /**
     * Vérifie si la page actuelle est sensible (admin, login, etc.)
     */
    private static function isSensitivePage(): bool {
        // Vérifier si REQUEST_URI existe (pas en CLI)
        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }
        
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!$path) {
            return false;
        }
        
        $sensitivePaths = ['/admin', '/login', '/logout', '/api/'];
        
        foreach ($sensitivePaths as $sensitivePath) {
            if (strpos($path, $sensitivePath) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rate limiting pour les tentatives de connexion
     */
    public static function checkLoginRateLimit(string $identifier = null): bool {
        $identifier = $identifier ?? self::getClientIdentifier();
        $key = "login_attempts_" . $identifier;
        
        return self::checkRateLimit(
            $key,
            self::$securityConfig['rate_limit']['login_attempts'],
            self::$securityConfig['rate_limit']['login_window']
        );
    }

    /**
     * Rate limiting pour les requêtes API
     */
    public static function checkApiRateLimit(string $identifier = null): bool {
        $identifier = $identifier ?? self::getClientIdentifier();
        $key = "api_requests_" . $identifier;
        
        return self::checkRateLimit(
            $key,
            self::$securityConfig['rate_limit']['api_requests'],
            self::$securityConfig['rate_limit']['api_window']
        );
    }

    /**
     * Logique générique de rate limiting
     */
    private static function checkRateLimit(string $key, int $maxAttempts, int $timeWindow): bool {
        $now = time();
        
        // Nettoyer les anciennes entrées
        self::cleanOldEntries($key, $timeWindow);
        
        // Récupérer les tentatives actuelles
        if (!isset(self::$rateLimitStorage[$key])) {
            self::$rateLimitStorage[$key] = [];
        }
        
        $attempts = self::$rateLimitStorage[$key];
        $recentAttempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        if (count($recentAttempts) >= $maxAttempts) {
            self::logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                'key' => $key,
                'attempts' => count($recentAttempts),
                'max_attempts' => $maxAttempts,
                'client' => self::getClientIdentifier()
            ], 'HIGH');
            return false;
        }
        
        return true;
    }

    /**
     * Enregistre une tentative pour le rate limiting
     */
    public static function recordAttempt(string $type, string $identifier = null): void {
        $identifier = $identifier ?? self::getClientIdentifier();
        $key = $type . "_" . $identifier;
        
        if (!isset(self::$rateLimitStorage[$key])) {
            self::$rateLimitStorage[$key] = [];
        }
        
        self::$rateLimitStorage[$key][] = time();
    }

    /**
     * Nettoie les anciennes entrées du rate limiting
     */
    private static function cleanOldEntries(string $key, int $timeWindow): void {
        if (!isset(self::$rateLimitStorage[$key])) {
            return;
        }
        
        $now = time();
        self::$rateLimitStorage[$key] = array_filter(
            self::$rateLimitStorage[$key],
            function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            }
        );
    }

    /**
     * Obtient un identifiant unique du client
     */
    private static function getClientIdentifier(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        return hash('sha256', $ip . '|' . $userAgent);
    }

    /**
     * Validation et sanitisation des entrées
     */
    public static function sanitizeInput(string $input, string $type = 'string'): string {
        // Trim whitespace
        $input = trim($input);
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'username':
                // Seulement alphanumériques et quelques caractères spéciaux
                return preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            case 'sql':
                // ATTENTION: Utiliser UNIQUEMENT des prepared statements
                // Cette méthode n'est qu'un fallback de dernier recours
                return filter_var($input, FILTER_SANITIZE_SPECIAL_CHARS);
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validation robuste des mots de passe
     */
    public static function validatePassword(string $password): array {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une majuscule";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une minuscule";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre";
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
        }
        
        // Vérifier contre les mots de passe communs
        $commonPasswords = ['password', '123456', 'admin', 'qwerty', 'azerty', 'password123'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Ce mot de passe est trop commun";
        }
        
        return $errors;
    }

    /**
     * Gestion sécurisée des sessions
     */
    public static function secureSession(): void {
        // Ne pas configurer les sessions si les headers sont déjà envoyés
        if (headers_sent()) {
            return;
        }
        
        // Configuration sécurisée des sessions AVANT session_start()
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_lifetime', 0); // Session cookie
            
            session_start();
        }
        
        // Régénération périodique de l'ID de session
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        }
        
        if (time() - $_SESSION['last_regeneration'] > self::$securityConfig['session']['regenerate_interval']) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Timeout de session
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::$securityConfig['session']['timeout']) {
                session_destroy();
                header('Location: ' . url('/login?timeout=1'));
                exit;
            }
        }
        
        $_SESSION['last_activity'] = time();
    }

    /**
     * Logging des événements de sécurité
     */
    public static function logSecurityEvent(string $event, array $data = [], string $severity = 'INFO'): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'severity' => $severity,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data,
            'session_id' => session_id()
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        $logFile = __DIR__ . '/../logs/security.log';
        
        // Créer le dossier logs s'il n'existe pas
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Si c'est critique, log aussi dans le log système
        if ($severity === 'HIGH' || $severity === 'CRITICAL') {
            error_log("FluxVision Security Alert: $event - " . json_encode($data));
        }
    }

    /**
     * Vérifie si une IP est bloquée
     */
    public static function isIpBlocked(string $ip = null): bool {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        $identifier = hash('sha256', $ip);
        
        // Vérifier si trop de tentatives de connexion
        if (!self::checkLoginRateLimit($identifier)) {
            return true;
        }
        
        return false;
    }

    /**
     * Initialisation complète de la sécurité
     */
    public static function initialize(): void {
        // Headers de sécurité
        self::setSecurityHeaders();
        
        // Session sécurisée
        self::secureSession();
        
        // Vérifier si l'IP est bloquée
        if (self::isIpBlocked()) {
            http_response_code(429);
            self::logSecurityEvent('IP_BLOCKED', ['ip' => $_SERVER['REMOTE_ADDR']], 'HIGH');
            die('Trop de tentatives. Veuillez réessayer plus tard.');
        }
        
        // Log de l'accès
        self::logSecurityEvent('PAGE_ACCESS', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]);
    }
} 