<?php
/**
 * API Bloc D1 Excursionnistes avec Cache Unifié - Départements
 * Version avec cache lisible et organisé
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Récupération des paramètres
$annee = (int)($_GET['annee'] ?? 2024);
$periode = $_GET['periode'] ?? 'hiver';
$zone = $_GET['zone'] ?? 'CANTAL';
$limit = (int)($_GET['limit'] ?? 15);
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;

// Inclure le gestionnaire intelligent des périodes et le cache unifié
require_once dirname(__DIR__, 2) . '/periodes_manager_db.php';
require_once dirname(__DIR__, 3) . '/classes/ZoneMapper.php';
require_once dirname(__DIR__, 2) . '/infographie/CacheManager.php';

/**
 * Calcule les plages de dates selon la période - VERSION INTELLIGENTE
 */
function calculateDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    // Initialiser le cache unifié
    $cacheManager = new CantalDestinationCacheManager();
    $cacheParams = [
        'annee' => $annee,
        'periode' => $periode,
        'zone' => $zone,
        'limit' => $limit
    ];
    
    // Vérifier le cache d'abord
    $cachedData = $cacheManager->get('tdb_departements_excursionnistes', $cacheParams);
    if ($cachedData !== null) {
        header('X-Cache-Status: HIT');
        header('X-Cache-Category: tdb_departements_excursionnistes');
        echo json_encode($cachedData, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Cache miss - calculer les données
    header('X-Cache-Status: MISS');
    header('X-Cache-Category: tdb_departements_excursionnistes');
    
    // Connexion à la base de données
    require_once dirname(__DIR__, 3) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // ✅ Utiliser le ZoneMapper pour récupérer les IDs
    $zoneId = ZoneMapper::getZoneId($zone, $pdo);
    if ($zoneId === null) {
        throw new Exception("Zone non trouvée: $zone");
    }
    
    // Récupérer les autres IDs
    $stmt = $pdo->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = 'EXCURSIONNISTE'");
    $stmt->execute();
    $categorieId = $stmt->fetch()['id_categorie'] ?? null;
    
    $stmt = $pdo->prepare("SELECT id_provenance FROM dim_provenances WHERE nom_provenance = 'NONLOCAL'");
    $stmt->execute();
    $provenanceId = $stmt->fetch()['id_provenance'] ?? null;
    
    if (!$categorieId || !$provenanceId) {
        throw new Exception("Catégorie ou provenance non trouvée");
    }
    
    // Calcul des plages de dates pour l'année courante et précédente
    $dateRanges = calculateDateRanges($annee, $periode);
    $dateRangesN1 = calculateDateRanges($annee - 1, $periode);

    if (!empty($debut) && !empty($fin)) {
        try {
            $ds = new DateTime($debut);
            $de = new DateTime($fin);
            if ($de >= $ds) {
                $dateRanges['start'] = $ds->format('Y-m-d') . ' 00:00:00';
                $dateRanges['end'] = $de->format('Y-m-d') . ' 23:59:59';
                $dateRangesN1['start'] = $ds->modify('-1 year')->format('Y-m-d') . ' 00:00:00';
                $dateRangesN1['end'] = $de->modify('-1 year')->format('Y-m-d') . ' 23:59:59';
            }
        } catch (Exception $e) {}
    }

    // ✅ Requête principale pour les données de l'année courante avec IDs directs
    $sqlCurrent = "
    SELECT
        d.nom_departement AS departement,
        d.nom_nouvelle_region AS region_nouvelle,
        SUM(f.volume) AS n_presences
    FROM fact_diurnes_departements AS f
    JOIN dim_departements AS d ON f.id_departement = d.id_departement
    WHERE
        f.id_zone = ?
        AND f.id_categorie = ?
        AND f.id_provenance = ?
        AND f.date BETWEEN ? AND ?
        AND d.nom_departement <> 'CUMUL'
    GROUP BY
        d.nom_departement, d.nom_nouvelle_region
    ORDER BY
        n_presences DESC
    LIMIT ?";

    $stmt = $pdo->prepare($sqlCurrent);
    $stmt->execute([$zoneId, $categorieId, $provenanceId, $dateRanges['start'], $dateRanges['end'], $limit]);
    $currentData = $stmt->fetchAll();

    // ✅ Requête pour les données de l'année précédente avec IDs directs
    $sqlPrevious = "
    SELECT
        d.nom_departement AS departement,
        SUM(f.volume) AS n_presences_n1
    FROM fact_diurnes_departements AS f
    JOIN dim_departements AS d ON f.id_departement = d.id_departement
    WHERE
        f.id_zone = ?
        AND f.id_categorie = ?
        AND f.id_provenance = ?
        AND f.date BETWEEN ? AND ?
        AND d.nom_departement <> 'CUMUL'
    GROUP BY
        d.nom_departement";

    $stmt = $pdo->prepare($sqlPrevious);
    $stmt->execute([$zoneId, $categorieId, $provenanceId, $dateRangesN1['start'], $dateRangesN1['end']]);
    $previousData = $stmt->fetchAll();

    // Créer un index pour les données précédentes
    $previousIndex = [];
    foreach ($previousData as $row) {
        $previousIndex[$row['departement']] = (int)$row['n_presences_n1'];
    }

    // Calculer le total pour les pourcentages
    $totalPresences = array_sum(array_column($currentData, 'n_presences'));

    // Fusionner les données et calculer les métriques
    $result = [];
    foreach ($currentData as $row) {
        $departement = $row['departement'];
        $nPresences = (int)$row['n_presences'];
        $nPresencesN1 = $previousIndex[$departement] ?? 0;
        
        // Calcul du delta
        $deltaPct = null;
        if ($nPresencesN1 > 0) {
            $deltaPct = round((($nPresences - $nPresencesN1) / $nPresencesN1) * 100, 1);
        }
        
        // Calcul du pourcentage
        $partPct = $totalPresences > 0 ? round(($nPresences / $totalPresences) * 100, 1) : 0;

        $result[] = [
            'nom_departement' => $departement,
            'nom_region' => $row['region_nouvelle'],
            'nom_nouvelle_region' => $row['region_nouvelle'],
            'n_presences' => $nPresences,
            'n_presences_n1' => $nPresencesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
    }

    // Stocker en cache avec le nouveau système
    $cacheManager->set('tdb_departements_excursionnistes', $cacheParams, $result);
    
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('X-Cache-Status: ERROR');
    
    echo json_encode([
        'error' => true,
        'message' => 'Erreur de connexion à la base de données',
        'details' => $e->getMessage()
    ]);

    error_log("API Départements Excursionnistes Error: " . $e->getMessage());
}
?> 
