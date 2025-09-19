<?php
/**
 * Test final des chemins de migration corrigés
 */

echo "🧪 TEST FINAL DES CHEMINS DE MIGRATION\n";
echo "======================================\n\n";

echo "📁 Vérification des chemins corrigés :\n";
echo "======================================\n\n";

// Test depuis AdminTempTablesController
$controller_dir = __DIR__ . '/../../classes';
$migration_path = $controller_dir . '/../tools/migration/migrate_temp_to_main.php';

echo "1️⃣ Depuis AdminTempTablesController :\n";
echo "   Chemin migration : $migration_path\n";
echo "   Existe : " . (file_exists($migration_path) ? "✅ OUI" : "❌ NON") . "\n\n";

// Test depuis migrate_temp_to_main.php
$migration_dir = __DIR__ . '/../../tools/migration';
$database_path = $migration_dir . '/../../config/database.php';
$log_path = $migration_dir . '/../../logs/migration_temp_to_main.log';

echo "2️⃣ Depuis migrate_temp_to_main.php :\n";
echo "   Chemin database.php : $database_path\n";
echo "   Database existe : " . (file_exists($database_path) ? "✅ OUI" : "❌ NON") . "\n";
echo "   Chemin log : $log_path\n";
echo "   Dossier logs existe : " . (is_dir(dirname($log_path)) ? "✅ OUI" : "❌ NON") . "\n\n";

echo "🔧 Test d'instanciation :\n";
echo "=========================\n\n";

try {
    // Inclure le fichier de migration
    require_once $migration_path;
    echo "✅ Inclusion du fichier de migration réussie\n";
    
    // Tester l'instanciation
    $migrator = new TempToMainMigrator(true); // Mode silencieux
    echo "✅ Instanciation de TempToMainMigrator réussie\n";
    
    // Fermer proprement
    $migrator->close();
    echo "✅ Fermeture réussie\n";
    
    echo "\n🎉 TOUS LES CHEMINS SONT CORRECTS !\n";
    
} catch (Exception $e) {
    echo "❌ Exception : " . $e->getMessage() . "\n";
    echo "📍 Fichier : " . $e->getFile() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ Erreur fatale : " . $e->getMessage() . "\n";
    echo "📍 Fichier : " . $e->getFile() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
}

echo "\n📋 Résumé des corrections apportées :\n";
echo "====================================\n\n";
echo "✅ AdminTempTablesController.php :\n";
echo "   require_once __DIR__ . '/../tools/migration/migrate_temp_to_main.php'\n\n";
echo "✅ migrate_temp_to_main.php :\n";
echo "   require_once __DIR__ . '/../../config/database.php'\n";
echo "   \$this->log_file = __DIR__ . '/../../logs/migration_temp_to_main.log'\n\n";

echo "🏁 Test terminé !\n";
?>
