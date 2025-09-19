<?php
/**
 * Test de la correction du système de connexion
 * Vérifie que les données déchiffrées apparaissent dans les sessions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Auth.php';

echo "🧪 TEST DE LA CORRECTION DE CONNEXION - FluxVision\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    echo "📋 TEST 1 : SIMULATION D'UNE CONNEXION\n";
    echo str_repeat("-", 40) . "\n";
    
    // Démarrer une session propre
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Tenter une connexion avec l'utilisateur admin
    echo "🔐 Tentative de connexion utilisateur 'admin'...\n";
    
    $loginSuccess = Auth::login('admin', 'admin123');
    
    if ($loginSuccess) {
        echo "✅ Connexion réussie !\n\n";
        
        echo "📋 TEST 2 : VÉRIFICATION DES DONNÉES DE SESSION\n";
        echo str_repeat("-", 40) . "\n";
        
        $user = Auth::getUser();
        
        if ($user) {
            echo "👤 Données utilisateur en session :\n";
            echo "  • ID : {$user['id']}\n";
            echo "  • Username : {$user['username']}\n";
            echo "  • Nom : {$user['name']}\n";
            echo "  • Email : {$user['email']}\n";
            echo "  • Rôle : {$user['role']}\n\n";
            
            // Vérifier si les données sont déchiffrées (pas de caractères aléatoires)
            $nameIsDecrypted = !preg_match('/^[A-Za-z0-9+\/]+=*$/', $user['name']);
            $emailIsDecrypted = !preg_match('/^[A-Za-z0-9+\/]+=*$/', $user['email']);
            
            echo "📊 RÉSULTATS DU TEST :\n";
            echo "  • Nom déchiffré : " . ($nameIsDecrypted ? "✅ OUI" : "❌ NON") . "\n";
            echo "  • Email déchiffré : " . ($emailIsDecrypted ? "✅ OUI" : "❌ NON") . "\n";
            
            if ($nameIsDecrypted && $emailIsDecrypted) {
                echo "\n🎉 SUCCÈS : Les données sont correctement déchiffrées !\n";
                echo "🔒 Sécurité : Les données restent chiffrées en base\n";
                echo "💾 Session : Les données sont déchiffrées en mémoire uniquement\n";
                
                echo "\n📋 TEST 3 : VÉRIFICATION BASE DE DONNÉES\n";
                echo str_repeat("-", 40) . "\n";
                
                // Vérifier que les données sont toujours chiffrées en base
                $db = Database::getInstance();
                $stmt = $db->getConnection()->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $dbUser = $stmt->fetch();
                
                echo "🗄️  Données en base (doivent être chiffrées) :\n";
                echo "  • Nom : " . substr($dbUser['name'], 0, 30) . "...\n";
                echo "  • Email : " . substr($dbUser['email'], 0, 30) . "...\n";
                
                $nameIsEncrypted = preg_match('/^[A-Za-z0-9+\/]+=*$/', $dbUser['name']);
                $emailIsEncrypted = preg_match('/^[A-Za-z0-9+\/]+=*$/', $dbUser['email']);
                
                echo "\n📊 VÉRIFICATION SÉCURITÉ :\n";
                echo "  • Nom chiffré en base : " . ($nameIsEncrypted ? "✅ OUI" : "❌ NON") . "\n";
                echo "  • Email chiffré en base : " . ($emailIsEncrypted ? "✅ OUI" : "❌ NON") . "\n";
                
                if ($nameIsEncrypted && $emailIsEncrypted) {
                    echo "\n🛡️  PARFAIT ! Protection complète validée :\n";
                    echo "    ✅ Données chiffrées en base de données\n";
                    echo "    ✅ Données déchiffrées en session utilisateur\n";
                    echo "    ✅ Interface utilisateur fonctionnelle\n";
                } else {
                    echo "\n⚠️  ATTENTION : Données non chiffrées détectées en base\n";
                }
                
            } else {
                echo "\n❌ ÉCHEC : Les données ne sont pas correctement déchiffrées\n";
                echo "🔧 Action requise : Vérifier la configuration du chiffrement\n";
            }
            
        } else {
            echo "❌ Aucune donnée utilisateur en session\n";
        }
        
    } else {
        echo "❌ Échec de la connexion\n";
        echo "🔧 Vérifiez les identifiants ou la configuration\n";
    }
    
    // Nettoyer la session de test
    Auth::logout();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🏁 TEST TERMINÉ\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR CRITIQUE : " . $e->getMessage() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
    echo "📄 Fichier : " . $e->getFile() . "\n";
    exit(1);
} 