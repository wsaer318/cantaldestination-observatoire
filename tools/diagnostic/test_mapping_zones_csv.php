<?php
/**
 * Test du mapping des zones dans les CSV pour s'assurer 
 * que l'import fonctionne correctement
 */

echo "🧪 TEST DU MAPPING DES ZONES DANS LES CSV\n";
echo "=========================================\n\n";

// Inclure les classes nécessaires
require_once __DIR__ . '/../../classes/ZoneMapper.php';
require_once __DIR__ . '/../../config/database.php';

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

// Zones trouvées dans les CSV (avec caractères mal encodés comme dans l'analyse)
$zones_csv = [
    'Cantal',
    'Chataigneraie',  // Version avec caractères spéciaux mal encodés (�)
    'Hautes Terres',
    'Pays Saint Flour', 
    'Pays dAurillac', // Version avec caractères spéciaux mal encodés (�)
    'Haut Cantal',
    'Val Truyere',     // Version avec caractères spéciaux mal encodés (�)
    'Pays Salers',
    'Pays de Mauriac',
    'Carlades',
    'Lioran'
];

echo "🔍 TEST DU MAPPING POUR CHAQUE ZONE CSV\n";
echo "=======================================\n\n";

$mapping_ok = 0;
$mapping_errors = 0;

foreach ($zones_csv as $zone_csv) {
    echo "🏷️ Zone CSV : '$zone_csv'\n";
    
    // Test 1 : Mapping via ZoneMapper
    $mapped_zone = ZoneMapper::displayToBase($zone_csv);
    echo "   📝 ZoneMapper::displayToBase() : '$zone_csv' → '$mapped_zone'\n";
    
    // Test 2 : Récupération de l'ID de zone
    $zone_id = ZoneMapper::getZoneId($mapped_zone, $pdo);
    
    if ($zone_id) {
        echo "   ✅ ID trouvé en base : $zone_id\n";
        
        // Test 3 : Vérifier le nom en base
        $stmt = $pdo->prepare("SELECT nom_zone FROM dim_zones_observation WHERE id_zone = ?");
        $stmt->execute([$zone_id]);
        $db_name = $stmt->fetchColumn();
        
        echo "   📊 Nom en base : '$db_name'\n";
        
        // Test 4 : Mapping inverse pour affichage
        $display_name = ZoneMapper::baseToDisplay($db_name);
        echo "   🔄 Mapping pour affichage : '$db_name' → '$display_name'\n";
        
        $mapping_ok++;
        echo "   ✅ Mapping complet OK\n\n";
        
    } else {
        echo "   ❌ ERREUR : Aucun ID trouvé en base\n";
        $mapping_errors++;
        
        // Debug : chercher des zones similaires
        $stmt = $pdo->prepare("SELECT nom_zone FROM dim_zones_observation WHERE nom_zone LIKE ?");
        $stmt->execute(['%' . $mapped_zone . '%']);
        $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($similar)) {
            echo "   🔍 Zones similaires trouvées : " . implode(', ', $similar) . "\n";
        }
        echo "\n";
    }
}

echo "📊 RÉSUMÉ DU TEST\n";
echo "=================\n\n";
echo "✅ Mappings réussis : $mapping_ok / " . count($zones_csv) . "\n";
echo "❌ Erreurs de mapping : $mapping_errors\n\n";

if ($mapping_errors > 0) {
    echo "🚨 ACTIONS CORRECTIVES NÉCESSAIRES\n";
    echo "==================================\n\n";
    echo "1. Vérifier les mappings dans ZoneMapper.php\n";
    echo "2. Vérifier les noms de zones dans dim_zones_observation\n";
    echo "3. Ajouter les mappings manquants si nécessaire\n\n";
}

// Test bonus : simulation de la fonction normalizeZone du script d'import
echo "🔧 TEST DE LA FONCTION NORMALIZATION (SIMULATION)\n";
echo "=================================================\n\n";

foreach ($zones_csv as $zone_csv) {
    $normalized = normalizeZoneSimulation($zone_csv);
    echo "🔄 '$zone_csv' → '$normalized'\n";
}

echo "\n🏁 Test terminé !\n";

/**
 * Simulation de la fonction normalizeZone du script d'import
 */
function normalizeZoneSimulation($zone_name) {
    $clean_name = trim($zone_name);
    
    // Nettoyer les caractères spéciaux de base
    $clean_name = str_replace(['é', 'è', 'ê', 'ë'], 'e', $clean_name);
    $clean_name = str_replace(['à', 'á', 'â', 'ã', 'ä'], 'a', $clean_name);
    $clean_name = str_replace(['ù', 'ú', 'û', 'ü'], 'u', $clean_name);
    $clean_name = str_replace(['ç'], 'c', $clean_name);
    $clean_name = str_replace([''', ''', '´', '`'], "'", $clean_name);
    
    // Nettoyer les espaces multiples
    $clean_name = preg_replace('/\s+/', ' ', $clean_name);
    
    // Convertir en majuscules
    $normalized = strtoupper($clean_name);
    
    // Appliquer les mappings
    $mappings = [
        'HAUT CANTAL' => 'GENTIANE',
        'HAUTES TERRES' => 'HTC',
        'PAYS D\'AURILLAC' => 'CABA',
        "PAYS D'AURILLAC" => 'CABA',
        'PAYS D AURILLAC' => 'CABA',
        'PAYS DAURILLAC' => 'CABA',
        'LIORAN' => 'STATION',
        'CHATAIGNERAIE' => 'CHÂTAIGNERAIE',
        'VAL TRUYERE' => 'VAL TRUYÈRE'
    ];
    
    if (isset($mappings[$normalized])) {
        return $mappings[$normalized];
    }
    
    return $normalized;
}
?>
