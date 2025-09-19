<?php
/**
 * Test direct de l'import des zones avec simulation du processus
 */

echo "🧪 TEST DE L'IMPORT DES ZONES\n";
echo "=============================\n\n";

// Inclure les dépendances
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

// Obtenir la configuration de base de données
$config = DatabaseConfig::getConfig();
$pdo = DatabaseConfig::getConnection();

if (!$pdo) {
    echo "❌ Impossible de se connecter à la base de données\n";
    exit(1);
}

echo "✅ Connexion à la base de données réussie\n\n";

// Lister les zones disponibles en base
echo "🗂️ ZONES DISPONIBLES EN BASE DE DONNÉES\n";
echo "=======================================\n\n";

$stmt = $pdo->query("SELECT id_zone, nom_zone FROM dim_zones_observation ORDER BY nom_zone");
$zones_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($zones_db as $zone) {
    echo "   {$zone['id_zone']} → {$zone['nom_zone']}\n";
}
echo "\n";

// Zones trouvées dans les CSV (selon l'analyse précédente)
$zones_csv_reelles = [
    'Cantal' => 1406,
    'Chataigneraie' => 1125,  // Caractère mal encodé
    'Hautes Terres' => 1075,
    'Pays Saint Flour' => 1048,
    'Pays dAurillac' => 991,  // Caractère mal encodé
    'Haut Cantal' => 846,
    'Val Truyere' => 738,     // Caractère mal encodé
    'Pays Salers' => 718,
    'Pays de Mauriac' => 705,
    'Carlades' => 698,
    'Lioran' => 650
];

echo "📊 TEST DU MAPPING POUR LES ZONES CSV RÉELLES\n";
echo "=============================================\n\n";

$mapping_success = 0;
$mapping_errors = 0;
$mapping_details = [];

foreach ($zones_csv_reelles as $zone_csv => $occurrences) {
    echo "📍 Zone CSV : '$zone_csv' ($occurrences occurrences)\n";
    
    // Étape 1 : Mapping via ZoneMapper
    $zone_mapped = ZoneMapper::displayToBase($zone_csv);
    echo "   🔄 Mapping displayToBase : '$zone_csv' → '$zone_mapped'\n";
    
    // Étape 2 : Obtenir l'ID
    $zone_id = ZoneMapper::getZoneId($zone_mapped, $pdo);
    
    if ($zone_id) {
        echo "   ✅ ID trouvé : $zone_id\n";
        
        // Vérifier le nom exact en base
        $stmt = $pdo->prepare("SELECT nom_zone FROM dim_zones_observation WHERE id_zone = ?");
        $stmt->execute([$zone_id]);
        $nom_base = $stmt->fetchColumn();
        echo "   📊 Nom en base : '$nom_base'\n";
        
        // Test du mapping inverse
        $display_name = ZoneMapper::baseToDisplay($nom_base);
        echo "   🔄 Mapping pour affichage : '$nom_base' → '$display_name'\n";
        
        $mapping_success++;
        $mapping_details[] = [
            'csv' => $zone_csv,
            'mapped' => $zone_mapped,
            'id' => $zone_id,
            'base_name' => $nom_base,
            'display' => $display_name,
            'occurrences' => $occurrences,
            'status' => 'OK'
        ];
        
    } else {
        echo "   ❌ AUCUN ID TROUVÉ - PROBLÈME DE MAPPING\n";
        
        // Chercher des zones similaires
        $stmt = $pdo->prepare("SELECT nom_zone FROM dim_zones_observation WHERE nom_zone LIKE ?");
        $stmt->execute(['%' . $zone_mapped . '%']);
        $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($similar)) {
            echo "   🔍 Zones similaires : " . implode(', ', $similar) . "\n";
        }
        
        $mapping_errors++;
        $mapping_details[] = [
            'csv' => $zone_csv,
            'mapped' => $zone_mapped,
            'id' => null,
            'base_name' => null,
            'display' => null,
            'occurrences' => $occurrences,
            'status' => 'ERROR'
        ];
    }
    echo "\n";
}

echo "📈 RÉSUMÉ DU MAPPING\n";
echo "====================\n\n";
echo "✅ Mappings réussis : $mapping_success / " . count($zones_csv_reelles) . "\n";
echo "❌ Erreurs de mapping : $mapping_errors\n";

if ($mapping_errors > 0) {
    echo "\n🚨 ZONES PROBLÉMATIQUES :\n";
    foreach ($mapping_details as $detail) {
        if ($detail['status'] === 'ERROR') {
            echo "   ❌ '{$detail['csv']}' ({$detail['occurrences']} occurrences) → Pas de mapping vers '{$detail['mapped']}'\n";
        }
    }
}

echo "\n📊 IMPACT SUR L'IMPORT\n";
echo "======================\n\n";

$total_occurrences = array_sum($zones_csv_reelles);
$lost_occurrences = 0;

foreach ($mapping_details as $detail) {
    if ($detail['status'] === 'ERROR') {
        $lost_occurrences += $detail['occurrences'];
    }
}

echo "📈 Total d'enregistrements CSV : " . number_format($total_occurrences) . "\n";
echo "✅ Enregistrements importables : " . number_format($total_occurrences - $lost_occurrences) . "\n";
echo "❌ Enregistrements perdus : " . number_format($lost_occurrences) . "\n";

if ($lost_occurrences > 0) {
    $perte_pourcentage = round(($lost_occurrences / $total_occurrences) * 100, 2);
    echo "📉 Pourcentage de perte : $perte_pourcentage%\n";
}

echo "\n🏁 Test terminé !\n";
?>
