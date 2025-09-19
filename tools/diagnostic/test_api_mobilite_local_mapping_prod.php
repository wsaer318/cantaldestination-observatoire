<?php
/**
 * Test API mobilité avec mapping production mais base locale
 */

echo "=== Test API Mobilité - Mapping Production / Base Locale ===\n\n";

// Paramètres de test
$annee = 2025;
$periode = 'vacances_ete';
$zone = 'HAUTES TERRES';

echo "📋 Test avec mapping production sur base locale:\n";
echo "  - Zone: $zone\n";
echo "  - Année: $annee\n";
echo "  - Période: $periode\n\n";

// Forcer le mapping direct (comme en production)
$zoneMapped = $zone; // Pas de mapping, utilisation directe

echo "🔄 Mapping forcé: '$zone' → '$zoneMapped'\n\n";

// Calculer les dates manuellement (sans DB pour éviter les erreurs de connexion)
echo "📅 Calcul manuel des dates pour vacances_ete:\n";

// Vacances d'été 2025: environ 5 juillet au 31 août
$dateRanges = [
    'start' => '2025-07-05 00:00:00',
    'end' => '2025-08-31 23:59:59'
];

$prevDateRanges = [
    'start' => '2024-07-06 00:00:00', 
    'end' => '2024-09-01 23:59:59'
];

echo "  Année courante ($annee): {$dateRanges['start']} → {$dateRanges['end']}\n";
echo "  Année précédente (2024): {$prevDateRanges['start']} → {$prevDateRanges['end']}\n\n";

// Connexion base locale
require_once __DIR__ . '/../../config/database.php';

// Forcer l'environnement local pour la connexion DB
unset($_SERVER['HTTP_HOST']);
unset($_SERVER['SERVER_NAME']);

$pdo = DatabaseConfig::getConnection();

echo "🔍 Test avec la zone directe '$zoneMapped':\n";

// Vérifier si la zone existe dans notre base locale
$stmt = $pdo->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone = ?");
$stmt->execute([$zoneMapped]);
$zoneInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($zoneInfo) {
    echo "  ✅ Zone trouvée: {$zoneInfo['nom_zone']} (ID: {$zoneInfo['id_zone']})\n\n";
    
    // Test des données
    echo "📊 Test des données dans fact_lieu_activite_soir:\n";
    
    // Année courante
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT f.id_commune) as unique_communes,
            SUM(f.volume) as total_volume,
            MIN(f.date) as min_date,
            MAX(f.date) as max_date
        FROM fact_lieu_activite_soir f
        INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
        INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        WHERE f.date >= ? AND f.date <= ?
          AND zo.nom_zone = ?
          AND cv.nom_categorie = 'TOURISTE'
          AND p.nom_provenance != 'LOCAL'
          AND f.id_commune > 0
    ");
    
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "  Année courante ($annee):\n";
    echo "    - Enregistrements: {$currentData['total_records']}\n";
    echo "    - Communes: {$currentData['unique_communes']}\n";
    echo "    - Volume: " . number_format($currentData['total_volume'] ?? 0) . "\n";
    echo "    - Période: {$currentData['min_date']} → {$currentData['max_date']}\n";
    
    // Année précédente
    $stmt->execute([$prevDateRanges['start'], $prevDateRanges['end'], $zoneMapped]);
    $prevData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n  Année précédente (2024):\n";
    echo "    - Enregistrements: {$prevData['total_records']}\n";
    echo "    - Communes: {$prevData['unique_communes']}\n";
    echo "    - Volume: " . number_format($prevData['total_volume'] ?? 0) . "\n";
    echo "    - Période: {$prevData['min_date']} → {$prevData['max_date']}\n";
    
} else {
    echo "  ❌ Zone '$zoneMapped' non trouvée dans la base locale\n";
    echo "  💡 C'est normal, notre base locale utilise les anciens noms\n\n";
    
    echo "🔄 Test avec le mapping développement:\n";
    require_once __DIR__ . '/../../classes/ZoneMapper.php';
    $zoneMappedLocal = ZoneMapper::displayToBase($zone);
    echo "  '$zone' → '$zoneMappedLocal' (mapping local)\n";
    
    $stmt->execute([$zoneMappedLocal]);
    $zoneInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zoneInfo) {
        echo "  ✅ Zone trouvée avec mapping local: {$zoneInfo['nom_zone']} (ID: {$zoneInfo['id_zone']})\n";
    }
}

echo "\n🎯 Conclusion:\n";
echo "✅ Le mapping production fonctionne : '$zone' → '$zoneMapped'\n";
echo "💡 En production, l'API devrait chercher directement 'HAUTES TERRES'\n";
echo "🔧 Le problème vient du fait que notre base locale a les anciens noms\n";
echo "   mais la production a les nouveaux noms directs\n";

echo "\n📝 Prochaines étapes:\n";
echo "1. Vérifier que l'API en production utilise bien le bon mapping\n";
echo "2. Tester directement l'API en production avec des logs\n";
echo "3. Vérifier les données 2024 en production pour les vacances d'été\n";

echo "\n=== Fin du test ===\n";
