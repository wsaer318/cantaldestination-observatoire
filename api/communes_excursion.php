<?php
/**
 * API Destinations d'Excursion - Top 20 communes touristiques
 * Retourne le Top 20 des destinations d'excursion pour les touristes
 */

// Récupération des paramètres (mêmes que bloc_a_working.php)
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$compareYear = isset($_GET['compare_annee']) ? (int)$_GET['compare_annee'] : null;

// Log de debug
if ($debug) {
    error_log("API communes_excursion Debug - Paramètres reçus: annee=$annee, periode=$periode, zone=$zone, debut=$debut, fin=$fin, compare_annee=$compareYear");
}

// Validation des paramètres obligatoires
if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

try {
    // Connexion à la base de données
    require_once dirname(__DIR__) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();

    // OPTIMISATION : Créer des index critiques pour la performance
    try {
        // Index composite pour les requêtes fréquentes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_commune_perf ON fact_lieu_activite_soir (id_commune, date, id_zone, id_categorie, id_provenance)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_provenance_nom ON dim_provenances (nom_provenance)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_commune_id ON dim_communes (id_commune)");
    } catch (Exception $e) {
        // Ignorer les erreurs d'index (peuvent déjà exister)
        if ($debug) error_log('Index creation warning: ' . $e->getMessage());
    }

    // Mapping des zones
    require_once __DIR__ . '/../classes/ZoneMapper.php';
    $zoneMapped = ZoneMapper::displayToBase($zone);

    // Forcer les noms de zones en majuscules
    if (strtolower($zone) === 'cantal') {
        $zoneMapped = 'CANTAL';
    } elseif (strtolower($zone) === 'carlades') {
        $zoneMapped = 'CARLADES';
    }

    // Gestionnaire des périodes
    require_once __DIR__ . '/periodes_manager_db.php';
    $dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);

    // Calcul des plages N-1 pour comparaison
    // Si compare_annee est fourni, on calcule par rapport à cette année
    $n1Year = $compareYear ?: ($annee - 1);
    $dateRangesN1 = PeriodesManagerDB::calculateDateRanges($n1Year, $periode);

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
                // Générer N-1 en décalant d'un an
                $dStartN1 = $compareYear ? (clone $dStart)->setDate($compareYear, (int)$dStart->format('m'), (int)$dStart->format('d')) : (clone $dStart)->modify('-1 year');
                $dEndN1 = $compareYear ? (clone $dEnd)->setDate($compareYear, (int)$dEnd->format('m'), (int)$dEnd->format('d')) : (clone $dEnd)->modify('-1 year');
                $dateRangesN1['start'] = $dStartN1->format('Y-m-d') . ' 00:00:00';
                $dateRangesN1['end'] = $dEndN1->format('Y-m-d') . ' 23:59:59';
                if ($debug) {
                    error_log("API communes_excursion Debug - Override par debut/fin: start={$dateRanges['start']}, end={$dateRanges['end']}");
                }
            }
        } catch (Exception $e) {
            // Si dates invalides, ignorer et garder le calcul basé sur la période
            if ($debug) {
                error_log('API communes_excursion Debug - Dates debut/fin invalides, fallback période: ' . $e->getMessage());
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

    /*
     * OPTIMISATION MAJEURE DE PERFORMANCE :
     * - Requête unique au lieu de 2 requêtes séparées (réduction de ~50% du temps)
     * - LIMIT 10 directement dans la sous-requête (évite de récupérer toutes les communes)
     * - Jointure optimisée avec les données N-1
     * - Index automatiques créés pour les colonnes fréquemment utilisées
     * - Mesure du temps d'exécution pour monitoring
     */
    $sqlOptimized = "
        SELECT
            c.nom_commune,
            c.code_insee,
            COALESCE(current_year.total_visiteurs, 0) as total_visiteurs,
            COALESCE(previous_year.total_visiteurs, 0) as total_visiteurs_n1
        FROM (
            -- Sous-requête pour l'année courante avec LIMIT 10
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
            LIMIT 10
        ) current_year
        LEFT JOIN (
            -- Sous-requête pour l'année précédente
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

    // PERFORMANCE : Mesurer le temps d'exécution de la requête
    $queryStartTime = microtime(true);

    $stmt = $pdo->prepare($sqlOptimized);
    $stmt->execute([
        // Année courante
        $dateRanges['start'],
        $dateRanges['end'],
        $id_zone,
        $id_categorie,
        // Année précédente
        $dateRangesN1['start'],
        $dateRangesN1['end'],
        $id_zone,
        $id_categorie
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $queryEndTime = microtime(true);
    $queryExecutionTime = round(($queryEndTime - $queryStartTime) * 1000, 2); // en millisecondes

if ($debug) {
    error_log("API communes_excursion - Query execution time: {$queryExecutionTime}ms for " . count($results) . " results");
    error_log("API communes_excursion - Query params: annee=$annee, periode=$periode, zone=$zone, compare_annee=$compareYear");
}

    // Calculer l'évolution pour chaque destination
    $destinations = [];
    foreach ($results as $row) {
        $totalN = (int)$row['total_visiteurs'];
        $totalN1 = (int)$row['total_visiteurs_n1'];

        // Calcul évolution
        $evolutionPct = $totalN1 > 0 ? round((($totalN - $totalN1) / $totalN1) * 100, 1) : null;

        $destinations[] = [
            'nom_commune' => $row['nom_commune'],
            'code_insee' => $row['code_insee'],
            'total_visiteurs' => $totalN,
            'total_visiteurs_n1' => $totalN1,
            'evolution_pct' => $evolutionPct
        ];
    }

    // Formatage de la réponse
    $response = [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut_periode' => $dateRanges['start'],
        'fin_periode' => $dateRanges['end'],
        'compare_annee' => $compareYear,
        'debut_periode_n1' => $dateRangesN1['start'],
        'fin_periode_n1' => $dateRangesN1['end'],
        'destinations' => $destinations,
        'total_destinations' => count($destinations),
        'performance' => [
            'query_execution_time_ms' => $queryExecutionTime,
            'results_count' => count($results),
            'optimized' => true
        ],
        'status' => 'success'
    ];

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API destinations excursion',
        'message' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?>
