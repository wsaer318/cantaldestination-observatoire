<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * API Blocs D - Version Avancée MySQL
 * Reproduit fidèlement la logique du script Python pour les comparaisons N vs N-1
 */

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/database.php';
require_once dirname(__DIR__, 2) . '/security_middleware.php';
require_once dirname(__DIR__, 2) . '/periodes_manager_db.php';
require_once dirname(__DIR__, 3) . '/classes/ZoneMapper.php';

/**
 * Calcule les plages de dates selon la période - VERSION INTELLIGENTE
 * Utilise le gestionnaire centralisé PeriodesManager
 */
function calculateDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

// Vérification des paramètres
$params = ApiSecurityMiddleware::sanitizeApiInput($_GET);
$annee = $params['annee'] ?? null;
$periode = $params['periode'] ?? null;
$zone = $params['zone'] ?? null;
$bloc = $params['bloc'] ?? null; // d1, d2, d3, d5, d6, d7

if (!$annee || !$periode || !$zone || !$bloc) {
    apiError('Paramètres manquants: annee, periode, zone, bloc requis');
}

try {
    $db = getCantalDestinationDatabase();
    
    // Normalisation des paramètres
    $periodeMapped = CantalDestinationDatabase::mapPeriode($periode);
    $zoneMapped = CantalDestinationDatabase::mapZone($zone);
    $prevYear = (int)$annee - 1;
    
    // Calcul des plages de dates
    $dateRanges = calculateDateRanges($annee, $periodeMapped);
    $prevDateRanges = calculateDateRanges($prevYear, $periodeMapped);
    
    $result = null;
    
    switch (strtolower($bloc)) {
        case 'd1':
            $result = calculateBlocD1($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd1_exc':
            $result = calculateBlocD1Exc($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd2':
            $result = calculateBlocD2($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd2_exc':
            $result = calculateBlocD2Exc($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd3':
            $result = calculateBlocD3($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd3_exc':
            $result = calculateBlocD3Exc($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd5':
            $result = calculateBlocD5($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd5_exc':
            $result = calculateBlocD5Exc($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd6':
            $result = calculateBlocD6($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd6_exc':
            $result = calculateBlocD6Exc($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        case 'd7':
            $result = calculateBlocD7($db, $dateRanges, $prevDateRanges, $zoneMapped, $annee, $prevYear, $periode);
            break;
        default:
            apiError("Bloc '$bloc' non supporté. Utilisez: d1, d2, d3, d5, d6, d7, d1_exc, d2_exc, d3_exc, d5_exc, d6_exc");
    }
    
    jsonResponse($result);
    
} catch (Exception $e) {
    ApiSecurityMiddleware::handleApiError($e, "/api/bloc_d_advanced_mysql/$bloc");
}

/**
 * D1: Départements (Touristes)
 */
function calculateBlocD1($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    $sql = "
        SELECT d.nom_departement, SUM(f.volume) as volume
        FROM fact_nuitees_departements f
        INNER JOIN dim_departements d ON f.id_departement = d.id_departement
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'TOURISTE'
        AND p.nom_provenance = 'NONLOCAL'
        AND d.nom_departement NOT IN ('CUMUL')
        GROUP BY d.id_departement, d.nom_departement
        ORDER BY volume DESC
    ";
    
    // Données année N
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    
    // Données année N-1
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'nom_departement', 'n_nuitees', 15);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d1' => $comparison
    ];
}

/**
 * D2: Régions (Touristes)
 */
function calculateBlocD2($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    // Nouvelle requête optimisée selon les spécifications utilisateur
    $sql = "
        SELECT
            r.nom_nouvelle_region AS nom_region,
            SUM(fr.volume) AS volume
        FROM
            fact_nuitees_regions AS fr
        JOIN
            dim_regions AS r ON fr.id_region = r.id_region
        JOIN
            dim_dates AS d ON fr.date = d.date
        JOIN
            dim_zones_observation AS z ON fr.id_zone = z.id_zone
        JOIN
            dim_categories_visiteur AS c ON fr.id_categorie = c.id_categorie
        JOIN
            dim_provenances AS p ON fr.id_provenance = p.id_provenance
        WHERE
            d.date BETWEEN ? AND ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'TOURISTE'
            AND p.nom_provenance = 'NONLOCAL'
            AND r.nom_nouvelle_region NOT IN ('CUMUL', 'Cumul')
        GROUP BY
            r.nom_nouvelle_region
        ORDER BY
            volume DESC
    ";
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'nom_region', 'n_nuitees', 5);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d2' => $comparison
    ];
}

/**
 * D3: Pays (Touristes)
 */
function calculateBlocD3($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    $sql = "
        SELECT p.nom_pays, SUM(f.volume) as volume
        FROM fact_nuitees_pays f
        INNER JOIN dim_pays p ON f.id_pays = p.id_pays
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances prov ON f.id_provenance = prov.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'TOURISTE'
        AND prov.nom_provenance = 'ETRANGER'
        AND p.nom_pays NOT IN ('CUMUL')
        GROUP BY p.id_pays, p.nom_pays
        ORDER BY volume DESC
    ";
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'nom_pays', 'n_nuitees', 5);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d3' => $comparison
    ];
}

/**
 * D5: CSP (Touristes)
 */
function calculateBlocD5($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    // Mapping CSP exactement comme dans le Python
    $cspMapping = [
        'CSP +' => ['PAVILLONNAIRE FAMILIAL AISE', 'URBAIN FAMILIAL AISE', 'URBAIN DYNAMIQUE', 'RURAL DYNAMIQUE'],
        'CSP en croissance' => ['PERIURBAIN EN CROISSANCE', 'RESIDENCE SECONDAIRE', 'URBAIN CLASSE MOYENNE'],
        'Populaire' => ['POPULAIRE', 'RURAL TRADITIONNEL', 'RURAL OUVRIER', 'URBAIN DEFAVORISE']
    ];
    
    $dataNCsp = getCSPData($db, $dateRanges, $zone, $cspMapping);
    $dataN1Csp = getCSPData($db, $prevDateRanges, $zone, $cspMapping);
    
    $comparison = calculateComparison($dataNCsp, $dataN1Csp, 'csp', 'n_nuitees', 3);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d5' => $comparison
    ];
}

/**
 * Récupère les données CSP avec mapping
 */
function getCSPData($db, $dateRanges, $zone, $cspMapping) {
    $allSegments = [];
    foreach ($cspMapping as $segments) {
        $allSegments = array_merge($allSegments, $segments);
    }
    
    $segmentsStr = "'" . implode("', '", $allSegments) . "'";
    
    $sql = "
        SELECT g.nom_segment, SUM(f.volume) as volume
        FROM fact_nuitees_geolife f
        INNER JOIN dim_segments_geolife g ON f.id_geolife = g.id_geolife
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'TOURISTE'
        AND p.nom_provenance = 'NONLOCAL'
        AND UPPER(g.nom_segment) IN ($segmentsStr)
        AND g.nom_segment NOT IN ('CUMUL', 'NR', 'NON RENSEIGNE')
        GROUP BY g.nom_segment
    ";
    
    $rawData = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    
    // Convertir en CSP
    $cspData = [];
    foreach ($rawData as $row) {
        $segment = strtoupper($row['nom_segment']);
        $csp = null;
        
        foreach ($cspMapping as $cspName => $segments) {
            if (in_array($segment, $segments)) {
                $csp = $cspName;
                break;
            }
        }
        
        if ($csp) {
            if (!isset($cspData[$csp])) {
                $cspData[$csp] = 0;
            }
            $cspData[$csp] += (int)$row['volume'];
        }
    }
    
    // Convertir en format compatible
    $result = [];
    foreach ($cspData as $csp => $volume) {
        $result[] = ['csp' => $csp, 'volume' => $volume];
    }
    
    return $result;
}

/**
 * D6: Age (Touristes)
 */
function calculateBlocD6($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    $sql = "
        SELECT a.tranche_age, SUM(f.volume) as volume
        FROM fact_nuitees_age f
        INNER JOIN dim_tranches_age a ON f.id_age = a.id_age
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'TOURISTE'
        AND p.nom_provenance = 'NONLOCAL'
        AND a.tranche_age NOT IN ('CUMUL')
        GROUP BY a.id_age, a.tranche_age
        ORDER BY volume DESC
    ";
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'tranche_age', 'n_nuitees', 3);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d6' => $comparison
    ];
}

/**
 * D7: Excursionnistes (Journée)
 */
function calculateBlocD7($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    // Total excursionnistes
    $totalN = getTotalExcursionnistes($db, $dateRanges, $zone);
    $totalN1 = getTotalExcursionnistes($db, $prevDateRanges, $zone);
    
    // Pic activité
    $picN = getPicExcursionnistesWithDate($db, $dateRanges, $zone);
    $picN1 = getPicExcursionnistesWithDate($db, $prevDateRanges, $zone);
    
    $rows = [
        [
            'rang' => 'Total',
            'n_exo' => $totalN,
            'n_exo_n1' => $totalN1,
            'delta_pct' => calculateDelta($totalN, $totalN1)
        ],
        [
            'rang' => 'Pic activité',
            'date' => $picN['date'],
            'n_exo' => $picN['volume'],
            'date_n1' => $picN1['date'],
            'n_exo_n1' => $picN1['volume'],
            'delta_pct' => calculateDelta($picN['volume'], $picN1['volume'])
        ]
    ];
    
    // Samedis (sauf pour Année et week-end de Pâques)
    $periodsToSkipSaturdays = ['Année', 'week-end de Pâques'];
    if (!in_array($periode, $periodsToSkipSaturdays)) {
        $samedisN = calculateSamediVolumesForD7($db, $dateRanges, $zone);
        $samedisN1 = calculateSamediVolumesForD7($db, $prevDateRanges, $zone);
        
        for ($i = 1; $i <= 3; $i++) {
            $rows[] = [
                'rang' => "{$i}e samedi",
                'date' => $samedisN["samedi{$i}"]['date'] ?? null,
                'n_exo' => $samedisN["samedi{$i}"]['volume'] ?? null,
                'n_exo_n1' => $samedisN1["samedi{$i}"]['volume'] ?? null,
                'delta_pct' => calculateDelta(
                    $samedisN["samedi{$i}"]['volume'] ?? null, 
                    $samedisN1["samedi{$i}"]['volume'] ?? null
                )
            ];
        }
    } else {
        // Ajouter lignes vides pour les samedis si période exclue
        for ($i = 1; $i <= 3; $i++) {
            $rows[] = [
                'rang' => "{$i}e samedi",
                'date' => null,
                'n_exo' => null,
                'n_exo_n1' => null,
                'delta_pct' => null
            ];
        }
    }
    
    // Dates spécifiques pour période "Année" (01/05, 14/07, 15/08)
    if ($periode === 'Année') {
        $datesSpecifiques = ['01/05', '14/07', '15/08'];
        
        foreach ($datesSpecifiques as $dateSpec) {
            $dateN = date('Y-m-d', strtotime("$dateSpec/$annee"));
            $dateN1 = date('Y-m-d', strtotime("$dateSpec/$prevYear"));
            
            $volumeN = getSamediVolume($db, $dateN, $zone);
            $volumeN1 = getSamediVolume($db, $dateN1, $zone);
            
            $rows[] = [
                'rang' => $dateSpec,
                'date' => $dateN,
                'n_exo' => $volumeN,
                'date_n1' => $dateN1,
                'n_exo_n1' => $volumeN1,
                'delta_pct' => calculateDelta($volumeN, $volumeN1)
            ];
        }
    }
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d7' => $rows
    ];
}

