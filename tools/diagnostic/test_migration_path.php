<?php
/**
 * Test du chemin de migration et des dépendances
 */

echo "🧪 TEST DU SYSTÈME DE MIGRATION\n";
echo "===============================\n\n";

echo "🔍 Vérification des chemins :\n";
echo "=============================\n\n";

// Test du chemin vers migrate_temp_to_main.php depuis AdminTempTablesController
$controller_path = __DIR__ . '/../../classes/AdminTempTablesController.php';
$migration_path_from_controller = dirname($controller_path) . '/../tools/migration/migrate_temp_to_main.php';
$migration_path_resolved = realpath($migration_path_from_controller);

echo "📁 Chemin depuis AdminTempTablesController :\n";
echo "   Calculé : $migration_path_from_controller\n";
echo "   Résolu : " . ($migration_path_resolved ?: "❌ INTROUVABLE") . "\n";
echo "   Existe : " . (file_exists($migration_path_from_controller) ? "✅ OUI" : "❌ NON") . "\n\n";

// Test du chemin vers database.php depuis migrate_temp_to_main.php
$migration_file = __DIR__ . '/../../tools/migration/migrate_temp_to_main.php';
$database_path_from_migration = dirname($migration_file) . '/../../config/database.php';
$database_path_resolved = realpath($database_path_from_migration);

echo "📁 Chemin vers database.php depuis migration :\n";
echo "   Calculé : $database_path_from_migration\n";
echo "   Résolu : " . ($database_path_resolved ?: "❌ INTROUVABLE") . "\n";
echo "   Existe : " . (file_exists($database_path_from_migration) ? "✅ OUI" : "❌ NON") . "\n\n";

echo "🔧 Test d'inclusion du fichier de migration :\n";
echo "=============================================\n\n";

try {
    if (file_exists($migration_path_from_controller)) {
        echo "✅ Fichier de migration trouvé\n";
        
        // Tenter d'inclure le fichier
        ob_start();
        include_once $migration_path_from_controller;
        $output = ob_get_clean();
        
        echo "✅ Inclusion réussie\n";
        
        // Vérifier si la classe existe
        if (class_exists('TempToMainMigrator')) {
            echo "✅ Classe TempToMainMigrator disponible\n";
            
            // Tester l'instanciation
            try {
                $migrator = new TempToMainMigrator(true); // Mode silencieux
                echo "✅ Instanciation réussie\n";
                $migrator->close();
            } catch (Exception $e) {
                echo "❌ Erreur d'instanciation : " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ Classe TempToMainMigrator non trouvée\n";
        }
        
    } else {
        echo "❌ Fichier de migration introuvable\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors du test : " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Erreur fatale : " . $e->getMessage() . "\n";
}

echo "\n🔍 Vérification structure du projet :\n";
echo "====================================\n\n";

$expected_files = [
    'classes/AdminTempTablesController.php',
    'tools/migration/migrate_temp_to_main.php',
    'config/database.php'
];

foreach ($expected_files as $file) {
    $full_path = __DIR__ . '/../../' . $file;
    $exists = file_exists($full_path);
    echo "📄 $file : " . ($exists ? "✅ OK" : "❌ MANQUANT") . "\n";
}

echo "\n🏁 Test terminé !\n";
?>
