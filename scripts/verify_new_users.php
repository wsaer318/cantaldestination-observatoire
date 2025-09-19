<?php
/**
 * Script de vérification des nouveaux utilisateurs ajoutés
 * Affiche les données déchiffrées et teste l'authentification
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/EncryptionManager.php';

echo "🔍 Vérification des Nouveaux Utilisateurs FluxVision\n";
echo "===================================================\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Récupérer tous les utilisateurs récemment créés
    $stmt = $db->prepare("
        SELECT id, username, name, email, role, created_at, active, last_login
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "❌ Aucun utilisateur trouvé dans les 2 dernières heures.\n";
        exit;
    }
    
    echo "👥 UTILISATEURS AJOUTÉS (" . count($users) . " trouvés):\n\n";
    
    foreach ($users as $user) {
        // Déchiffrer les données
        $decryptedName = EncryptionManager::decryptName($user['name']);
        $decryptedEmail = EncryptionManager::decryptEmail($user['email']);
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🆔 ID: {$user['id']}\n";
        echo "👤 Username: {$user['username']}\n";
        echo "📛 Nom complet: $decryptedName\n";
        echo "📧 Email: $decryptedEmail\n";
        echo "🎭 Rôle: {$user['role']}\n";
        echo "🕐 Créé le: {$user['created_at']}\n";
        echo "✅ Actif: " . ($user['active'] ? 'Oui' : 'Non') . "\n";
        echo "🔑 Dernière connexion: " . ($user['last_login'] ? $user['last_login'] : 'Jamais') . "\n";
        echo "\n";
    }
    
    // Test de l'intégrité du chiffrement
    echo "🔐 TEST D'INTÉGRITÉ DU CHIFFREMENT:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $testData = "Test d'intégrité FluxVision";
    $encrypted = EncryptionManager::encrypt($testData);
    $decrypted = EncryptionManager::decrypt($encrypted);
    
    if ($testData === $decrypted) {
        echo "✅ Chiffrement/Déchiffrement: OK\n";
    } else {
        echo "❌ Chiffrement/Déchiffrement: ERREUR\n";
    }
    
    // Test spécifique pour emails
    $testEmail = "test@exemple.com";
    $encryptedEmail = EncryptionManager::encryptEmail($testEmail);
    $decryptedEmail = EncryptionManager::decryptEmail($encryptedEmail);
    
    if ($testEmail === $decryptedEmail) {
        echo "✅ Chiffrement Email: OK\n";
    } else {
        echo "❌ Chiffrement Email: ERREUR\n";
    }
    
    // Test spécifique pour noms
    $testName = "Jean Dupont";
    $encryptedName = EncryptionManager::encryptName($testName);
    $decryptedName = EncryptionManager::decryptName($encryptedName);
    
    if ($testName === $decryptedName) {
        echo "✅ Chiffrement Nom: OK\n";
    } else {
        echo "❌ Chiffrement Nom: ERREUR\n";
    }
    
    echo "\n";
    
    // Informations de connexion
    echo "🔑 INFORMATIONS DE CONNEXION:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🌐 URL de connexion: http://localhost/fluxvision_fin/templates/login.php\n";
    echo "🔒 Mot de passe pour tous: FluxVision2024!\n";
    echo "⚠️  Important: Chaque utilisateur doit changer son mot de passe à la première connexion\n\n";
    
    // Statistiques
    echo "📊 STATISTIQUES DE LA BASE:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $total = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as admins FROM users WHERE role = 'admin'");
    $admins = $stmt->fetch()['admins'];
    
    $stmt = $db->query("SELECT COUNT(*) as users_count FROM users WHERE role = 'user'");
    $usersCount = $stmt->fetch()['users_count'];
    
    $stmt = $db->query("SELECT COUNT(*) as active FROM users WHERE active = 1");
    $active = $stmt->fetch()['active'];
    
    echo "📈 Total utilisateurs: $total\n";
    echo "👑 Administrateurs: $admins\n";
    echo "👤 Utilisateurs standards: $usersCount\n";
    echo "✅ Comptes actifs: $active\n";
    
    echo "\n🎉 Vérification terminée avec succès!\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>