/**
 * Fonction générique de comparaison N vs N-1 (reproduit logique Python calculate_comparison)
 */
function calculateComparison($dataN, $dataN1, $groupCol, $volumeKey, $topN = 5) {
    $rows = [];
    
    // Convertir N-1 en array associatif pour lookup rapide
    $n1Lookup = [];
    foreach ($dataN1 as $row) {
        $key = $row[$groupCol];
        $n1Lookup[$key] = (int)$row['volume'];
    }
    
    // Calculer totaux pour pourcentages
    $totalN = array_sum(array_column($dataN, 'volume'));
    $totalN1 = array_sum(array_column($dataN1, 'volume'));
    
    // Top N éléments
    $count = 0;
    $topSum = 0;
    $topSumN1 = 0;
    
    foreach ($dataN as $row) {
        if ($count >= $topN) break;
        
        $item = $row[$groupCol];
        $volumeN = (int)$row['volume'];
        $volumeN1 = $n1Lookup[$item] ?? 0;
        
        $topSum += $volumeN;
        $topSumN1 += $volumeN1;
        
        $partN = $totalN > 0 ? round(($volumeN / $totalN) * 100, 1) : 0;
        $partN1 = $totalN1 > 0 ? round(($volumeN1 / $totalN1) * 100, 1) : 0;
        
        $rows[] = [
            'rang' => $count + 1,
            $groupCol => $item ?: 'Inconnu',
            $volumeKey => $volumeN,
            $volumeKey . '_n1' => $volumeN1,
            'delta_pct' => calculateDelta($volumeN, $volumeN1),
            'part_pct' => $partN,
            'part_pct_n1' => $partN1
        ];
        
        $count++;
    }
    
    // Compléter avec des lignes vides si nécessaire
    while (count($rows) < $topN) {
        $rows[] = [
            'rang' => count($rows) + 1,
            $groupCol => 'Données N/A',
            $volumeKey => 0,
            $volumeKey . '_n1' => 0,
            'delta_pct' => null,
            'part_pct' => 0,
            'part_pct_n1' => 0
        ];
    }
    
    // Ligne "Autre"
    $autresN = max(0, $totalN - $topSum);
    $autresN1 = max(0, $totalN1 - $topSumN1);
    
    $autresPartN = $totalN > 0 ? round(($autresN / $totalN) * 100, 1) : 0;
    $autresPartN1 = $totalN1 > 0 ? round(($autresN1 / $totalN1) * 100, 1) : 0;
    
    $rows[] = [
        'rang' => 'Autre',
        $groupCol => 'Autre',
        $volumeKey => $autresN,
        $volumeKey . '_n1' => $autresN1,
        'delta_pct' => calculateDelta($autresN, $autresN1),
        'part_pct' => $autresPartN,
        'part_pct_n1' => $autresPartN1
    ];
    
    return $rows;
}

