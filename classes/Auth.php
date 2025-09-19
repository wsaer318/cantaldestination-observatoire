<?php

require_once 'Database.php';
require_once 'Security.php';
require_once 'EncryptionManager.php';
require_once 'EmailManager.php';
require_once 'UserDataManager.php';

class Auth {
    private static $db = null;
    
    private static function getDb() {
        if (self::$db === null) {
            self::$db = Database::getInstance();
            // Initialiser la base de données avec la table et les utilisateurs par défaut
            self::$db->createDefaultUsers();
        }
        return self::$db;
    }
    
    public static function login($username, $password) {
        try {
            // Sanitiser les entrées
            $username = Security::sanitizeInput($username);
            
            // Vérification des tentatives de force brute
            Security::checkBruteForce($username);
            Security::checkBruteForce(Security::getClientIP(), 'ip_login');
            
            $db = self::getDb();
            $connection = $db->getConnection();
            
            // Récupérer l'utilisateur de la base de données
            $stmt = $connection->prepare("SELECT * FROM users WHERE username = ? AND active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Enregistrer la tentative échouée
                Security::recordFailedAttempt($username);
                Security::recordFailedAttempt(Security::getClientIP(), 'ip_login');
                Security::logSecurityEvent('LOGIN_FAILED', ['username' => $username, 'reason' => 'user_not_found'], 'MEDIUM');
                return false;
            }
            
            // Vérifier le mot de passe avec password_verify
            if (password_verify($password, $user['password'])) {
                // Démarrer la session si pas déjà fait
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Déchiffrer les données sensibles avant de les stocker en session
                $decryptedName = '';
                $decryptedEmail = '';
                
                try {
                    if (!empty($user['name'])) {
                        $decryptedName = EncryptionManager::decryptName($user['name']);
                    }
                    if (!empty($user['email'])) {
                        $decryptedEmail = EncryptionManager::decryptEmail($user['email']);
                    }
                } catch (Exception $e) {
                    // En cas d'erreur de déchiffrement, utiliser des valeurs par défaut
                    Security::logSecurityEvent('SESSION_DECRYPT_ERROR', [
                        'user_id' => $user['id'],
                        'error' => $e->getMessage()
                    ], 'HIGH');
                    $decryptedName = 'Utilisateur';
                    $decryptedEmail = '';
                }
                
                $_SESSION['authenticated'] = true;
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'name' => $decryptedName,
                    'email' => $decryptedEmail
                ];
                
                // Mettre à jour la date de dernière connexion
                self::updateLastLogin($user['id']);
                
                // Journaliser la connexion réussie
                Security::logSecurityEvent('LOGIN_SUCCESS', ['user_id' => $user['id'], 'username' => $username], 'INFO');
                
                return true;
            } else {
                // Enregistrer la tentative échouée
                Security::recordFailedAttempt($username);
                Security::recordFailedAttempt(Security::getClientIP(), 'ip_login');
                Security::logSecurityEvent('LOGIN_FAILED', ['username' => $username, 'reason' => 'invalid_password'], 'MEDIUM');
            }
            
            return false;
        } catch (SecurityException $e) {
            // Relancer l'exception de sécurité
            throw $e;
        } catch (Exception $e) {
            error_log('Erreur lors de la connexion : ' . $e->getMessage());
            Security::logSecurityEvent('LOGIN_ERROR', ['username' => $username ?? '', 'error' => $e->getMessage()], 'HIGH');
            return false;
        }
    }
    
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_destroy();
    }
    
    public static function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    public static function getUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user'] ?? null;
    }
    
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            // Vérifier si c'est une requête API
            $isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
            
            if ($isApiRequest) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentification requise'
                ]);
                exit;
            } else {
                header('Location: /fluxvision_fin/login');
                exit;
            }
        }
    }
    
    public static function redirectIfAuthenticated() {
        if (self::isAuthenticated()) {
            header('Location: /fluxvision_fin/');
            exit;
        }
    }
    
    // Nouvelles méthodes pour la gestion des utilisateurs
    
    public static function createUser($username, $password, $name, $role = 'user', $email = null) {
        try {
            // Sanitiser les entrées
            $username = Security::sanitizeInput($username);
            $name = Security::sanitizeInput($name);
            $email = $email ? Security::sanitizeInput($email, 'email') : null;
            $role = Security::sanitizeInput($role);
            
            // Valider la force du mot de passe
            $passwordErrors = Security::validatePasswordStrength($password);
            if (!empty($passwordErrors)) {
                throw new SecurityException('Mot de passe trop faible: ' . implode(', ', $passwordErrors));
            }
            
            // Valider l'email si fourni
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new SecurityException('Adresse email invalide');
            }
            
            // Valider le nom d'utilisateur
            if (strlen($username) < 3 || strlen($username) > 50) {
                throw new SecurityException('Le nom d\'utilisateur doit contenir entre 3 et 50 caractères');
            }
            
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
                throw new SecurityException('Le nom d\'utilisateur ne doit contenir que des lettres, chiffres, tirets et underscores');
            }
            
            $db = self::getDb();
            $connection = $db->getConnection();
            
            // Vérifier si l'utilisateur existe déjà
            $stmt = $connection->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetchColumn() > 0) {
                Security::logSecurityEvent('USER_CREATION_FAILED', ['username' => $username, 'reason' => 'username_exists'], 'MEDIUM');
                return false; // L'utilisateur existe déjà
            }
            
            // Hasher le mot de passe avec options sécurisées
            // Utiliser ARGON2ID si disponible, sinon BCRYPT
            if (defined('PASSWORD_ARGON2ID')) {
                $hashedPassword = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536, // 64 MB
                    'time_cost' => 4,       // 4 iterations
                    'threads' => 3,         // 3 threads
                ]);
            } else {
                // Fallback vers BCRYPT avec coût élevé
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, [
                    'cost' => 12
                ]);
            }
            
            // Chiffrer les données sensibles avant insertion
            $encryptedName = EncryptionManager::encryptName($name);
            $encryptedEmail = $email ? EncryptionManager::encryptEmail($email) : null;
            
            // Créer l'utilisateur avec les données chiffrées
            $stmt = $connection->prepare("INSERT INTO users (username, password, role, name, email) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $username,
                $hashedPassword,
                $role,
                $encryptedName,
                $encryptedEmail
            ]);
            
            if ($result) {
                Security::logSecurityEvent('USER_CREATED', ['username' => $username, 'role' => $role], 'INFO');
                
                // Envoyer notification email à l'admin
                try {
                    $currentUser = self::getUser();
                    $createdByUsername = $currentUser ? $currentUser['username'] : 'système';
                    
                    EmailManager::sendUserCreationNotification($username, $role, $createdByUsername);
                } catch (Exception $e) {
                    // Ne pas faire échouer la création si l'email échoue
                    error_log('Erreur envoi notification email création utilisateur : ' . $e->getMessage());
                }
            }
            
            return $result;
        } catch (SecurityException $e) {
            throw $e;
        } catch (Exception $e) {
            error_log('Erreur lors de la création d\'utilisateur : ' . $e->getMessage());
            Security::logSecurityEvent('USER_CREATION_ERROR', ['username' => $username ?? '', 'error' => $e->getMessage()], 'HIGH');
            return false;
        }
    }
    
    public static function updatePassword($userId, $newPassword) {
        try {
            $db = self::getDb();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            return $stmt->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                $userId
            ]);
        } catch (Exception $e) {
            error_log('Erreur lors de la mise à jour du mot de passe : ' . $e->getMessage());
            return false;
        }
    }
    
    public static function getUserById($userId) {
        try {
            $db = self::getDb();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("SELECT id, username, role, name, email, created_at FROM users WHERE id = ? AND active = 1");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log('Erreur lors de la récupération d\'utilisateur : ' . $e->getMessage());
            return false;
        }
    }
    
    public static function getAllUsers() {
        try {
            $db = self::getDb();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("SELECT id, username, role, name, email, created_at, last_login, active FROM users ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Erreur lors de la récupération des utilisateurs : ' . $e->getMessage());
            return [];
        }
    }
    
    public static function deactivateUser($userId) {
        try {
            $db = self::getDb();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("UPDATE users SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log('Erreur lors de la désactivation d\'utilisateur : ' . $e->getMessage());
            return false;
        }
    }
    
    public static function deleteUser($userId) {
        try {
            // Log pour diagnostic
            error_log("Tentative de suppression utilisateur ID: " . $userId);
            
            // Vérification de sécurité : ne pas supprimer l'admin principal
            // Utiliser UserDataManager qui gère le déchiffrement
            $user = UserDataManager::getUserById($userId);
            if (!$user) {
                error_log("Utilisateur non trouvé pour suppression: " . $userId);
                return false;
            }
            
            error_log("Utilisateur trouvé pour suppression: " . $user['username']);
            
            // Empêcher la suppression de l'utilisateur admin principal
            if ($user['username'] === 'admin') {
                error_log("Tentative de suppression de l'admin principal bloquée");
                throw new SecurityException('Impossible de supprimer l\'utilisateur administrateur principal');
            }
            
            $db = self::getDb();
            $connection = $db->getConnection();
            
            // Commencer une transaction pour s'assurer de la cohérence
            $connection->beginTransaction();
            
            try {
                // Supprimer l'utilisateur (suppression définitive)
                $stmt = $connection->prepare("DELETE FROM users WHERE id = ? AND username != 'admin'");
                $result = $stmt->execute([$userId]);
                
                error_log("Résultat suppression SQL: " . ($result ? 'TRUE' : 'FALSE'));
                error_log("Lignes affectées: " . $stmt->rowCount());
                
                if ($result && $stmt->rowCount() > 0) {
                    // Journaliser la suppression pour audit
                    $currentUser = self::getUser();
                    $deletedByUsername = $currentUser ? $currentUser['username'] : 'unknown';
                    
                    Security::logSecurityEvent('USER_DELETED', [
                        'deleted_user_id' => $userId,
                        'deleted_username' => $user['username'],
                        'deleted_by' => $deletedByUsername
                    ], 'CRITICAL');
                    
                    // Envoyer notification email à l'admin pour suppression
                    try {
                        EmailManager::sendUserDeletionNotification($user['username'], $user['role'], $deletedByUsername);
                    } catch (Exception $e) {
                        // Ne pas faire échouer la suppression si l'email échoue
                        error_log('Erreur envoi notification email suppression utilisateur : ' . $e->getMessage());
                    }
                    
                    $connection->commit();
                    return true;
                } else {
                    $connection->rollback();
                    return false;
                }
            } catch (Exception $e) {
                $connection->rollback();
                throw $e;
            }
            
        } catch (SecurityException $e) {
            throw $e;
        } catch (Exception $e) {
            error_log('Erreur lors de la suppression d\'utilisateur : ' . $e->getMessage());
            Security::logSecurityEvent('USER_DELETION_ERROR', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ], 'HIGH');
            return false;
        }
    }
    
    private static function updateLastLogin($userId) {
        try {
            $db = self::getDb();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            // Ignorer silencieusement les erreurs de cette méthode auxiliaire
            error_log('Erreur lors de la mise à jour de la dernière connexion : ' . $e->getMessage());
        }
    }
    
    /**
     * Vérifier si l'utilisateur actuel est administrateur
     */
    public static function isAdmin() {
        $user = self::getUser();
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Exiger que l'utilisateur soit administrateur
     * Redirige vers la page d'accueil si non admin
     */
    public static function requireAdmin() {
        if (!self::isAuthenticated()) {
            self::requireAuth();
        }
        
        if (!self::isAdmin()) {
            // Vérifier si c'est une requête API
            $isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
            
            if ($isApiRequest) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Accès réservé aux administrateurs'
                ]);
                exit;
            } else {
                header('Location: /fluxvision_fin/');
                exit;
            }
        }
    }
} 