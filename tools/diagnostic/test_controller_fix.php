<?php
/**
 * Test pour vérifier que le contrôleur trouve bien les fichiers CSV maintenant
 */

echo "🧪 TEST DU CONTRÔLEUR APRÈS CORRECTION\n";
echo "=====================================\n\n";

// Inclure les dépendances
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../classes/AdminTempTablesController.php';

echo "📊 Test du contrôleur AdminTempTablesController :\n";
echo "================================================\n\n";

// Créer une instance du contrôleur
$controller = new AdminTempTablesController();

// Simuler l'appel à calculateStats (privée) en copiant la logique
echo "🔍 Test de la logique calculateStats() :\n";
echo "=======================================\n\n";

// Utiliser un chemin absolu plus robuste
$primary_dir = DATA_TEMP_PRIMARY_PATH;
$legacy_dir = DATA_TEMP_LEGACY_PATH;
$data_dir = resolve_data_temp_dir(true);

echo "Chemin principal attendu : $primary_dir\n";
echo "Ancien chemin (legacy) : $legacy_dir\n";
echo "Chemin utilise par resolve_data_temp_dir() : $data_dir\n\n";

if (!is_dir($data_dir)) {
    echo "ATTENTION: resolve_data_temp_dir() retourne un dossier introuvable\n";
}

if (is_dir($data_dir)) {
    echo "✅ Dossier final accessible : $data_dir\n\n";

    // Lister les fichiers CSV
    $csv_files = glob($data_dir . '/*.csv');
    echo "📋 Fichiers CSV trouvés : " . count($csv_files) . "\n";

    foreach ($csv_files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        echo "   • $filename (" . number_format($size / 1024, 1) . " KB)\n";
    }

    echo "\n";

    // Test de recherche récursive comme dans le template
    echo "🔍 Test de recherche récursive (comme dans l'interface) :\n";
    echo "======================================================\n\n";

    $all_csv_files = [];
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($data_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fi) {
            if ($fi->isFile() && strtolower($fi->getExtension()) === 'csv') {
                $all_csv_files[] = $fi->getPathname();
            }
        }
        echo "✅ Recherche récursive réussie : " . count($all_csv_files) . " fichiers trouvés\n";
    } catch (Exception $e) {
        echo "⚠️ Échec recherche récursive : " . $e->getMessage() . "\n";
        $all_csv_files = glob($data_dir . '/*.csv');
        echo "🔄 Fallback simple : " . count($all_csv_files) . " fichiers trouvés\n";
    }

    foreach ($all_csv_files as $file_path) {
        $filename = basename($file_path);
        $size = filesize($file_path);
        echo "   • $filename (" . number_format($size / 1024, 1) . " KB)\n";
    }

} else {
    echo "❌ Dossier final inaccessible : $data_dir\n";
}

echo "\n🎯 CONCLUSION :\n";
echo "===============\n\n";

if (isset($all_csv_files) && count($all_csv_files) > 0) {
    echo "✅ Le contrôleur devrait maintenant trouver " . count($all_csv_files) . " fichier(s) CSV\n";
    echo "✅ L'interface d'administration devrait les afficher\n";
} else {
    echo "❌ Le contrôleur ne trouve toujours pas de fichiers CSV\n";
    echo "🔍 Vérifiez que les fichiers sont dans le bon dossier\n";
}

echo "\n🏁 Test terminé !\n";
?>
