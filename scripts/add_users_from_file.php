<?php
/**
 * Script d'ajout des utilisateurs depuis le fichier useurs.txt
 * Avec chiffrement des données sensibles et mots de passe sécurisés
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/EncryptionManager.php';
require_once __DIR__ . '/../classes/Security.php';
require_once __DIR__ . '/../classes/SecurityManager.php';

class UsersImporter {
    
    private $db;
    private $defaultPassword = 'FluxVision2024!';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Données des utilisateurs à ajouter
     */
    private function getUsersData() {
        return [
            [
                'name' => 'Bruno AVIGNON',
                'email' => 'bavignon@cantal-destination.com',
                'username' => 'bavignon'
            ],
            [
                'name' => 'Christophe CLERMONT',
                'email' => 'cclermont@cantal-destination.com',
                'username' => 'cclermont'
            ],
            [
                'name' => 'Alain EVINA',
                'email' => 'aevina@cantal-destination.com',
                'username' => 'aevina'
            ],
            [
                'name' => 'Valérie PINQUIER',
                'email' => 'vpinquier@cantal-destination.com', // Email généré car manquant
                'username' => 'vpinquier'
            ],
            [
                'name' => 'Mathieu ALLAIN',
                'email' => 'directionot@carlades.fr',
                'username' => 'mallain'
            ],
            [
                'name' => 'Julien COUTY',
                'email' => 'direction@hautesterrestourisme.fr',
                'username' => 'jcouty'
            ],
            [
                'name' => 'Marlène LIADOUZE',
                'email' => 'mliadouze@hautesterrestourisme.fr',
                'username' => 'mliadouze'
            ],
            [
                'name' => 'Sandrine MOURLON',
                'email' => 'sandrine.mourlon@lelioran.com',
                'username' => 'smourlon'
            ],
            [
                'name' => 'Séverine ANDURAND',
                'email' => 'sandurand@chataigneraie-cantal.com',
                'username' => 'sandurand'
            ],
            [
                'name' => 'Pascal MOUREAUX',
                'email' => 'pmoureaux@chataigneraie-cantal.com',
                'username' => 'pmoureaux'
            ],
            [
                'name' => 'Laurent BALMISSE',
                'email' => 'direction@chataigneraie-cantal.com',
                'username' => 'lbalmisse'
            ],
            [
                'name' => 'Karine DECQ',
                'email' => 'direction@pays-saint-flour.fr',
                'username' => 'kdecq'
            ],
            [
                'name' => 'Virginie RAYNAL',
                'email' => 'chaudesaigues.info@pays-saint-flour.fr',
                'username' => 'vraynal'
            ],
            [
                'name' => 'Franck REY',
                'email' => 'directeur@iaurillac.com',
                'username' => 'frey'
            ],
            [
                'name' => 'Loïc RENAULT',
                'email' => 'direction@destinationhautcantal.fr',
                'username' => 'lrenault'
            ],
            [
                'name' => 'Sylvie GANRY',
                'email' => 'direction@salers-tourisme.fr',
                'username' => 'sganry'
            ]
        ];
    }
    
    /**
     * Vérifie si un utilisateur existe déjà
     */
    private function userExists($username, $email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Ajoute un utilisateur avec chiffrement
     */
    private function addUser($userData) {
        try {
            // Vérifier si l'utilisateur existe déjà
            if ($this->userExists($userData['username'], $userData['email'])) {
                echo "⚠️  Utilisateur {$userData['username']} existe déjà, ignoré.\n";
                return false;
            }
            
            // Chiffrer les données sensibles
            $encryptedName = EncryptionManager::encryptName($userData['name']);
            $encryptedEmail = EncryptionManager::encryptEmail($userData['email']);
            
            // Hasher le mot de passe
            $hashedPassword = password_hash($this->defaultPassword, PASSWORD_ARGON2ID);
            
            // Insérer l'utilisateur
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, name, email, role, created_at, active) 
                VALUES (?, ?, ?, ?, 'user', NOW(), 1)
            ");
            
            $result = $stmt->execute([
                $userData['username'],
                $hashedPassword,
                $encryptedName,
                $encryptedEmail
            ]);
            
            if ($result) {
                echo "✅ Utilisateur {$userData['username']} ({$userData['name']}) ajouté avec succès.\n";
                return true;
            } else {
                echo "❌ Erreur lors de l'ajout de {$userData['username']}.\n";
                return false;
            }
            
        } catch (Exception $e) {
            echo "❌ Erreur pour {$userData['username']}: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Importe tous les utilisateurs
     */
    public function importUsers() {
        echo "🚀 Début de l'import des utilisateurs...\n\n";
        
        $users = $this->getUsersData();
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($users as $userData) {
            if ($this->addUser($userData)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        echo "\n📊 RÉSULTATS DE L'IMPORT:\n";
        echo "✅ Utilisateurs ajoutés: $successCount\n";
        echo "❌ Erreurs: $errorCount\n";
        echo "📄 Total traité: " . count($users) . "\n\n";
        
        if ($successCount > 0) {
            echo "🔑 MOT DE PASSE POUR TOUS LES NOUVEAUX UTILISATEURS:\n";
            echo "Password: {$this->defaultPassword}\n\n";
            echo "⚠️  IMPORTANT: Demandez aux utilisateurs de changer leur mot de passe à la première connexion!\n\n";
        }
        
        return ['success' => $successCount, 'errors' => $errorCount];
    }
    
    /**
     * Affiche les utilisateurs créés avec leurs informations déchiffrées
     */
    public function showCreatedUsers() {
        echo "👥 LISTE DES UTILISATEURS CRÉÉS:\n\n";
        
        $stmt = $this->db->prepare("
            SELECT id, username, name, email, role, created_at 
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            // Déchiffrer les données pour affichage
            $decryptedName = EncryptionManager::decryptName($user['name']);
            $decryptedEmail = EncryptionManager::decryptEmail($user['email']);
            
            echo "ID: {$user['id']}\n";
            echo "Username: {$user['username']}\n";
            echo "Nom: $decryptedName\n";
            echo "Email: $decryptedEmail\n";
            echo "Rôle: {$user['role']}\n";
            echo "Créé le: {$user['created_at']}\n";
            echo "---\n";
        }
    }
}

// Exécution du script
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    echo "🔐 FluxVision - Import d'utilisateurs avec chiffrement\n";
    echo "================================================\n\n";
    
    try {
        $importer = new UsersImporter();
        $results = $importer->importUsers();
        
        if ($results['success'] > 0) {
            echo "\n";
            $importer->showCreatedUsers();
        }
        
    } catch (Exception $e) {
        echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Import Utilisateurs FluxVision</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn { background: #007cba; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            .btn:hover { background: #005a87; }
            .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🔐 Import des Utilisateurs FluxVision</h1>
            <p>Ce script va ajouter 16 nouveaux utilisateurs dans la base de données avec chiffrement des données sensibles.</p>
            
            <div class='warning'>
                <strong>⚠️ Important:</strong>
                <ul>
                    <li>Tous les utilisateurs auront le mot de passe: <code>FluxVision2024!</code></li>
                    <li>Les noms et emails seront chiffrés en base de données</li>
                    <li>Les utilisateurs existants seront ignorés</li>
                </ul>
            </div>
            
            <button class='btn' onclick=\"window.location.href='?run=1'\">
                🚀 Lancer l'Import
            </button>
        </div>
    </body>
    </html>";
}
?>