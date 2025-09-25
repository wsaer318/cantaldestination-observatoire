<?php
/**
 * API Bloc D3 Excursionnistes avec Cache Unifié - Pays
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
$limit = (int)($_GET['limit'] ?? 5);
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;

// Inclure le gestionnaire intelligent des périodes et le cache unifié
require_once dirname(__DIR__, 2) . '/periodes_manager_db.php';
require_once dirname(__DIR__, 3) . '/classes/ZoneMapper.php';
require_once dirname(__DIR__, 2) . '/infographie/CacheManager.php';

/**
 * Calcule les plages de dates selon la période
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
    $cachedData = $cacheManager->get('tdb_pays_excursionnistes', $cacheParams);
    if ($cachedData !== null) {
        header('X-Cache-Status: HIT');
        header('X-Cache-Category: tdb_pays_excursionnistes');
        echo json_encode($cachedData, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Cache miss - calculer les données
    header('X-Cache-Status: MISS');
    header('X-Cache-Category: tdb_pays_excursionnistes');
    
    // Connexion à la base de données
    require_once dirname(__DIR__, 3) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Calcul des plages de dates
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

    // Définition des variables pour optimiser les requêtes - SÉCURISÉ
    $stmt = $pdo->prepare("SET @zone = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?)");
    $stmt->execute([$zone]);
    
    $stmt = $pdo->prepare("SET @cat = (SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = ?)");
    $stmt->execute(['EXCURSIONNISTE']);
    
    $stmt = $pdo->prepare("SET @prov = (SELECT id_provenance FROM dim_provenances WHERE nom_provenance = ?)");
    $stmt->execute(['ETRANGER']);

    // Requête principale pour les données de l'année courante
    $sqlCurrent = "
    SELECT
        p.nom_pays AS pays,
        SUM(f.volume) AS n_presences
    FROM fact_diurnes_pays AS f
    JOIN dim_pays AS p ON f.id_pays = p.id_pays
    WHERE
        f.id_zone = @zone
        AND f.id_categorie = @cat
        AND f.id_provenance = @prov
        AND f.date BETWEEN ? AND ?
        AND p.nom_pays <> 'CUMUL'
        AND p.nom_pays IS NOT NULL
        AND p.nom_pays <> ''
    GROUP BY
        p.nom_pays
    ORDER BY
        n_presences DESC
    LIMIT ?";

    $stmt = $pdo->prepare($sqlCurrent);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $limit]);
    $currentData = $stmt->fetchAll();

    // Requête pour les données de l'année précédente
    $sqlPrevious = "
    SELECT
        p.nom_pays AS pays,
        SUM(f.volume) AS n_presences_n1
    FROM fact_diurnes_pays AS f
    JOIN dim_pays AS p ON f.id_pays = p.id_pays
    WHERE
        f.id_zone = @zone
        AND f.id_categorie = @cat
        AND f.id_provenance = @prov
        AND f.date BETWEEN ? AND ?
        AND p.nom_pays <> 'CUMUL'
        AND p.nom_pays IS NOT NULL
        AND p.nom_pays <> ''
    GROUP BY
        p.nom_pays";

    $stmt = $pdo->prepare($sqlPrevious);
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
    $previousData = $stmt->fetchAll();

    // Créer un index pour les données précédentes
    $previousIndex = [];
    foreach ($previousData as $row) {
        $previousIndex[$row['pays']] = (int)$row['n_presences_n1'];
    }

    // Calculer le total pour les pourcentages
    $totalPresences = array_sum(array_column($currentData, 'n_presences'));

    // ✅ GESTION DES DONNÉES VIDES (comme dashboard temps réel)
    if (empty($currentData)) {
        $result = [['nom_pays' => 'Aucune donnée', 'n_presences' => 0, 'n_presences_n1' => 0, 'delta_pct' => null, 'part_pct' => 0]];
        
        // Stocker en cache avec le nouveau système
        $cacheManager->set('tdb_pays_excursionnistes', $cacheParams, $result);
        
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }

    // Fusionner les données et calculer les métriques
    $result = [];
    foreach ($currentData as $row) {
        $pays = $row['pays'];
        $nPresences = (int)$row['n_presences'];
        $nPresencesN1 = $previousIndex[$pays] ?? 0;
        
        // Calcul du delta
        $deltaPct = null;
        if ($nPresencesN1 > 0) {
            $deltaPct = round((($nPresences - $nPresencesN1) / $nPresencesN1) * 100, 1);
        }
        
        // Calcul du pourcentage
        $partPct = $totalPresences > 0 ? round(($nPresences / $totalPresences) * 100, 1) : 0;

        $result[] = [
            'nom_pays' => $pays,
            'n_presences' => $nPresences,
            'n_presences_n1' => $nPresencesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
    }

    // Stocker en cache avec le nouveau système
    $cacheManager->set('tdb_pays_excursionnistes', $cacheParams, $result);
    
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
