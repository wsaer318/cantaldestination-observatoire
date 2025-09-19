<?php
/**
 * Test direct de l'API mobilité interne avec simulation production
 */

echo "=== Test API Mobilité Interne - Simulation Production ===\n\n";

// Simuler l'environnement de production pour ce test
$_SERVER['HTTP_HOST'] = 'observatoire.cantal-destination.com';
$_SERVER['SERVER_NAME'] = 'observatoire.cantal-destination.com';

echo "🌍 Simulation environnement production activée\n";
echo "  - HTTP_HOST: {$_SERVER['HTTP_HOST']}\n";
echo "  - SERVER_NAME: {$_SERVER['SERVER_NAME']}\n\n";

// Paramètres de test
$annee = 2025;
$periode = 'vacances_ete';
$zone = 'HAUTES TERRES';
$limit = 10;

echo "📋 Paramètres de test:\n";
echo "  - Année: $annee\n";
echo "  - Période: $periode\n"; 
echo "  - Zone: $zone\n";
echo "  - Limit: $limit\n\n";

// Charger les classes nécessaires
require_once __DIR__ . '/../../classes/ZoneMapper.php';
require_once __DIR__ . '/../../api/infographie/periodes_manager_db.php';
require_once __DIR__ . '/../../config/database.php';

// Test du mapping avec simulation production
echo "🔄 Test mapping avec simulation production:\n";
$zoneMapped = ZoneMapper::displayToBase($zone);
echo "  '$zone' → '$zoneMapped'\n\n";

// Calculer les dates
$dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);
$prevYear = (int)$annee - 1;
$prevDateRanges = PeriodesManagerDB::calculateDateRanges($prevYear, $periode);

echo "📅 Dates calculées:\n";
echo "  Année courante ($annee): {$dateRanges['start']} → {$dateRanges['end']}\n";
echo "  Année précédente ($prevYear): {$prevDateRanges['start']} → {$prevDateRanges['end']}\n\n";

// Connexion base de données
$pdo = DatabaseConfig::getConnection();

// Requête simplifiée pour tester les données
echo "🔍 Test des données disponibles:\n";

// Test 1: Vérifier si la zone mappée existe
$stmt = $pdo->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone = ?");
$stmt->execute([$zoneMapped]);
$zoneInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($zoneInfo) {
    echo "  ✅ Zone trouvée: {$zoneInfo['nom_zone']} (ID: {$zoneInfo['id_zone']})\n";
} else {
    echo "  ❌ Zone non trouvée: '$zoneMapped'\n";
    
    // Essayer avec le nom direct
    $stmt->execute([$zone]);
    $zoneInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zoneInfo) {
        echo "  ✅ Zone trouvée avec nom direct: {$zoneInfo['nom_zone']} (ID: {$zoneInfo['id_zone']})\n";
        $zoneMapped = $zone; // Utiliser le nom direct
    } else {
        echo "  ❌ Zone non trouvée même avec nom direct\n";
        echo "\n=== Test abandonné ===\n";
        exit;
    }
}

// Test 2: Données année courante
echo "\n📊 Test données année courante ($annee):\n";
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

echo "  - Enregistrements: {$currentData['total_records']}\n";
echo "  - Communes uniques: {$currentData['unique_communes']}\n";
echo "  - Volume total: " . number_format($currentData['total_volume'] ?? 0) . "\n";
echo "  - Période réelle: {$currentData['min_date']} → {$currentData['max_date']}\n";

// Test 3: Données année précédente
echo "\n📊 Test données année précédente ($prevYear):\n";
$stmt->execute([$prevDateRanges['start'], $prevDateRanges['end'], $zoneMapped]);
$prevData = $stmt->fetch(PDO::FETCH_ASSOC);

echo "  - Enregistrements: {$prevData['total_records']}\n";
echo "  - Communes uniques: {$prevData['unique_communes']}\n";
echo "  - Volume total: " . number_format($prevData['total_volume'] ?? 0) . "\n";
echo "  - Période réelle: {$prevData['min_date']} → {$prevData['max_date']}\n";

// Test 4: Top communes pour année courante
if ($currentData['total_records'] > 0) {
    echo "\n🏆 Top 5 communes année courante:\n";
    $stmt = $pdo->prepare("
        SELECT 
            c.nom_commune,
            SUM(f.volume) as total_visiteurs
        FROM fact_lieu_activite_soir f
        INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
        INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_communes c ON f.id_commune = c.id_commune
        WHERE f.date >= ? AND f.date <= ?
          AND zo.nom_zone = ?
          AND cv.nom_categorie = 'TOURISTE'
          AND p.nom_provenance != 'LOCAL'
          AND f.id_commune > 0
        GROUP BY f.id_commune, c.nom_commune
        ORDER BY total_visiteurs DESC
        LIMIT 5
    ");
    
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $topCommunes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($topCommunes as $i => $commune) {
        $rank = $i + 1;
        echo "  $rank. {$commune['nom_commune']}: " . number_format($commune['total_visiteurs']) . " visiteurs\n";
    }
}

echo "\n🎯 Diagnostic final:\n";
if ($currentData['total_records'] > 0 && $prevData['total_records'] == 0) {
    echo "❌ Problème confirmé: Données 2025 présentes mais pas 2024\n";
    echo "💡 Le graphique ne peut pas afficher la comparaison N-1\n";
    echo "🔧 Solution: Vérifier les données 2024 ou ajuster la logique de comparaison\n";
} elseif ($currentData['total_records'] > 0 && $prevData['total_records'] > 0) {
    echo "✅ Données présentes pour les deux années\n";
    echo "🔧 Le problème pourrait être dans le rendu frontend\n";
} else {
    echo "❌ Pas de données pour l'année courante\n";
    echo "🔧 Vérifier les paramètres de requête\n";
}

echo "\n=== Fin du test ===\n";