/**
 * Calcul du delta en % (reproduit logique Python calc_delta)
 */
function calculateDelta($n, $n1) {
    if ($n === null || $n1 === null) return null;
    
    $n = (float)$n;
    $n1 = (float)$n1;
    
    if ($n1 == 0) {
        return ($n == 0) ? 0.0 : null;
    }
    
    return round((($n - $n1) / $n1) * 100, 1);
}

/**
 * Helpers pour D7
 */
function getTotalExcursionnistes($db, $dateRanges, $zone) {
    $sql = "
        SELECT COALESCE(SUM(f.volume), 0) as total
        FROM fact_diurnes f
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'EXCURSIONNISTE'
        AND p.nom_provenance != 'LOCAL'
    ";
    
    $result = $db->queryOne($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    return (int)($result['total'] ?? 0);
}

function getPicExcursionnistesWithDate($db, $dateRanges, $zone) {
    $sql = "
        SELECT f.date, SUM(f.volume) as volume_jour
        FROM fact_diurnes f
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'EXCURSIONNISTE'
        AND p.nom_provenance != 'LOCAL'
        GROUP BY f.date
        ORDER BY volume_jour DESC
        LIMIT 1
    ";
    
    $result = $db->queryOne($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    
    return [
        'date' => $result['date'] ?? null,
        'volume' => (int)($result['volume_jour'] ?? 0)
    ];
}

function calculateSamediVolumesForD7($db, $dateRanges, $zone) {
    $startDate = new DateTime($dateRanges['start']);
    $endDate = new DateTime($dateRanges['end']);
    
    $weekdayStart = (int)$startDate->format('N');
    $daysToSaturday = (6 - $weekdayStart + 7) % 7;
    
    $firstSaturday = clone $startDate;
    $firstSaturday->add(new DateInterval("P{$daysToSaturday}D"));
    
    $result = [];
    
    for ($i = 1; $i <= 3; $i++) {
        $samediDate = clone $firstSaturday;
        $samediDate->add(new DateInterval('P' . (($i - 1) * 7) . 'D'));
        
        if ($samediDate <= $endDate) {
            $dateStr = $samediDate->format('Y-m-d');
            $volume = getSamediVolume($db, $dateStr, $zone);
            
            $result["samedi{$i}"] = [
                'date' => $dateStr,
                'volume' => $volume
            ];
        } else {
            $result["samedi{$i}"] = [
                'date' => null,
                'volume' => null
            ];
        }
    }
    
    return $result;
}

function getSamediVolume($db, $date, $zone) {
    $sql = "
        SELECT COALESCE(SUM(f.volume), 0) as volume
        FROM fact_diurnes f
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date = ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'EXCURSIONNISTE'
        AND p.nom_provenance != 'LOCAL'
    ";
    
    $result = $db->queryOne($sql, [$date, $zone]);
    return (int)($result['volume'] ?? 0);
}

function getRegionColumn($db) {
    $columns = $db->query("SHOW COLUMNS FROM dim_departements LIKE '%region%'");
    
    foreach ($columns as $col) {
        if (strpos(strtolower($col['Field']), 'nouvelle') !== false) {
            return $col['Field'];
        }
    }
    
    return $columns[0]['Field'] ?? 'nom_region';
}

// === FONCTIONS POUR LES EXCURSIONNISTES ===

/**
 * D1: Départements (Excursionnistes)
 */
function calculateBlocD1Exc($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    $sql = "
        SELECT d.nom_departement, SUM(f.volume) as volume
        FROM fact_diurnes_departements f
        INNER JOIN dim_departements d ON f.id_departement = d.id_departement
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'EXCURSIONNISTE'
        AND p.nom_provenance = 'NONLOCAL'
        AND d.nom_departement NOT IN ('CUMUL')
        GROUP BY d.id_departement, d.nom_departement
        ORDER BY volume DESC
    ";
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'nom_departement', 'n_nuitees', 15);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d1' => $comparison
    ];
}

