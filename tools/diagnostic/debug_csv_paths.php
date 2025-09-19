<?php
/**
 * Diagnostic complet des chemins CSV et visibilité des fichiers
 */

require_once __DIR__ . '/../../config/app.php';

echo "🔍 DIAGNOSTIC COMPLET DES CHEMINS CSV\n";
echo "=====================================\n\n";

// Chemins possibles où les fichiers CSV peuvent se trouver
$possible_paths = array_unique(array_filter([
    resolve_data_temp_dir(),
    DATA_TEMP_PRIMARY_PATH,
    DATA_TEMP_LEGACY_PATH,
    DATA_PATH,
    BASE_PATH . '/fluxvision_automation/data'
]));

echo "📂 CHEMINS POSSIBLES POUR LES FICHIERS CSV :\n";
echo "===========================================\n\n";

$current_dir = getcwd();
echo "📍 Répertoire de travail actuel : $current_dir\n\n";

$all_csv_files = [];

foreach ($possible_paths as $path) {
    echo "🔍 Vérification du chemin : $path\n";

    $full_path = rtrim($path, DIRECTORY_SEPARATOR . '/');
    $real_path = realpath($full_path);
    $target_path = $real_path ?: $full_path;

    if (is_dir($target_path)) {
        echo "   OK. Dossier trouve : $target_path\n";

        // Compter les fichiers CSV dans ce dossier
        $csv_files = glob($target_path . DIRECTORY_SEPARATOR . '*.csv');
        $csv_count = is_array($csv_files) ? count($csv_files) : 0;

        echo "   Infos CSV detectes : $csv_count\n";

        if ($csv_count > 0) {
            echo "   Detail des fichiers :\n";
            foreach ($csv_files as $csv_file) {
                $filename = basename($csv_file);
                $file_size = filesize($csv_file);
                $file_modified = filemtime($csv_file);

                echo "      - $filename (" . number_format($file_size / 1024, 1) . " KB, " . date('d/m/Y H:i', $file_modified) . ")\n";

                $all_csv_files[] = [
                    'path' => $csv_file,
                    'filename' => $filename,
                    'size' => $file_size,
                    'modified' => $file_modified,
                    'directory' => $target_path
                ];
            }
        }

        // Verifier les sous-dossiers
        $subdirs = glob($target_path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if (is_array($subdirs) && !empty($subdirs)) {
            echo "   Sous-dossiers :\n";
            foreach ($subdirs as $subdir) {
                $subdir_name = basename($subdir);
                $subdir_csv = glob($subdir . DIRECTORY_SEPARATOR . '*.csv');
                $subdir_count = is_array($subdir_csv) ? count($subdir_csv) : 0;

                if ($subdir_count > 0) {
                    echo "      - $subdir_name/ ($subdir_count CSV)\n";
                    foreach ($subdir_csv as $csv_file) {
                        $filename = basename($csv_file);
                        $file_size = filesize($csv_file);
                        echo "         - $filename (" . number_format($file_size / 1024, 1) . " KB)\n";

                        $all_csv_files[] = [
                            'path' => $csv_file,
                            'filename' => $filename,
                            'size' => $file_size,
                            'modified' => filemtime($csv_file),
                            'directory' => $subdir
                        ];
                    }
                } else {
                    echo "      - $subdir_name/ (vide)\n";
                }
            }
        }

    } else {
        echo "   KO. Dossier introuvable : $target_path\n";
    }

    echo "\n";
}

// Analyse du contrôleur AdminTempTablesController
echo "🎮 ANALYSE DU CONTRÔLEUR :\n";
echo "==========================\n\n";

$controller_data_dir = resolve_data_temp_dir();
$controller_real_dir = realpath($controller_data_dir) ?: $controller_data_dir;
echo "Chemin actif pour l'interface : {$controller_real_dir}\n";

if ($controller_real_dir && is_dir($controller_real_dir)) {
    echo "OK: le dossier est accessible\n";

    $controller_csv_files = glob($controller_real_dir . DIRECTORY_SEPARATOR . '*.csv');
    $controller_csv_files = is_array($controller_csv_files) ? $controller_csv_files : [];
    echo 'CSV detectes : ' . count($controller_csv_files) . "\n";

    if (!empty($controller_csv_files)) {
        echo "Fichiers :\n";
        foreach ($controller_csv_files as $file) {
            $filename = basename($file);
            $file_size = filesize($file);
            echo '  - ' . $filename . ' (' . number_format($file_size / 1024, 1) . ' KB)' . "\n";
        }
    }
} else {
    echo "ATTENTION: dossier introuvable\n";
}

echo "\n";

// Comparaison avec ce que voit l'interface web
echo "🌐 CE QUE L'INTERFACE WEB DEVRAIT VOIR :\n";
echo "=======================================\n\n";

// Simuler la logique du template
$template_csv_files = [];
if ($controller_real_dir && is_dir($controller_real_dir)) {
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controller_real_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fi) {
            if ($fi->isFile() && strtolower($fi->getExtension()) === 'csv') {
                $template_csv_files[] = $fi->getPathname();
            }
        }
        echo "✅ Recherche récursive réussie\n";
    } catch (Exception $e) {
        echo "⚠️ Échec recherche récursive : " . $e->getMessage() . "\n";
        $template_csv_files = glob($controller_real_dir . DIRECTORY_SEPARATOR . '*.csv');
        echo "🔄 Fallback vers recherche simple\n";
    }
}

