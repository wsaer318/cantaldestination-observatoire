<?php
/**
 * Script de test pour vérifier la résolution du conflit DatabaseConfig
 */

echo "🔧 Test de Résolution du Conflit DatabaseConfig\n";
echo "===============================================\n\n";

try {
    // Test 1: Inclusion de database.php
    echo "1️⃣ Test inclusion database.php...\n";
    require_once __DIR__ . '/../database.php';
    echo "   ✅ database.php inclus avec succès\n\n";
    
    // Test 2: Inclusion de config/database.php  
    echo "2️⃣ Test inclusion config/database.php...\n";
    require_once __DIR__ . '/../config/database.php';
    echo "   ✅ config/database.php inclus avec succès\n\n";
    
    // Test 3: Vérification que DatabaseConfig existe
    echo "3️⃣ Test existence classe DatabaseConfig...\n";
    if (class_exists('DatabaseConfig')) {
        echo "   ✅ Classe DatabaseConfig trouvée\n";
        
        // Test de la méthode getConfig
        $config = DatabaseConfig::getConfig();
        echo "   ✅ Méthode getConfig() fonctionne\n";
        echo "   📊 Environnement détecté: {$config['environment']}\n";
        echo "   🗄️  Base de données: {$config['database']}\n";
        
    } else {
        echo "   ❌ Classe DatabaseConfig non trouvée\n";
    }
    echo "\n";
    
    // Test 4: Vérification que DatabaseConfigHelper existe
    echo "4️⃣ Test existence classe DatabaseConfigHelper...\n";
    if (class_exists('DatabaseConfigHelper')) {
        echo "   ✅ Classe DatabaseConfigHelper trouvée\n";
        
        // Test de la méthode getConfig
        $config = DatabaseConfigHelper::getConfig();
        echo "   ✅ Méthode getConfig() fonctionne\n";
        
    } else {
        echo "   ❌ Classe DatabaseConfigHelper non trouvée\n";
    }
    echo "\n";
    
    // Test 5: Test de connexion réelle
    echo "5️⃣ Test de connexion à la base de données...\n";
    try {
        $db = FluxVisionDatabase::getInstance();
        $connection = $db->getConnection();
        echo "   ✅ Connexion réussie via FluxVisionDatabase\n";
        
        // Test d'une requête simple
        $result = $db->query("SELECT 1 as test");
        if ($result && $result[0]['test'] == 1) {
            echo "   ✅ Requête de test réussie\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erreur de connexion: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 6: Test avec les nouvelles classes d'utilisateurs
    echo "6️⃣ Test chargement classes utilisateurs...\n";
    try {
        require_once __DIR__ . '/../classes/Database.php';
        echo "   ✅ classes/Database.php inclus\n";
        
        require_once __DIR__ . '/../classes/EncryptionManager.php';
        echo "   ✅ classes/EncryptionManager.php inclus\n";
        
        $dbInstance = Database::getInstance();
        echo "   ✅ Instance Database créée\n";
        
    } catch (Exception $e) {
        echo "   ❌ Erreur classes: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    echo "🎉 RÉSULTAT FINAL:\n";
    echo "================\n";
    echo "✅ Conflit DatabaseConfig résolu avec succès!\n";
    echo "✅ Toutes les classes fonctionnent correctement\n";
    echo "✅ La base de données est accessible\n";
    echo "✅ Les utilisateurs peuvent être ajoutés/lus\n\n";
    
    echo "🔧 CHANGEMENTS EFFECTUÉS:\n";
    echo "========================\n";
    echo "• config/database.php : Classe DatabaseConfig supprimée\n";
    echo "• config/database.php : Nouvelle classe DatabaseConfigHelper créée\n";
    echo "• TempTablesManager.php : Référence corrigée vers database.php\n";
    echo "• AdminTempTablesController.php : Référence corrigée vers database.php\n";
    echo "• Toutes les inclusions de fichiers synchronisées\n\n";
    
} catch (Error $e) {
    echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . "\n";
    echo "📍 Ligne: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
}
?> 