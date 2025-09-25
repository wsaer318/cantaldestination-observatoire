<?php
/**
 * API Bloc D1 avec Cache Unifié - Départements Touristes
 * Version avec cache lisible et organisé
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Récupération directe des paramètres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$limit = (int)($_GET['limit'] ?? 15);
// Bornes personnalisées facultatives
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;

if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

// Inclure le gestionnaire intelligent des périodes et le cache unifié
require_once dirname(__DIR__, 2) . '/periodes_manager_db.php';
require_once dirname(__DIR__, 2) . '/infographie/CacheManager.php';
require_once dirname(__DIR__, 3) . '/classes/ZoneMapper.php';

/**
 * Calcule les plages de dates selon la période - VERSION INTELLIGENTE
 */
function calculateWorkingDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    // Initialiser le cache unifié
    $cacheManager = new CantalDestinationCacheManager();
    $cacheParams = [
        'annee' => $annee,
        'periode' => $periode, 
        'zone' => $zone,
        'limit' => $limit,
        // Inclure les bornes si fournies pour éviter des collisions de cache
        'debut' => $debut,
        'fin' => $fin
    ];
    
    // Vérifier le cache d'abord
    $cachedData = $cacheManager->get('tdb_departements_touristes', $cacheParams);
    if ($cachedData !== null) {
        header('Content-Type: application/json');
        header('X-Cache-Status: HIT');
        header('X-Cache-Category: tdb_departements_touristes');
        echo json_encode($cachedData, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Cache miss - calculer les données
    header('X-Cache-Status: MISS');
    header('X-Cache-Category: tdb_departements_touristes');
    
    // Connexion directe à la base
    require_once dirname(__DIR__, 3) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Normalisation des paramètres avec mapping direct des zones
    $zoneMapped = ZoneMapper::displayToBase($zone);
    
    // Calcul des plages de dates
    $dateRanges = calculateWorkingDateRanges($annee, $periode);
    $prevYear = (int)$annee - 1;
    $prevDateRanges = calculateWorkingDateRanges($prevYear, $periode);

    // Override par debut/fin si fournis
    if (!empty($debut) && !empty($fin)) {
        try {
            $dStart = new DateTime($debut);
            $dEnd = new DateTime($fin);
            if ($dEnd >= $dStart) {
                $dateRanges['start'] = $dStart->format('Y-m-d') . ' 00:00:00';
                $dateRanges['end'] = $dEnd->format('Y-m-d') . ' 23:59:59';
                $prevDateRanges['start'] = $dStart->modify('-1 year')->format('Y-m-d') . ' 00:00:00';
                $prevDateRanges['end'] = $dEnd->modify('-1 year')->format('Y-m-d') . ' 23:59:59';
            }
        } catch (Exception $e) {
            if ($debug) { header('X-Date-Override', 'invalid'); }
        }
    }
    
    // Vérification que la table existe
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fact_nuitees_departements', $tables)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Table fact_nuitees_departements non disponible']);
        exit;
    }
    
    // Pré-résoudre les IDs des dimensions
    $dimensionIds = getDimensionIds($pdo, $zoneMapped);
    
    // Requête optimisée avec agrégation directe
    $sql = "
        SELECT 
            d.nom_departement,
            d.nom_region,
            d.nom_nouvelle_region,
            COALESCE(current_data.n_nuitees, 0) as n_nuitees,
            COALESCE(prev_data.n_nuitees_n1, 0) as n_nuitees_n1
        FROM dim_departements d
        LEFT JOIN (
            SELECT 
                id_departement,
                SUM(volume) as n_nuitees
            FROM fact_nuitees_departements 
            WHERE date BETWEEN ? AND ?
            AND id_zone = ?
            AND id_categorie = ?
            AND id_provenance = ?
            GROUP BY id_departement
        ) current_data ON d.id_departement = current_data.id_departement
        LEFT JOIN (
            SELECT 
                id_departement,
                SUM(volume) as n_nuitees_n1
            FROM fact_nuitees_departements 
            WHERE date BETWEEN ? AND ?
            AND id_zone = ?
            AND id_categorie = ?
            AND id_provenance = ?
            GROUP BY id_departement
        ) prev_data ON d.id_departement = prev_data.id_departement
        WHERE d.nom_departement NOT IN ('CUMUL')
        AND (COALESCE(current_data.n_nuitees, 0) > 0 OR COALESCE(prev_data.n_nuitees_n1, 0) > 0)
        ORDER BY COALESCE(current_data.n_nuitees, 0) DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        // Periode actuelle
        $dateRanges['start'], $dateRanges['end'],
        $dimensionIds['id_zone'], $dimensionIds['id_categorie'], $dimensionIds['id_provenance'],
        // Periode precedente
        $prevDateRanges['start'], $prevDateRanges['end'],
        $dimensionIds['id_zone'], $dimensionIds['id_categorie'], $dimensionIds['id_provenance'],
        // Limite
        $limit
    ]);
    
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul du total pour les pourcentages
    $totalNuitees = calculateTotal($pdo, $dateRanges, $dimensionIds);
    
    // Transformation des données
    $result = [];
    
    foreach ($rawData as $row) {
        $nuitees = (int)$row['n_nuitees'];
        $nuiteesN1 = (int)$row['n_nuitees_n1'];
        
        // Calculs des évolutions et pourcentages
        $deltaPct = 0;
        if ($nuiteesN1 > 0) {
            $deltaPct = round((($nuitees - $nuiteesN1) / $nuiteesN1) * 100, 1);
        } elseif ($nuitees > 0) {
            $deltaPct = 100;
        }
        
        $partPct = $totalNuitees > 0 ? round(($nuitees / $totalNuitees) * 100, 1) : 0;
        
        $result[] = [
            'nom_departement' => $row['nom_departement'],
            'nom_region' => $row['nom_region'],
            'nom_nouvelle_region' => $row['nom_nouvelle_region'],
            'n_nuitees' => $nuitees,
            'n_nuitees_n1' => $nuiteesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
    }
    
    // Stocker en cache avec le nouveau système
    $cacheManager->set('tdb_departements_touristes', $cacheParams, $result);
    
    // Informations sur les tables temporaires disponibles
    if (class_exists('TempTablesManager')) {
        $tempManager = new TempTablesManager();
        $result['_meta'] = [
            'temp_tables_available' => $tempManager->getAvailableTempTables(),
            'cache_status' => 'MISS - Data calculated and cached',
            'cache_category' => 'tdb_departements_touristes'
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    
    echo json_encode([
        'error' => true,
        'message' => 'Erreur lors du traitement',
        'details' => $debug ? $e->getMessage() : 'Erreur interne'
    ]);
    
    error_log("API Départements Touristes Error: " . $e->getMessage());
}

/**
 * Pré-résoudre les IDs des dimensions pour éviter les JOINs
 */
function getDimensionIds($pdo, $zoneMapped) {
    // Utiliser le ZoneMapper pour obtenir l'ID de la zone
    $zoneId = ZoneMapper::getZoneId($zoneMapped, $pdo);
    
    if ($zoneId === null) {
        throw new Exception("Impossible de résoudre l'ID de la zone. Zone recherchée: '$zoneMapped'");
    }
    
    $stmt = $pdo->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = 'TOURISTE'");
    $stmt->execute();
    $id_categorie = $stmt->fetch()['id_categorie'] ?? null;
    
    $stmt = $pdo->prepare("SELECT id_provenance FROM dim_provenances WHERE nom_provenance = 'NONLOCAL'");
    $stmt->execute();
    $id_provenance = $stmt->fetch()['id_provenance'] ?? null;
    
    if (!$id_categorie || !$id_provenance) {
        throw new Exception("Impossible de résoudre les IDs des dimensions. Catégorie ou provenance non trouvée.");
    }
    
    return [
        'id_zone' => $zoneId,
        'id_categorie' => $id_categorie,
        'id_provenance' => $id_provenance
    ];
}

/**
 * Calcul efficace du total séparé
 */
function calculateTotal($pdo, $dateRanges, $dimensionIds) {
    $sql = "
        SELECT SUM(volume) as total
        FROM fact_nuitees_departements
        WHERE date BETWEEN ? AND ?
        AND id_zone = ?
        AND id_categorie = ?
        AND id_provenance = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $dateRanges['start'], $dateRanges['end'],
        $dimensionIds['id_zone'], $dimensionIds['id_categorie'], $dimensionIds['id_provenance']
    ]);
    
    return (int)($stmt->fetch()['total'] ?? 0);
}
?> 