/**
 * D2: Régions (Excursionnistes)
 */
function calculateBlocD2Exc($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    // Vérifier si la table fact_diurnes_regions existe
    $tablesResult = $db->query("SHOW TABLES LIKE 'fact_diurnes_regions'");
    $useRegionsTable = !empty($tablesResult);
    
    if ($useRegionsTable) {
        // Utiliser la table dédiée fact_diurnes_regions
        $regionCol = getRegionColumn($db);
        
        $sql = "
            SELECT r.$regionCol as nom_region, SUM(f.volume) as volume
            FROM fact_diurnes_regions f
            INNER JOIN dim_regions r ON f.id_region = r.id_region
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            WHERE f.date BETWEEN ? AND ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'EXCURSIONNISTE'
            AND p.nom_provenance = 'NONLOCAL'
            AND r.$regionCol NOT IN ('CUMUL')
            GROUP BY r.$regionCol
            ORDER BY volume DESC
        ";
    } else {
        // Fallback vers l'ancienne méthode avec fact_diurnes_departements
        $regionCol = getRegionColumn($db);
        
        $sql = "
            SELECT d.$regionCol as nom_region, SUM(f.volume) as volume
            FROM fact_diurnes_departements f
            INNER JOIN dim_departements d ON f.id_departement = d.id_departement
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            WHERE f.date BETWEEN ? AND ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'EXCURSIONNISTE'
            AND p.nom_provenance = 'NONLOCAL'
            AND d.$regionCol NOT IN ('CUMUL')
            GROUP BY d.$regionCol
            ORDER BY volume DESC
        ";
    }
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'nom_region', 'n_nuitees', 5);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d2' => $comparison
    ];
}

