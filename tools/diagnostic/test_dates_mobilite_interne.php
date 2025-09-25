<?php
/**
 * Test des dates pour la mobilité interne (communes excursion)
 */

echo "=== Test des dates pour mobilité interne ===\n\n";

// Simuler les paramètres de l'API
$annee = 2025;
$periode = 'vacances_ete';
$zone = 'HAUTES TERRES';

echo "📋 Paramètres testés:\n";
echo "  - Année: $annee\n";
echo "  - Période: $periode\n";
echo "  - Zone: $zone\n\n";

// Charger les dépendances
require_once __DIR__ . '/../../api/periodes_manager_db.php';

// Calculer les dates comme dans l'API
$dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);
$prevYear = (int)$annee - 1;
$prevDateRanges = PeriodesManagerDB::calculateDateRanges($prevYear, $periode);

echo "📅 Dates calculées:\n";
echo "  Année courante ($annee):\n";
echo "    - Début: {$dateRanges['start']}\n";
echo "    - Fin: {$dateRanges['end']}\n";
echo "  Année précédente ($prevYear):\n";
echo "    - Début: {$prevDateRanges['start']}\n";
echo "    - Fin: {$prevDateRanges['end']}\n\n";

// Test de connexion à la base de données
require_once __DIR__ . '/../../config/database.php';
$pdo = DatabaseConfig::getConnection();

// Vérifier les données disponibles pour HAUTES TERRES
echo "🔍 Vérification des données en base:\n";

// Données année courante
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, SUM(f.volume) as volume_total, MIN(f.date) as min_date, MAX(f.date) as max_date
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

$stmt->execute([$dateRanges['start'], $dateRanges['end'], $zone]);
$current_data = $stmt->fetch(PDO::FETCH_ASSOC);

echo "  Année courante ($annee):\n";
echo "    - Enregistrements: {$current_data['count']}\n";
echo "    - Volume total: " . number_format($current_data['volume_total'] ?? 0) . "\n";
echo "    - Période réelle: {$current_data['min_date']} → {$current_data['max_date']}\n\n";

// Données année précédente
$stmt->execute([$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
$prev_data = $stmt->fetch(PDO::FETCH_ASSOC);

echo "  Année précédente ($prevYear):\n";
echo "    - Enregistrements: {$prev_data['count']}\n";
echo "    - Volume total: " . number_format($prev_data['volume_total'] ?? 0) . "\n";
echo "    - Période réelle: {$prev_data['min_date']} → {$prev_data['max_date']}\n\n";

// Vérifier toutes les années disponibles pour cette zone
echo "📊 Années disponibles pour HAUTES TERRES:\n";
$stmt = $pdo->prepare("
    SELECT 
        YEAR(f.date) as annee,
        COUNT(*) as count,
        SUM(f.volume) as volume_total,
        MIN(f.date) as min_date,
        MAX(f.date) as max_date
    FROM fact_lieu_activite_soir f
    INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
    INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie
    INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
    WHERE zo.nom_zone = ?
      AND cv.nom_categorie = 'TOURISTE'
      AND p.nom_provenance != 'LOCAL'
      AND f.id_commune > 0
    GROUP BY YEAR(f.date)
    ORDER BY YEAR(f.date) DESC
");

$stmt->execute([$zone]);
$yearly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($yearly_data as $year_data) {
    echo "  - {$year_data['annee']}: " . number_format($year_data['count']) . " enregistrements, " . 
         number_format($year_data['volume_total']) . " volume\n";
    echo "    Période: {$year_data['min_date']} → {$year_data['max_date']}\n";
}

echo "\n🎯 Diagnostic:\n";
if ($current_data['count'] > 0 && $prev_data['count'] == 0) {
    echo "❌ Problème identifié: Données présentes pour $annee mais absentes pour $prevYear\n";
    echo "💡 Cela explique pourquoi le graphique ne montre pas la comparaison N-1\n";
    
    // Vérifier si 2024 a des données pour cette période
    $stmt->execute(['2024-07-06 00:00:00', '2024-09-01 23:59:59', $zone]);
    $data_2024 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data_2024['count'] > 0) {
        echo "✅ Données 2024 disponibles: {$data_2024['count']} enregistrements\n";
        echo "🔧 Le problème pourrait être dans le calcul des dates de vacances d'été 2024\n";
    } else {
        echo "❌ Aucune donnée 2024 pour les vacances d'été\n";
        echo "📝 C'est normal si les données ne remontent qu'à 2025\n";
    }
} elseif ($current_data['count'] > 0 && $prev_data['count'] > 0) {
    echo "✅ Données présentes pour les deux années\n";
    echo "🔧 Le problème pourrait être ailleurs dans l'API\n";
} else {
    echo "❌ Pas de données pour l'année courante\n";
    echo "🔧 Vérifiez les paramètres de la requête\n";
}

echo "\n=== Fin du diagnostic ===\n";


