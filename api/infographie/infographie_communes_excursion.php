﻿<?php
/**
 * API Infographie Communes Excursion
 * RÃ©cupÃ¨re les donnÃ©es des destinations d'excursion pour l'infographie
 * Version optimisÃ©e avec cache unifiÃ© pour les meilleures performances
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure le gestionnaire de cache unifiÃ©
require_once __DIR__ . '/CacheManager.php';

// RÃ©cupÃ©ration des paramÃ¨tres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debutOverride = $_GET['debut'] ?? null;
$finOverride = $_GET['fin'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$limit = (int)($_GET['limit'] ?? 10);

if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ParamÃ¨tres manquants: annee, periode, zone requis']);
    exit;
}

// Inclure le gestionnaire de pÃ©riodes et le mapper de zones
require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

/**
 * Calcule les plages de dates selon la pÃ©riode
 */
function calculateWorkingDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    // Initialiser le gestionnaire de cache
    $cacheManager = new CantalDestinationCacheManager();

    // ParamÃ¨tres de cache
    $cacheParams = [
        'annee' => $annee,
        'periode' => $periode,
        'zone' => $zone,
        'limit' => $limit
    ];

    // VÃ©rifier le cache d'abord (sauf si debug activÃ© ou force_refresh demandÃ©)
    $forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] === '1';
    if (!$debug && !$forceRefresh) {
    $cachedData = $cacheManager->get('infographie_communes_excursion', $cacheParams);
    if ($cachedData !== null) {
        header('Content-Type: application/json');
        header('X-Cache-Status: HIT');
        header('X-Cache-Category: infographie_communes_excursion');
        echo json_encode($cachedData, JSON_PRETTY_PRINT);
        exit;
        }
    }

    // Cache miss - calculer les donnÃ©es
    header('X-Cache-Status: MISS');
    header('X-Cache-Category: infographie_communes_excursion');

    // Connexion Ã  la base de donnÃ©es
    require_once dirname(dirname(__DIR__)) . '/database.php';
    $pdo = DatabaseConfig::getConnection();

    // OPTIMISATION : CrÃ©er des index critiques pour la performance
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_commune_perf ON fact_lieu_activite_soir (id_commune, date, id_zone, id_categorie, id_provenance)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_provenance_nom ON dim_provenances (nom_provenance)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_commune_id ON dim_communes (id_commune)");
    } catch (Exception $e) {
        // Ignorer les erreurs d'index (peuvent dÃ©jÃ  exister)
        if ($debug) error_log('Index creation warning: ' . $e->getMessage());
    }

    // Mapping des zones avec support historique pour HAUTES TERRES
    $zoneMapped = ZoneMapper::displayToBase($zone);

    // Gestion spÃ©ciale pour HAUTES TERRES : inclure les donnÃ©es historiques
    $zoneNames = [$zoneMapped];
    if ($zoneMapped === 'HAUTES TERRES') {
        // Ajouter HAUTES TERRES COMMUNAUTE pour l'historique 2019-2022
        $zoneNames[] = 'HAUTES TERRES COMMUNAUTE';
    }

    // CrÃ©er les placeholders pour les noms de zones
    $zonePlaceholders = implode(',', array_fill(0, count($zoneNames), '?'));

    // Calcul des plages de dates
    if ($debutOverride && $finOverride) {
        try {
            $dStart = new DateTime($debutOverride);
            $dEnd = new DateTime($finOverride);
            if ($dEnd >= $dStart) {
                $dateRanges['start'] = $dStart->format('Y-m-d') . ' 00:00:00';
                $dateRanges['end'] = $dEnd->format('Y-m-d') . ' 23:59:59';
                // âœ… FIX : Utiliser clone pour Ã©viter la modification par rÃ©fÃ©rence
                $prevDateRanges['start'] = (clone $dStart)->modify('-1 year')->format('Y-m-d') . ' 00:00:00';
                $prevDateRanges['end'] = (clone $dEnd)->modify('-1 year')->format('Y-m-d') . ' 23:59:59';
            }
        } catch (Exception $e) {
            if ($debug) { header('X-Date-Override', 'invalid'); }
        }
    } else {
        $dateRanges = calculateWorkingDateRanges($annee, $periode);
        $prevYear = (int)$annee - 1;
        $prevDateRanges = calculateWorkingDateRanges($prevYear, $periode);
    }

    // âœ… APPROCHE OPTIMALE : Utiliser directement les noms dans les jointures
    // Plus besoin de rÃ©cupÃ©rer les IDs - on joint directement sur les noms
    // Cela Ã©vite complÃ¨tement les problÃ¨mes d'IDs diffÃ©rents entre environnements

    /*
     * OPTIMISATION MAJEURE DE PERFORMANCE :
     * - RequÃªte unique au lieu de 2 requÃªtes sÃ©parÃ©es (rÃ©duction de ~50% du temps)
     * - LIMIT 10 directement dans la sous-requÃªte (Ã©vite de rÃ©cupÃ©rer toutes les communes)
     * - Jointure optimisÃ©e avec les donnÃ©es N-1
     * - Index automatiques crÃ©Ã©s pour les colonnes frÃ©quemment utilisÃ©es
     * - Mesure du temps d'exÃ©cution pour monitoring
     */
    $sqlOptimized = "
        SELECT
            c.nom_commune,
            c.code_insee,
            COALESCE(cur.total_visiteurs, 0)      AS total_visiteurs,
            COALESCE(prev.total_visiteurs, 0)     AS total_visiteurs_n1
        FROM (
            -- TOP 10 communes annÃ©e courante (2025)
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
            -- DonnÃ©es annÃ©e prÃ©cÃ©dente (2024) sur les mÃªmes communes
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

    // PERFORMANCE : Mesurer le temps d'exÃ©cution de la requÃªte
    $queryStartTime = microtime(true);

    $stmt = $pdo->prepare($sqlOptimized);

    // Construction des paramÃ¨tres avec les noms de zones
    $params = [
        // AnnÃ©e courante
        $dateRanges['start'],
        $dateRanges['end']
    ];
    // Ajouter tous les noms de zones pour l'annÃ©e courante
    $params = array_merge($params, $zoneNames);
    $params[] = $limit; // LIMIT pour top destinations

    // AnnÃ©e prÃ©cÃ©dente
    $params[] = $prevDateRanges['start'];
    $params[] = $prevDateRanges['end'];
    // Ajouter tous les noms de zones pour l'annÃ©e prÃ©cÃ©dente
    $params = array_merge($params, $zoneNames);

    $stmt->execute($params);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $queryEndTime = microtime(true);
    $queryExecutionTime = round(($queryEndTime - $queryStartTime) * 1000, 2); // en millisecondes

    if ($debug) {
        error_log("API infographie_communes_excursion - Query execution time: {$queryExecutionTime}ms for " . count($results) . " results");
        error_log("API infographie_communes_excursion - Query params: annee=$annee, periode=$periode, zone=$zone, limit=$limit");
        error_log("API infographie_communes_excursion - Names used: zone_name=$zoneMapped, categorie_name=TOURISTE");
        error_log("API infographie_communes_excursion - Date ranges: current=[{$dateRanges['start']} to {$dateRanges['end']}], previous=[{$prevDateRanges['start']} to {$prevDateRanges['end']}]");

        // ðŸ” DIAGNOSTIC SUPPLÃ‰MENTAIRE : VÃ©rifier si la table contient des donnÃ©es pour cette pÃ©riode
        $diagStmt = $pdo->prepare("
            SELECT COUNT(*) as total_rows, MIN(f.date) as min_date, MAX(f.date) as max_date
            FROM fact_lieu_activite_soir f
            INNER JOIN dim_zones_observation zo ON f.id_zone = zo.id_zone
            INNER JOIN dim_categories_visiteur cv ON f.id_categorie = cv.id_categorie
            WHERE zo.nom_zone IN ($zonePlaceholders) AND cv.nom_categorie = 'TOURISTE'
            AND f.date >= ? AND f.date <= ?
        ");
        $diagParams = array_merge($zoneNames, [$dateRanges['start'], $dateRanges['end']]);
        $diagStmt->execute($diagParams);
        $diagResult = $diagStmt->fetch(PDO::FETCH_ASSOC);
        error_log("API infographie_communes_excursion - DIAGNOSTIC: total_rows={$diagResult['total_rows']}, date_range=[{$diagResult['min_date']} to {$diagResult['max_date']}]");
    }

    // Calculer l'Ã©volution pour chaque destination
    $destinations = [];
    foreach ($results as $row) {
        $totalN = (int)$row['total_visiteurs'];
        $totalN1 = (int)$row['total_visiteurs_n1'];

        // Calcul Ã©volution
        $evolutionPct = $totalN1 > 0 ? round((($totalN - $totalN1) / $totalN1) * 100, 1) : null;

        $destinations[] = [
            'nom_commune' => $row['nom_commune'],
            'code_insee' => $row['code_insee'],
            'total_visiteurs' => $totalN,
            'total_visiteurs_n1' => $totalN1,
            'evolution_pct' => $evolutionPct
        ];
    }

    // ðŸ” DIAGNOSTIC : Ajouter des informations de debug dans la rÃ©ponse
    $diagnosticInfo = null;
    if ($debug || count($destinations) === 0) {
        // Compter les donnÃ©es brutes disponibles
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
        $diagParams2 = array_merge($zoneNames, [$dateRanges['start'], $dateRanges['end']]);
        $diagStmt->execute($diagParams2);
        $diagResult = $diagStmt->fetch(PDO::FETCH_ASSOC);

        $diagnosticInfo = [
            'raw_data_available' => (int)$diagResult['total_rows'],
            'unique_communes_with_data' => (int)$diagResult['unique_communes'],
            'zone_name' => $zoneMapped,
            'zone_ids_used' => $zoneIds,
            'categorie_name' => 'TOURISTE',
            'date_range_current' => $dateRanges['start'] . ' to ' . $dateRanges['end'],
            'date_range_previous' => $prevDateRanges['start'] . ' to ' . $prevDateRanges['end'],
            'empty_result_reason' => count($destinations) === 0 ? 'No data found for specified criteria' : null
        ];
    }

    // Formatage de la rÃ©ponse
    $response = [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut_periode' => $dateRanges['start'],
        'fin_periode' => $dateRanges['end'],
        'compare_annee' => $prevYear ?? ($annee - 1),
        'debut_periode_n1' => $prevDateRanges['start'],
        'fin_periode_n1' => $prevDateRanges['end'],
        'destinations' => $destinations,
        'total_destinations' => count($destinations),
        'performance' => [
            'query_execution_time_ms' => $queryExecutionTime,
            'results_count' => count($results),
            'optimized' => true,
            'cache_category' => 'infographie_communes_excursion'
        ],
        'status' => 'success'
    ];

    // Ajouter les informations de diagnostic si nÃ©cessaire
    if ($diagnosticInfo) {
        $response['diagnostic'] = $diagnosticInfo;
    }

    // Stocker en cache avec le nouveau systÃ¨me
    $cacheManager->set('infographie_communes_excursion', $cacheParams, $response);

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API infographie communes excursion',
        'message' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?>

