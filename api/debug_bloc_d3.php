<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Récupération des paramètres
$annee = (int)($_GET['annee'] ?? 2024);
$periode = $_GET['periode'] ?? 'vacances_hiver';
$zone = $_GET['zone'] ?? 'CANTAL';
$limit = (int)($_GET['limit'] ?? 5);

require_once __DIR__ . '/periodes_manager_db.php';
require_once dirname(__DIR__) . '/database.php';

echo "<pre>";

try {
    // Connexion à la base de données
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // 1. Calcul des plages de dates
    $dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);
    $dateRangesN1 = PeriodesManagerDB::calculateDateRanges($annee - 1, $periode);

    echo "DEBUG: Plage de dates pour $annee ($periode):\n";
    print_r($dateRanges);
    echo "\nDEBUG: Plage de dates pour " . ($annee - 1) . " ($periode):\n";
    print_r($dateRangesN1);
    echo "\n---\n";

    // 2. Vérification des IDs
    $id_zone_stmt = $pdo->prepare("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?");
    $id_zone_stmt->execute([$zone]);
    $id_zone = $id_zone_stmt->fetchColumn();
    echo "DEBUG: ID pour la zone '$zone': $id_zone\n";

    $id_cat_stmt = $pdo->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = 'EXCURSIONNISTE'");
    $id_cat_stmt->execute();
    $id_cat = $id_cat_stmt->fetchColumn();
    echo "DEBUG: ID pour la catégorie 'EXCURSIONNISTE': $id_cat\n";

    $id_prov_stmt = $pdo->prepare("SELECT id_provenance FROM dim_provenances WHERE nom_provenance = 'NONLOCAL'");
    $id_prov_stmt->execute();
    $id_prov = $id_prov_stmt->fetchColumn();
    echo "DEBUG: ID pour la provenance 'NONLOCAL': $id_prov\n";
    echo "---\n";

    if (!$id_zone || !$id_cat || !$id_prov) {
        echo "ERREUR: Un ou plusieurs IDs de dimension n'ont pas été trouvés. Vérifiez les tables '_'.\n";
        exit;
    }

    // 3. Requête principale pour l'année courante
    $sqlCurrent = "
    SELECT
        p.nom_pays AS pays,
        SUM(f.volume) AS n_presences
    FROM fact_diurnes_pays AS f
    JOIN dim_pays AS p ON f.id_pays = p.id_pays
    WHERE
        f.id_zone = ?
        AND f.id_categorie = ?
        AND f.id_provenance = ?
        AND f.date BETWEEN ? AND ?
        AND p.nom_pays <> 'CUMUL'
        AND p.nom_pays IS NOT NULL
        AND p.nom_pays <> ''
    GROUP BY
        p.nom_pays
    ORDER BY
        n_presences DESC
    LIMIT ?";

    echo "DEBUG: Requête SQL pour l'année en cours:\n$sqlCurrent\n";
    $paramsCurrent = [$id_zone, $id_cat, $id_prov, $dateRanges['start'], $dateRanges['end'], $limit];
    echo "DEBUG: Paramètres:\n";
    print_r($paramsCurrent);
    echo "\n";

    $stmt = $pdo->prepare($sqlCurrent);
    $stmt->execute($paramsCurrent);
    $currentData = $stmt->fetchAll();
    
    echo "DEBUG: Nombre de résultats pour $annee: " . count($currentData) . "\n";
    echo "DEBUG: Résultats pour $annee:\n";
    print_r($currentData);
    echo "\n---\n";

} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage();
}

echo "</pre>";
?>