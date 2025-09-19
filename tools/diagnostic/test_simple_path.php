<?php
/**
 * Test simple pour vérifier les chemins CSV sans dépendances complexes
 */

echo "🧪 TEST SIMPLE DES CHEMINS CSV\n";
echo "==============================\n\n";

require_once __DIR__ . '/../../config/app.php';
echo "📁 Test des chemins depuis la racine du projet :\n";
echo "===============================================\n\n";

$base_dir = __DIR__ . '/../..'; // Remonte à la racine du projet
echo "📍 Racine du projet : $base_dir\n\n";

// Test du chemin principal
$data_dir_primary = DATA_TEMP_PRIMARY_PATH;
echo "🔍 Chemin principal : $data_dir_primary\n";

if (is_dir($data_dir_primary)) {
    echo "   ✅ Dossier existe\n";
    $csv_files = glob($data_dir_primary . '/*.csv');
    echo "   📊 CSV trouvés : " . count($csv_files) . "\n";

    foreach ($csv_files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        echo "      • $filename (" . number_format($size / 1024, 1) . " KB)\n";
    }
} else {
    echo "   ❌ Dossier n'existe pas\n";
}

echo "\n";

// Test du chemin de fallback
$data_dir_fallback = DATA_TEMP_LEGACY_PATH;
echo "🔄 Chemin de fallback : $data_dir_fallback\n";

if (is_dir($data_dir_fallback)) {
    echo "   ✅ Dossier existe\n";
    $csv_files = glob($data_dir_fallback . '/*.csv');
    echo "   📊 CSV trouvés : " . count($csv_files) . "\n";

    foreach ($csv_files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        echo "      • $filename (" . number_format($size / 1024, 1) . " KB)\n";
    }
} else {
    echo "   ❌ Dossier n'existe pas\n";
}

echo "\n";

// Test de création automatique du dossier
echo "🔧 Test de création automatique :\n";
echo "=================================\n\n";

$test_dir = $base_dir . '/data/test_auto';
echo "📁 Test de création : $test_dir\n";

if (!is_dir($test_dir)) {
    if (mkdir($test_dir, 0755, true)) {
        echo "   ✅ Dossier créé avec succès\n";

        // Créer un fichier de test
        $test_file = $test_dir . '/test.csv';
        $test_content = "colonne1,colonne2,colonne3\nvaleur1,valeur2,valeur3\ntest1,test2,test3\n";

        if (file_put_contents($test_file, $test_content)) {
            echo "   ✅ Fichier de test créé\n";
            echo "   📄 Contenu : " . strlen($test_content) . " caractères\n";

            // Supprimer le fichier de test
            unlink($test_file);
            echo "   🗑️ Fichier de test supprimé\n";
        } else {
            echo "   ❌ Impossible de créer le fichier de test\n";
        }

        // Supprimer le dossier de test
        rmdir($test_dir);
        echo "   🗑️ Dossier de test supprimé\n";

    } else {
        echo "   ❌ Impossible de créer le dossier\n";
    }
} else {
    echo "   ✅ Dossier existe déjà\n";
}

echo "\n🎯 SIMULATION DE L'INTERFACE WEB :\n";
echo "===================================\n\n";

// Simuler ce que l'interface devrait voir maintenant
$final_data_dir = resolve_data_temp_dir(true);

if (!is_dir($final_data_dir)) {
    @mkdir($final_data_dir, 0755, true);
}

echo "📁 Dossier final utilisé : $final_data_dir\n";

if (is_dir($final_data_dir)) {
    $csv_files = glob($final_data_dir . '/*.csv');

    // Recherche récursive
    $all_csv = [];
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($final_data_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fi) {
            if ($fi->isFile() && strtolower($fi->getExtension()) === 'csv') {
                $all_csv[] = $fi->getPathname();
            }
        }
    } catch (Exception $e) {
        $all_csv = $csv_files;
    }

    echo "📊 Fichiers que l'interface devrait afficher : " . count($all_csv) . "\n\n";

    if (!empty($all_csv)) {
        echo "📋 Liste des fichiers :\n";
        foreach ($all_csv as $file_path) {
            $filename = basename($file_path);
            $size = filesize($file_path);
            $modified = date('d/m/Y H:i', filemtime($file_path));
            echo "   • $filename | " . number_format($size / 1024, 1) . " KB | $modified\n";
        }
    } else {
        echo "❌ Aucun fichier trouvé - L'interface affichera \"Aucun fichier CSV trouvé\"\n";
    }
} else {
    echo "❌ Dossier final inaccessible\n";
}

echo "\n✅ CONCLUSION :\n";
echo "===============\n\n";

if (isset($all_csv) && count($all_csv) > 0) {
    echo "🎉 Les corrections devraient résoudre le problème !\n";
    echo "📊 " . count($all_csv) . " fichier(s) CSV seront visibles dans l'interface\n";
} else {
    echo "⚠️ Le problème persiste - vérifiez les chemins\n";
}

echo "\n🏁 Test terminé !\n";
?>
