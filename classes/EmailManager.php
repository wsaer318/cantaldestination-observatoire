<?php

require_once 'Security.php';
require_once 'EncryptionManager.php';

class EmailManager {
    private static $config = null;
    
    /**
     * Initialise la configuration email
     */
    private static function initConfig() {
        if (self::$config === null) {
            // Configuration par défaut (peut être surchargée par un fichier config)
            self::$config = [
                'smtp_host' => 'localhost',
                'smtp_port' => 587,
                'smtp_secure' => 'tls', // 'tls', 'ssl' ou false
                'smtp_auth' => false,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => 'noreply@cantaldestination.fr',
                'from_name' => 'FluxVision - Observatoire Touristique',
                'admin_email' => 'admin@cantaldestination.fr',
                'enabled' => true // Peut être désactivé en développement
            ];
            
            // Charger la configuration depuis un fichier si elle existe
            $configFile = __DIR__ . '/../config/email.php';
            if (file_exists($configFile)) {
                $fileConfig = include $configFile;
                if (is_array($fileConfig)) {
                    self::$config = array_merge(self::$config, $fileConfig);
                }
            }
        }
    }
    
    /**
     * Vérifie si l'envoi d'email est activé
     */
    public static function isEnabled(): bool {
        self::initConfig();
        return self::$config['enabled'] && !empty(self::$config['admin_email']);
    }
    
    /**
     * Envoie un email de notification pour la création d'un utilisateur
     */
    public static function sendUserCreationNotification(string $newUsername, string $newUserRole, string $createdByUsername): bool {
        if (!self::isEnabled()) {
            return false;
        }
        
        try {
            self::initConfig();
            
            $subject = "🔔 Nouvel utilisateur créé - FluxVision";
            
            $body = self::generateUserCreationEmailBody($newUsername, $newUserRole, $createdByUsername);
            
            return self::sendEmail(
                self::$config['admin_email'],
                'Administrateur FluxVision',
                $subject,
                $body
            );
            
        } catch (Exception $e) {
            error_log('Erreur envoi email notification utilisateur : ' . $e->getMessage());
            // Ne pas faire échouer la création d'utilisateur si l'email échoue
            return false;
        }
    }
    
