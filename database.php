<?php
/**
 * CantalDestination - Configuration de base de donnÃ©es
 * Fichier : database.php
 * Configuration de base de donnÃ©es CantalDestination avec dÃ©tection d'environnement
 * DÃ©tecte automatiquement si on est en local ou en production
 */

// Protection contre la redÃ©claration de classe
if (!class_exists('DatabaseConfig')) {

class DatabaseConfig {

    private static function env(string $key, $default = null) {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    public static function getEnvironment() {
        $forced = self::env('APP_ENV');
        if ($forced !== null && $forced !== '') {
            return strtolower($forced);
        }
        return self::detectEnvironmentLegacy();
    }

    private static function detectEnvironmentLegacy() {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        error_log("CantalDestination Environment Detection - Host: $host");

        if (strpos($host, 'cantal-destination.com') !== false ||
            strpos($host, 'observatoire.cantal-destination.com') !== false) {
            error_log('CantalDestination Environment Detection - PRODUCTION detected (domain match)');
            return 'production';
        }

        $serverAddr = $_SERVER['SERVER_ADDR'] ?? 'unknown';
        if (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' &&
            $_SERVER['SERVER_ADDR'] !== '::1' &&
            !preg_match('/^192\\.168\./', $_SERVER['SERVER_ADDR']) &&
            !preg_match('/^10\\./', $_SERVER['SERVER_ADDR'])) {
            error_log("CantalDestination Environment Detection - PRODUCTION detected (IP match: $serverAddr)");
            return 'production';
        }

        error_log("CantalDestination Environment Detection - LOCAL detected (Host: $host, IP: $serverAddr)");
        return 'local';
    }

    private static function buildConfig(string $context) {
        $isLocal = ($context !== 'production');
        $suffix = $isLocal ? 'LOCAL' : 'PROD';

        $host = self::env('DB_HOST_' . $suffix, self::env('DB_HOST', 'localhost'));
        $port = (int) self::env('DB_PORT_' . $suffix, self::env('DB_PORT', $isLocal ? 3307 : 3306));
        $database = self::env('DB_NAME_' . $suffix, self::env('DB_DATABASE', $isLocal ? 'fluxvision' : 'observatoire'));
        $username = self::env('DB_USER_' . $suffix, self::env('DB_USERNAME', $isLocal ? 'root' : 'observatoire'));
        $password = self::env('DB_PASSWORD_' . $suffix, self::env('DB_PASSWORD', $isLocal ? '' : 'Sf4d8gsfsdGsg59sqq54g'));
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        $options = self::defaultOptions($context);

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
            'environment' => $context,
            'options' => $options
        ];
    }

    private static function defaultOptions(string $context) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];

        if ($context === 'production') {
            $timeout = (int) self::env('DB_TIMEOUT_PROD', self::env('DB_TIMEOUT', 30));
            if ($timeout > 0) {
                $options[PDO::ATTR_TIMEOUT] = $timeout;
            }
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        return $options;
    }

    public static function isProduction() {
        return self::getEnvironment() === 'production';
    }

    public static function getConfig() {
        $environment = self::getEnvironment();
        return self::buildConfig($environment);
    }

    public static function getCurrentEnvironment() {
        $config = self::getConfig();
        return $config['environment'];
    }

    public static function logCurrentConfig() {
        $config = self::getConfig();
        $logConfig = $config;
        $logConfig['password'] = '***HIDDEN***';

        error_log("CantalDestination DB Config: " . json_encode($logConfig));
    }

    public static function getConnection() {
        $config = self::getConfig();

        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ];

            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            return $pdo;

        } catch (PDOException $e) {
            $env = $config['environment'] ?? 'unknown';
            error_log("Erreur connexion DatabaseConfig::getConnection() ($env): " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
}
} // Fin de la protection class_exists('DatabaseConfig')

// Protection contre la redÃ©claration de CantalDestinationDatabase
if (!class_exists('CantalDestinationDatabase')) {

class CantalDestinationDatabase {
    private static $instance = null;
    private $connection;
    private $config;
    
    private function __construct() {
        // Obtenir la configuration selon l'environnement
        $this->config = DatabaseConfig::getConfig();
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            // Log de la configuration utilisÃ©e (pour debug)
            DatabaseConfig::logCurrentConfig();
            
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );
            
            // Utiliser les options dÃ©finies dans la configuration
            $options = $this->config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ];
            
            // Tentative de connexion avec retry en cas d'Ã©chec temporaire
            $maxRetries = 3;
            $retryDelay = 1; // secondes
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
                    
                    // Si on arrive ici, la connexion a rÃ©ussi
                    if ($attempt > 1) {
                        error_log("CantalDestination: Connexion rÃ©ussie Ã  la tentative $attempt");
                    }
                    break;
                    
                } catch (PDOException $e) {
                    if ($attempt === $maxRetries) {
                        // DerniÃ¨re tentative, on relance l'exception
                        throw $e;
                    }
                    
                    // Attendre avant la prochaine tentative
                    error_log("CantalDestination: Tentative de connexion $attempt Ã©chouÃ©e, retry dans {$retryDelay}s...");
                    sleep($retryDelay);
                    $retryDelay *= 2; // Backoff exponentiel
                }
            }
            
        } catch (PDOException $e) {
            $env = $this->config['environment'] ?? 'unknown';
            $errorMsg = "Erreur connexion MySQL CantalDestination ($env): " . $e->getMessage();
            $errorMsg .= " | Host: {$this->config['host']}:{$this->config['port']} | DB: {$this->config['database']}";
            
            error_log($errorMsg);
            
            // Message d'erreur plus explicite selon le code
            switch ($e->getCode()) {
                case 2002: // Connection refused
                    throw new Exception("Serveur MySQL inaccessible. VÃ©rifiez que le service MySQL est dÃ©marrÃ© et accessible.");
                case 1045: // Access denied
                    throw new Exception("Erreur d'authentification MySQL. VÃ©rifiez les identifiants de connexion.");
                case 1049: // Unknown database
                    throw new Exception("Base de donnÃ©es '{$this->config['database']}' introuvable.");
                default:
                    throw new Exception("Erreur de connexion Ã  la base de donnÃ©es: " . $e->getMessage());
            }
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur requÃªte MySQL: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Erreur lors de l'exÃ©cution de la requÃªte");
        }
    }
    
    public function queryOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result ? $result[0] : null;
    }
    
    /**
     * Mappage des pÃ©riodes pour la base de donnÃ©es
     */
    public static function mapPeriode($periode) {
        $mapping = [
            'annee' => 'annee',
            'ete' => 'ete',
            'hiver' => 'hiver',
            'automne' => 'automne',
            'printemps' => 'printemps'
        ];
        
        return $mapping[strtolower($periode)] ?? $periode;
    }
    
    /**
     * Mappage des zones pour la base de donnÃ©es
     */
    public static function mapZone($zone) {
        // Normalisation pour correspondre aux donnÃ©es en base
        return strtoupper(trim($zone));
    }
}

} // Fin de la protection class_exists('CantalDestinationDatabase')

/**
 * Fonction utilitaire pour obtenir une connexion Ã  la base
 */
if (!function_exists('getCantalDestinationDB')) {
    function getCantalDestinationDatabase() {
        return CantalDestinationDatabase::getInstance();
    }
} 





