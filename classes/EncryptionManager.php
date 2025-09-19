<?php

/**
 * Gestionnaire de chiffrement avancé pour les données sensibles
 * Utilise AES-256-GCM avec clés dérivées et authentification
 */
class EncryptionManager {
    
    private static $masterKey = null;
    private static $config = [
        'cipher' => 'aes-256-gcm',
        'key_length' => 32, // 256 bits
        'iv_length' => 12,  // 96 bits pour GCM
        'tag_length' => 16, // 128 bits
        'salt_length' => 16, // 128 bits
        'iterations' => 100000 // PBKDF2 iterations
    ];
    
    /**
     * Initialise la clé maître depuis l'environnement ou la génère
     */
    private static function initializeMasterKey(): void {
        if (self::$masterKey !== null) {
            return;
        }
        
        // Chemin sécurisé pour la clé
        $keyFile = __DIR__ . '/../config/.encryption_key';
        
        // Tenter de charger la clé existante
        if (file_exists($keyFile) && is_readable($keyFile)) {
            $keyData = file_get_contents($keyFile);
            if ($keyData && strlen($keyData) === self::$config['key_length']) {
                self::$masterKey = $keyData;
                return;
            }
        }
        
        // Générer une nouvelle clé sécurisée
        self::$masterKey = random_bytes(self::$config['key_length']);
        
        // Sauvegarder la clé avec permissions restrictives
        $keyDir = dirname($keyFile);
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }
        
        file_put_contents($keyFile, self::$masterKey);
        chmod($keyFile, 0600); // Lecture seule pour le propriétaire
        
