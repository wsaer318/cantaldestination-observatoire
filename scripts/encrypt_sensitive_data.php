<?php
/**
 * Script de chiffrement des données sensibles existantes
 * Migration sécurisée des données en clair vers format chiffré
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/EncryptionManager.php';
require_once __DIR__ . '/../classes/SecurityManager.php';
require_once __DIR__ . '/../classes/Database.php';

echo "🔐 CHIFFREMENT DES DONNÉES SENSIBLES - FluxVision\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // Test du système de chiffrement
    echo "📋 PHASE 1 : TEST DU SYSTÈME DE CHIFFREMENT\n";
    echo str_repeat("-", 50) . "\n";
    
    $testResults = EncryptionManager::testEncryption();
    foreach ($testResults as $test => $result) {
        $status = $result ? "✅" : "❌";
        echo "$status Test $test: " . ($result ? "RÉUSSI" : "ÉCHEC") . "\n";
    }
    
    if (isset($testResults['error'])) {
        throw new Exception("Erreur dans les tests : " . $testResults['error']);
    }
    
    echo "\n📊 INFORMATIONS SYSTÈME :\n";
    $info = EncryptionManager::getEncryptionStats();
    foreach ($info as $key => $value) {
        echo "  • $key: " . (is_bool($value) ? ($value ? 'Oui' : 'Non') : $value) . "\n";
    }
    
    echo "\n📋 PHASE 2 : ANALYSE DES DONNÉES À CHIFFRER\n";
    echo str_repeat("-", 50) . "\n";
    
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Analyser la table users
    $stmt = $connection->prepare("SELECT COUNT(*) as total, 
                                        SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) as with_email,
                                        SUM(CASE WHEN two_factor_secret IS NOT NULL AND two_factor_secret != '' THEN 1 ELSE 0 END) as with_2fa
                                 FROM users");
    $stmt->execute();
    $userStats = $stmt->fetch();
    
    echo "👥 Table USERS :\n";
    echo "  • Total utilisateurs : {$userStats['total']}\n";
    echo "  • Avec email : {$userStats['with_email']}\n";
    echo "  • Avec secret 2FA : {$userStats['with_2fa']}\n";
    
    // Analyser les tables d'authentification
    $tables = [
        'auth_login_history' => 'Historique connexions',
        'auth_trusted_devices' => 'Appareils de confiance',
        'auth_2fa_sessions' => 'Sessions 2FA'
    ];
    
    foreach ($tables as $table => $desc) {
        try {
            $stmt = $connection->prepare("SELECT COUNT(*) as total FROM $table");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "📊 $desc ($table) : $count enregistrements\n";
        } catch (Exception $e) {
            echo "⚠️  Table $table non trouvée\n";
        }
    }
    
    echo "\n📋 PHASE 3 : MIGRATION DES DONNÉES\n";
    echo str_repeat("-", 50) . "\n";
    
    $confirmation = readline("Voulez-vous procéder à la migration ? (y/N) : ");
    if (strtolower($confirmation) !== 'y') {
        echo "❌ Migration annulée par l'utilisateur\n";
        exit(0);
    }
    
    $connection->beginTransaction();
    
    try {
        // 1. Migrer les emails dans la table users
        echo "🔒 Chiffrement des emails utilisateurs...\n";
        $stmt = $connection->prepare("SELECT id, email FROM users WHERE email IS NOT NULL AND email != '' AND email NOT LIKE 'AQE%'");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $emailCount = 0;
        foreach ($users as $user) {
            try {
                $encryptedEmail = EncryptionManager::encryptEmail($user['email']);
                $updateStmt = $connection->prepare("UPDATE users SET email = ? WHERE id = ?");
                $updateStmt->execute([$encryptedEmail, $user['id']]);
                $emailCount++;
                echo "  ✓ Email chiffré pour utilisateur ID {$user['id']}\n";
            } catch (Exception $e) {
                echo "  ❌ Erreur pour utilisateur ID {$user['id']} : " . $e->getMessage() . "\n";
            }
        }
        
        // 2. Migrer les noms utilisateurs
        echo "\n🔒 Chiffrement des noms utilisateurs...\n";
        $stmt = $connection->prepare("SELECT id, name FROM users WHERE name IS NOT NULL AND name != '' AND name NOT LIKE 'AQE%'");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $nameCount = 0;
        foreach ($users as $user) {
            try {
                $encryptedName = EncryptionManager::encryptName($user['name']);
                $updateStmt = $connection->prepare("UPDATE users SET name = ? WHERE id = ?");
                $updateStmt->execute([$encryptedName, $user['id']]);
                $nameCount++;
                echo "  ✓ Nom chiffré pour utilisateur ID {$user['id']}\n";
            } catch (Exception $e) {
                echo "  ❌ Erreur pour utilisateur ID {$user['id']} : " . $e->getMessage() . "\n";
            }
        }
        
        // 3. Migrer les secrets 2FA si ils existent
        echo "\n🔒 Chiffrement des secrets 2FA...\n";
        $stmt = $connection->prepare("SELECT id, two_factor_secret FROM users WHERE two_factor_secret IS NOT NULL AND two_factor_secret != '' AND two_factor_secret NOT LIKE 'AQE%'");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $secretCount = 0;
        foreach ($users as $user) {
            try {
                $encryptedSecret = EncryptionManager::encrypt2FASecret($user['two_factor_secret']);
                $updateStmt = $connection->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
                $updateStmt->execute([$encryptedSecret, $user['id']]);
                $secretCount++;
                echo "  ✓ Secret 2FA chiffré pour utilisateur ID {$user['id']}\n";
            } catch (Exception $e) {
                echo "  ❌ Erreur pour utilisateur ID {$user['id']} : " . $e->getMessage() . "\n";
            }
        }
        
        // 4. Migrer les IPs dans auth_login_history
        echo "\n🔒 Chiffrement des adresses IP...\n";
        try {
            $stmt = $connection->prepare("SELECT id, ip_address FROM auth_login_history WHERE ip_address IS NOT NULL AND ip_address NOT LIKE 'AQE%' LIMIT 100");
            $stmt->execute();
            $records = $stmt->fetchAll();
            
            $ipCount = 0;
            foreach ($records as $record) {
                try {
                    $encryptedIP = EncryptionManager::encryptIP($record['ip_address']);
                    $updateStmt = $connection->prepare("UPDATE auth_login_history SET ip_address = ? WHERE id = ?");
                    $updateStmt->execute([$encryptedIP, $record['id']]);
                    $ipCount++;
                } catch (Exception $e) {
                    echo "  ⚠️  Erreur IP record ID {$record['id']}\n";
                }
            }
            echo "  ✓ $ipCount adresses IP chiffrées\n";
        } catch (Exception $e) {
            echo "  ⚠️  Table auth_login_history non disponible\n";
        }
        
        // 5. Migrer les User-Agents
        echo "\n🔒 Chiffrement des User-Agents...\n";
        try {
            $stmt = $connection->prepare("SELECT id, user_agent FROM auth_login_history WHERE user_agent IS NOT NULL AND user_agent NOT LIKE 'AQE%' LIMIT 100");
            $stmt->execute();
            $records = $stmt->fetchAll();
            
            $uaCount = 0;
            foreach ($records as $record) {
                try {
                    $encryptedUA = EncryptionManager::encryptUserAgent($record['user_agent']);
                    $updateStmt = $connection->prepare("UPDATE auth_login_history SET user_agent = ? WHERE id = ?");
                    $updateStmt->execute([$encryptedUA, $record['id']]);
                    $uaCount++;
                } catch (Exception $e) {
                    echo "  ⚠️  Erreur UA record ID {$record['id']}\n";
                }
            }
            echo "  ✓ $uaCount User-Agents chiffrés\n";
        } catch (Exception $e) {
            echo "  ⚠️  Chiffrement User-Agents non disponible\n";
        }
        
        $connection->commit();
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🎉 MIGRATION TERMINÉE AVEC SUCCÈS !\n\n";
        echo "📊 RÉSUMÉ :\n";
        echo "  • Emails chiffrés : $emailCount\n";
        echo "  • Noms chiffrés : $nameCount\n";
        echo "  • Secrets 2FA chiffrés : $secretCount\n";
        echo "  • Adresses IP chiffrées : $ipCount\n";
        echo "  • User-Agents chiffrés : $uaCount\n\n";
        
        echo "🔐 SÉCURITÉ :\n";
        echo "  • Algorithme : AES-256-CBC\n";
        echo "  • Clés contextuelles par type de donnée\n";
        echo "  • Données authentifiées et intègres\n\n";
        
        echo "⚠️  IMPORTANT :\n";
        echo "  • Sauvegardez la base de données avant tout changement\n";
        echo "  • Les données chiffrées commencent par des caractères aléatoirement encodés\n";
        echo "  • Pour déchiffrer, utilisez EncryptionManager::decrypt[Type]()\n\n";
        
        // Log de sécurité
        SecurityManager::logSecurityEvent('DATA_ENCRYPTION_MIGRATION', [
            'emails_encrypted' => $emailCount,
            'names_encrypted' => $nameCount,
            'secrets_encrypted' => $secretCount,
            'ips_encrypted' => $ipCount,
            'user_agents_encrypted' => $uaCount
        ], 'HIGH');
        
    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR CRITIQUE : " . $e->getMessage() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
    echo "📄 Fichier : " . $e->getFile() . "\n\n";
    
    SecurityManager::logSecurityEvent('DATA_ENCRYPTION_FAILED', [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ], 'CRITICAL');
    
    exit(1);
}

echo "✅ Script terminé - Données sensibles sécurisées !\n"; 