<?php
/**
 * Test rapide de la correction de migration
 */

echo "🧪 TEST DE LA CORRECTION DE MIGRATION\n";
echo "=====================================\n\n";

// Simuler l'appel depuis AdminTempTablesController
$controller_dir = __DIR__ . '/../../classes';
$migration_path = $controller_dir . '/../tools/migration/migrate_temp_to_main.php';

echo "📁 Chemin calculé : $migration_path\n";
echo "📄 Fichier existe : " . (file_exists($migration_path) ? "✅ OUI" : "❌ NON") . "\n";

if (file_exists($migration_path)) {
    echo "\n🔧 Test d'inclusion...\n";
    
    try {
        // Inclure le fichier
        require_once $migration_path;
        echo "✅ Inclusion réussie\n";
        
        // Vérifier la classe
        if (class_exists('TempToMainMigrator')) {
            echo "✅ Classe TempToMainMigrator disponible\n";
            
            // Test d'instanciation (mode silencieux)
            $migrator = new TempToMainMigrator(true);
            echo "✅ Instanciation réussie\n";
            
            // Fermer proprement
            $migrator->close();
            echo "✅ Fermeture réussie\n";
            
        } else {
            echo "❌ Classe TempToMainMigrator non trouvée\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception : " . $e->getMessage() . "\n";
        echo "📍 Fichier : " . $e->getFile() . "\n";
        echo "📍 Ligne : " . $e->getLine() . "\n";
    } catch (Error $e) {
        echo "❌ Erreur fatale : " . $e->getMessage() . "\n";
        echo "📍 Fichier : " . $e->getFile() . "\n";
        echo "📍 Ligne : " . $e->getLine() . "\n";
    }
} else {
    echo "❌ Le fichier n'existe pas au chemin calculé\n";
}

echo "\n🏁 Test terminé !\n";
?>