    /**
     * Envoie un email de notification pour la suppression d'un utilisateur
     */
    public static function sendUserDeletionNotification(string $deletedUsername, string $deletedUserRole, string $deletedByUsername): bool {
        if (!self::isEnabled()) {
            return false;
        }
        
        try {
            self::initConfig();
            
            $subject = "⚠️ Utilisateur supprimé - FluxVision";
            
            $body = self::generateUserDeletionEmailBody($deletedUsername, $deletedUserRole, $deletedByUsername);
            
            return self::sendEmail(
                self::$config['admin_email'],
                'Administrateur FluxVision',
                $subject,
                $body
            );
            
        } catch (Exception $e) {
            error_log('Erreur envoi email notification suppression : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Génère le corps de l'email pour la création d'utilisateur
     */
    private static function generateUserCreationEmailBody(string $newUsername, string $newUserRole, string $createdByUsername): string {
        $timestamp = date('d/m/Y à H:i:s');
        $roleLabel = $newUserRole === 'admin' ? 'Administrateur' : 'Utilisateur';
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #F1C40F, #1ABC9C); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
        .info-box { background: white; padding: 15px; border-left: 4px solid #F1C40F; margin: 15px 0; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #666; }
        .warning { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>🔔 Nouveau compte utilisateur créé</h2>
            <p>Observatoire Touristique du Cantal - FluxVision</p>
        </div>
        
        <div class='content'>
            <p>Bonjour,</p>
            
            <p>Un nouveau compte utilisateur vient d'être créé sur la plateforme FluxVision.</p>
            
            <div class='info-box'>
                <h3>📋 Détails du nouvel utilisateur :</h3>
                <ul>
                    <li><strong>Nom d'utilisateur :</strong> {$newUsername}</li>
                    <li><strong>Rôle :</strong> {$roleLabel}</li>
                    <li><strong>Créé par :</strong> {$createdByUsername}</li>
                    <li><strong>Date et heure :</strong> {$timestamp}</li>
                </ul>
            </div>
            
            " . ($newUserRole === 'admin' ? "<p class='warning'>⚠️ ATTENTION : Ce nouvel utilisateur a des privilèges administrateur et peut accéder à toutes les fonctionnalités de gestion.</p>" : "") . "
            
            <p>Cette notification automatique vous permet de surveiller les créations de comptes sur votre plateforme.</p>
            
            <p>Si cette création n'était pas attendue, veuillez vérifier immédiatement les logs de sécurité et contacter votre équipe technique.</p>
            
            <div class='footer'>
                <p>📧 Message automatique envoyé par FluxVision<br>
                🕒 {$timestamp}<br>
                🔒 Ne pas répondre à ce message</p>
            </div>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Génère le corps de l'email pour la suppression d'utilisateur
     */
    private static function generateUserDeletionEmailBody(string $deletedUsername, string $deletedUserRole, string $deletedByUsername): string {
        $timestamp = date('d/m/Y à H:i:s');
        $roleLabel = $deletedUserRole === 'admin' ? 'Administrateur' : 'Utilisateur';
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
        .info-box { background: white; padding: 15px; border-left: 4px solid #e74c3c; margin: 15px 0; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #666; }
        .critical { color: #e74c3c; font-weight: bold; background: #fdf2f2; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>⚠️ Compte utilisateur supprimé</h2>
            <p>Observatoire Touristique du Cantal - FluxVision</p>
        </div>
        
        <div class='content'>
            <p>Bonjour,</p>
            
            <div class='critical'>
                <strong>🚨 ACTION CRITIQUE :</strong> Un compte utilisateur a été supprimé définitivement de la plateforme FluxVision.
            </div>
            
            <div class='info-box'>
                <h3>📋 Détails de la suppression :</h3>
                <ul>
                    <li><strong>Utilisateur supprimé :</strong> {$deletedUsername}</li>
                    <li><strong>Rôle :</strong> {$roleLabel}</li>
                    <li><strong>Supprimé par :</strong> {$deletedByUsername}</li>
                    <li><strong>Date et heure :</strong> {$timestamp}</li>
                </ul>
            </div>
            
            <p><strong>⚠️ IMPORTANT :</strong> Cette suppression est définitive et irréversible. Toutes les données associées à ce compte ont été supprimées.</p>
            
            <p>Cette notification automatique vous permet de surveiller les suppressions de comptes sur votre plateforme.</p>
            
            <p>Si cette suppression n'était pas autorisée, veuillez immédiatement :</p>
            <ul>
                <li>Vérifier les logs de sécurité</li>
                <li>Contacter l'utilisateur {$deletedByUsername}</li>
                <li>Examiner les accès administrateur récents</li>
            </ul>
            
            <div class='footer'>
                <p>📧 Message automatique envoyé par FluxVision<br>
                🕒 {$timestamp}<br>
                🔒 Ne pas répondre à ce message</p>
            </div>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Envoie un email simple
     */
    private static function sendEmail(string $to, string $toName, string $subject, string $body): bool {
        self::initConfig();
        
        // En mode développement ou si SMTP n'est pas configuré, log l'email au lieu de l'envoyer
        if (!self::$config['smtp_auth'] || empty(self::$config['smtp_username'])) {
            error_log("EMAIL SIMULATION - To: {$to}, Subject: {$subject}");
            return true;
        }
        
        // Headers pour email HTML
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::$config['from_name'] . ' <' . self::$config['from_email'] . '>',
            'Reply-To: ' . self::$config['from_email'],
            'X-Mailer: FluxVision EmailManager',
            'X-Priority: 3'
        ];
        
        // Utilisation de la fonction mail() de PHP (peut être remplacée par PHPMailer si besoin)
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Test la configuration email
     */
    public static function testConfiguration(): array {
        self::initConfig();
        
        $results = [
            'enabled' => self::$config['enabled'],
            'admin_email' => self::$config['admin_email'],
            'from_email' => self::$config['from_email'],
            'smtp_configured' => !empty(self::$config['smtp_host']),
            'can_send' => false
        ];
        
        if (self::isEnabled()) {
            try {
                $testResult = self::sendEmail(
                    self::$config['admin_email'],
                    'Test Admin',
                    'Test FluxVision EmailManager',
                    '<h2>Test de configuration email</h2><p>Ce message confirme que l\'EmailManager fonctionne correctement.</p>'
                );
                $results['can_send'] = $testResult;
            } catch (Exception $e) {
                $results['error'] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Retourne la configuration actuelle (sans les mots de passe)
     */
    public static function getConfiguration(): array {
        self::initConfig();
        $config = self::$config;
        unset($config['smtp_password']); // Ne pas exposer le mot de passe
        return $config;
    }
} 