/**
 * D3: Pays (Excursionnistes)
 */
function calculateBlocD3Exc($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    $sql = "
        SELECT p.nom_pays, SUM(f.volume) as volume
        FROM fact_diurnes_pays f
        INNER JOIN dim_pays p ON f.id_pays = p.id_pays
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances prov ON f.id_provenance = prov.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'EXCURSIONNISTE'
        AND prov.nom_provenance = 'ETRANGER'
        AND p.nom_pays NOT IN ('CUMUL')
        GROUP BY p.id_pays, p.nom_pays
        ORDER BY volume DESC
    ";
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'nom_pays', 'n_nuitees', 5);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d3' => $comparison
    ];
}

/**
 * D5: CSP (Excursionnistes)
 */
function calculateBlocD5Exc($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    // Mapping CSP exactement comme dans le Python
    $cspMapping = [
        'CSP +' => ['PAVILLONNAIRE FAMILIAL AISE', 'URBAIN FAMILIAL AISE', 'URBAIN DYNAMIQUE', 'RURAL DYNAMIQUE'],
        'CSP en croissance' => ['PERIURBAIN EN CROISSANCE', 'RESIDENCE SECONDAIRE', 'URBAIN CLASSE MOYENNE'],
        'Populaire' => ['POPULAIRE', 'RURAL TRADITIONNEL', 'RURAL OUVRIER', 'URBAIN DEFAVORISE']
    ];
    
    $dataNCsp = getCSPDataExc($db, $dateRanges, $zone, $cspMapping);
    $dataN1Csp = getCSPDataExc($db, $prevDateRanges, $zone, $cspMapping);
    
    $comparison = calculateComparison($dataNCsp, $dataN1Csp, 'csp', 'n_nuitees', 3);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d5' => $comparison
    ];
}

