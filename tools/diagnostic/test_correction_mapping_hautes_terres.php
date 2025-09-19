<?php
/**
 * Test de la correction du mapping HAUTES TERRES
 */

echo "=== TEST CORRECTION MAPPING HAUTES TERRES ===\n\n";

// Simuler l'environnement de production
$_SERVER['HTTP_HOST'] = 'observatoire.cantal-destination.com';
$_SERVER['SERVER_NAME'] = 'observatoire.cantal-destination.com';

require_once __DIR__ . '/../../classes/ZoneMapper.php';

echo "🌍 Simulation environnement production activée\n\n";

// Test du mapping corrigé
$zone_test = 'HAUTES TERRES';
$zone_mapped = ZoneMapper::displayToBase($zone_test);

echo "🔄 Test du mapping corrigé:\n";
echo "  '$zone_test' → '$zone_mapped'\n\n";

if ($zone_mapped === 'HAUTES TERRES COMMUNAUTE') {
    echo "✅ CORRECTION RÉUSSIE !\n";
    echo "💡 Maintenant l'API va chercher dans 'HAUTES TERRES COMMUNAUTE'\n";
    echo "📊 Cette zone a des données complètes (2019-2025)\n";
} else {
    echo "❌ CORRECTION ÉCHOUÉE !\n";
    echo "🔧 Le mapping pointe toujours vers: '$zone_mapped'\n";
}

// Test avec la base locale pour vérifier la cohérence
echo "\n🔍 Test avec base locale:\n";
require_once __DIR__ . '/../../config/database.php';

// Forcer l'environnement local pour la connexion DB
unset($_SERVER['HTTP_HOST']);
unset($_SERVER['SERVER_NAME']);

$pdo = DatabaseConfig::getConnection();

// Vérifier si la zone mappée existe
$stmt = $pdo->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone = ?");
$stmt->execute([$zone_mapped]);
$zone_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($zone_info) {
    echo "  ✅ Zone trouvée en base locale: {$zone_info['nom_zone']} (ID: {$zone_info['id_zone']})\n";
    
    // Vérifier les données
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, MIN(date) as min_date, MAX(date) as max_date 
        FROM fact_lieu_activite_soir 
        WHERE id_zone = ?
    ");
    $stmt->execute([$zone_info['id_zone']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "  📊 Données: {$data['count']} enregistrements\n";
    echo "  📅 Période: {$data['min_date']} → {$data['max_date']}\n";
} else {
    echo "  ❌ Zone '$zone_mapped' non trouvée en base locale\n";
    echo "  💡 C'est normal si nos bases locale/production sont différentes\n";
}

echo "\n🚀 Prochaines étapes:\n";
echo "1. Déployer cette correction en production\n";
echo "2. Tester l'API infographie_communes_excursion.php\n";
echo "3. Vérifier que le graphique affiche maintenant la comparaison N-1\n";

echo "\n=== FIN TEST CORRECTION ===\n";
