<?php
/**
 * API Bloc D5 Excursionnistes avec Cache Unifié - CSP
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
$limit = (int)($_GET['limit'] ?? 10);

// Inclure le gestionnaire intelligent des périodes et le cache unifié
require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../classes/ZoneMapper.php';
require_once __DIR__ . '/infographie/CacheManager.php';

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
    $cachedData = $cacheManager->get('tdb_csp_excursionnistes', $cacheParams);
    if ($cachedData !== null) {
        header('X-Cache-Status: HIT');
        header('X-Cache-Category: tdb_csp_excursionnistes');
        echo json_encode($cachedData, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Cache miss - calculer les données
    header('X-Cache-Status: MISS');
    header('X-Cache-Category: tdb_csp_excursionnistes');
    
    // Connexion à la base de données
    require_once dirname(__DIR__) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Calcul des plages de dates
    $dateRanges = calculateDateRanges($annee, $periode);
    $dateRangesN1 = calculateDateRanges($annee - 1, $periode);

    // Définition des variables pour optimiser les requêtes - SÉCURISÉ
    $stmt = $pdo->prepare("SET @zone = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?)");
    $stmt->execute([$zone]);
    
    $stmt = $pdo->prepare("SET @cat = (SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = ?)");
    $stmt->execute(['EXCURSIONNISTE']);
    
    $stmt = $pdo->prepare("SET @prov = (SELECT id_provenance FROM dim_provenances WHERE nom_provenance = ?)");
    $stmt->execute(['NONLOCAL']);

    // Requête principale pour les données de l'année courante
    $sqlCurrent = "
    SELECT
        c.nom_csp AS csp,
        c.code_csp AS code_csp,
        SUM(f.volume) AS n_presences
    FROM fact_diurnes_csp AS f
    JOIN dim_csp AS c ON f.id_csp = c.id_csp
    WHERE
        f.id_zone = @zone
        AND f.id_categorie = @cat
        AND f.id_provenance = @prov
        AND f.date BETWEEN ? AND ?
        AND c.nom_csp <> 'CUMUL'
        AND c.nom_csp IS NOT NULL
        AND c.nom_csp <> ''
    GROUP BY
        c.nom_csp, c.code_csp
    ORDER BY
        n_presences DESC
    LIMIT ?";

    $stmt = $pdo->prepare($sqlCurrent);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $limit]);
    $currentData = $stmt->fetchAll();

    // Requête pour les données de l'année précédente
    $sqlPrevious = "
    SELECT
        c.nom_csp AS csp,
        SUM(f.volume) AS n_presences_n1
    FROM fact_diurnes_csp AS f
    JOIN dim_csp AS c ON f.id_csp = c.id_csp
    WHERE
        f.id_zone = @zone
        AND f.id_categorie = @cat
        AND f.id_provenance = @prov
        AND f.date BETWEEN ? AND ?
        AND c.nom_csp <> 'CUMUL'
        AND c.nom_csp IS NOT NULL
        AND c.nom_csp <> ''
    GROUP BY
        c.nom_csp";

    $stmt = $pdo->prepare($sqlPrevious);
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
    $previousData = $stmt->fetchAll();

    // Créer un index pour les données précédentes
    $previousIndex = [];
    foreach ($previousData as $row) {
        $previousIndex[$row['csp']] = (int)$row['n_presences_n1'];
    }

    // Calculer le total pour les pourcentages
    $totalPresences = array_sum(array_column($currentData, 'n_presences'));

    // Fusionner les données et calculer les métriques
    $result = [];
    foreach ($currentData as $row) {
        $csp = $row['csp'];
        $nPresences = (int)$row['n_presences'];
        $nPresencesN1 = $previousIndex[$csp] ?? 0;
        
        // Calcul du delta
        $deltaPct = null;
        if ($nPresencesN1 > 0) {
            $deltaPct = round((($nPresences - $nPresencesN1) / $nPresencesN1) * 100, 1);
        }
        
        // Calcul du pourcentage
        $partPct = $totalPresences > 0 ? round(($nPresences / $totalPresences) * 100, 1) : 0;

        $result[] = [
            'nom_csp' => $csp,
            'code_csp' => $row['code_csp'] ?? '',
            'n_presences' => $nPresences,
            'n_presences_n1' => $nPresencesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
    }

    // Stocker en cache avec le nouveau système
    $cacheManager->set('tdb_csp_excursionnistes', $cacheParams, $result);
    
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('X-Cache-Status: ERROR');
    
    echo json_encode([
        'error' => true,
        'message' => 'Erreur de connexion à la base de données',
        'details' => $e->getMessage()
    ]);

    error_log("API CSP Excursionnistes Error: " . $e->getMessage());
}
?> 