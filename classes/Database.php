<?php

/**
 * CantalDestination - Database class
 * Fichier: classes/Database.php
 */

// Inclure la configuration de base de données
require_once dirname(__DIR__) . '/database.php';

class Database {
    private static $instance = null;
    private $connection;
    private $config;
    
    private function __construct() {
        // Obtenir la configuration selon l'environnement
        $this->config = DatabaseConfig::getConfig();
        
        try {
            $this->connection = new PDO(
                "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}",
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            $env = $this->config['environment'] ?? 'unknown';
            error_log("Erreur connexion Database ($env): " . $e->getMessage());
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Méthode pour créer la table users si elle n'existe pas
    public function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->connection->exec($sql);
    }
    
    // Méthode pour créer les utilisateurs par défaut
    // OBSOLÈTE - Les utilisateurs sont maintenant gérés via l'interface d'administration
    public function createDefaultUsers() {
        $this->createUsersTable();
        
        // Vérifier si des utilisateurs existent déjà
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            error_log("ATTENTION: Aucun utilisateur trouvé en base. Utilisez l'interface d'administration pour créer des utilisateurs.");
            // Ne plus créer automatiquement d'utilisateurs par défaut
            // Cela évite les problèmes de sécurité et de cohérence des données
        }
    }
} 