<?php
/**
 * API Bloc D3 Excursionnistes - Pays Optimisée avec Cache
 * Retourne les données des pays pour les excursionnistes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Inclure le gestionnaire de cache amélioré
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

// Récupération des paramètres
$annee = (int)($_GET['annee'] ?? 2024);
$periode = $_GET['periode'] ?? 'hiver';
$zone = $_GET['zone'] ?? 'CANTAL';
$debutOverride = $_GET['debut'] ?? null;
$finOverride = $_GET['fin'] ?? null;
$limit = (int)($_GET['limit'] ?? 5);

// Initialiser le gestionnaire de cache unifié
$cacheManager = new CantalDestinationCacheManager();

// Paramètres pour le cache
$cacheParams = [
    'annee' => $annee,
    'periode' => $periode,
    'zone' => $zone,
    'limit' => $limit
];

// Vérifier le cache
$cachedData = $cacheManager->get('infographie_pays', $cacheParams);
if ($cachedData !== null) {
    header('X-Cache-Status: HIT');
    header('X-Cache-Category: infographie_pays');
    echo json_encode($cachedData, JSON_PRETTY_PRINT);
    exit;
}

// Inclure le gestionnaire intelligent des périodes
require_once __DIR__ . '/periodes_manager_db.php';

// Inclure le système de correction des données
require_once __DIR__ . '/correction_helper.php';

/**
 * Calcule les plages de dates selon la période - VERSION INTELLIGENTE
 * Utilise les vraies dates depuis la base de données
 */
function calculateDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    $start_time = microtime(true);
    
    // Connexion à la base de données
    require_once dirname(dirname(__DIR__)) . '/database.php';
    $pdo = DatabaseConfig::getConnection();
    
    // ✅ Mapper la zone d'abord, puis récupérer l'ID
    $zoneMapped = ZoneMapper::displayToBase($zone);
    $zoneId = ZoneMapper::getZoneId($zoneMapped, $pdo);
    if ($zoneId === null) {
        throw new Exception("Zone non trouvée: $zone");
    }
    
    // Récupérer les autres IDs
    $stmt = $pdo->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = 'EXCURSIONNISTE'");
    $stmt->execute();
    $categorieId = $stmt->fetch()['id_categorie'] ?? null;
    
    $stmt = $pdo->prepare("SELECT id_provenance FROM dim_provenances WHERE nom_provenance = 'ETRANGER'");
    $stmt->execute();
    $provenanceId = $stmt->fetch()['id_provenance'] ?? null;
    
    if (!$categorieId || !$provenanceId) {
        throw new Exception("Catégorie ou provenance non trouvée");
    }

    // Calcul des plages de dates (support override)
    if ($debutOverride && $finOverride) {
        $start = new DateTime($debutOverride . ' 00:00:00');
        $end = new DateTime($finOverride . ' 23:59:59');
        $dateRanges = [ 'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s') ];
        $s1 = (clone $start)->modify('-1 year');
        $e1 = (clone $end)->modify('-1 year');
        $dateRangesN1 = [ 'start' => $s1->format('Y-m-d H:i:s'), 'end' => $e1->format('Y-m-d H:i:s') ];
    } else {
        $dateRanges = calculateDateRanges($annee, $periode);
        $dateRangesN1 = calculateDateRanges($annee - 1, $periode);
    }

    // REQUÊTE OPTIMISÉE : Une seule requête avec LEFT JOIN pour les deux années
    $sqlOptimized = "
    SELECT 
        p.nom_pays AS pays_origine,
        COALESCE(current_year.total_presences, 0) AS total_presences,
        COALESCE(prev_year.total_presences, 0) AS total_presences_n1
    FROM dim_pays p
    LEFT JOIN (
        SELECT 
            f.id_pays,
            SUM(f.volume) AS total_presences
        FROM fact_diurnes_pays f
        WHERE f.id_zone = ?
            AND f.id_categorie = ?
            AND f.id_provenance = ?
            AND f.date BETWEEN ? AND ?
        GROUP BY f.id_pays
    ) current_year ON p.id_pays = current_year.id_pays
    LEFT JOIN (
        SELECT 
            f.id_pays,
            SUM(f.volume) AS total_presences
        FROM fact_diurnes_pays f
        WHERE f.id_zone = ?
            AND f.id_categorie = ?
            AND f.id_provenance = ?
            AND f.date BETWEEN ? AND ?
        GROUP BY f.id_pays
    ) prev_year ON p.id_pays = prev_year.id_pays
    WHERE p.nom_pays <> 'CUMUL'
        AND (COALESCE(current_year.total_presences, 0) > 0 
             OR COALESCE(prev_year.total_presences, 0) > 0)
    ORDER BY COALESCE(current_year.total_presences, 0) DESC
    LIMIT ?";

    $stmt = $pdo->prepare($sqlOptimized);
    $stmt->execute([
        // Current year
        $zoneId, $categorieId, $provenanceId,
        $dateRanges['start'], $dateRanges['end'],
        // Previous year
        $zoneId, $categorieId, $provenanceId,
        $dateRangesN1['start'], $dateRangesN1['end'],
        // Limit
        $limit
    ]);
    
    $allData = $stmt->fetchAll();
    
    // Calcul du total pour les pourcentages - optimisé
    $totalPresences = 0;
    foreach ($allData as $row) {
        $totalPresences += (int)$row['total_presences'];
    }

    // Transformation des données - une seule boucle
    $result = [];
    foreach ($allData as $row) {
        $pays = $row['pays_origine'];
        $nPresences = (int)$row['total_presences'];
        $nPresencesN1 = (int)$row['total_presences_n1'];
        
        // Calcul du delta
        $deltaPct = null;
        if ($nPresencesN1 > 0) {
            $deltaPct = round((($nPresences - $nPresencesN1) / $nPresencesN1) * 100, 1);
        }
        
        // Calcul du pourcentage
        $partPct = $totalPresences > 0 ? round(($nPresences / $totalPresences) * 100, 1) : 0;

        $result[] = [
            'nom_pays' => $pays,
            'pays_origine' => $pays,
            'n_presences' => $nPresences,
            'total_presences' => $nPresences,
            'n_presences_n1' => $nPresencesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
    }

    $execution_time = round((microtime(true) - $start_time) * 1000, 2);

    // Mise en cache avec le gestionnaire unifié
    $cacheManager->set('infographie_pays', $cacheParams, $result);
    
    header('X-Cache-Status: MISS');
    header('X-Cache-Category: infographie_pays');
    header('X-Execution-Time: ' . $execution_time . 'ms');
    // Appliquer les corrections si nécessaire
    if (isset($result['data'])) {
        $result['data'] = applyCorrectionIfNeeded($result['data'], $zone, $annee, $periode, 'infographie_pays_excursionnistes.php');
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('X-Cache-Status: ERROR');
    
    echo json_encode([
        'error' => true,
        'message' => 'Erreur de connexion à la base de données',
        'details' => $e->getMessage()
    ]);

    
    error_log("API Pays Excursionnistes Error: " . $e->getMessage());
}
?>