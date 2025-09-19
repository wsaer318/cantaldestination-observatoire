<?php
/**
 * Diagnostic complet des chemins dans le système de migration
 */

echo "🔍 DIAGNOSTIC COMPLET DES CHEMINS DE MIGRATION\n";
echo "==============================================\n\n";

echo "📁 1. VÉRIFICATION DES CHEMINS DE BASE :\n";
echo "========================================\n\n";

$base_paths = [
    'Racine projet' => __DIR__ . '/../..',
    'Classes' => __DIR__ . '/../../classes',
    'Tools/migration' => __DIR__ . '/../../tools/migration',
    'Config' => __DIR__ . '/../../config',
    'Data' => __DIR__ . '/../../data',
    'Data/logs' => __DIR__ . '/../../data/logs',
    'Data/temp' => __DIR__ . '/../../data/temp'
];

foreach ($base_paths as $name => $path) {
    $resolved = realpath($path);
    $exists = is_dir($path);
    echo "📂 $name :\n";
    echo "   Chemin : $path\n";
    echo "   Résolu : " . ($resolved ?: "❌ Non résolvable") . "\n";
    echo "   Existe : " . ($exists ? "✅ OUI" : "❌ NON") . "\n\n";
}

echo "📄 2. VÉRIFICATION DES FICHIERS CRITIQUES :\n";
echo "==========================================\n\n";

$critical_files = [
    'AdminTempTablesController' => __DIR__ . '/../../classes/AdminTempTablesController.php',
    'migrate_temp_to_main' => __DIR__ . '/../../tools/migration/migrate_temp_to_main.php',
    'database config' => __DIR__ . '/../../config/database.php'
];

foreach ($critical_files as $name => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    echo "📄 $name :\n";
    echo "   Chemin : $path\n";
    echo "   Existe : " . ($exists ? "✅ OUI" : "❌ NON") . "\n";
    echo "   Lisible : " . ($readable ? "✅ OUI" : "❌ NON") . "\n\n";
}

echo "🔧 3. TEST DES CHEMINS DEPUIS AdminTempTablesController :\n";
echo "========================================================\n\n";

// Simuler le contexte d'AdminTempTablesController
$controller_dir = __DIR__ . '/../../classes';
$migration_path_from_controller = $controller_dir . '/../tools/migration/migrate_temp_to_main.php';

echo "📍 Depuis AdminTempTablesController :\n";
echo "   __DIR__ simulé : $controller_dir\n";
echo "   Chemin calculé : $migration_path_from_controller\n";
echo "   Fichier existe : " . (file_exists($migration_path_from_controller) ? "✅ OUI" : "❌ NON") . "\n\n";

echo "🔧 4. TEST DES CHEMINS DEPUIS migrate_temp_to_main.php :\n";
echo "======================================================\n\n";

// Simuler le contexte du fichier de migration
$migration_dir = __DIR__ . '/../../tools/migration';
$database_path_from_migration = $migration_dir . '/../../config/database.php';
$log_path_from_migration = $migration_dir . '/../../data/logs/migration_temp_to_main.log';

echo "📍 Depuis migrate_temp_to_main.php :\n";
echo "   __DIR__ simulé : $migration_dir\n";
echo "   Chemin database.php : $database_path_from_migration\n";
echo "   Database existe : " . (file_exists($database_path_from_migration) ? "✅ OUI" : "❌ NON") . "\n";
echo "   Chemin log : $log_path_from_migration\n";
echo "   Dossier log existe : " . (is_dir(dirname($log_path_from_migration)) ? "✅ OUI" : "❌ NON") . "\n\n";

echo "🧪 5. TEST D'INCLUSION ET D'INSTANCIATION :\n";
echo "==========================================\n\n";

try {
    if (file_exists($migration_path_from_controller)) {
        echo "✅ Fichier de migration accessible\n";
        
        // Tenter l'inclusion
        require_once $migration_path_from_controller;
        echo "✅ Inclusion réussie\n";
        
        // Vérifier la classe
        if (class_exists('TempToMainMigrator')) {
            echo "✅ Classe TempToMainMigrator disponible\n";
            
            // Tenter l'instanciation
            $migrator = new TempToMainMigrator(true); // Mode silencieux
            echo "✅ Instanciation réussie\n";
            
            // Vérifier les propriétés internes
            echo "📊 Propriétés de l'objet :\n";
            
            // Test de fermeture
            $migrator->close();
            echo "✅ Fermeture réussie\n";
            
        } else {
            echo "❌ Classe TempToMainMigrator non trouvée\n";
        }
        
    } else {
        echo "❌ Fichier de migration inaccessible\n";
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

echo "\n🎯 6. RECOMMANDATIONS :\n";
echo "======================\n\n";

$recommendations = [];

// Vérifier si les dossiers existent
if (!is_dir(__DIR__ . '/../../data/logs')) {
    $recommendations[] = "Créer le dossier data/logs";
}
if (!is_dir(__DIR__ . '/../../data/temp')) {
    $recommendations[] = "Créer le dossier data/temp";
}

// Vérifier les permissions
if (is_dir(__DIR__ . '/../../data/logs') && !is_writable(__DIR__ . '/../../data/logs')) {
    $recommendations[] = "Vérifier les permissions d'écriture sur data/logs";
}

if (empty($recommendations)) {
    echo "✅ Tous les chemins semblent corrects !\n";
} else {
    echo "🔧 Actions recommandées :\n";
    foreach ($recommendations as $i => $rec) {
        echo "   " . ($i + 1) . ". $rec\n";
    }
}

echo "\n🏁 Diagnostic terminé !\n";
?>
