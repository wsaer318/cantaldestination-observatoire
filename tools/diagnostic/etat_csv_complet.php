<?php
/**
 * État complet des fichiers CSV attendus vs présents
 * et analyse des zones d'observation
 */

echo "📊 ÉTAT COMPLET DES FICHIERS CSV ET ZONES D'OBSERVATION\n";
echo "=======================================================\n\n";
require_once __DIR__ . '/../../config/app.php';

// Fichiers CSV attendus selon update_temp_tables.php
$expected_files = [
    'frequentation_nuitee_fr.csv' => 'fact_nuitees_departements_temp',
    'frequentation_nuitee_int.csv' => 'fact_nuitees_pays_temp', 
    'frequentation_nuitee.csv' => 'fact_nuitees_temp',
    'frequentation_journee_fr.csv' => 'fact_diurnes_departements_temp',
    'frequentation_journee_int.csv' => 'fact_diurnes_pays_temp',
    'frequentation_journee.csv' => 'fact_diurnes_temp',
    'export_mobilite.csv' => 'fact_lieu_activite_soir_temp',
    'duree_sejour.csv' => 'fact_sejours_duree_temp',
    'duree_sejour_fr.csv' => 'fact_sejours_duree_departements_temp',
    'duree_sejour_int.csv' => 'fact_sejours_duree_pays_temp'
];

// Chemins à vérifier
$data_paths = array_unique(array_filter([
    resolve_data_temp_dir(),
    DATA_TEMP_PRIMARY_PATH,
    DATA_TEMP_LEGACY_PATH,
    DATA_PATH
]));

echo "📋 FICHIERS CSV ATTENDUS :\n";
echo "==========================\n\n";

$found_files = [];
$missing_files = [];
$all_zones = [];

foreach ($expected_files as $csv_file => $table_name) {
    echo "🔍 $csv_file → $table_name\n";
    
    $found = false;
    foreach ($data_paths as $path) {
        $full_path = rtrim($path, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $csv_file;
        
        if (file_exists($full_path)) {
            $file_size = filesize($full_path);
            $file_date = date('Y-m-d H:i:s', filemtime($full_path));
            
            echo "   ✅ Trouvé : $full_path\n";
            echo "   📊 Taille : " . number_format($file_size / 1024, 1) . " KB\n";
            echo "   📅 Modifié : $file_date\n";
            
            // Analyser les zones dans ce fichier
            $zones = analyzeZonesInCSV($full_path);
            if (!empty($zones)) {
                echo "   🗂️ Zones trouvées : " . count($zones) . "\n";
                foreach ($zones as $zone => $count) {
                    echo "      • $zone ($count occurrences)\n";
                    if (!isset($all_zones[$zone])) {
                        $all_zones[$zone] = [];
                    }
                    $all_zones[$zone][] = $csv_file;
                }
            }
            
            $found_files[] = [
                'name' => $csv_file,
                'path' => $full_path,
                'table' => $table_name,
                'size' => $file_size,
                'zones' => $zones
            ];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "   ❌ MANQUANT\n";
        $missing_files[] = [
            'name' => $csv_file,
            'table' => $table_name
        ];
    }
    echo "\n";
}

echo "📊 RÉSUMÉ GLOBAL\n";
echo "================\n\n";
echo "✅ Fichiers trouvés : " . count($found_files) . " / " . count($expected_files) . "\n";
echo "❌ Fichiers manquants : " . count($missing_files) . "\n\n";

if (!empty($missing_files)) {
    echo "🚨 FICHIERS MANQUANTS :\n";
    echo "=======================\n\n";
    
    foreach ($missing_files as $missing) {
        echo "❌ {$missing['name']} → {$missing['table']}\n";
    }
    echo "\n";
}

if (!empty($all_zones)) {
    echo "🗂️ ZONES D'OBSERVATION TROUVÉES\n";
    echo "===============================\n\n";
    
    // Charger le ZoneMapper pour vérifier les mappings
    require_once __DIR__ . '/../../classes/ZoneMapper.php';
    
    $zones_mappees = 0;
    $zones_non_mappees = 0;
    
    foreach ($all_zones as $zone => $files) {
        $mapped = ZoneMapper::displayToBase($zone);
        $mapping_status = ($mapped !== $zone) ? "✅ → $mapped" : "❌ Pas de mapping";
        
        if ($mapped !== $zone) {
            $zones_mappees++;
        } else {
            $zones_non_mappees++;
        }
        
        echo "🏷️ '$zone'\n";
        echo "   📁 Dans : " . implode(', ', array_unique($files)) . "\n";
        echo "   🔄 Mapping : $mapping_status\n\n";
    }
    
    echo "📈 STATISTIQUES MAPPING :\n";
    echo "=========================\n\n";
    echo "✅ Zones correctement mappées : $zones_mappees\n";
    echo "❌ Zones sans mapping : $zones_non_mappees\n";
    
    if ($zones_non_mappees > 0) {
        echo "\n🚨 ZONES À AJOUTER DANS LE MAPPING :\n";
        echo "====================================\n\n";
        
        foreach ($all_zones as $zone => $files) {
            $mapped = ZoneMapper::displayToBase($zone);
            if ($mapped === $zone) {
                echo "// À ajouter dans ZoneMapper.php :\n";
                echo "'$zone' => 'ZONE_BASE_CORRESPONDANTE',\n\n";
            }
        }
    }
}

echo "🏁 Analyse terminée !\n";

/**
 * Analyser les zones dans un fichier CSV
 */
function analyzeZonesInCSV($file_path) {
    $zones = [];
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return $zones;
    }
    
    // Lire l'en-tête (essayer ; puis ,)
    $header = fgetcsv($handle, 0, ';');
    if (!$header || count($header) == 1) {
        rewind($handle);
        $header = fgetcsv($handle, 0, ',');
    }
    
    if (!$header) {
        fclose($handle);
        return $zones;
    }
    
    // Trouver la colonne des zones
    $zone_column = -1;
    $possible_zone_columns = ['ZoneObservation', 'zone_observation', 'Zone d\'observation', 'Zone', 'zone'];
    
    foreach ($possible_zone_columns as $col_name) {
        $index = array_search($col_name, $header);
        if ($index !== false) {
            $zone_column = $index;
            break;
        }
    }
    
    if ($zone_column === -1) {
        fclose($handle);
        return $zones;
    }
    
    // Déterminer le séparateur
    $separator = (count($header) > 1) ? ';' : ',';
    
    // Analyser les zones (limiter à 10000 lignes pour performance)
    $line_count = 0;
    while (($data = fgetcsv($handle, 0, $separator)) !== false && $line_count < 10000) {
        $line_count++;
        
        if (isset($data[$zone_column]) && !empty($data[$zone_column])) {
            $zone = trim($data[$zone_column]);
            
            if (!isset($zones[$zone])) {
                $zones[$zone] = 0;
            }
            $zones[$zone]++;
        }
    }
    
    fclose($handle);
    
    // Trier par nombre d'occurrences décroissant
    arsort($zones);
    
    return $zones;
}
?>