echo "📊 Interface devrait afficher : " . count($template_csv_files) . " fichier(s)\n\n";

if (!empty($template_csv_files)) {
    echo "📋 Fichiers visibles dans l'interface :\n";
    foreach ($template_csv_files as $file_path) {
        $filename = basename($file_path);
        $file_size = @filesize($file_path);
        $file_modified = @filemtime($file_path);

        echo "   • $filename | " . ($file_size ? number_format($file_size / 1024, 1) . ' KB' : '-') . " | " . ($file_modified ? date('d/m/Y H:i', $file_modified) : '-') . "\n";
    }
} else {
    echo "❌ Aucun fichier visible - C'EST LE PROBLÈME !\n\n";
    echo "💡 Causes possibles :\n";
    echo "   • Le dossier configuré n'existe pas\n";
    echo "   • Aucun fichier CSV dans le dossier configuré\n";
    echo "   • Permissions insuffisantes\n";
    echo "   • Chemin incorrect dans la configuration\n\n";
}

// Détection automatique du problème
echo "🔍 DIAGNOSTIC AUTOMATIQUE :\n";
echo "===========================\n\n";

$issues = [];

if (!$controller_real_dir || !is_dir($controller_real_dir)) {
    $issues[] = "Le dossier configuré n'existe pas : $controller_data_dir";
}

if (count($template_csv_files) == 0) {
    $issues[] = "Aucun fichier CSV trouvé dans le dossier configuré";
}

if (count($all_csv_files) > 0 && count($template_csv_files) == 0) {
    $issues[] = "Des fichiers CSV existent ailleurs mais pas dans le dossier configuré";
}

if (empty($issues)) {
    echo "✅ Aucun problème détecté - l'interface devrait fonctionner normalement\n";
} else {
    echo "❌ Problèmes détectés :\n";
    foreach ($issues as $issue) {
        echo "   • $issue\n";
    }
    echo "\n";
}

// Recommandations
echo "💡 RECOMMANDATIONS :\n";
echo "===================\n\n";

if (count($all_csv_files) > 0) {
    echo "📁 Vos fichiers CSV sont dans ces dossiers :\n";
    $dirs_with_csv = array_unique(array_column($all_csv_files, 'directory'));
    foreach ($dirs_with_csv as $dir) {
        echo "   • $dir\n";
    }
    echo "\n";

    echo "🎯 Pour que l'interface les voit :\n";
    echo "   1. Copiez vos fichiers CSV dans : $controller_data_dir\n";
    echo "   2. OU ajustez resolve_data_temp_dir() dans config/app.php\n";
    echo "   3. Actualisez la page d'administration\n\n";
}

if (!is_dir($controller_data_dir)) {
    echo "📁 Le dossier configuré n'existe pas :\n";
    echo "   • Créez le dossier : $controller_data_dir\n";
    echo "   • OU modifiez le chemin dans le contrôleur\n\n";
}

echo "🔧 Si vous voulez changer le dossier de recherche :\n";
echo "   • Éditez classes/AdminTempTablesController.php ligne 1145\n";
echo "   • Changez le chemin dans calculateStats()\n\n";

echo "🏁 Diagnostic terminé !\n";
?>
