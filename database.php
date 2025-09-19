<?php
/**
 * CantalDestination - Configuration de base de données
 * Fichier : database.php
 * Configuration de base de données CantalDestination avec détection d'environnement
 * Détecte automatiquement si on est en local ou en production
 */

// Protection contre la redéclaration de classe
if (!class_exists('DatabaseConfig')) {

class DatabaseConfig {
    
    /**
     * Détecte si on est en environnement de production ou local
     */
    public static function isProduction() {
        // Vérifier l'hôte - si c'est l'hébergeur, on est en production
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        
        // Log pour debug
        error_log("CantalDestination Environment Detection - Host: $host");
        
        // Si on est sur le domaine de l'hébergeur
        if (strpos($host, 'cantal-destination.com') !== false || 
            strpos($host, 'observatoire.cantal-destination.com') !== false) {
            error_log("CantalDestination Environment Detection - PRODUCTION detected (domain match)");
            return true;
        }
        
        // Vérifier d'autres indicateurs de production
        $serverAddr = $_SERVER['SERVER_ADDR'] ?? 'unknown';
        if (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && 
            $_SERVER['SERVER_ADDR'] !== '::1' && 
            !preg_match('/^192\.168\./', $_SERVER['SERVER_ADDR']) &&
            !preg_match('/^10\./', $_SERVER['SERVER_ADDR'])) {
            error_log("CantalDestination Environment Detection - PRODUCTION detected (IP match: $serverAddr)");
            return true;
        }
        
        error_log("CantalDestination Environment Detection - LOCAL detected (Host: $host, IP: $serverAddr)");
        return false;
    }
    
    /**
     * Retourne la configuration de base de données selon l'environnement
     */
    public static function getConfig() {
        if (self::isProduction()) {
            // Configuration pour l'hébergeur Cantal Destination - Port 3306 confirmé
            return [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'observatoire',
                'username' => 'observatoire',
                'password' => 'Sf4d8gsfsdGsg59sqq54g',
                'charset' => 'utf8mb4',
                'environment' => 'production',
                // Ajout d'options de connexion robustes
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_FOUND_ROWS => true,
                    PDO::ATTR_TIMEOUT => 30, // Timeout plus long
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            ];
            
        } else {
            // Configuration locale (XAMPP)
            return [
                'host' => 'localhost',
                'port' => 3307, // Port XAMPP
                'database' => 'fluxvision',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'environment' => 'local',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_FOUND_ROWS => true
                ]
            ];
        }
    }
    
    /**
     * Affiche l'environnement actuel (pour debug)
     */
    public static function getCurrentEnvironment() {
        $config = self::getConfig();
        return $config['environment'];
    }
    
    /**
     * Log la configuration utilisée (sans mot de passe)
     */
    public static function logCurrentConfig() {
        $config = self::getConfig();
        $logConfig = $config;
        $logConfig['password'] = '***HIDDEN***';
        
        error_log("CantalDestination DB Config: " . json_encode($logConfig));
    }
    
    /**
     * Retourne une connexion MySQL selon la configuration d'environnement
     * Méthode statique pour compatibilité avec AdminTempTablesController
     */
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
            
            // Utiliser les options de connexion robustes
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

// Protection contre la redéclaration de CantalDestinationDatabase
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
            // Log de la configuration utilisée (pour debug)
            DatabaseConfig::logCurrentConfig();
            
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );
            
            // Utiliser les options définies dans la configuration
            $options = $this->config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ];
            
            // Tentative de connexion avec retry en cas d'échec temporaire
            $maxRetries = 3;
            $retryDelay = 1; // secondes
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
                    
                    // Si on arrive ici, la connexion a réussi
                    if ($attempt > 1) {
                        error_log("CantalDestination: Connexion réussie à la tentative $attempt");
                    }
                    break;
                    
                } catch (PDOException $e) {
                    if ($attempt === $maxRetries) {
                        // Dernière tentative, on relance l'exception
                        throw $e;
                    }
                    
                    // Attendre avant la prochaine tentative
                    error_log("CantalDestination: Tentative de connexion $attempt échouée, retry dans {$retryDelay}s...");
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
                    throw new Exception("Serveur MySQL inaccessible. Vérifiez que le service MySQL est démarré et accessible.");
                case 1045: // Access denied
                    throw new Exception("Erreur d'authentification MySQL. Vérifiez les identifiants de connexion.");
                case 1049: // Unknown database
                    throw new Exception("Base de données '{$this->config['database']}' introuvable.");
                default:
                    throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
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
            error_log("Erreur requête MySQL: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
    }
    
    public function queryOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result ? $result[0] : null;
    }
    
    /**
     * Mappage des périodes pour la base de données
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
     * Mappage des zones pour la base de données
     */
    public static function mapZone($zone) {
        // Normalisation pour correspondre aux données en base
        return strtoupper(trim($zone));
    }
}

} // Fin de la protection class_exists('CantalDestinationDatabase')

/**
 * Fonction utilitaire pour obtenir une connexion à la base
 */
if (!function_exists('getCantalDestinationDB')) {
    function getCantalDestinationDatabase() {
        return CantalDestinationDatabase::getInstance();
    }
} 