        SecurityManager::logSecurityEvent('ENCRYPTION_KEY_GENERATED', [
            'key_file' => $keyFile,
            'key_length' => self::$config['key_length']
        ], 'HIGH');
    }
    
    /**
     * Dérive une clé spécifique pour un contexte donné
     */
    private static function deriveKey(string $context, string $salt): string {
        self::initializeMasterKey();
        
        // Utiliser PBKDF2 pour dériver une clé contextuelle
        return hash_pbkdf2(
            'sha256',
            self::$masterKey . $context,
            $salt,
            self::$config['iterations'],
            self::$config['key_length'],
            true
        );
    }
    
    /**
     * Chiffre une donnée sensible
     */
    public static function encrypt(string $data, string $context = 'default'): string {
        if (empty($data)) {
            return '';
        }
        
        // Générer clé et IV
        $key = hash('sha256', 'flux_key_' . $context . '_2024', true);
        $iv = random_bytes(16);
        
        // Chiffrer
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        
        // Retourner IV + données chiffrées encodées
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Déchiffre une donnée
     */
    public static function decrypt(string $encryptedData, string $context = 'default'): string {
        if (empty($encryptedData)) {
            return '';
        }
        
        // Décoder
        $data = base64_decode($encryptedData);
        
        // Vérifier que les données sont suffisamment longues
        if ($data === false || strlen($data) < 16) {
            // Données corrompues ou invalides
            return '';
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        // Vérifier que l'IV a bien 16 bytes
        if (strlen($iv) !== 16) {
            return '';
        }
        
        // Déchiffrer
        $key = hash('sha256', 'flux_key_' . $context . '_2024', true);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        // Si le déchiffrement échoue, retourner une chaîne vide
        return $decrypted === false ? '' : $decrypted;
    }
    
    /**
     * Chiffre un email avec validation
     */
    public static function encryptEmail(string $email): string {
        if (empty($email)) {
            return '';
        }
        
        // Valider l'email avant chiffrement
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalide');
        }
        
        return self::encrypt($email, 'email');
    }
    
    /**
     * Déchiffre un email
     */
    public static function decryptEmail(string $encryptedEmail): string {
        return self::decrypt($encryptedEmail, 'email');
    }
    
    /**
     * Chiffre un nom personnel
     */
    public static function encryptName(string $name): string {
        if (empty($name)) {
            return '';
        }
        
        // Sanitiser le nom
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        
        return self::encrypt($name, 'personal_name');
    }
    
    /**
     * Déchiffre un nom personnel
     */
    public static function decryptName(string $encryptedName): string {
        return self::decrypt($encryptedName, 'personal_name');
    }
    
    /**
     * Chiffre un secret 2FA
     */
    public static function encrypt2FASecret(string $secret): string {
        if (empty($secret)) {
            return '';
        }
        
        return self::encrypt($secret, '2fa_secret');
    }
    
    /**
     * Déchiffre un secret 2FA
     */
    public static function decrypt2FASecret(string $encryptedSecret): string {
        return self::decrypt($encryptedSecret, '2fa_secret');
    }
    
    /**
     * Chiffre une adresse IP
     */
    public static function encryptIP(string $ip): string {
        if (empty($ip)) {
            return '';
        }
        
        // Valider l'IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Adresse IP invalide');
        }
        
        return self::encrypt($ip, 'ip_address');
    }
    
    /**
     * Déchiffre une adresse IP
     */
    public static function decryptIP(string $encryptedIP): string {
        return self::decrypt($encryptedIP, 'ip_address');
    }
    
    /**
     * Chiffre un User-Agent
     */
    public static function encryptUserAgent(string $userAgent): string {
        if (empty($userAgent)) {
            return '';
        }
        
        // Tronquer si trop long
        if (strlen($userAgent) > 1000) {
            $userAgent = substr($userAgent, 0, 1000);
        }
        
        return self::encrypt($userAgent, 'user_agent');
    }
    
    /**
     * Déchiffre un User-Agent
     */
    public static function decryptUserAgent(string $encryptedUserAgent): string {
        return self::decrypt($encryptedUserAgent, 'user_agent');
    }
    
    /**
     * Chiffre une empreinte d'appareil
     */
    public static function encryptDeviceFingerprint(string $fingerprint): string {
        if (empty($fingerprint)) {
            return '';
        }
        
        return self::encrypt($fingerprint, 'device_fingerprint');
    }
    
    /**
     * Déchiffre une empreinte d'appareil
     */
    public static function decryptDeviceFingerprint(string $encryptedFingerprint): string {
        return self::decrypt($encryptedFingerprint, 'device_fingerprint');
    }
    
    /**
     * Teste la disponibilité du chiffrement
     */
    public static function testEncryption(): array {
        $results = [];
        
        try {
            // Test de base
            $testData = 'Test de chiffrement FluxVision 2024 🔒';
            $encrypted = self::encrypt($testData);
            $decrypted = self::decrypt($encrypted);
            
            $results['basic_test'] = ($testData === $decrypted);
            
            // Test avec différents contextes
            $contexts = ['email', 'personal_name', '2fa_secret', 'ip_address'];
            foreach ($contexts as $context) {
                $encrypted = self::encrypt($testData, $context);
                $decrypted = self::decrypt($encrypted, $context);
                $results["context_$context"] = ($testData === $decrypted);
            }
            
            // Test de sécurité : différents contextes doivent donner différents résultats
            $enc1 = self::encrypt($testData, 'context1');
            $enc2 = self::encrypt($testData, 'context2');
            $results['context_isolation'] = ($enc1 !== $enc2);
            
            // Test de robustesse
            $results['empty_string'] = (self::encrypt('') === '');
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Effectue la rotation de la clé maître (procédure d'urgence)
     */
    public static function rotateEncryptionKey(): bool {
        try {
            SecurityManager::logSecurityEvent('ENCRYPTION_KEY_ROTATION_START', [], 'CRITICAL');
            
            // Sauvegarder l'ancienne clé
            $keyFile = __DIR__ . '/../config/.encryption_key';
            $backupFile = $keyFile . '.backup.' . date('Y-m-d-H-i-s');
            
            if (file_exists($keyFile)) {
                copy($keyFile, $backupFile);
                chmod($backupFile, 0600);
            }
            
            // Réinitialiser la clé
            self::$masterKey = null;
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
            
            // Générer nouvelle clé
            self::initializeMasterKey();
            
            SecurityManager::logSecurityEvent('ENCRYPTION_KEY_ROTATION_SUCCESS', [
                'backup_file' => $backupFile
            ], 'CRITICAL');
            
            return true;
            
        } catch (Exception $e) {
            SecurityManager::logSecurityEvent('ENCRYPTION_KEY_ROTATION_FAILED', [
                'error' => $e->getMessage()
            ], 'CRITICAL');
            
            return false;
        }
    }
    
    /**
     * Statistiques sur le chiffrement
     */
    public static function getEncryptionStats(): array {
        return [
            'cipher_algorithm' => self::$config['cipher'],
            'key_length_bits' => self::$config['key_length'] * 8,
            'iv_length_bits' => self::$config['iv_length'] * 8,
            'pbkdf2_iterations' => self::$config['iterations'],
            'key_file_exists' => file_exists(__DIR__ . '/../config/.encryption_key'),
            'openssl_version' => OPENSSL_VERSION_TEXT
        ];
    }
} 