/**
 * Récupère les données CSP avec mapping pour excursionnistes
 */
function getCSPDataExc($db, $dateRanges, $zone, $cspMapping) {
    $allSegments = [];
    foreach ($cspMapping as $segments) {
        $allSegments = array_merge($allSegments, $segments);
    }
    
    $segmentsStr = "'" . implode("', '", $allSegments) . "'";
    
    $sql = "
        SELECT g.nom_segment, SUM(f.volume) as volume
        FROM fact_diurnes_geolife f
        INNER JOIN dim_segments_geolife g ON f.id_geolife = g.id_geolife
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND c.nom_categorie = 'EXCURSIONNISTE'
        AND p.nom_provenance = 'NONLOCAL'
        AND UPPER(g.nom_segment) IN ($segmentsStr)
        AND g.nom_segment NOT IN ('CUMUL', 'NR', 'NON RENSEIGNE')
        GROUP BY g.nom_segment
    ";
    
    $rawData = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    
    // Convertir en CSP
    $cspData = [];
    foreach ($rawData as $row) {
        $segment = strtoupper($row['nom_segment']);
        $csp = null;
        
        foreach ($cspMapping as $cspName => $segments) {
            if (in_array($segment, $segments)) {
                $csp = $cspName;
                break;
            }
        }
        
        if ($csp) {
            if (!isset($cspData[$csp])) {
                $cspData[$csp] = 0;
            }
            $cspData[$csp] += (int)$row['volume'];
        }
    }
    
    // Convertir en format compatible
    $result = [];
    foreach ($cspData as $csp => $volume) {
        $result[] = ['csp' => $csp, 'volume' => $volume];
    }
    
    return $result;
}

/**
 * D6: Age (Excursionnistes)
 */
function calculateBlocD6Exc($db, $dateRanges, $prevDateRanges, $zone, $annee, $prevYear, $periode) {
    $sql = "
        SELECT a.tranche_age, SUM(f.volume) as volume
        FROM fact_diurnes_age f
        INNER JOIN dim_tranches_age a ON f.id_age = a.id_age
        INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        WHERE f.date BETWEEN ? AND ?
        AND z.nom_zone = ?
        AND p.nom_provenance = 'NONLOCAL'
        AND a.tranche_age NOT IN ('CUMUL')
        GROUP BY a.id_age, a.tranche_age
        ORDER BY volume DESC
    ";
    
    $dataN = $db->query($sql, [$dateRanges['start'], $dateRanges['end'], $zone]);
    $dataN1 = $db->query($sql, [$prevDateRanges['start'], $prevDateRanges['end'], $zone]);
    
    $comparison = calculateComparison($dataN, $dataN1, 'tranche_age', 'n_nuitees', 3);
    
    return [
        'zone_observation' => $zone,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'debut_n1' => $prevDateRanges['start'],
        'fin_n1' => $prevDateRanges['end'],
        'bloc_d6' => $comparison
    ];
}
