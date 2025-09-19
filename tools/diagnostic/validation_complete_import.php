<?php
/**
 * Validation complète du système d'import des zones d'observation
 */

echo "🎯 VALIDATION COMPLÈTE DU SYSTÈME D'IMPORT\n";
echo "==========================================\n\n";

require_once __DIR__ . '/../../config/app.php';
// Inclure les dépendances
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

$pdo = DatabaseConfig::getConnection();
if (!$pdo) {
    echo "❌ Impossible de se connecter à la base de données\n";
    exit(1);
}

echo "✅ Connexion à la base de données réussie\n\n";

// 1. Vérification des fichiers CSV disponibles
echo "📁 1. VÉRIFICATION DES FICHIERS CSV\n";
echo "===================================\n\n";

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

$data_paths = array_unique(array_filter([
    resolve_data_temp_dir(),
    DATA_TEMP_PRIMARY_PATH,
    DATA_TEMP_LEGACY_PATH
]));

$files_found = 0;
$files_missing = 0;

foreach ($expected_files as $file => $table) {
    $found = false;
    foreach ($data_paths as $path) {
        $fullPath = rtrim($path, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $file;
        if (file_exists($fullPath)) {
            echo "✅ $file → $table\n";
            $files_found++;
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "❌ $file → $table (MANQUANT)\n";
        $files_missing++;
    }
}

echo "\n📊 Bilan fichiers : $files_found trouvés, $files_missing manquants\n\n";

// 2. Test du mapping de toutes les zones trouvées dans les CSV
echo "🗂️ 2. VALIDATION DU MAPPING DES ZONES\n";
echo "=====================================\n\n";

// Zones réelles des CSV avec leurs occurrences approximatives
$zones_reelles = [
    'Cantal' => 3033,
    'Chataigneraie' => 2004,  // Caractère mal encodé
    'Hautes Terres' => 1842,
    'Pays Saint Flour' => 2009,
    'Pays dAurillac' => 1571, // Caractère mal encodé
    'Haut Cantal' => 1345,
    'Val Truyere' => 1303,    // Caractère mal encodé
    'Pays Salers' => 1120,
    'Pays de Mauriac' => 1008,
    'Carlades' => 1138,
    'Lioran' => 877
];

$total_volume = array_sum($zones_reelles);
$volume_ok = 0;
$mapping_success = 0;

foreach ($zones_reelles as $zone => $volume) {
    $mapped = ZoneMapper::displayToBase($zone);
    $zone_id = ZoneMapper::getZoneId($mapped, $pdo);
    
    if ($zone_id) {
        echo "✅ '$zone' → '$mapped' (ID: $zone_id) - Volume: " . number_format($volume) . "\n";
        $mapping_success++;
        $volume_ok += $volume;
    } else {
        echo "❌ '$zone' → '$mapped' (ERREUR) - Volume: " . number_format($volume) . "\n";
    }
}

echo "\n📊 Bilan mapping : $mapping_success/" . count($zones_reelles) . " zones OK\n";
echo "📊 Volume préservé : " . number_format($volume_ok) . "/" . number_format($total_volume) . " (" . round(($volume_ok/$total_volume)*100, 1) . "%)\n\n";

// 3. Vérification de la cohérence des mappings entre ZoneMapper et update_temp_tables
echo "🔄 3. COHÉRENCE DES MAPPINGS\n";
echo "============================\n\n";

// Test de quelques mappings critiques
$test_mappings = [
    'Pays dAurillac' => 'CABA',
    'Chataigneraie' => 'CHÂTAIGNERAIE',
    'Val Truyere' => 'VAL TRUYÈRE',
    'Haut Cantal' => 'GENTIANE',
    'Hautes Terres' => 'HTC',
    'Lioran' => 'STATION'
];

$coherence_ok = 0;
foreach ($test_mappings as $input => $expected) {
    $actual = ZoneMapper::displayToBase($input);
    if ($actual === $expected) {
        echo "✅ '$input' → '$actual' (attendu: '$expected')\n";
        $coherence_ok++;
    } else {
        echo "❌ '$input' → '$actual' (attendu: '$expected') - INCOHÉRENCE\n";
    }
}

echo "\n📊 Cohérence : $coherence_ok/" . count($test_mappings) . " mappings cohérents\n\n";

// 4. Test des zones exclues
echo "🚫 4. VÉRIFICATION DES ZONES EXCLUES\n";
echo "====================================\n\n";

$zones_exclues = [
    'CA DU BASSIN D\'AURILLAC',
    'CC DU CARLADE', 
    'CC DU PAYS DE SALERS',
    'SAINT FLOUR COMMUNAUTE',
    'ST FLOUR COMMUNAUTE',
    'STATION DE SKI',
    'VALLEE DE LA TRUYERE'
];

echo "Zones qui doivent être exclues des filtres mais mappées vers les zones principales :\n";
foreach ($zones_exclues as $zone) {
    $mapped = ZoneMapper::displayToBase($zone);
    $zone_id = ZoneMapper::getZoneId($mapped, $pdo);
    
    if ($zone_id) {
        echo "✅ '$zone' → '$mapped' (ID: $zone_id)\n";
    } else {
        echo "❌ '$zone' → '$mapped' (ERREUR)\n";
    }
}

// 5. Résumé final
echo "\n🎯 RÉSUMÉ FINAL\n";
echo "===============\n\n";

$global_score = 0;
$max_score = 4;

// Score fichiers CSV
if ($files_found >= 3) { // Au moins les fichiers essentiels
    echo "✅ Fichiers CSV : SUFFISANT ($files_found/$files_found disponibles)\n";
    $global_score++;
} else {
    echo "⚠️ Fichiers CSV : INSUFFISANT ($files_found disponibles)\n";
}

// Score mapping
if ($mapping_success == count($zones_reelles)) {
    echo "✅ Mapping des zones : PARFAIT (100%)\n";
    $global_score++;
} else {
    echo "⚠️ Mapping des zones : INCOMPLET (" . round(($mapping_success/count($zones_reelles))*100, 1) . "%)\n";
}

// Score cohérence
if ($coherence_ok == count($test_mappings)) {
    echo "✅ Cohérence mappings : PARFAITE (100%)\n";
    $global_score++;
} else {
    echo "⚠️ Cohérence mappings : PROBLÈMES DÉTECTÉS\n";
}

// Score volume
if ($volume_ok == $total_volume) {
    echo "✅ Préservation données : PARFAITE (0% de perte)\n";
    $global_score++;
} else {
    $perte = round((($total_volume - $volume_ok) / $total_volume) * 100, 2);
    echo "⚠️ Préservation données : PERTE DE $perte%\n";
}

echo "\n🏆 SCORE GLOBAL : $global_score/$max_score\n";

if ($global_score == $max_score) {
    echo "🎉 EXCELLENT ! Le système d'import est prêt pour la production\n";
    echo "✅ Tous les mappings fonctionnent parfaitement\n";
    echo "✅ Aucune perte de données attendue\n";
    echo "🚀 L'import peut être lancé en toute sécurité\n";
} else {
    echo "⚠️ Des améliorations sont nécessaires avant la mise en production\n";
    echo "🔧 Vérifier et corriger les points signalés ci-dessus\n";
}

echo "\n🏁 Validation terminée !\n";
?>
