<?php
/**
 * Test rapide pour vérifier que les chemins sont corrects
 */

echo "🧪 TEST RAPIDE DES CHEMINS CORRIGÉS\n";
echo "===================================\n\n";

// Test du fichier update_temp_tables.php
echo "📁 update_temp_tables.php :\n";
echo "==========================\n\n";

$config_path = __DIR__ . '/../import/../../config/database.php';
$resolved_path = realpath($config_path);

echo "🔍 Chemin demandé : $config_path\n";
echo "🔍 Chemin résolu : $resolved_path\n";

if (file_exists($config_path)) {
    echo "✅ Fichier config/database.php accessible\n";

    // Tester l'inclusion
    try {
        require_once $config_path;
        echo "✅ Inclusion réussie\n";

        if (class_exists('DatabaseConfig')) {
            echo "✅ Classe DatabaseConfig trouvée\n";
        } else {
            echo "❌ Classe DatabaseConfig non trouvée\n";
        }

    } catch (Exception $e) {
        echo "❌ Erreur d'inclusion : " . $e->getMessage() . "\n";
    }

} else {
    echo "❌ Fichier config/database.php inaccessible\n";
}

echo "\n";

// Test du fichier check_import_progress.php
echo "📁 check_import_progress.php :\n";
echo "==============================\n\n";

$config_path2 = __DIR__ . '/../import/../../config/database.php';
$resolved_path2 = realpath($config_path2);

echo "🔍 Chemin demandé : $config_path2\n";
echo "🔍 Chemin résolu : $resolved_path2\n";

if (file_exists($config_path2)) {
    echo "✅ Fichier config/database.php accessible\n";
} else {
    echo "❌ Fichier config/database.php inaccessible\n";
}

echo "\n🎯 CONCLUSION :\n";
echo "===============\n\n";

if (file_exists($config_path) && file_exists($config_path2)) {
    echo "✅ Les chemins sont corrects !\n";
    echo "🎉 L'erreur 'Failed to open stream' devrait être résolue\n\n";

    echo "💡 Prochaines étapes :\n";
    echo "   • Actualisez la page d'administration\n";
    echo "   • Essayez à nouveau l'action 'force'\n";
    echo "   • L'erreur devrait avoir disparu\n\n";
} else {
    echo "❌ Les chemins sont encore incorrects\n";
    echo "🔧 Vérifiez la structure des dossiers\n\n";
}

echo "🏁 Test terminé !\n";
?>
