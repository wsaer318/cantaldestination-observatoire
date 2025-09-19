<?php
/**
 * Debug spécifique pour le problème de mobilité interne en production
 */

echo "=== Debug mobilité interne - Production ===\n\n";

// Simuler l'appel exact qui pose problème
$annee = 2025;
$periode = 'vacances_ete';  
$zone = 'HAUTES TERRES';

echo "🎯 Simulation de l'appel API problématique:\n";
echo "URL: /api/infographie/infographie_communes_excursion.php?annee=$annee&periode=$periode&zone=" . urlencode($zone) . "&limit=10\n\n";

// Charger les dépendances comme l'API
require_once __DIR__ . '/../../api/infographie/periodes_manager_db.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

// Mapper la zone comme l'API le fait
$zoneMapped = ZoneMapper::displayToBase($zone);
echo "🔄 Zone mappée: '$zone' → '$zoneMapped'\n";

// Calculer les dates comme l'API
$dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);
$prevYear = (int)$annee - 1;
$prevDateRanges = PeriodesManagerDB::calculateDateRanges($prevYear, $periode);

echo "\n📅 Dates calculées:\n";
echo "  Année courante ($annee): {$dateRanges['start']} → {$dateRanges['end']}\n";
echo "  Année précédente ($prevYear): {$prevDateRanges['start']} → {$prevDateRanges['end']}\n\n";

// Requête exacte de l'API (version simplifiée pour diagnostic)
echo "🔍 Requête SQL de diagnostic (équivalente à l'API):\n";

$sql_diagnostic = "
-- DIAGNOSTIC : Vérifier les données disponibles pour HAUTES TERRES
SELECT 
    'Année courante' as periode,
    COUNT(*) as total_enregistrements,
    COUNT(DISTINCT f.id_commune) as communes_uniques,
    SUM(f.volume) as volume_total,
    MIN(f.date) as date_min,
    MAX(f.date) as date_max
FROM fact_lieu_activite_soir f
INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie  
INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
WHERE f.date >= '{$dateRanges['start']}'
  AND f.date <= '{$dateRanges['end']}'
  AND zo.nom_zone = '$zoneMapped'
  AND cv.nom_categorie = 'TOURISTE'
  AND p.nom_provenance != 'LOCAL'
  AND f.id_commune > 0

UNION ALL

SELECT 
    'Année précédente' as periode,
    COUNT(*) as total_enregistrements,
    COUNT(DISTINCT f.id_commune) as communes_uniques,
    SUM(f.volume) as volume_total,
    MIN(f.date) as date_min,
    MAX(f.date) as date_max
FROM fact_lieu_activite_soir f
INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie
INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
WHERE f.date >= '{$prevDateRanges['start']}'
  AND f.date <= '{$prevDateRanges['end']}'
  AND zo.nom_zone = '$zoneMapped'
  AND cv.nom_categorie = 'TOURISTE'
  AND p.nom_provenance != 'LOCAL'
  AND f.id_commune > 0;
";

echo "$sql_diagnostic\n";

echo "🚀 Instructions pour tester en production:\n";
echo "1. Exécutez cette requête dans phpMyAdmin sur la base 'observatoire'\n";
echo "2. Vérifiez si les deux années retournent des données\n";
echo "3. Si année précédente = 0 enregistrements → c'est le problème !\n\n";

echo "🔧 Solutions possibles:\n";
echo "1. Vérifier si les données 2024 existent pour la période vacances_ete\n";
echo "2. Vérifier si le calcul des dates 2024 est correct\n";
echo "3. Vérifier si les filtres (TOURISTE, non LOCAL) sont trop restrictifs\n\n";

echo "📋 Requête simplifiée pour vérifier toutes les années:\n";
echo "SELECT YEAR(f.date) as annee, COUNT(*) as total\n";
echo "FROM fact_lieu_activite_soir f\n";
echo "INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone\n";
echo "WHERE zo.nom_zone = '$zoneMapped'\n";
echo "GROUP BY YEAR(f.date) ORDER BY annee DESC;\n\n";

echo "💡 Si cette requête montre des données pour 2024 mais pas la requête principale,\n";
echo "   le problème est dans les filtres (TOURISTE, non LOCAL, id_commune > 0)\n";
echo "\n=== Fin du diagnostic ===\n";
