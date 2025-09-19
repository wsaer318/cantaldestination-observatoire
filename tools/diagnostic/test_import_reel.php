<?php
/**
 * Test de l'import réel avec un échantillon de données CSV
 */

echo "🧪 TEST DE L'IMPORT RÉEL AVEC ÉCHANTILLON CSV\n";
echo "============================================\n\n";

// Inclure les dépendances
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

$pdo = DatabaseConfig::getConnection();
if (!$pdo) {
    echo "❌ Impossible de se connecter à la base de données\n";
    exit(1);
}

echo "✅ Connexion à la base de données réussie\n\n";

// Fichier CSV à tester
$csv_file = data_temp_file('frequentation_journee_fr.csv');

if (!file_exists($csv_file)) {
    echo "❌ Fichier CSV non trouvé : $csv_file\n";
    exit(1);
}

echo "📁 Test avec le fichier : $csv_file\n";
echo "📊 Taille : " . number_format(filesize($csv_file) / 1024, 1) . " KB\n\n";

// Ouvrir le fichier CSV
$handle = fopen($csv_file, 'r');
if (!$handle) {
    echo "❌ Impossible d'ouvrir le fichier CSV\n";
    exit(1);
}

// Lire l'en-tête
$header = fgetcsv($handle, 0, ';');
if (!$header) {
    echo "❌ Impossible de lire l'en-tête du CSV\n";
    fclose($handle);
    exit(1);
}

echo "📋 En-tête CSV : " . implode(', ', $header) . "\n\n";

// Trouver les colonnes importantes
$zone_col = array_search('ZoneObservation', $header);
$date_col = array_search('Date', $header);
$volume_col = array_search('Volume', $header);

if ($zone_col === false || $date_col === false || $volume_col === false) {
    echo "❌ Colonnes requises non trouvées (ZoneObservation, Date, Volume)\n";
    fclose($handle);
    exit(1);
}

echo "✅ Colonnes trouvées : Zone($zone_col), Date($date_col), Volume($volume_col)\n\n";

// Statistiques de test
$stats = [
    'total_lignes' => 0,
    'lignes_mappees' => 0,
    'lignes_erreur' => 0,
    'zones_uniques' => [],
    'volume_total' => 0,
    'volume_mappe' => 0,
    'erreurs_detail' => []
];

echo "🔍 ANALYSE DES PREMIÈRES 100 LIGNES\n";
echo "===================================\n\n";

$line_count = 0;
while (($data = fgetcsv($handle, 0, ';')) !== false && $line_count < 100) {
    $line_count++;
    $stats['total_lignes']++;
    
    if (!isset($data[$zone_col]) || !isset($data[$volume_col])) {
        continue;
    }
    
    $zone_csv = trim($data[$zone_col]);
    $volume = intval($data[$volume_col]);
    $stats['volume_total'] += $volume;
    
    // Compter les zones uniques
    if (!isset($stats['zones_uniques'][$zone_csv])) {
        $stats['zones_uniques'][$zone_csv] = 0;
    }
    $stats['zones_uniques'][$zone_csv]++;
    
    // Test du mapping
    $zone_mapped = ZoneMapper::displayToBase($zone_csv);
    $zone_id = ZoneMapper::getZoneId($zone_mapped, $pdo);
    
    if ($zone_id) {
        $stats['lignes_mappees']++;
        $stats['volume_mappe'] += $volume;
        
        if ($line_count <= 10) {
            echo "✅ Ligne $line_count : '$zone_csv' → '$zone_mapped' (ID: $zone_id, Volume: $volume)\n";
        }
    } else {
        $stats['lignes_erreur']++;
        $stats['erreurs_detail'][] = [
            'ligne' => $line_count,
            'zone_csv' => $zone_csv,
            'zone_mapped' => $zone_mapped,
            'volume' => $volume
        ];
        
        if ($line_count <= 10) {
            echo "❌ Ligne $line_count : '$zone_csv' → '$zone_mapped' (ERREUR, Volume: $volume)\n";
        }
    }
}

fclose($handle);

echo "\n📊 RÉSULTATS DU TEST\n";
echo "====================\n\n";

echo "📈 Lignes analysées : " . number_format($stats['total_lignes']) . "\n";
echo "✅ Lignes mappées avec succès : " . number_format($stats['lignes_mappees']) . "\n";
echo "❌ Lignes en erreur : " . number_format($stats['lignes_erreur']) . "\n";

if ($stats['total_lignes'] > 0) {
    $taux_succes = round(($stats['lignes_mappees'] / $stats['total_lignes']) * 100, 2);
    echo "📈 Taux de succès : $taux_succes%\n";
}

echo "\n📊 VOLUME DE DONNÉES :\n";
echo "=====================\n\n";
echo "📈 Volume total : " . number_format($stats['volume_total']) . "\n";
echo "✅ Volume mappé : " . number_format($stats['volume_mappe']) . "\n";

if ($stats['volume_total'] > 0) {
    $taux_volume = round(($stats['volume_mappe'] / $stats['volume_total']) * 100, 2);
    echo "📈 Taux de volume préservé : $taux_volume%\n";
}

echo "\n🗂️ ZONES UNIQUES TROUVÉES :\n";
echo "===========================\n\n";

foreach ($stats['zones_uniques'] as $zone => $count) {
    $mapped = ZoneMapper::displayToBase($zone);
    $zone_id = ZoneMapper::getZoneId($mapped, $pdo);
    $status = $zone_id ? "✅" : "❌";
    
    echo "$status '$zone' ($count occurrences) → '$mapped'\n";
}

if (!empty($stats['erreurs_detail'])) {
    echo "\n🚨 DÉTAIL DES ERREURS :\n";
    echo "======================\n\n";
    
    foreach ($stats['erreurs_detail'] as $erreur) {
        echo "❌ Ligne {$erreur['ligne']} : '{$erreur['zone_csv']}' → '{$erreur['zone_mapped']}' (Volume: {$erreur['volume']})\n";
    }
}

echo "\n🎯 RECOMMANDATIONS :\n";
echo "====================\n\n";

if ($stats['lignes_erreur'] == 0) {
    echo "✅ Tous les mappings fonctionnent parfaitement !\n";
    echo "✅ L'import peut être lancé en toute sécurité\n";
    echo "✅ Aucune perte de données attendue\n";
} else {
    echo "⚠️ Des erreurs de mapping ont été détectées\n";
    echo "🔧 Vérifier et corriger les mappings manquants\n";
    echo "📊 Impact estimé : " . number_format($stats['lignes_erreur']) . " lignes perdues\n";
}

echo "\n🏁 Test terminé !\n";
?>
