<?php
/**\n * API Bloc D2 - Top 5 Régions (Nuitées) - Version avec support des tables temporaires\n * Bascule automatiquement vers les tables temporaires si les données ne sont pas dans les tables principales\n * Basée sur la logique de bloc_a_working.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Récupération directe des paramètres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;
$limit = intval($_GET['limit'] ?? 5);

if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

// Inclure le gestionnaire des périodes basé sur la base de données
require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../classes/ZoneMapper.php';

/**
 * Calcule les plages de dates selon la période - VERSION BASE DE DONNÉES
 * Utilise la table dim_periodes au lieu du fichier JSON
 */
function calculateWorkingDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    // Utiliser notre système de configuration de base de données
    require_once dirname(__DIR__) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Tables temporaires supprimées - utilisation directe des tables principales
    
    // Normalisation des paramètres
    $zoneMapped = ZoneMapper::displayToBase($zone);
    
    // Calcul des plages de dates
    $dateRanges = calculateWorkingDateRanges($annee, $periode);
    $prevYear = (int)$annee - 1;
    $prevDateRanges = calculateWorkingDateRanges($prevYear, $periode);

    if (!empty($debut) && !empty($fin)) {
        try {
            $ds = new DateTime($debut);
            $de = new DateTime($fin);
            if ($de >= $ds) {
                $dateRanges['start'] = $ds->format('Y-m-d') . ' 00:00:00';
                $dateRanges['end'] = $de->format('Y-m-d') . ' 23:59:59';
                $prevDateRanges['start'] = $ds->modify('-1 year')->format('Y-m-d') . ' 00:00:00';
                $prevDateRanges['end'] = $de->modify('-1 year')->format('Y-m-d') . ' 23:59:59';
            }
        } catch (Exception $e) {}
    }
    
    // ✅ REQUÊTE OPTIMISÉE DIRECTE - Top 5 Régions (Nuitées)
    // Utilise fact_nuitees_departements car fact_nuitees_regions est vide
    
    // Requête année courante avec agrégation par région depuis les départements
    $sqlN = "
        SELECT
            d.nom_nouvelle_region AS region,
            SUM(fd.volume) AS total_nuitees
        FROM
            fact_nuitees_departements AS fd
        JOIN
            dim_departements AS d ON fd.id_departement = d.id_departement
        JOIN
            dim_dates AS dt ON fd.date = dt.date
        JOIN
            dim_zones_observation AS z ON fd.id_zone = z.id_zone
        JOIN
            dim_categories_visiteur AS c ON fd.id_categorie = c.id_categorie
        JOIN
            dim_provenances AS p ON fd.id_provenance = p.id_provenance
        WHERE
            dt.date BETWEEN ? AND ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'TOURISTE'
            AND p.nom_provenance = 'NONLOCAL'
            AND d.nom_nouvelle_region IS NOT NULL
            AND d.nom_nouvelle_region NOT IN ('CUMUL', 'Cumul', '')
        GROUP BY
            d.nom_nouvelle_region
        ORDER BY
            total_nuitees DESC
        LIMIT " . intval($limit) . "
    ";
     
     $stmtN = $pdo->prepare($sqlN);
     $stmtN->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $dataRawN = $stmtN->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dataRawN)) {
        $results = [['nom_region' => 'Aucune donnée', 'n_nuitees' => 0, 'n_nuitees_n1' => 0, 'delta_pct' => null, 'part_pct' => 0]];
        header('Content-Type: application/json');
        echo json_encode($results, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Requête année précédente avec même structure (départements -> régions)
    $sqlN1 = "
        SELECT
            d.nom_nouvelle_region AS region,
            SUM(fd.volume) AS total_nuitees
        FROM
            fact_nuitees_departements AS fd
        JOIN
            dim_departements AS d ON fd.id_departement = d.id_departement
        JOIN
            dim_dates AS dt ON fd.date = dt.date
        JOIN
            dim_zones_observation AS z ON fd.id_zone = z.id_zone
        JOIN
            dim_categories_visiteur AS c ON fd.id_categorie = c.id_categorie
        JOIN
            dim_provenances AS p ON fd.id_provenance = p.id_provenance
        WHERE
            dt.date BETWEEN ? AND ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'TOURISTE'
            AND p.nom_provenance = 'NONLOCAL'
            AND d.nom_nouvelle_region IS NOT NULL
            AND d.nom_nouvelle_region NOT IN ('CUMUL', 'Cumul', '')
        GROUP BY
            d.nom_nouvelle_region
        ORDER BY
            total_nuitees DESC
    ";
    
    $stmtN1 = $pdo->prepare($sqlN1);
    $stmtN1->execute([$prevDateRanges['start'], $prevDateRanges['end'], $zoneMapped]);
    $dataN1Raw = $stmtN1->fetchAll(PDO::FETCH_ASSOC);
    
    // Indexer les données N-1 par région
    $dataN1 = [];
    foreach ($dataN1Raw as $row) {
        $dataN1[$row['region']] = (int)$row['total_nuitees'];
    }
    
    // Calculer le total pour les pourcentages
    $totalNuitees = array_sum(array_column($dataRawN, 'total_nuitees'));
    
    // Formater les résultats
    $results = [];
    foreach ($dataRawN as $row) {
        $regionName = $row['region'];
        $nuitees = (int)$row['total_nuitees'];
        $nuiteesN1 = $dataN1[$regionName] ?? 0;
        $partPct = $totalNuitees > 0 ? round(($nuitees / $totalNuitees) * 100, 1) : 0;
        
        $deltaPct = null;
        if ($nuiteesN1 > 0) {
            $deltaPct = round((($nuitees - $nuiteesN1) / $nuiteesN1) * 100, 1);
        } elseif ($nuitees > 0) {
            $deltaPct = 100;
        }
        
        $results[] = [
            'nom_region' => $regionName,
            'n_nuitees' => $nuitees,
            'n_nuitees_n1' => $nuiteesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
    }
    
    // Retourner les résultats dans le même format que bloc_a_working.php
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API Regions',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 