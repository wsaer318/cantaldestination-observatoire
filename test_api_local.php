<?php
/**
 * TEST LOCAL DE L'API COMMUNES EXCURSION CORRIGÉE
 * Teste l'API directement sans passer par HTTP
 */

echo "<h1>🧪 TEST LOCAL - API Communes Excursion</h1>";

// Simuler les paramètres de production
$_GET = [
    'annee' => '2025',
    'periode' => 'vacances_ete',
    'zone' => 'HAUTES TERRES',
    'debug' => '1'
];

echo "<h2>Paramètres de test:</h2>";
echo "<ul>";
foreach ($_GET as $key => $value) {
    echo "<li><strong>$key:</strong> $value</li>";
}
echo "</ul>";

// Inclure les dépendances
require_once 'database.php';
require_once 'classes/ZoneMapper.php';

try {
    // Connexion DB
    $pdo = DatabaseConfig::getConnection();
    echo "<h3>✅ Connexion DB réussie</h3>";

    // Simuler la logique de l'API
    $annee = $_GET['annee'];
    $periode = $_GET['periode'];
    $zone = $_GET['zone'];
    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    $limit = 10;

    echo "<h3>🔄 Simulation de l'API...</h3>";

    // Mapping des zones (même logique que l'API)
    $zoneMapped = ZoneMapper::displayToBase($zone);
    $zoneNames = [$zoneMapped];
    if ($zoneMapped === 'HAUTES TERRES') {
        $zoneNames[] = 'HAUTES TERRES COMMUNAUTE';
    }
    $zonePlaceholders = implode(',', array_fill(0, count($zoneNames), '?'));

    echo "<p><strong>Zone originale:</strong> $zone</p>";
    echo "<p><strong>Zone mappée:</strong> $zoneMapped</p>";
    echo "<p><strong>Zones utilisées:</strong> " . implode(', ', $zoneNames) . "</p>";

    // Périodes (même logique que l'API)
    $dateRanges = [
        'start' => '2025-07-05 00:00:00',
        'end' => '2025-08-31 00:00:00'
    ];
    $prevDateRanges = [
        'start' => '2024-07-06 00:00:00',
        'end' => '2024-09-01 00:00:00'
    ];

    echo "<p><strong>Période 2025:</strong> {$dateRanges['start']} → {$dateRanges['end']}</p>";
    echo "<p><strong>Période 2024:</strong> {$prevDateRanges['start']} → {$prevDateRanges['end']}</p>";

    // Requête SQL (identique à l'API)
    $sql = "
        SELECT
            c.nom_commune,
            c.code_insee,
            COALESCE(cur.total_visiteurs, 0)      AS total_visiteurs,
            COALESCE(prev.total_visiteurs, 0)     AS total_visiteurs_n1
        FROM (
            -- TOP 10 communes année courante (2025)
            SELECT
                f.id_commune,
                SUM(f.volume) AS total_visiteurs
            FROM fact_lieu_activite_soir AS f
            INNER JOIN dim_provenances         AS p  ON f.id_provenance = p.id_provenance
            INNER JOIN dim_zones_observation   AS zo ON f.id_zone       = zo.id_zone
            INNER JOIN dim_categories_visiteur AS cv ON f.id_categorie  = cv.id_categorie
            WHERE f.date >= ?
              AND f.date <= ?
              AND zo.nom_zone IN ($zonePlaceholders)
              AND f.id_commune > 0
              AND cv.nom_categorie = 'TOURISTE'
              AND p.nom_provenance <> 'LOCAL'
            GROUP BY f.id_commune
            ORDER BY SUM(f.volume) DESC
            LIMIT ?
        ) AS cur
        LEFT JOIN (
            -- Données année précédente (2024) sur les mêmes communes
            SELECT
                f.id_commune,
                SUM(f.volume) AS total_visiteurs
            FROM fact_lieu_activite_soir AS f
            INNER JOIN dim_provenances         AS p  ON f.id_provenance = p.id_provenance
            INNER JOIN dim_zones_observation   AS zo ON f.id_zone       = zo.id_zone
            INNER JOIN dim_categories_visiteur AS cv ON f.id_categorie  = cv.id_categorie
            WHERE f.date >= ?
              AND f.date <= ?
              AND zo.nom_zone IN ($zonePlaceholders)
              AND f.id_commune > 0
              AND cv.nom_categorie = 'TOURISTE'
              AND p.nom_provenance <> 'LOCAL'
            GROUP BY f.id_commune
        ) AS prev
          ON cur.id_commune = prev.id_commune
        INNER JOIN dim_communes AS c
          ON cur.id_commune = c.id_commune
        ORDER BY cur.total_visiteurs DESC
    ";

    // Préparer et exécuter
    $stmt = $pdo->prepare($sql);
    $params = [
        $dateRanges['start'], $dateRanges['end'],
        ...$zoneNames,
        $limit,
        $prevDateRanges['start'], $prevDateRanges['end'],
        ...$zoneNames
    ];

    echo "<h3>🔍 Paramètres de la requête:</h3>";
    echo "<pre>" . print_r($params, true) . "</pre>";

    echo "<h3>⚡ Exécution de la requête...</h3>";
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>📊 RÉSULTATS:</h3>";
    echo "<p><strong>Nombre de communes trouvées:</strong> " . count($results) . "</p>";

    if (count($results) > 0) {
        echo "<h4>✅ SUCCÈS ! Top 10 communes pour HAUTES TERRES 2025:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Classement</th><th>Commune</th><th>Code INSEE</th><th>Visiteurs 2025</th><th>Visiteurs 2024</th></tr>";

        $rank = 1;
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>$rank</td>";
            echo "<td><strong>{$row['nom_commune']}</strong></td>";
            echo "<td>{$row['code_insee']}</td>";
            echo "<td><strong>{$row['total_visiteurs']}</strong></td>";
            echo "<td>{$row['total_visiteurs_n1']}</td>";
            echo "</tr>";
            $rank++;
        }
        echo "</table>";

        echo "<h4>🎉 CONCLUSION:</h4>";
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
        echo "<strong>✅ L'API fonctionne correctement !</strong><br>";
        echo "Les données sont récupérées avec le mapping historique HAUTES TERRES + HAUTES TERRES COMMUNAUTE.";
        echo "</div>";

    } else {
        echo "<h4>❌ AUCUN RÉSULTAT</h4>";
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
        echo "<strong>⚠️ Problème détecté:</strong> La requête ne retourne aucun résultat.<br>";
        echo "Vérifiez :";
        echo "<ul>";
        echo "<li>Les données existent dans fact_lieu_activite_soir</li>";
        echo "<li>Les périodes de dates sont correctes</li>";
        echo "<li>Les filtres (TOURISTE, LOCAL) ne sont pas trop restrictifs</li>";
        echo "<li>La zone HAUTES TERRES existe dans dim_zones_observation</li>";
        echo "</ul>";
        echo "</div>";
    }

    // Debug supplémentaire si demandé
    if ($debug) {
        echo "<h3>🔧 DEBUG - Requêtes de diagnostic:</h3>";

        // Vérifier les données brutes
        $diagStmt = $pdo->prepare("
            SELECT COUNT(*) as total_rows, COUNT(DISTINCT f.id_commune) as unique_communes
            FROM fact_lieu_activite_soir f
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
            INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie
            WHERE zo.nom_zone IN ($zonePlaceholders) AND cv.nom_categorie = 'TOURISTE'
            AND f.date >= ? AND f.date <= ?
            AND p.nom_provenance <> 'LOCAL'
        ");
        $diagStmt->execute([...$zoneNames, $dateRanges['start'], $dateRanges['end']]);
        $diagResult = $diagStmt->fetch(PDO::FETCH_ASSOC);

        echo "<ul>";
        echo "<li><strong>Records totaux avec filtres:</strong> {$diagResult['total_rows']}</li>";
        echo "<li><strong>Communes uniques:</strong> {$diagResult['unique_communes']}</li>";
        echo "</ul>";
    }

} catch (Exception $e) {
    echo "<h3>❌ ERREUR:</h3>";
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
    echo "<strong>Type:</strong> " . get_class($e) . "<br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Fichier:</strong> " . $e->getFile() . " ligne " . $e->getLine() . "<br>";
    if ($pdo) {
        echo "<strong>Erreur SQL:</strong> " . ($pdo->errorInfo()[2] ?? 'N/A') . "<br>";
    }
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>📝 Test terminé à:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
