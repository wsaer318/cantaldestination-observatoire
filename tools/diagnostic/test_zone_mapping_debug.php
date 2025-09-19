<?php
/**
 * Test spécifique du mapping de la zone HAUTES TERRES
 */

echo "🔍 TEST SPÉCIFIQUE DU MAPPING ZONE 'HAUTES TERRES'\n";
echo "=================================================\n\n";

// Inclure les dépendances
require_once __DIR__ . '/../../classes/ZoneMapper.php';
require_once __DIR__ . '/../../database.php';

$pdo = DatabaseConfig::getConnection();

if (!$pdo) {
    echo "❌ Impossible de se connecter à la base de données\n";
    exit(1);
}

echo "✅ Connexion à la base de données réussie\n\n";

// Test du mapping pour HAUTES TERRES
$zone_input = 'HAUTES TERRES';

echo "🧪 Test du mapping pour : '$zone_input'\n";
echo "========================================\n\n";

// Étape 1 : Mapping displayToBase
$zoneMapped = ZoneMapper::displayToBase($zone_input);
echo "📝 ZoneMapper::displayToBase('$zone_input') = '$zoneMapped'\n";

// Étape 2 : Récupération de l'ID
$zoneId = ZoneMapper::getZoneId($zoneMapped, $pdo);
echo "🔍 ZoneMapper::getZoneId('$zoneMapped', \$pdo) = " . ($zoneId ? $zoneId : 'NULL') . "\n";

if ($zoneId) {
    echo "✅ Zone ID trouvé : $zoneId\n\n";

    // Vérifier le nom exact en base
    $stmt = $pdo->prepare("SELECT nom_zone FROM dim_zones_observation WHERE id_zone = ?");
    $stmt->execute([$zoneId]);
    $nom_base = $stmt->fetchColumn();
    echo "📊 Nom exact en base : '$nom_base'\n";

} else {
    echo "❌ Aucun ID trouvé - PROBLÈME !\n\n";

    // Chercher des zones similaires
    echo "🔍 Recherche de zones similaires :\n";
    $stmt = $pdo->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone LIKE ?");
    $stmt->execute(['%' . $zoneMapped . '%']);
    $similar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($similar)) {
        echo "📋 Zones similaires trouvées :\n";
        foreach ($similar as $zone) {
            echo "   • ID: {$zone['id_zone']}, Nom: '{$zone['nom_zone']}'\n";
        }
    } else {
        echo "❌ Aucune zone similaire trouvée\n";
    }
    echo "\n";

    // Lister toutes les zones pour debug
    echo "📋 TOUTES LES ZONES EN BASE :\n";
    echo "============================\n\n";
    $stmt = $pdo->query("SELECT id_zone, nom_zone FROM dim_zones_observation ORDER BY nom_zone");
    $all_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_zones as $zone) {
        echo "   • ID: {$zone['id_zone']}, Nom: '{$zone['nom_zone']}'\n";
    }
}

echo "\n🧪 TEST AVEC D'AUTRES VARIANTES :\n";
echo "=================================\n\n";

$variantes = [
    'HAUTES TERRES',
    'HAUTES-TERRES',
    'HAUTESTERRES',
    'HTC',
    'Hautes Terres',
    'hautes terres'
];

foreach ($variantes as $variante) {
    $mapped = ZoneMapper::displayToBase($variante);
    $id = ZoneMapper::getZoneId($mapped, $pdo);
    
    $status = $id ? "✅ ID: $id" : "❌ Pas d'ID";
    echo "🔄 '$variante' → '$mapped' → $status\n";
}

echo "\n🎯 CONCLUSION :\n";
echo "===============\n\n";

if ($zoneId) {
    echo "✅ Le mapping fonctionne correctement\n";
    echo "✅ L'API devrait fonctionner normalement\n";
} else {
    echo "❌ Problème de mapping détecté\n";
    echo "🔧 Vérifiez les mappings dans ZoneMapper.php\n";
    echo "🔧 Vérifiez les noms de zones dans dim_zones_observation\n";
}

echo "\n🏁 Test terminé !\n";
?>
