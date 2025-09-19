<?php
/**
 * Test du mapping des zones pour les APIs en production
 */

echo "=== Test Zone Mapping pour APIs - Production ===\n\n";

require_once __DIR__ . '/../../classes/ZoneMapper.php';

// Test des zones problématiques
$zones_test = [
    'HAUT CANTAL',
    'HAUTES TERRES', 
    'PAYS D\'AURILLAC',
    'LIORAN'
];

echo "🌍 Détection d'environnement:\n";
$reflection = new ReflectionClass('ZoneMapper');
$method = $reflection->getMethod('isProductionEnvironment');
$method->setAccessible(true);
$isProduction = $method->invoke(null);

echo "  - Environnement détecté: " . ($isProduction ? "PRODUCTION 🏭" : "DÉVELOPPEMENT 💻") . "\n\n";

echo "🔄 Test du mapping displayToBase():\n";
foreach ($zones_test as $zone) {
    $mapped = ZoneMapper::displayToBase($zone);
    echo "  '$zone' → '$mapped'\n";
}

echo "\n🔍 Test avec la base de données:\n";
require_once __DIR__ . '/../../config/database.php';
$pdo = DatabaseConfig::getConnection();

foreach ($zones_test as $zone) {
    $mapped = ZoneMapper::displayToBase($zone);
    
    // Vérifier si la zone mappée existe en base
    $stmt = $pdo->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone = ?");
    $stmt->execute([$mapped]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "  ✅ '$zone' → '$mapped' (ID: {$result['id_zone']})\n";
        
        // Vérifier s'il y a des données dans fact_lieu_activite_soir
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as count, MIN(date) as min_date, MAX(date) as max_date 
            FROM fact_lieu_activite_soir 
            WHERE id_zone = ?
        ");
        $stmt2->execute([$result['id_zone']]);
        $data = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($data['count'] > 0) {
            echo "    📊 {$data['count']} enregistrements ({$data['min_date']} → {$data['max_date']})\n";
        } else {
            echo "    ❌ Aucune donnée dans fact_lieu_activite_soir\n";
        }
    } else {
        echo "  ❌ '$zone' → '$mapped' (ZONE NON TROUVÉE EN BASE !)\n";
    }
}

echo "\n🎯 Diagnostic pour HAUTES TERRES spécifiquement:\n";
$zone_test = 'HAUTES TERRES';
$mapped = ZoneMapper::displayToBase($zone_test);

echo "  Zone originale: '$zone_test'\n";
echo "  Zone mappée: '$mapped'\n";

// Test des deux possibilités
$zones_candidates = ['HAUTES TERRES', 'HTC'];

foreach ($zones_candidates as $candidate) {
    $stmt = $pdo->prepare("
        SELECT 
            zo.id_zone,
            zo.nom_zone,
            COUNT(f.id_zone) as total_records
        FROM dim_zones_observation zo
        LEFT JOIN fact_lieu_activite_soir f ON zo.id_zone = f.id_zone
        WHERE zo.nom_zone = ?
        GROUP BY zo.id_zone, zo.nom_zone
    ");
    $stmt->execute([$candidate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "  🔍 Zone '$candidate':\n";
        echo "    - ID: {$result['id_zone']}\n";
        echo "    - Enregistrements: {$result['total_records']}\n";
        
        if ($result['total_records'] > 0) {
            echo "    - ✅ Cette zone a des données !\n";
        } else {
            echo "    - ❌ Cette zone n'a pas de données\n";
        }
    } else {
        echo "  ❌ Zone '$candidate' n'existe pas en base\n";
    }
}

echo "\n💡 Conclusion:\n";
if ($mapped == 'HAUTES TERRES') {
    echo "✅ Le mapping semble correct pour la production\n";
} else {
    echo "❌ Le mapping pointe vers '$mapped' au lieu de 'HAUTES TERRES'\n";
    echo "🔧 Il faut corriger la détection d'environnement\n";
}

echo "\n=== Fin du test ===\n";
