<?php

/**
 * Amélioration de l'authentification avec des fonctionnalités avancées
 * 2FA, historique des connexions, détection d'anomalies
 */
class AuthenticationEnhancer {
    
    private static $db = null;
    
    private static function getDatabase() {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
    
    /**
     * Initialise les tables nécessaires pour l'authentification renforcée
     */
    public static function initializeTables(): void {
        $db = self::getDatabase();
        $connection = $db->getConnection();
        
        // Table pour les sessions 2FA temporaires
        $sql = "CREATE TABLE IF NOT EXISTS auth_2fa_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(6) NOT NULL,
            secret VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP DEFAULT (NOW() + INTERVAL 1 HOUR),
            verified BOOLEAN DEFAULT FALSE,
            attempts INT DEFAULT 0,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token),
            INDEX idx_expires_at (expires_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $connection->exec($sql);
        
        // Table pour l'historique des connexions
        $sql = "CREATE TABLE IF NOT EXISTS auth_login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            location VARCHAR(255),
            success BOOLEAN NOT NULL,
            failure_reason VARCHAR(255),
            session_id VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_success (success),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $connection->exec($sql);
        
        // Table pour les appareils de confiance
        $sql = "CREATE TABLE IF NOT EXISTS auth_trusted_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_fingerprint VARCHAR(255) NOT NULL,
            device_name VARCHAR(255),
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            active BOOLEAN DEFAULT TRUE,
            INDEX idx_user_id (user_id),
            INDEX idx_fingerprint (device_fingerprint),
            INDEX idx_last_used (last_used),
            UNIQUE KEY unique_user_device (user_id, device_fingerprint),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $connection->exec($sql);
        
        // Ajouter la colonne 2FA à la table users si elle n'existe pas
        try {
            $sql = "ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE";
            $connection->exec($sql);
        } catch (PDOException $e) {
            // La colonne existe déjà
        }
        
        try {
            $sql = "ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) NULL";
            $connection->exec($sql);
        } catch (PDOException $e) {
            // La colonne existe déjà
        }
    }
    
    /**
     * Génère une empreinte unique pour l'appareil
     */
    public static function generateDeviceFingerprint(): string {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Vérifie si l'appareil est de confiance
     */
    public static function isTrustedDevice(int $userId): bool {
        $fingerprint = self::generateDeviceFingerprint();
        
        $db = self::getDatabase();
        $stmt = $db->prepare("
            SELECT id FROM auth_trusted_devices 
            WHERE user_id = ? AND device_fingerprint = ? AND active = 1
            AND last_used > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId, $fingerprint]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Ajoute un appareil de confiance
     */
    public static function addTrustedDevice(int $userId, string $deviceName = null): void {
        $fingerprint = self::generateDeviceFingerprint();
        
        if ($deviceName === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Appareil inconnu';
            $deviceName = self::parseDeviceName($userAgent);
        }
        
        $db = self::getDatabase();
        $stmt = $db->prepare("
            INSERT INTO auth_trusted_devices 
            (user_id, device_fingerprint, device_name, ip_address, user_agent, last_used)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            last_used = NOW(), device_name = VALUES(device_name), active = 1
        ");
        
        $stmt->execute([
            $userId,
            $fingerprint,
            $deviceName,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        SecurityManager::logSecurityEvent('TRUSTED_DEVICE_ADDED', [
            'user_id' => $userId,
            'device_name' => $deviceName,
            'fingerprint' => substr($fingerprint, 0, 8) . '...'
        ], 'INFO');
    }
    
    /**
     * Parse le nom de l'appareil depuis le User-Agent
     */
    private static function parseDeviceName(string $userAgent): string {
        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            return 'Appareil mobile';
        } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            return 'Appareil iOS';
        } elseif (strpos($userAgent, 'Windows') !== false) {
            return 'PC Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            return 'Mac';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } else {
            return 'Appareil inconnu';
        }
    }
    
    /**
     * Enregistre une tentative de connexion dans l'historique
     */
    public static function logLoginAttempt(int $userId, bool $success, string $failureReason = null): void {
        $db = self::getDatabase();
        $stmt = $db->prepare("
            INSERT INTO auth_login_history 
            (user_id, ip_address, user_agent, success, failure_reason, session_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $success ? 1 : 0,
            $failureReason,
            session_id()
        ]);
    }
    
    /**
     * Détecte les anomalies de connexion (nouvelle localisation, appareil, etc.)
     */
    public static function detectLoginAnomaly(int $userId): array {
        $anomalies = [];
        $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
        $currentFingerprint = self::generateDeviceFingerprint();
        
        $db = self::getDatabase();
        
        // Vérifier les connexions des 30 derniers jours
        $stmt = $db->prepare("
            SELECT DISTINCT ip_address, user_agent 
            FROM auth_login_history 
            WHERE user_id = ? AND success = 1 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $recentLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Nouveau appareil
        if (!self::isTrustedDevice($userId)) {
            $anomalies[] = [
                'type' => 'NEW_DEVICE',
                'severity' => 'MEDIUM',
                'description' => 'Connexion depuis un nouvel appareil'
            ];
        }
        
        // Nouvelle adresse IP
        $knownIPs = array_column($recentLogins, 'ip_address');
        if (!in_array($currentIP, $knownIPs)) {
            $anomalies[] = [
                'type' => 'NEW_IP',
                'severity' => 'HIGH',
                'description' => 'Connexion depuis une nouvelle adresse IP'
            ];
        }
        
        // Connexion à une heure inhabituelle
        $currentHour = (int)date('H');
        $stmt = $db->prepare("
            SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM auth_login_history 
            WHERE user_id = ? AND success = 1 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY count DESC
        ");
        $stmt->execute([$userId]);
        $hourStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($hourStats)) {
            $commonHours = array_column(array_slice($hourStats, 0, 3), 'hour');
            $isUnusualHour = !in_array($currentHour, $commonHours) && 
                           ($currentHour < 6 || $currentHour > 23);
            
            if ($isUnusualHour) {
                $anomalies[] = [
                    'type' => 'UNUSUAL_TIME',
                    'severity' => 'LOW',
                    'description' => 'Connexion à une heure inhabituelle'
                ];
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Génère un token 2FA temporaire
     */
    public static function generate2FAToken(int $userId): string {
        // Nettoyer les anciens tokens expirés
        $db = self::getDatabase();
        $db->prepare("DELETE FROM auth_2fa_sessions WHERE expires_at < NOW()")->execute();
        
        // Générer un nouveau token
        $token = sprintf('%06d', mt_rand(100000, 999999));
        $secret = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("
            INSERT INTO auth_2fa_sessions 
            (user_id, token, secret, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        
        $stmt->execute([
            $userId,
            $token,
            $secret,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return $token;
    }
    
    /**
     * Valide un token 2FA
     */
    public static function validate2FAToken(int $userId, string $token): bool {
        $db = self::getDatabase();
        $stmt = $db->prepare("
            SELECT id, attempts FROM auth_2fa_sessions 
            WHERE user_id = ? AND token = ? AND expires_at > NOW() AND verified = 0
        ");
        $stmt->execute([$userId, $token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            SecurityManager::logSecurityEvent('2FA_INVALID_TOKEN', [
                'user_id' => $userId,
                'token' => substr($token, 0, 2) . '****'
            ], 'HIGH');
            return false;
        }
        
        // Incrémenter les tentatives
        $newAttempts = $session['attempts'] + 1;
        
        // Limiter les tentatives (max 3)
        if ($newAttempts > 3) {
            $db->prepare("UPDATE auth_2fa_sessions SET verified = 1 WHERE id = ?")
               ->execute([$session['id']]);
            
            SecurityManager::logSecurityEvent('2FA_MAX_ATTEMPTS', [
                'user_id' => $userId,
                'attempts' => $newAttempts
            ], 'HIGH');
            return false;
        }
        
        $db->prepare("UPDATE auth_2fa_sessions SET attempts = ? WHERE id = ?")
           ->execute([$newAttempts, $session['id']]);
        
        // Marquer comme vérifié si token valide
        $db->prepare("UPDATE auth_2fa_sessions SET verified = 1 WHERE id = ?")
           ->execute([$session['id']]);
        
        SecurityManager::logSecurityEvent('2FA_SUCCESS', ['user_id' => $userId], 'INFO');
        return true;
    }
    
    /**
     * Obtient l'historique des connexions pour un utilisateur
     */
    public static function getLoginHistory(int $userId, int $limit = 10): array {
        $db = self::getDatabase();
        $stmt = $db->prepare("
            SELECT ip_address, user_agent, success, failure_reason, created_at
            FROM auth_login_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les appareils de confiance pour un utilisateur
     */
    public static function getTrustedDevices(int $userId): array {
        $db = self::getDatabase();
        $stmt = $db->prepare("
            SELECT device_name, device_fingerprint, ip_address, last_used, created_at
            FROM auth_trusted_devices 
            WHERE user_id = ? AND active = 1
            ORDER BY last_used DESC
        ");
        $stmt->execute([$userId]);
        
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Masquer les empreintes complètes
        foreach ($devices as &$device) {
            $device['device_fingerprint'] = substr($device['device_fingerprint'], 0, 8) . '...';
        }
        
        return $devices;
    }
    
    /**
     * Révoque un appareil de confiance
     */
    public static function revokeTrustedDevice(int $userId, string $fingerprint): bool {
        $db = self::getDatabase();
        $stmt = $db->prepare("
            UPDATE auth_trusted_devices 
            SET active = 0 
            WHERE user_id = ? AND device_fingerprint = ?
        ");
        
        $result = $stmt->execute([$userId, $fingerprint]);
        
        if ($result && $stmt->rowCount() > 0) {
            SecurityManager::logSecurityEvent('TRUSTED_DEVICE_REVOKED', [
                'user_id' => $userId,
                'fingerprint' => substr($fingerprint, 0, 8) . '...'
            ], 'INFO');
            return true;
        }
        
        return false;
    }
    
    /**
     * Processus de connexion renforcé
     */
    public static function enhancedLogin(string $username, string $password): array {
        // Authentification de base
        $user = Auth::authenticate($username, $password);
        if (!$user) {
            return ['success' => false, 'error' => 'Identifiants invalides'];
        }
        
        // Enregistrer la tentative
        self::logLoginAttempt($user['id'], true);
        
        // Détecter les anomalies
        $anomalies = self::detectLoginAnomaly($user['id']);
        
        // Si anomalies détectées, demander une vérification supplémentaire
        if (!empty($anomalies)) {
            foreach ($anomalies as $anomaly) {
                SecurityManager::logSecurityEvent('LOGIN_ANOMALY_DETECTED', [
                    'user_id' => $user['id'],
                    'anomaly' => $anomaly
                ], $anomaly['severity']);
            }
            
            // Générer un token 2FA pour les anomalies HIGH
            $highSeverityAnomaly = array_filter($anomalies, function($a) {
                return $a['severity'] === 'HIGH';
            });
            
            if (!empty($highSeverityAnomaly)) {
                $token = self::generate2FAToken($user['id']);
                return [
                    'success' => false,
                    'requires_2fa' => true,
                    'anomalies' => $anomalies,
                    'message' => 'Vérification supplémentaire requise pour cette connexion'
                ];
            }
        }
        
        // Ajouter l'appareil aux appareils de confiance si connexion réussie
        if (!self::isTrustedDevice($user['id'])) {
            self::addTrustedDevice($user['id']);
        }
        
        return ['success' => true, 'user' => $user, 'anomalies' => $anomalies];
    }
}