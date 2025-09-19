<?php
/**
 * Analyse des zones d'observation présentes dans tous les fichiers CSV
 * pour s'assurer du bon mapping
 */

echo "🔍 ANALYSE DES ZONES D'OBSERVATION DANS LES CSV\n";
echo "===============================================\n\n";
require_once __DIR__ . '/../../config/app.php';

// Chemins des fichiers CSV à analyser
$filenames = [
    'frequentation_journee.csv',
    'frequentation_journee_fr.csv',
    'frequentation_journee_int.csv'
];
$candidate_dirs = array_unique(array_filter([
    resolve_data_temp_dir(),
    DATA_TEMP_PRIMARY_PATH,
    DATA_TEMP_LEGACY_PATH
]));

$csv_files = [];
foreach ($filenames as $filename) {
    foreach ($candidate_dirs as $dir) {
        $csv_files[] = rtrim($dir, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $filename;
    }
}

$csv_files = array_unique($csv_files);

$all_zones = [];
$zones_by_file = [];

foreach ($csv_files as $file_path) {
    echo "📁 Analyse du fichier : $file_path\n";
    
    if (!file_exists($file_path)) {
        echo "   ❌ Fichier non trouvé\n\n";
        continue;
    }
    
    $file_size = filesize($file_path);
    echo "   📊 Taille : " . number_format($file_size / 1024, 1) . " KB\n";
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo "   ❌ Impossible d'ouvrir le fichier\n\n";
        continue;
    }
    
    // Lire l'en-tête (essayer d'abord avec ; puis avec ,)
    $header = fgetcsv($handle, 0, ';');
    if (!$header || count($header) == 1) {
        rewind($handle);
        $header = fgetcsv($handle, 0, ',');
    }
    if (!$header) {
        echo "   ❌ Impossible de lire l'en-tête\n\n";
        fclose($handle);
        continue;
    }
    
    echo "   📋 En-tête : " . implode(', ', array_slice($header, 0, 5)) . "...\n";
    
    // Trouver la colonne des zones
    $zone_column = -1;
    $possible_zone_columns = ['ZoneObservation', 'zone_observation', 'Zone d\'observation', 'Zone', 'zone'];
    
    foreach ($possible_zone_columns as $col_name) {
        $index = array_search($col_name, $header);
        if ($index !== false) {
            $zone_column = $index;
            echo "   🎯 Colonne zone trouvée : '$col_name' (index $index)\n";
            break;
        }
    }
    
    if ($zone_column === -1) {
        echo "   ❌ Aucune colonne de zone trouvée\n";
        echo "   🔍 Colonnes disponibles : " . implode(', ', $header) . "\n\n";
        fclose($handle);
        continue;
    }
    
    // Déterminer le séparateur utilisé
    $separator = (count($header) > 1) ? ';' : ',';
    echo "   🔧 Séparateur détecté : '$separator'\n";
    
    // Analyser les zones
    $file_zones = [];
    $line_count = 0;
    
    while (($data = fgetcsv($handle, 0, $separator)) !== false) {
        $line_count++;
        
        if (isset($data[$zone_column]) && !empty($data[$zone_column])) {
            $zone = trim($data[$zone_column]);
            
            if (!isset($file_zones[$zone])) {
                $file_zones[$zone] = 0;
            }
            $file_zones[$zone]++;
            
            if (!isset($all_zones[$zone])) {
                $all_zones[$zone] = [];
            }
            $all_zones[$zone][] = basename($file_path);
        }
        
        // Limiter pour éviter de traiter des fichiers trop volumineux
        if ($line_count > 50000) {
            echo "   ⚠️ Limite de 50 000 lignes atteinte, arrêt de l'analyse\n";
            break;
        }
    }
    
    fclose($handle);
    
    echo "   📊 Lignes analysées : " . number_format($line_count) . "\n";
    echo "   🗂️ Zones uniques trouvées : " . count($file_zones) . "\n";
    
    // Afficher les zones de ce fichier
    if (!empty($file_zones)) {
        echo "   📋 Zones dans ce fichier :\n";
        arsort($file_zones);
        foreach ($file_zones as $zone => $count) {
            echo "      • $zone ($count occurrences)\n";
        }
    }
    
    $zones_by_file[basename($file_path)] = $file_zones;
    echo "\n";
}

echo "🎯 RÉSUMÉ GLOBAL DES ZONES\n";
echo "==========================\n\n";

if (!empty($all_zones)) {
    echo "📊 Total des zones uniques trouvées : " . count($all_zones) . "\n\n";
    
    foreach ($all_zones as $zone => $files) {
        echo "🗂️ Zone : '$zone'\n";
        echo "   📁 Présente dans : " . implode(', ', array_unique($files)) . "\n";
        echo "   📊 Nombre de fichiers : " . count(array_unique($files)) . "\n\n";
    }
    
    echo "🔍 ANALYSE DES MAPPINGS NÉCESSAIRES\n";
    echo "===================================\n\n";
    
    // Charger le ZoneMapper pour vérifier les mappings
    require_once __DIR__ . '/../../classes/ZoneMapper.php';
    
    $zones_non_mappees = [];
    $zones_mappees_correctement = [];
    
    foreach (array_keys($all_zones) as $zone) {
        $mapped = ZoneMapper::displayToBase($zone);
        
        if ($mapped === $zone) {
            // Pas de mapping trouvé ou zone déjà en format base
            $base_to_display = ZoneMapper::baseToDisplay($zone);
            if ($base_to_display !== $zone) {
                $zones_mappees_correctement[] = $zone;
                echo "✅ '$zone' → mapping OK (zone de base)\n";
            } else {
                $zones_non_mappees[] = $zone;
                echo "❌ '$zone' → AUCUN MAPPING TROUVÉ\n";
            }
        } else {
            $zones_mappees_correctement[] = $zone;
            echo "✅ '$zone' → '$mapped' (mapping OK)\n";
        }
    }
    
    echo "\n📊 BILAN MAPPINGS :\n";
    echo "✅ Zones correctement mappées : " . count($zones_mappees_correctement) . "\n";
    echo "❌ Zones sans mapping : " . count($zones_non_mappees) . "\n\n";
    
    if (!empty($zones_non_mappees)) {
        echo "🚨 ZONES À AJOUTER DANS LE MAPPING :\n";
        echo "====================================\n\n";
        
        foreach ($zones_non_mappees as $zone) {
            echo "// À ajouter dans ZoneMapper.php :\n";
            echo "'$zone' => 'ZONE_BASE_CORRESPONDANTE',\n\n";
        }
    }
    
} else {
    echo "❌ Aucune zone trouvée dans les fichiers CSV\n";
}

echo "🏁 Analyse terminée !\n";
?>
