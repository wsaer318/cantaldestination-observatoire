<?php
/**
 * Test simple du mapping des zones CSV
 */

echo "🧪 TEST SIMPLE DU MAPPING DES ZONES\n";
echo "===================================\n\n";

// Inclure les classes nécessaires
require_once __DIR__ . '/../../classes/ZoneMapper.php';
require_once __DIR__ . '/../../database.php';

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connexion à la base de données réussie\n\n";
} catch (Exception $e) {
    echo "❌ Erreur de connexion à la base : " . $e->getMessage() . "\n";
    exit(1);
}

// Zones réelles trouvées dans les CSV (selon l'analyse précédente)
$zones_reelles = [
    'Cantal',
    'Chataigneraie',
    'Hautes Terres', 
    'Pays Saint Flour',
    'Pays dAurillac',
    'Haut Cantal',
    'Val Truyere',
    'Pays Salers',
    'Pays de Mauriac',
    'Carlades',
    'Lioran'
];

echo "🔍 TEST DU MAPPING\n";
echo "==================\n\n";

$total_ok = 0;
$total_errors = 0;

foreach ($zones_reelles as $zone) {
    echo "📍 Zone : '$zone'\n";
    
    // Test du mapping
    $mapped = ZoneMapper::displayToBase($zone);
    echo "   🔄 Mapping : '$zone' → '$mapped'\n";
    
    // Test de l'ID
    $zone_id = ZoneMapper::getZoneId($mapped, $pdo);
    
    if ($zone_id) {
        echo "   ✅ ID en base : $zone_id\n";
        $total_ok++;
    } else {
        echo "   ❌ Aucun ID trouvé\n";
        $total_errors++;
    }
    echo "\n";
}

echo "📊 RÉSUMÉ\n";
echo "=========\n\n";
echo "✅ Mappings OK : $total_ok\n";
echo "❌ Erreurs : $total_errors\n\n";

// Test avec les vraies zones des CSV (avec caractères spéciaux mal encodés)
echo "🔍 TEST AVEC CARACTÈRES MAL ENCODÉS\n";
echo "===================================\n\n";

$zones_mal_encodees = [
    'Chataigneraie',
    'Pays dAurillac', 
    'Val Truyere'
];

foreach ($zones_mal_encodees as $zone) {
    echo "📍 Zone mal encodée : '$zone'\n";
    
    // Simulation de nettoyage
    $zone_clean = str_replace(['d'], "d'", $zone);
    $zone_clean = str_replace(['ataigneraie'], 'âtaigneraie', $zone_clean);
    $zone_clean = str_replace(['Truyere'], 'Truyère', $zone_clean);
    
    echo "   🧹 Après nettoyage : '$zone_clean'\n";
    
    $mapped = ZoneMapper::displayToBase($zone_clean);
    echo "   🔄 Mapping : '$zone_clean' → '$mapped'\n";
    
    $zone_id = ZoneMapper::getZoneId($mapped, $pdo);
    if ($zone_id) {
        echo "   ✅ ID trouvé : $zone_id\n";
    } else {
        echo "   ❌ Pas d'ID\n";
    }
    echo "\n";
}

echo "🏁 Test terminé !\n";
?>
