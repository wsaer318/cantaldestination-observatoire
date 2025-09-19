<?php

/**
 * Gestionnaire des données utilisateur avec chiffrement automatique
 * Couche d'abstraction pour protéger les données sensibles
 */
class UserDataManager {
    
    private static $db = null;
    
    private static function getDatabase() {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
    
    /**
     * Créer un utilisateur avec chiffrement automatique
     */
    public static function createUser(array $userData): bool {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            // Chiffrer les données sensibles
            $encryptedData = [
                'username' => SecurityManager::sanitizeInput($userData['username']),
                'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                'role' => SecurityManager::sanitizeInput($userData['role']),
                'name' => EncryptionManager::encryptName($userData['name']),
                'email' => !empty($userData['email']) ? EncryptionManager::encryptEmail($userData['email']) : null
            ];
            
            $stmt = $connection->prepare("
                INSERT INTO users (username, password, role, name, email) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $encryptedData['username'],
                $encryptedData['password'],
                $encryptedData['role'],
                $encryptedData['name'],
                $encryptedData['email']
            ]);
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Récupérer un utilisateur avec déchiffrement automatique
     */
    public static function getUserById(int $userId): ?array {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("
                SELECT id, username, role, name, email, created_at, last_login, active 
                FROM users 
                WHERE id = ? AND active = 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return null;
            }
            
            // Déchiffrer les données sensibles
            return self::decryptUserData($user);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Récupérer un utilisateur par nom d'utilisateur
     */
    public static function getUserByUsername(string $username): ?array {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("
                SELECT id, username, password, role, name, email, created_at, 
                       last_login, two_factor_enabled, two_factor_secret, active 
                FROM users 
                WHERE username = ? AND active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return null;
            }
            
            // Déchiffrer les données sensibles (garder le password hashé)
            $decrypted = self::decryptUserData($user);
            $decrypted['password'] = $user['password']; // Garder le hash
            
            // Déchiffrer le secret 2FA si présent
            if (!empty($user['two_factor_secret'])) {
                try {
                    $decrypted['two_factor_secret'] = EncryptionManager::decrypt2FASecret($user['two_factor_secret']);
                } catch (Exception $e) {
                    $decrypted['two_factor_secret'] = null;
                }
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            SecurityManager::logSecurityEvent('USER_FETCH_ERROR', [
                'username' => $username,
                'error' => $e->getMessage()
            ], 'MEDIUM');
            return null;
        }
    }
    
    /**
     * Mettre à jour les informations utilisateur
     */
    public static function updateUser(int $userId, array $updateData): bool {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            $fields = [];
            $values = [];
            
            // Traiter chaque champ avec chiffrement si nécessaire
            foreach ($updateData as $field => $value) {
                switch ($field) {
                    case 'name':
                        $fields[] = 'name = ?';
                        $values[] = EncryptionManager::encryptName($value);
                        break;
                    case 'email':
                        $fields[] = 'email = ?';
                        $values[] = !empty($value) ? EncryptionManager::encryptEmail($value) : null;
                        break;
                    case 'password':
                        $fields[] = 'password = ?';
                        $values[] = password_hash($value, PASSWORD_ARGON2ID);
                        break;
                    case 'two_factor_secret':
                        $fields[] = 'two_factor_secret = ?';
                        $values[] = !empty($value) ? EncryptionManager::encrypt2FASecret($value) : null;
                        break;
                    case 'role':
                    case 'two_factor_enabled':
                    case 'active':
                        $fields[] = "$field = ?";
                        $values[] = $value;
                        break;
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $values[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $connection->prepare($sql);
            
            $result = $stmt->execute($values);
            
            if ($result) {
                SecurityManager::logSecurityEvent('USER_UPDATED_ENCRYPTED', [
                    'user_id' => $userId,
                    'fields' => array_keys($updateData)
                ], 'INFO');
            }
            
            return $result;
            
        } catch (Exception $e) {
            SecurityManager::logSecurityEvent('USER_UPDATE_FAILED', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ], 'HIGH');
            throw $e;
        }
    }
    
    /**
     * Lister tous les utilisateurs avec déchiffrement
     */
    public static function getAllUsers(): array {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("
                SELECT id, username, role, name, email, created_at, 
                       last_login, two_factor_enabled, active 
                FROM users 
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Déchiffrer chaque utilisateur
            $decryptedUsers = [];
            foreach ($users as $user) {
                $decryptedUsers[] = self::decryptUserData($user);
            }
            
            return $decryptedUsers;
            
        } catch (Exception $e) {
            SecurityManager::logSecurityEvent('USERS_FETCH_ERROR', [
                'error' => $e->getMessage()
            ], 'MEDIUM');
            return [];
        }
    }
    
    /**
     * Sauvegarder un enregistrement de connexion avec chiffrement
     */
    public static function logLogin(int $userId, bool $success, string $failureReason = null): void {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            // Créer la table si elle n'existe pas
            AuthenticationEnhancer::initializeTables();
            
            $stmt = $connection->prepare("
                INSERT INTO auth_login_history 
                (user_id, ip_address, user_agent, success, failure_reason, session_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                EncryptionManager::encryptIP($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                EncryptionManager::encryptUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
                $success,
                $failureReason,
                session_id()
            ]);
            
        } catch (Exception $e) {
            // Log silencieux pour éviter les boucles
            error_log("Erreur lors de l'enregistrement de connexion : " . $e->getMessage());
        }
    }
    
    /**
     * Récupérer l'historique de connexion déchiffré
     */
    public static function getLoginHistory(int $userId, int $limit = 10): array {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("
                SELECT id, ip_address, user_agent, success, failure_reason, 
                       created_at, session_id
                FROM auth_login_history 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $history = $stmt->fetchAll();
            
            // Déchiffrer les données
            foreach ($history as &$record) {
                try {
                    $record['ip_address'] = EncryptionManager::decryptIP($record['ip_address']);
                    $record['user_agent'] = EncryptionManager::decryptUserAgent($record['user_agent']);
                } catch (Exception $e) {
                    // Si le déchiffrement échoue, garder les données chiffrées
                    $record['ip_address'] = '[Chiffré]';
                    $record['user_agent'] = '[Chiffré]';
                }
            }
            
            return $history;
            
        } catch (Exception $e) {
            SecurityManager::logSecurityEvent('LOGIN_HISTORY_ERROR', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ], 'MEDIUM');
            return [];
        }
    }
    
    /**
     * Déchiffrer les données d'un utilisateur
     */
    private static function decryptUserData(array $user): array {
        try {
            // Déchiffrer le nom
            if (!empty($user['name'])) {
                $user['name'] = EncryptionManager::decryptName($user['name']);
            }
            
            // Déchiffrer l'email
            if (!empty($user['email'])) {
                $user['email'] = EncryptionManager::decryptEmail($user['email']);
            }
            
            return $user;
            
        } catch (Exception $e) {
            // En cas d'erreur de déchiffrement, retourner les données partiellement déchiffrées
            SecurityManager::logSecurityEvent('USER_DECRYPT_ERROR', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ], 'MEDIUM');
            
            // Marquer les champs non déchiffrables
            if (!empty($user['name']) && substr($user['name'], 0, 3) !== '[D]') {
                $user['name'] = '[Données chiffrées]';
            }
            if (!empty($user['email']) && substr($user['email'], 0, 3) !== '[D]') {
                $user['email'] = '[Données chiffrées]';
            }
            
            return $user;
        }
    }
    
    /**
     * Récupérer tous les utilisateurs disponibles pour les espaces partagés
     */
    public static function getAvailableUsers(): array {
        try {
            $db = self::getDatabase();
            $connection = $db->getConnection();
            
            $stmt = $connection->prepare("
                SELECT id, username, role, name, email, active 
                FROM users 
                WHERE active = 1 
                ORDER BY username ASC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Déchiffrer les données sensibles
            foreach ($users as &$user) {
                $user = self::decryptUserData($user);
            }
            
            return $users;
            
        } catch (Exception $e) {
            error_log("Erreur récupération utilisateurs disponibles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Vérifier l'intégrité des données chiffrées
     */
    public static function verifyDataIntegrity(): array {
        $results = [
            'total_users' => 0,
            'encrypted_names' => 0,
            'encrypted_emails' => 0,
            'decrypt_errors' => 0,
            'integrity_score' => 0
        ];
        
        try {
            $users = self::getAllUsers();
            $results['total_users'] = count($users);
            
            foreach ($users as $user) {
                if (!empty($user['name']) && $user['name'] !== '[Données chiffrées]') {
                    $results['encrypted_names']++;
                }
                if (!empty($user['email']) && $user['email'] !== '[Données chiffrées]') {
                    $results['encrypted_emails']++;
                } else if ($user['email'] === '[Données chiffrées]') {
                    $results['decrypt_errors']++;
                }
            }
            
            // Calculer le score d'intégrité (pourcentage de données correctement déchiffrées)
            $totalEncrypted = $results['encrypted_names'] + $results['encrypted_emails'];
            $results['integrity_score'] = $totalEncrypted > 0 ? 
                (1 - ($results['decrypt_errors'] / $totalEncrypted)) * 100 : 100;
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
} 