<?php
/**
 * API Régions d'Excursion - Top 10 régions touristiques
 * Retourne le Top 10 des régions d'excursion pour les touristes
 */

// Récupération des paramètres (mêmes que bloc_a_working.php)
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$compareYear = isset($_GET['compare_annee']) ? (int)$_GET['compare_annee'] : null;

// Validation des paramètres obligatoires
if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

try {
    // Connexion à la base de données
    require_once dirname(__DIR__, 2) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();

    // Mapping des zones
    require_once dirname(__DIR__, 2) . '/classes/ZoneMapper.php';
    $zoneMapped = ZoneMapper::displayToBase($zone);

    // Forcer les noms de zones en majuscules
    if (strtolower($zone) === 'cantal') {
        $zoneMapped = 'CANTAL';
    } elseif (strtolower($zone) === 'carlades') {
        $zoneMapped = 'CARLADES';
    }

    // Gestionnaire des périodes
    require_once dirname(__DIR__) . '/periodes_manager_db.php';
    $dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);

    // Priorité aux bornes personnalisées si fournies (debut/fin)
    if (!empty($debut) && !empty($fin)) {
        try {
            $dStart = new DateTime($debut);
            $dEnd = new DateTime($fin);
            // Normaliser aux bornes journalières
            $startStr = $dStart->format('Y-m-d') . ' 00:00:00';
            $endStr = $dEnd->format('Y-m-d') . ' 23:59:59';
            if ($dEnd >= $dStart) {
                $dateRanges['start'] = $startStr;
                $dateRanges['end'] = $endStr;
                if ($debug) {
                    error_log("API Debug - Override par debut/fin: start={$dateRanges['start']}, end={$dateRanges['end']}");
                }
            }
        } catch (Exception $e) {
            // Si dates invalides, ignorer et garder le calcul basé sur la période
            if ($debug) {
                error_log('API Debug - Dates debut/fin invalides, fallback période: ' . $e->getMessage());
            }
        }
    }

    // ID de la zone
    $stmt = $pdo->prepare("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?");
    $stmt->execute([$zoneMapped]);
    $zoneResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_zone = $zoneResult['id_zone'] ?? 2; // Default Cantal

    // ID de la catégorie TOURISTE
    $stmt = $pdo->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = ?");
    $stmt->execute(['TOURISTE']);
    $categorieResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_categorie = $categorieResult['id_categorie'] ?? 3; // Default TOURISTE

    // Exécution de la requête - Top 10 régions
    $sql = "
        SELECT nom_region, total_visiteurs
        FROM (
            SELECT
                d.nom_nouvelle_region as nom_region,
                SUM(f.volume) as total_visiteurs,
                ROW_NUMBER() OVER (ORDER BY SUM(f.volume) DESC) as ranking
            FROM fact_lieu_activite_soir f
            INNER JOIN dim_communes c ON f.id_commune = c.id_commune
            INNER JOIN dim_departements d ON c.id_departement = d.id_departement
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date >= ?
              AND f.date <= ?
              AND f.id_zone = ?
              AND f.id_commune > 0
              AND f.id_categorie = ?
              AND p.nom_provenance != 'LOCAL'
              AND d.nom_nouvelle_region IS NOT NULL
              AND d.nom_nouvelle_region NOT IN ('CUMUL', 'Cumul', '')
            GROUP BY d.nom_nouvelle_region
        ) ranked
        WHERE ranking <= 10
        ORDER BY total_visiteurs DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $dateRanges['start'],
        $dateRanges['end'],
        $id_zone,
        $id_categorie
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatage de la réponse
    $response = [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut_periode' => $dateRanges['start'],
        'fin_periode' => $dateRanges['end'],
        'regions' => $results,
        'total_regions' => count($results),
        'status' => 'success'
    ];

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API régions excursion',
        'message' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?>

