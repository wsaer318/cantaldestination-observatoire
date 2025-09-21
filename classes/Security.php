<?php

/**
 * Classe de sÃ©curitÃ© complÃ¨te pour FluxVision
 * GÃ¨re la protection contre les attaques courantes et la sÃ©curisation de l'application
 */
class Security {
    
    // Configuration des limites de sÃ©curitÃ©
    private static $config = [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'session_timeout' => 3600, // 1 heure
        'csrf_token_lifetime' => 3600,
        'password_min_length' => 8,
        'password_require_special' => true,
        'max_file_upload_size' => 5242880, // 5MB
        'allowed_file_extensions' => ['pdf', 'xlsx', 'csv', 'png', 'jpg', 'jpeg'],
    ];
    
    /**
     * Initialise les protections de sÃ©curitÃ© de base
     */
    public static function initialize() {
        // Configuration de session sÃ©curisÃ©e
        self::configureSecureSession();
        
        // Headers de sÃ©curitÃ©
        self::setSecurityHeaders();
        
        // Protection CSRF
        self::initCSRFProtection();
        
        // Nettoyage automatique des tentatives de connexion
        self::cleanupOldAttempts();
        
        // VÃ©rification du timeout de session
        self::checkSessionTimeout();
    }
    
    /**
     * Configuration sÃ©curisÃ©e des sessions PHP
     */
    private static function configureSecureSession() {
        // Ne pas configurer les sessions en mode CLI
        if (php_sapi_name() === 'cli') {
            return;
        }
        
        // Configuration sÃ©curisÃ©e de session
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', self::$config['session_timeout']);
            
            // Nom de session alÃ©atoire
            session_name('CANTALDESTINATION_' . substr(md5(__DIR__), 0, 8));
            
            session_start();
            
            // RÃ©gÃ©nÃ©ration pÃ©riodique de l'ID de session
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * DÃ©finit les headers de sÃ©curitÃ© HTTP
     */
    private static function setSecurityHeaders() {
        // VÃ©rifier si nous sommes dans un contexte web
        if (php_sapi_name() === 'cli' || headers_sent()) {
            return;
        }
        
        // Protection contre le clickjacking
        // Permettre l'iframe en mode embed pour les prÃ©visualisations
        if (isset($_GET['embed']) && $_GET['embed'] === '1') {
            header('X-Frame-Options: SAMEORIGIN');
        } else {
            header('X-Frame-Options: DENY');
        }
        
        // Protection contre le MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Protection XSS basique
        header('X-XSS-Protection: 1; mode=block');
        
        // Référence politique stricte
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "media-src 'self' data:; " .
               "connect-src 'self' https://geo.api.gouv.fr https://raw.githubusercontent.com; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'";
        
        // Permettre les iframes en mode embed
        if (isset($_GET['embed']) && $_GET['embed'] === '1') {
            $csp .= "; frame-ancestors 'self'";
        } else {
            $csp .= "; frame-ancestors 'none'";
        }
        
        header('Content-Security-Policy: ' . $csp);
        
        // HSTS (si HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Initialise la protection CSRF
     */
    private static function initCSRFProtection() {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) || 
            time() - $_SESSION['csrf_token_time'] > self::$config['csrf_token_lifetime']) {
            
            // Conserver l'ancien pour une courte pÃ©riode de grÃ¢ce
            if (!empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token_prev'] = $_SESSION['csrf_token'];
                $_SESSION['csrf_token_prev_time'] = $_SESSION['csrf_token_time'] ?? (time()-1);
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
    }
    
    /**
     * GÃ©nÃ¨re un token CSRF pour les formulaires
     */
    public static function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    /**
     * VÃ©rifie la validitÃ© d'un token CSRF
     */
    public static function validateCSRFToken($token) {
        if (empty($token)) return false;
        $current = $_SESSION['csrf_token'] ?? '';
        if ($current && hash_equals($current, $token)) return true;
        // GrÃ¢ce si le token vient juste d'Ãªtre rÃ©gÃ©nÃ©rÃ©
        $prev   = $_SESSION['csrf_token_prev'] ?? '';
        $prevAt = $_SESSION['csrf_token_prev_time'] ?? 0;
        if ($prev && (time() - (int)$prevAt) <= self::$config['csrf_token_lifetime']) {
            return hash_equals($prev, $token);
        }
        return false;
    }
    
    /**
     * Protection contre les attaques par force brute
     */
    public static function checkBruteForce($identifier, $type = 'login') {
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        // CrÃ©er la table des tentatives si elle n'existe pas
        self::createSecurityTables();
        
        // VÃ©rifier les tentatives rÃ©centes
        $stmt = $connection->prepare(
            "SELECT COUNT(*) as attempts, MAX(created_at) as last_attempt 
             FROM security_attempts 
             WHERE identifier = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $type, self::$config['lockout_duration']]);
        $result = $stmt->fetch();
        
        if ($result['attempts'] >= self::$config['max_login_attempts']) {
            $timeLeft = self::$config['lockout_duration'] - (time() - strtotime($result['last_attempt']));
            throw new SecurityException("Trop de tentatives. Veuillez rÃ©essayer dans " . ceil($timeLeft/60) . " minutes.");
        }
        
        return true;
    }
    
    /**
     * Enregistre une tentative Ã©chouÃ©e
     */
    public static function recordFailedAttempt($identifier, $type = 'login') {
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        $stmt = $connection->prepare(
            "INSERT INTO security_attempts (identifier, type, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $identifier,
            $type,
            self::getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
    
    /**
     * Nettoie les anciennes tentatives
     */
    private static function cleanupOldAttempts() {
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        try {
            $stmt = $connection->prepare(
                "DELETE FROM security_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $stmt->execute([self::$config['lockout_duration'] * 2]);
        } catch (Exception $e) {
            // Table n'existe pas encore, sera crÃ©Ã©e Ã  la premiÃ¨re tentative
        }
    }
    
    /**
     * VÃ©rification du timeout de session
     */
    private static function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::$config['session_timeout']) {
                session_destroy();
                header('Location: ' . url('/login?timeout=1'));
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Sanitise les donnÃ©es d'entrÃ©e
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Valide la force d'un mot de passe
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < self::$config['password_min_length']) {
            $errors[] = "Le mot de passe doit contenir au moins " . self::$config['password_min_length'] . " caractÃ¨res.";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une majuscule.";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une minuscule.";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
        }
        
        if (self::$config['password_require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractÃ¨re spÃ©cial.";
        }
        
        // VÃ©rifier les mots de passe communs
        $commonPasswords = ['password', '123456', 'admin', 'root', 'user', 'test', 'guest'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Ce mot de passe est trop commun.";
        }
        
        return $errors;
    }
    
    /**
     * Validation sÃ©curisÃ©e des fichiers uploadÃ©s
     */
    public static function validateFileUpload($file) {
        $errors = [];
        
        // VÃ©rifier la taille
        if ($file['size'] > self::$config['max_file_upload_size']) {
            $errors[] = "Le fichier est trop volumineux.";
        }
        
        // VÃ©rifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$config['allowed_file_extensions'])) {
            $errors[] = "Type de fichier non autorisÃ©.";
        }
        
        // VÃ©rifier le type MIME
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg'
        ];
        
        if (isset($allowedMimes[$extension])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mimeType !== $allowedMimes[$extension]) {
                $errors[] = "Le contenu du fichier ne correspond pas Ã  son extension.";
            }
        }
        
        return $errors;
    }
    
