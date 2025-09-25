<?php
/**
 * API Bloc D1 - Version MySQL  
 * Récupère les données départements depuis la base de données MySQL FluxVision
 */

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/database.php';
require_once dirname(__DIR__, 2) . '/security_middleware.php';

// Vérification et sanitisation des paramètres
$params = ApiSecurityMiddleware::sanitizeApiInput($_GET);
$annee = $params['annee'] ?? null;
$periode = $params['periode'] ?? null;
$zone = $params['zone'] ?? null;
$limit = (int)($params['limit'] ?? 15); // Top 15 par défaut

if (!$annee || !$periode || !$zone) {
    apiError('Paramètres manquants: annee, periode, zone requis');
}

try {
    $db = getCantalDestinationDatabase();
    
    // Normalisation des paramètres
    $periodeMapped = CantalDestinationDatabase::mapPeriode($periode);
    $zoneMapped = CantalDestinationDatabase::mapZone($zone);
    
    // Calcul des plages de dates
    $dateRanges = calculateDateRanges($annee, $periodeMapped);
    $prevYear = (int)$annee - 1;
    $prevDateRanges = calculateDateRanges($prevYear, $periodeMapped);
    
    // Requête principale pour les départements avec comparaison N vs N-1
    $sql = "
        SELECT 
            d.nom_departement,
            d.nom_region,
            d.nom_nouvelle_region,
            COALESCE(SUM(fn.volume), 0) as n_nuitees,
            COALESCE(SUM(fn1.volume), 0) as n_nuitees_n1,
            COALESCE(SUM(fd.volume), 0) as n_exo,
            COALESCE(SUM(fd1.volume), 0) as n_exo_n1
        FROM dim_departements d
        LEFT JOIN fact_nuitees_departements fn ON d.id_departement = fn.id_departement
            AND fn.date BETWEEN ? AND ?
            AND fn.id_zone = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?)
        LEFT JOIN fact_nuitees_departements fn1 ON d.id_departement = fn1.id_departement  
            AND fn1.date BETWEEN ? AND ?
            AND fn1.id_zone = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?)
        LEFT JOIN fact_diurnes_departements fd ON d.id_departement = fd.id_departement
            AND fd.date BETWEEN ? AND ?
            AND fd.id_zone = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?)
        LEFT JOIN fact_diurnes_departements fd1 ON d.id_departement = fd1.id_departement
            AND fd1.date BETWEEN ? AND ?
            AND fd1.id_zone = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?)
        GROUP BY d.id_departement, d.nom_departement, d.nom_region, d.nom_nouvelle_region
        HAVING n_nuitees > 0 OR n_nuitees_n1 > 0 OR n_exo > 0 OR n_exo_n1 > 0
        ORDER BY n_nuitees DESC
        LIMIT ?
    ";
    
    $params_sql = [
        // Nuitées année courante
        $dateRanges['start'], $dateRanges['end'], $zoneMapped,
        // Nuitées année N-1
        $prevDateRanges['start'], $prevDateRanges['end'], $zoneMapped,
        // Diurnes année courante  
        $dateRanges['start'], $dateRanges['end'], $zoneMapped,
        // Diurnes année N-1
        $prevDateRanges['start'], $prevDateRanges['end'], $zoneMapped,
        // Limite
        $limit
    ];
    
    $rawData = $db->query($sql, $params_sql);
    
    // Calcul du total pour les pourcentages
    $totalNuitees = array_sum(array_column($rawData, 'n_nuitees'));
    $totalExo = array_sum(array_column($rawData, 'n_exo'));
    
    // Transformation des données pour le format attendu
    $result = [];
    
    foreach ($rawData as $row) {
        $nuitees = (int)$row['n_nuitees'];
        $nuiteesN1 = (int)$row['n_nuitees_n1'];
        $exo = (int)$row['n_exo'];
        $exoN1 = (int)$row['n_exo_n1'];
        
        // Calculs des évolutions et pourcentages
        $deltaPctNuitees = calculateEvolution($nuitees, $nuiteesN1);
        $deltaPctExo = calculateEvolution($exo, $exoN1);
        $partPctNuitees = $totalNuitees > 0 ? round(($nuitees / $totalNuitees) * 100, 2) : 0;
        $partPctExo = $totalExo > 0 ? round(($exo / $totalExo) * 100, 2) : 0;
        
        $result[] = [
            'nom_departement' => $row['nom_departement'],
            'nom_region' => $row['nom_region'], 
            'nom_nouvelle_region' => $row['nom_nouvelle_region'],
            'n_nuitees' => $nuitees,
            'n_nuitees_n1' => $nuiteesN1,
            'delta_pct' => $deltaPctNuitees,
            'part_pct' => $partPctNuitees,
            'n_exo' => $exo,
            'n_exo_n1' => $exoN1,
            'delta_pct_exo' => $deltaPctExo,
            'part_pct_exo' => $partPctExo
        ];
    }
    
    jsonResponse($result);
    
} catch (Exception $e) {
    ApiSecurityMiddleware::handleApiError($e, '/api/bloc_d1_mysql');
}

/**
 * Calcule les plages de dates selon la période
 */
function calculateDateRanges($annee, $periode) {
    switch (strtolower($periode)) {
        case 'annee':
            return [
                'start' => "$annee-01-01",
                'end' => "$annee-12-31"
            ];
        case 'ete':
            return [
                'start' => "$annee-06-21", 
                'end' => "$annee-09-22"
            ];
        case 'hiver':
            return [
                'start' => "$annee-12-21",
                'end' => (((int)$annee) + 1) . "-03-20"
            ];
        case 'printemps':
            return [
                'start' => "$annee-03-21",
                'end' => "$annee-06-20"
            ];
        case 'automne':
            return [
                'start' => "$annee-09-23",
                'end' => "$annee-12-20"
            ];
        default:
            return [
                'start' => "$annee-01-01",
                'end' => "$annee-12-31"
            ];
    }
}

/**
 * Calcule l'évolution en pourcentage
 */
function calculateEvolution($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
} 
