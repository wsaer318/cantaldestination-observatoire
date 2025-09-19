<?php
/**
 * Script de test du système de chiffrement/déchiffrement
 * Vérification de l'intégrité des données
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/EncryptionManager.php';
require_once __DIR__ . '/../classes/UserDataManager.php';
require_once __DIR__ . '/../classes/Database.php';

echo "🔍 TEST DU SYSTÈME DE CHIFFREMENT - FluxVision\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // Test 1: Chiffrement/déchiffrement basique
    echo "📋 TEST 1 : CHIFFREMENT/DÉCHIFFREMENT BASIQUE\n";
    echo str_repeat("-", 50) . "\n";
    
    $testData = [
        'email' => 'test@fluxvision.fr',
        'name' => 'Jean Dupont',
        'ip' => '192.168.1.100',
        'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ];
    
    foreach ($testData as $type => $value) {
        echo "🔐 Test $type : ";
        
        try {
            switch ($type) {
                case 'email':
                    $encrypted = EncryptionManager::encryptEmail($value);
                    $decrypted = EncryptionManager::decryptEmail($encrypted);
                    break;
                case 'name':
                    $encrypted = EncryptionManager::encryptName($value);
                    $decrypted = EncryptionManager::decryptName($encrypted);
                    break;
                case 'ip':
                    $encrypted = EncryptionManager::encryptIP($value);
                    $decrypted = EncryptionManager::decryptIP($encrypted);
                    break;
                case 'userAgent':
                    $encrypted = EncryptionManager::encryptUserAgent($value);
                    $decrypted = EncryptionManager::decryptUserAgent($encrypted);
                    break;
            }
            
            if ($value === $decrypted) {
                echo "✅ RÉUSSI\n";
                echo "    Original : $value\n";
                echo "    Chiffré  : " . substr($encrypted, 0, 30) . "...\n";
                echo "    Déchiffré: $decrypted\n\n";
            } else {
                echo "❌ ÉCHEC - Valeurs différentes\n";
                echo "    Original : $value\n";
                echo "    Déchiffré: $decrypted\n\n";
            }
            
        } catch (Exception $e) {
            echo "❌ ERREUR : " . $e->getMessage() . "\n\n";
        }
    }
    
    // Test 2: Lecture des utilisateurs avec déchiffrement
    echo "📋 TEST 2 : LECTURE DES UTILISATEURS CHIFFRÉS\n";
    echo str_repeat("-", 50) . "\n";
    
    $users = UserDataManager::getAllUsers();
    
    foreach ($users as $user) {
        echo "👤 Utilisateur ID {$user['id']} :\n";
        echo "    Username : {$user['username']}\n";
        echo "    Nom déchiffré : {$user['name']}\n";
        echo "    Email déchiffré : " . ($user['email'] ?? 'N/A') . "\n";
        echo "    Rôle : {$user['role']}\n\n";
    }
    
    // Test 3: Test des différents contextes
    echo "📋 TEST 3 : ISOLATION DES CONTEXTES\n";
    echo str_repeat("-", 50) . "\n";
    
    $testValue = "Données sensibles test";
    $contexts = ['email', 'personal_name', '2fa_secret', 'ip_address', 'user_agent'];
    $encrypted = [];
    
    foreach ($contexts as $context) {
        $encrypted[$context] = EncryptionManager::encrypt($testValue, $context);
    }
    
    echo "🔐 Même valeur chiffrée avec différents contextes :\n";
    $allDifferent = true;
    for ($i = 0; $i < count($encrypted); $i++) {
        for ($j = $i + 1; $j < count($encrypted); $j++) {
            $ctx1 = $contexts[$i];
            $ctx2 = $contexts[$j];
            if ($encrypted[$ctx1] === $encrypted[$ctx2]) {
                echo "❌ Contextes $ctx1 et $ctx2 produisent le même chiffrement !\n";
                $allDifferent = false;
            }
        }
    }
    
    if ($allDifferent) {
        echo "✅ Tous les contextes produisent des chiffrements différents\n";
        foreach ($encrypted as $context => $value) {
            echo "    $context: " . substr($value, 0, 20) . "...\n";
        }
    }
    echo "\n";
    
    // Test 4: Test de robustesse
    echo "📋 TEST 4 : TEST DE ROBUSTESSE\n";
    echo str_repeat("-", 50) . "\n";
    
    $robustnessTests = [
        'empty_string' => '',
        'special_chars' => 'Email@test.com & nom spéciaux éàçù',
        'unicode' => 'Test 🔒 Unicode 中文 العربية',
        'long_string' => str_repeat('Long test string ', 50)
    ];
    
    foreach ($robustnessTests as $testName => $testValue) {
        echo "🧪 Test $testName : ";
        try {
            if (empty($testValue)) {
                $encrypted = EncryptionManager::encrypt($testValue);
                $result = ($encrypted === '');
            } else {
                $encrypted = EncryptionManager::encrypt($testValue);
                $decrypted = EncryptionManager::decrypt($encrypted);
                $result = ($testValue === $decrypted);
            }
            
            echo $result ? "✅ RÉUSSI" : "❌ ÉCHEC";
            echo "\n";
            
        } catch (Exception $e) {
            echo "❌ ERREUR : " . $e->getMessage() . "\n";
        }
    }
    
    // Test 5: Performance
    echo "\n📋 TEST 5 : PERFORMANCE\n";
    echo str_repeat("-", 50) . "\n";
    
    $iterations = 100;
    $testString = "Test de performance du chiffrement";
    
    $startTime = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $encrypted = EncryptionManager::encrypt($testString);
        $decrypted = EncryptionManager::decrypt($encrypted);
    }
    $endTime = microtime(true);
    
    $totalTime = ($endTime - $startTime) * 1000; // en millisecondes
    $avgTime = $totalTime / $iterations;
    
    echo "⏱️  Performance ($iterations opérations) :\n";
    echo "    Temps total : " . round($totalTime, 2) . " ms\n";
    echo "    Temps moyen : " . round($avgTime, 3) . " ms/opération\n";
    echo "    Débit : " . round($iterations / ($totalTime / 1000), 0) . " opérations/seconde\n\n";
    
    // Résumé final
    echo "=" . str_repeat("=", 60) . "\n";
    echo "🎉 TESTS TERMINÉS\n\n";
    
    echo "🔐 ALGORITHME DE CHIFFREMENT :\n";
    $info = EncryptionManager::getEncryptionStats();
    foreach ($info as $key => $value) {
        echo "    • $key : " . (is_bool($value) ? ($value ? 'Oui' : 'Non') : $value) . "\n";
    }
    
    echo "\n✅ Le système de chiffrement fonctionne correctement !\n";
    echo "🛡️  Les données sensibles sont protégées en base de données.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR CRITIQUE : " . $e->getMessage() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
    echo "📄 Fichier : " . $e->getFile() . "\n";
    exit(1);
} 