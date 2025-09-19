<?php
/**
 * Vérifier pourquoi la comparaison des communes ne fonctionne pas entre 2025 et 2024
 */

echo "🔍 VÉRIFICATION DE LA CORRESPONDANCE DES COMMUNES\n";
echo "================================================\n\n";

require_once 'database.php';

$db = getCantalDestinationDatabase();
$pdo = $db->getConnection();

// Dates pour 2025 et 2024
$dates2025 = ['2025-07-05 00:00:00', '2025-08-31 00:00:00'];
$dates2024 = ['2024-07-06 00:00:00', '2024-09-01 00:00:00'];

echo "📅 PÉRIODES :\n";
echo "  2025: {$dates2025[0]} → {$dates2025[1]}\n";
echo "  2024: {$dates2024[0]} → {$dates2024[1]}\n\n";

// Récupérer les top communes pour 2025
$stmt2025 = $pdo->prepare("
    SELECT
        c.id_commune,
        c.nom_commune,
        c.code_insee,
        SUM(f.volume) as total_visiteurs
    FROM fact_lieu_activite_soir f
    INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
    INNER JOIN dim_communes c ON f.id_commune = c.id_commune
    WHERE f.date >= ? AND f.date <= ?
      AND f.id_zone = 2
      AND f.id_commune > 0
      AND f.id_categorie = 3
      AND p.nom_provenance != 'LOCAL'
    GROUP BY f.id_commune, c.nom_commune, c.code_insee
    ORDER BY SUM(f.volume) DESC
    LIMIT 10
");
$stmt2025->execute($dates2025);
$communes2025 = $stmt2025->fetchAll(PDO::FETCH_ASSOC);

echo "🏆 TOP 10 COMMUNES 2025 :\n";
foreach ($communes2025 as $i => $commune) {
    echo "  " . ($i + 1) . ". {$commune['nom_commune']} (ID: {$commune['id_commune']}) : {$commune['total_visiteurs']} visiteurs\n";
}
echo "\n";

// Pour chaque commune de 2025, vérifier si elle existe en 2024
echo "🔍 CORRESPONDANCE AVEC 2024 :\n";
echo "===============================\n";

foreach ($communes2025 as $commune2025) {
    $stmt2024 = $pdo->prepare("
        SELECT SUM(f.volume) as total_visiteurs_2024
        FROM fact_lieu_activite_soir f
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        WHERE f.date >= ? AND f.date <= ?
          AND f.id_zone = 2
          AND f.id_commune = ?
          AND f.id_categorie = 3
          AND p.nom_provenance != 'LOCAL'
    ");
    $stmt2024->execute([$dates2024[0], $dates2024[1], $commune2025['id_commune']]);
    $result2024 = $stmt2024->fetch(PDO::FETCH_ASSOC);

    $visiteurs2024 = $result2024['total_visiteurs_2024'] ?? 0;

    $status = $visiteurs2024 > 0 ? "✅" : "❌";
    echo "  {$status} {$commune2025['nom_commune']} (ID: {$commune2025['id_commune']}): ";
    echo "{$commune2025['total_visiteurs']} (2025) → {$visiteurs2024} (2024)\n";
}

echo "\n";

// Vérifier l'inverse : communes de 2024 qui ne sont pas dans le top 10 de 2025
echo "🔄 COMMUNES 2024 NON PRÉSENTES DANS TOP 10 2025 :\n";
echo "====================================================\n";

$stmt2024 = $pdo->prepare("
    SELECT
        c.id_commune,
        c.nom_commune,
        SUM(f.volume) as total_visiteurs
    FROM fact_lieu_activite_soir f
    INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
    INNER JOIN dim_communes c ON f.id_commune = c.id_commune
    WHERE f.date >= ? AND f.date <= ?
      AND f.id_zone = 2
      AND f.id_commune > 0
      AND f.id_categorie = 3
      AND p.nom_provenance != 'LOCAL'
    GROUP BY f.id_commune, c.nom_commune
    ORDER BY SUM(f.volume) DESC
    LIMIT 15
");
$stmt2024->execute($dates2024);
$communes2024 = $stmt2024->fetchAll(PDO::FETCH_ASSOC);

// IDs des communes du top 10 2025
$ids2025 = array_column($communes2025, 'id_commune');

$notInTop10 = [];
foreach ($communes2024 as $commune2024) {
    if (!in_array($commune2024['id_commune'], $ids2025)) {
        $notInTop10[] = $commune2024;
    }
}

if (count($notInTop10) > 0) {
    echo "Communes de 2024 qui ne sont pas dans le top 10 de 2025 :\n";
    foreach (array_slice($notInTop10, 0, 5) as $commune) {
        echo "  📍 {$commune['nom_commune']} (ID: {$commune['id_commune']}): {$commune['total_visiteurs']} visiteurs\n";
    }
} else {
    echo "Toutes les communes du top 10 2024 sont aussi dans le top 10 2025\n";
}

echo "\n";

// Test de la requête exacte de l'API
echo "🧪 TEST DE LA REQUÊTE API EXACTE :\n";
echo "==================================\n";

$sqlApi = "
    SELECT
        c.nom_commune,
        c.code_insee,
        COALESCE(current_year.total_visiteurs, 0) as total_visiteurs,
        COALESCE(previous_year.total_visiteurs, 0) as total_visiteurs_n1
    FROM (
        SELECT
            f.id_commune,
            SUM(f.volume) as total_visiteurs
        FROM fact_lieu_activite_soir f
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        WHERE f.date >= ?
          AND f.date <= ?
          AND f.id_zone = ?
          AND f.id_commune > 0
          AND f.id_categorie = ?
          AND p.nom_provenance != 'LOCAL'
        GROUP BY f.id_commune
        ORDER BY SUM(f.volume) DESC
        LIMIT ?
    ) current_year
    LEFT JOIN (
        SELECT
            f.id_commune,
            SUM(f.volume) as total_visiteurs
        FROM fact_lieu_activite_soir f
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        WHERE f.date >= ?
          AND f.date <= ?
          AND f.id_zone = ?
          AND f.id_commune > 0
          AND f.id_categorie = ?
          AND p.nom_provenance != 'LOCAL'
        GROUP BY f.id_commune
    ) previous_year ON current_year.id_commune = previous_year.id_commune
    INNER JOIN dim_communes c ON current_year.id_commune = c.id_commune
    ORDER BY current_year.total_visiteurs DESC
";

$stmt = $pdo->prepare($sqlApi);
$stmt->execute([
    $dates2025[0], $dates2025[1], 2, 3, 10,  // 2025
    $dates2024[0], $dates2024[1], 2, 3       // 2024
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Résultats de la requête API exacte :\n";
foreach ($results as $i => $result) {
    $evolution = $result['total_visiteurs_n1'] > 0 ?
        round((($result['total_visiteurs'] - $result['total_visiteurs_n1']) / $result['total_visiteurs_n1']) * 100, 1) :
        null;

    echo "  " . ($i + 1) . ". {$result['nom_commune']}: ";
    echo "{$result['total_visiteurs']} (2025) → {$result['total_visiteurs_n1']} (2024)";
    if ($evolution !== null) {
        echo " ({$evolution}%)";
    } else {
        echo " (pas de données 2024)";
    }
    echo "\n";
}

echo "\n🎯 CONCLUSION :\n";
$withData = count(array_filter($results, function($r) { return $r['total_visiteurs_n1'] > 0; }));
$withoutData = count(array_filter($results, function($r) { return $r['total_visiteurs_n1'] == 0; }));

echo "✅ Communes avec données de comparaison: {$withData}\n";
echo "❌ Communes sans données de comparaison: {$withoutData}\n\n";

if ($withoutData > 0) {
    echo "🔍 PROBLÈME IDENTIFIÉ :\n";
    echo "Les communes de 2025 n'ont pas de correspondance exacte dans les données de 2024\n";
    echo "Cela peut être dû à :\n";
    echo "• Différences dans les périodes (dates légèrement différentes)\n";
    echo "• Communes qui n'existaient pas ou n'avaient pas d'activité en 2024\n";
    echo "• Changements dans la classification des communes\n";
} else {
    echo "✅ Toutes les communes ont des données de comparaison\n";
    echo "Le problème doit être ailleurs\n";
}
?>
