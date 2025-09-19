<?php
/**
 * Vérification des fichiers CSV manquants pour l'alimentation des tables temporaires
 */

echo "🔍 VÉRIFICATION DES FICHIERS CSV MANQUANTS\n";
echo "==========================================\n\n";

// Lire le script update_temp_tables.php pour identifier les fichiers CSV attendus
require_once __DIR__ . '/../../config/app.php';
$update_script = __DIR__ . '/../import/update_temp_tables.php';

if (!file_exists($update_script)) {
    echo "❌ Script update_temp_tables.php introuvable à : $update_script\n";
    exit(1);
}

echo "📖 Analyse du script : " . $update_script . "\n\n";

$script_content = file_get_contents($update_script);

// Chercher les patterns de fichiers CSV dans le script
$csv_patterns = [
    '/data_temp\/([^"\']+\.csv)/',
    '/data\/data_temp\/([^"\']+\.csv)/',
    '/fluxvision_automation\/data\/data_temp\/([^"\']+\.csv)/',
];

$expected_csvs = [];

foreach ($csv_patterns as $pattern) {
    if (preg_match_all($pattern, $script_content, $matches)) {
        foreach ($matches[1] as $csv_file) {
            $expected_csvs[] = $csv_file;
        }
    }
}

// Supprimer les doublons
$expected_csvs = array_unique($expected_csvs);

echo "📋 Fichiers CSV attendus selon le script :\n";
foreach ($expected_csvs as $csv) {
    echo "   • $csv\n";
}
echo "\n";

// Vérifier les chemins possibles où les CSV peuvent se trouver
$possible_paths = array_unique(array_filter([
    resolve_data_temp_dir(),
    DATA_TEMP_PRIMARY_PATH,
    DATA_TEMP_LEGACY_PATH,
    DATA_PATH,
    BASE_PATH
]));

echo "🔍 Vérification de la présence des fichiers :\n";
echo "=============================================\n\n";

$found_files = [];
$missing_files = [];

foreach ($expected_csvs as $csv_file) {
    $found = false;
    
    foreach ($possible_paths as $path) {
        $basePath = rtrim($path, DIRECTORY_SEPARATOR . '/');
        $full_path = $basePath . DIRECTORY_SEPARATOR . $csv_file;
        
        if (file_exists($full_path)) {
            $file_size = filesize($full_path);
            $file_date = date('Y-m-d H:i:s', filemtime($full_path));
            
            echo "✅ $csv_file\n";
            echo "   📁 Chemin : $full_path\n";
            echo "   📊 Taille : " . number_format($file_size / 1024, 1) . " KB\n";
            echo "   📅 Modifié : $file_date\n\n";
            
            $found_files[] = [
                'name' => $csv_file,
                'path' => $full_path,
                'size' => $file_size,
                'date' => $file_date
            ];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "❌ $csv_file\n";
        echo "   🔍 Recherché dans : " . implode(', ', array_map(function($p) use ($csv_file) { return $p . $csv_file; }, $possible_paths)) . "\n\n";
        $missing_files[] = $csv_file;
    }
}

// Chercher d'autres fichiers CSV qui pourraient exister
echo "🔍 AUTRES FICHIERS CSV TROUVÉS\n";
echo "==============================\n\n";

$other_csvs = [];

foreach ($possible_paths as $path) {
        $dirPath = rtrim($path, DIRECTORY_SEPARATOR . '/');
    if (is_dir($dirPath)) {
        $csv_files = glob($dirPath . DIRECTORY_SEPARATOR . '*.csv');
        foreach ($csv_files as $csv_file) {
            $basename = basename($csv_file);
            if (!in_array($basename, $expected_csvs)) {
                $file_size = filesize($csv_file);
                $file_date = date('Y-m-d H:i:s', filemtime($csv_file));
                
                echo "📄 $basename\n";
                echo "   📁 Chemin : $csv_file\n";
                echo "   📊 Taille : " . number_format($file_size / 1024, 1) . " KB\n";
                echo "   📅 Modifié : $file_date\n\n";
                
                $other_csvs[] = [
                    'name' => $basename,
                    'path' => $csv_file,
                    'size' => $file_size,
                    'date' => $file_date
                ];
            }
        }
    }
}

// Résumé
echo "📊 RÉSUMÉ\n";
echo "=========\n\n";
echo "✅ Fichiers CSV trouvés : " . count($found_files) . "\n";
echo "❌ Fichiers CSV manquants : " . count($missing_files) . "\n";
echo "📄 Autres fichiers CSV : " . count($other_csvs) . "\n\n";

if (!empty($missing_files)) {
    echo "🚨 FICHIERS MANQUANTS :\n";
    foreach ($missing_files as $missing) {
        echo "   • $missing\n";
    }
    echo "\n";
    
    echo "💡 ACTIONS RECOMMANDÉES :\n";
    echo "=========================\n";
    echo "1. Vérifier si ces fichiers sont dans d'autres dossiers\n";
    echo "2. Lancer le processus d'extraction/téléchargement des données\n";
    echo "3. Vérifier la configuration des chemins dans update_temp_tables.php\n";
    echo "4. Contacter l'équipe pour obtenir les fichiers manquants\n\n";
}

if (!empty($other_csvs)) {
    echo "💡 FICHIERS SUPPLÉMENTAIRES :\n";
    echo "=============================\n";
    echo "Ces fichiers CSV existent mais ne sont pas utilisés par le script d'import.\n";
    echo "Vérifiez s'ils doivent être intégrés au processus d'import.\n\n";
}

echo "🏁 Vérification terminée !\n";
?>
