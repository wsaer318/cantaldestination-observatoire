<?php
/**
 * Configuration de la base de données
 * Ce fichier charge la configuration depuis database.php pour éviter les doublons
 */

// Protection contre les inclusions multiples et redéclarations
if (!defined('CANTALDESTINATION_DATABASE_CONFIG_LOADED')) {
    define('CANTALDESTINATION_DATABASE_CONFIG_LOADED', true);
    
    // Charger la configuration principale depuis database.php
    if (!class_exists('DatabaseConfig')) {
        require_once dirname(__DIR__) . '/database.php';
    }
}

/**
 * Classe d'utilitaires pour la configuration de base de données
 * (Évite les conflits avec la classe DatabaseConfig principale)
 */
if (!class_exists('DatabaseConfigHelper')) {
    class DatabaseConfigHelper {
        
        /**
         * Retourne la configuration de base de données
         */
        public static function getConfig() {
            if (!class_exists('DatabaseConfig')) {
                require_once dirname(__DIR__) . '/database.php';
            }
            return DatabaseConfig::getConfig();
        }
        
        /**
         * Teste la connexion à la base de données
         */
        public static function testConnection() {
            if (!class_exists('DatabaseConfig')) {
                require_once dirname(__DIR__) . '/database.php';
            }
            
            $config = DatabaseConfig::getConfig();
            
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset']
                );
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                
                $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
                
                $result = [
                    'success' => true,
                    'environment' => $config['environment'],
                    'host' => $config['host'],
                    'database' => $config['database'],
                    'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                    'message' => "Connexion réussie en environnement {$config['environment']}"
                ];
                
                return $result;
                
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'environment' => $config['environment'],
                    'host' => $config['host'],
                    'database' => $config['database'],
                    'error' => $e->getMessage(),
                    'message' => "Échec de connexion en environnement {$config['environment']}"
                ];
            }
        }
        
        /**
         * Retourne les informations de configuration (sans le mot de passe)
         */
        public static function getConfigInfo() {
            if (!class_exists('DatabaseConfig')) {
                require_once dirname(__DIR__) . '/database.php';
            }
            
            $config = DatabaseConfig::getConfig();
            unset($config['password']); // Ne pas exposer le mot de passe
            return $config;
        }
    }
} 