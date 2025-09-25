<?php
// API Infographie: Distribution de la durée de séjour (FR vs INTL) 100% empilée

header('Content-Type: application/json');

require_once __DIR__ . '/periodes_manager_db.php';

// Inclure le système de correction des données
require_once __DIR__ . '/correction_helper.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';
require_once dirname(dirname(__DIR__)) . '/database.php';

$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debutOverride = $_GET['debut'] ?? null;
$finOverride = $_GET['fin'] ?? null;

if (!$annee || !$periode || !$zone) {
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

try {
    $pdo = DatabaseConfig::getConnection();

    $zoneMapped = ZoneMapper::displayToBase($zone);
    if ($debutOverride && $finOverride) {
        $start = new DateTime($debutOverride . ' 00:00:00');
        $end = new DateTime($finOverride . ' 23:59:59');
        $dateRanges = [ 'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s') ];
        $s1 = (clone $start)->modify('-1 year');
        $e1 = (clone $end)->modify('-1 year');
        $dateRangesN1 = [ 'start' => $s1->format('Y-m-d H:i:s'), 'end' => $e1->format('Y-m-d H:i:s') ];
    } else {
        $dateRanges = PeriodesManagerDB::calculateDateRanges($annee, $periode);
        $dateRangesN1 = PeriodesManagerDB::calculateDateRanges($annee - 1, $periode);
    }

    $sqlDistBase = "
        SELECT d.libelle AS duree, d.nb_nuits AS nb_nuits, SUM(f.volume) AS volume
        FROM fact_sejours_duree f
        JOIN dim_durees_sejour d ON d.id_duree = f.id_duree
        JOIN dim_zones_observation z ON z.id_zone = f.id_zone
        JOIN dim_categories_visiteur c ON c.id_categorie = f.id_categorie
        JOIN dim_provenances pr ON pr.id_provenance = f.id_provenance
        WHERE f.date BETWEEN ? AND ?
          AND z.nom_zone = ?
          AND c.nom_categorie = 'TOURISTE'
          AND %PROV_COND%
        GROUP BY d.id_duree, d.libelle, d.nb_nuits
        ORDER BY d.nb_nuits
    ";

    $sqlDistFR   = str_replace('%PROV_COND%', "pr.nom_provenance = 'NONLOCAL'", $sqlDistBase);
    $sqlDistINTL = str_replace('%PROV_COND%', "pr.nom_provenance = 'ETRANGER'", $sqlDistBase);

    $stmt = $pdo->prepare($sqlDistFR);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $distFR_N = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMapped]);
    $distFR_N1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare($sqlDistINTL);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $distINTL_N = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMapped]);
    $distINTL_N1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $computeDist = function(array $distN, array $distN1) {
        $mapN1 = [];
        foreach ($distN1 as $row) { $mapN1[$row['duree']] = (int)$row['volume']; }
        $totalN = array_sum(array_map(fn($r) => (int)$r['volume'], $distN));
        $out = [];
        foreach ($distN as $row) {
            $vol = (int)$row['volume'];
            $volN1 = (int)($mapN1[$row['duree']] ?? 0);
            $part = $totalN > 0 ? round(($vol / $totalN) * 100, 1) : null;
            $delta = $volN1 > 0 ? round((($vol - $volN1) / $volN1) * 100, 1) : null;
            $out[] = [
                'duree' => $row['duree'],
                'nb_nuits' => (int)$row['nb_nuits'],
                'volume' => $vol,
                'volume_n1' => $volN1,
                'part_pct' => $part,
                'delta_pct' => $delta
            ];
        }
        return $out;
    };

    $stayDistributionFR   = $computeDist($distFR_N, $distFR_N1);
    $stayDistributionINTL = $computeDist($distINTL_N, $distINTL_N1);

    echo json_encode([
        'annee' => (int)$annee,
        'periode' => $periode,
        'zone' => $zoneMapped,
        'stay_distribution_fr' => $stayDistributionFR,
        'stay_distribution_intl' => $stayDistributionINTL
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}