    /**
     * Obtient l'adresse IP rÃ©elle du client
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Journalisation des Ã©vÃ©nements de sÃ©curitÃ©
     */
    public static function logSecurityEvent($event, $details = [], $level = 'INFO') {
        // CrÃ©er le dossier logs s'il n'existe pas
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $sessionId = session_id();
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'session_hash' => $sessionId ? hash('sha256', $sessionId) : null,
            'user_id' => Auth::isAuthenticated() ? (Auth::getUser()['id'] ?? null) : null,
            'details' => $details
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($logDir . '/security.log', $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * CrÃ©e les tables de sÃ©curitÃ© nÃ©cessaires
     */
    private static function createSecurityTables() {
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        // Table des tentatives de connexion
        $sql = "CREATE TABLE IF NOT EXISTS security_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_identifier_type (identifier, type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $connection->exec($sql);
        
        // Table des Ã©vÃ©nements de sÃ©curitÃ©
        $sql = "CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            user_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            details JSON,
            severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_severity (severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $connection->exec($sql);
    }
    
    /**
     * Valide une URL pour Ã©viter les redirections malveillantes
     */
    public static function validateRedirectURL($url) {
        // Accepter seulement les URLs relatives ou de mÃªme domaine
        if (empty($url) || $url[0] === '/') {
            return true;
        }
        
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }
        
        $allowedHosts = [$_SERVER['HTTP_HOST']];
        return in_array($parsed['host'], $allowedHosts);
    }
    
    /**
     * GÃ©nÃ¨re un nonce pour CSP
     */
    public static function generateNonce() {
        if (!isset($_SESSION['csp_nonce'])) {
            $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
        }
        return $_SESSION['csp_nonce'];
    }
    
    /**
     * VÃ©rifie si l'utilisateur actuel a les permissions requises
     */
    public static function checkPermission($required_role = 'user') {
        if (!Auth::isAuthenticated()) {
            return false;
        }
        
        $user = Auth::getUser();
        $user_role = $user['role'] ?? 'user';
        
        $roleHierarchy = ['user' => 1, 'admin' => 2, 'superadmin' => 3];
        
        return ($roleHierarchy[$user_role] ?? 0) >= ($roleHierarchy[$required_role] ?? 999);
    }
}

/**
 * Exception de sÃ©curitÃ© personnalisÃ©e
 */
class SecurityException extends Exception {
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        
        // Journaliser automatiquement les exceptions de sÃ©curitÃ©
        Security::logSecurityEvent('SECURITY_EXCEPTION', [
            'message' => $message,
            'code' => $code,
            'trace' => $this->getTraceAsString()
        ], 'ERROR');
    }
} 

