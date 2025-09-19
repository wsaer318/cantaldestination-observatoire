<?php
/**\n * API Bloc A - Version Fonctionnelle - Version avec support des tables temporaires\n * Bascule automatiquement vers les tables temporaires si les données ne sont pas dans les tables principales\n * Copie exacte de la logique qui marchait dans bloc_a_fixed.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure le gestionnaire intelligent des périodes
require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../classes/ZoneMapper.php';

// Récupération directe des paramètres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debut = $_GET['debut'] ?? null;
$fin = $_GET['fin'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

// Log de debug
if ($debug) {
    error_log("API Debug - Paramètres reçus: annee=$annee, periode=$periode, zone=$zone, debut=$debut, fin=$fin");
}

if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

/**
 * Calcule les plages de dates selon la période - VERSION INTELLIGENTE
 * Utilise les vraies dates depuis la base de données
 */
function calculateWorkingDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

/**
 * Résout de manière robuste le code_periode exact pour une année donnée
 */
function resolvePeriodCode(PDO $pdo, int $annee, string $periodeInput): ?string {
    // 1) Essai direct par code_periode exact
    $stmt = $pdo->prepare("SELECT code_periode FROM dim_periodes WHERE code_periode = ? AND annee = ? LIMIT 1");
    $stmt->execute([$periodeInput, $annee]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['code_periode'])) {
        return $row['code_periode'];
    }

    // 2) Essai par nom_periode
    $stmt = $pdo->prepare("SELECT code_periode FROM dim_periodes WHERE LOWER(nom_periode) = LOWER(?) AND annee = ? LIMIT 1");
    $stmt->execute([$periodeInput, $annee]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['code_periode'])) {
        return $row['code_periode'];
    }

    // 3) Essai flexible en normalisant underscores/espaces/accents basiques
    $normalized = strtolower(str_replace(['_', '-'], ' ', trim($periodeInput)));
    $stmt = $pdo->prepare("SELECT code_periode FROM dim_periodes 
                           WHERE annee = ? AND (
                               LOWER(REPLACE(REPLACE(code_periode,'_',' '),'-',' ')) = ? 
                            OR LOWER(REPLACE(REPLACE(nom_periode,'_',' '),'-',' ')) = ?
                           ) LIMIT 1");
    $stmt->execute([$annee, $normalized, $normalized]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['code_periode'])) {
        return $row['code_periode'];
    }

    return null; // L'appelant gérera le fallback année complète
}

try {
    // Utiliser notre système de configuration de base de données
    require_once dirname(__DIR__) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Tables temporaires supprimées - utilisation directe des tables principales
    
    // ✅ OPTIMISATIONS : Créer des index critiques pour la performance
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_nuitees_dept_perf ON fact_nuitees_departements (id_zone, id_categorie, id_provenance, date)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_nuitees_pays_perf ON fact_nuitees_pays (id_zone, id_categorie, id_provenance, date)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_diurnes_dept_perf ON fact_diurnes_departements (id_zone, id_categorie, id_provenance, date)");
    } catch (Exception $e) {
        // Ignorer les erreurs d'index (peuvent déjà exister)
    }
    
    // Normalisation des paramètres avec mapping direct des zones
    $zoneMapped = ZoneMapper::displayToBase($zone);
    // Année de comparaison facultative (impacte uniquement l'affichage et le calcul N-1 si fournie)
    $compareYear = isset($_GET['compare_annee']) ? (int)$_GET['compare_annee'] : null;

    // Mapping pour N-1 (même zone, logique simplifiée)
    $n1Year = $compareYear ?: ($annee - 1);
    $zoneMappedN1 = ZoneMapper::displayToBase($zone);
    
    // Calcul des plages de dates avec résolution du code exact
    $resolvedCode = resolvePeriodCode($pdo, (int)$annee, (string)$periode) ?? $periode;
    $dateRanges = calculateWorkingDateRanges($annee, $resolvedCode);
    
    // Log de debug
    if ($debug) {
        error_log("API Debug - resolvedCode: $resolvedCode, dateRanges: " . json_encode($dateRanges));
    }
    
    // Construction des indicateurs Bloc A
    $indicators = [];
    
    // Calcul des plages N-1 pour comparaison
    // Si compare_annee est fourni, on calcule par rapport à cette année
    $n1Year = $compareYear ?: ($annee - 1);
    $dateRangesN1 = calculateWorkingDateRanges($n1Year, $resolvedCode);

    // Priorité aux bornes personnalisées si fournies (debut/fin)
    if (!empty($debut) && !empty($fin)) {
        try {
            $dStart = new DateTime($debut);
            $dEnd = new DateTime($fin);
            // Normaliser aux bornes journalières
            $startStr = $dStart->format('Y-m-d') . ' 00:00:00';
            $endStr = $dEnd->format('Y-m-d') . ' 23:59:59';
            if ($dEnd >= $dStart) {
                $dateRanges['start'] = $startStr;
                $dateRanges['end'] = $endStr;
                // Générer N-1 en décalant d'un an
            $dStartN1 = $compareYear ? (clone $dStart)->setDate($compareYear, (int)$dStart->format('m'), (int)$dStart->format('d')) : (clone $dStart)->modify('-1 year');
            $dEndN1 = $compareYear ? (clone $dEnd)->setDate($compareYear, (int)$dEnd->format('m'), (int)$dEnd->format('d')) : (clone $dEnd)->modify('-1 year');
                $dateRangesN1['start'] = $dStartN1->format('Y-m-d') . ' 00:00:00';
                $dateRangesN1['end'] = $dEndN1->format('Y-m-d') . ' 23:59:59';
                if ($debug) {
                    error_log("API Debug - Override par debut/fin: start={$dateRanges['start']}, end={$dateRanges['end']}");
                }
            }
        } catch (Exception $e) {
            // Si dates invalides, ignorer et garder le calcul basé sur la période
            if ($debug) {
                error_log('API Debug - Dates debut/fin invalides, fallback période: ' . $e->getMessage());
            }
        }
    }
    
    // 1. Nuitées totales (FR + INTL) - Touristes NonLocaux + Etrangers
    $sql1 = "
        SELECT COALESCE(SUM(fact_nuitees.volume), 0) as total
        FROM fact_nuitees
        INNER JOIN dim_zones_observation ON fact_nuitees.id_zone = dim_zones_observation.id_zone
        INNER JOIN dim_provenances ON fact_nuitees.id_provenance = dim_provenances.id_provenance
        INNER JOIN dim_categories_visiteur ON fact_nuitees.id_categorie = dim_categories_visiteur.id_categorie
        WHERE fact_nuitees.date BETWEEN ? AND ?
        AND dim_zones_observation.nom_zone = ?
        AND dim_categories_visiteur.nom_categorie = 'TOURISTE'
        AND dim_provenances.nom_provenance IN ('NONLOCAL', 'ETRANGER')
    ";
    
    $stmt = $pdo->prepare($sql1);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $totalNuitees = (int)($stmt->fetch()['total'] ?? 0);

    // Calcul N-1
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
    $totalNuiteesN1 = (int)($stmt->fetch()['total'] ?? 0);
    
    // Calcul évolution
    $evolutionPct1 = $totalNuiteesN1 > 0 ? round((($totalNuitees - $totalNuiteesN1) / $totalNuiteesN1) * 100, 1) : null;
    
    $indicators[] = [
        'numero' => 1,
        'indicateur' => '1. Nuitées totales (FR + INTL)',
        'N' => $totalNuitees,
        'N_1' => $totalNuiteesN1,
        'evolution_pct' => $evolutionPct1,
        'unite' => 'Nuitées',
        'remarque' => 'Touristes NonLocaux + Etrangers'
    ];
    
    // 2. Nuitées françaises - Touristes NonLocaux
    $sql2 = "
        SELECT COALESCE(SUM(fact_nuitees.volume), 0) as total
        FROM fact_nuitees
        INNER JOIN dim_zones_observation ON fact_nuitees.id_zone = dim_zones_observation.id_zone
        INNER JOIN dim_provenances ON fact_nuitees.id_provenance = dim_provenances.id_provenance
        INNER JOIN dim_categories_visiteur ON fact_nuitees.id_categorie = dim_categories_visiteur.id_categorie
        WHERE fact_nuitees.date BETWEEN ? AND ?
        AND dim_zones_observation.nom_zone = ?
        AND dim_categories_visiteur.nom_categorie = 'TOURISTE'
        AND dim_provenances.nom_provenance = 'NONLOCAL'
    ";
    
    $stmt = $pdo->prepare($sql2);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $nuiteesF2 = (int)($stmt->fetch()['total'] ?? 0);

    // Calcul N-1
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
    $nuiteesF2N1 = (int)($stmt->fetch()['total'] ?? 0);
    
    // Calcul évolution
    $evolutionPct2 = $nuiteesF2N1 > 0 ? round((($nuiteesF2 - $nuiteesF2N1) / $nuiteesF2N1) * 100, 1) : null;
    
    $indicators[] = [
        'numero' => 2,
        'indicateur' => '2. Nuitées françaises',
        'N' => $nuiteesF2,
        'N_1' => $nuiteesF2N1,
        'evolution_pct' => $evolutionPct2,
        'unite' => 'Nuitées',
        'remarque' => 'Touristes NonLocaux'
    ];
    
    // 3. Nuitées internationales - Touristes Etrangers
    $sql3 = "
        SELECT COALESCE(SUM(fact_nuitees.volume), 0) as total
        FROM fact_nuitees
        INNER JOIN dim_zones_observation ON fact_nuitees.id_zone = dim_zones_observation.id_zone
        INNER JOIN dim_provenances ON fact_nuitees.id_provenance = dim_provenances.id_provenance
        INNER JOIN dim_categories_visiteur ON fact_nuitees.id_categorie = dim_categories_visiteur.id_categorie
        WHERE fact_nuitees.date BETWEEN ? AND ?
        AND dim_zones_observation.nom_zone = ?
        AND dim_categories_visiteur.nom_categorie = 'TOURISTE'
        AND dim_provenances.nom_provenance = 'ETRANGER'
    ";
    
    $stmt = $pdo->prepare($sql3);
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $nuiteesIntl = (int)($stmt->fetch()['total'] ?? 0);

    // Calcul N-1
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
    $nuiteesIntlN1 = (int)($stmt->fetch()['total'] ?? 0);
    
    // Calcul évolution
    $evolutionPct3 = $nuiteesIntlN1 > 0 ? round((($nuiteesIntl - $nuiteesIntlN1) / $nuiteesIntlN1) * 100, 1) : null;
    
    $indicators[] = [
        'numero' => 3,
        'indicateur' => '3. Nuitées internationales',
        'N' => $nuiteesIntl,
        'N_1' => $nuiteesIntlN1,
        'evolution_pct' => $evolutionPct3,
        'unite' => 'Nuitées',
        'remarque' => 'Touristes Etrangers'
    ];
    
    // 4. Top 15 Départements (VERSION ULTRA-OPTIMISÉE)
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('fact_nuitees_departements', $tables)) {
        // ✅ OPTIMISATION 1: Pré-résoudre les IDs pour éviter les JOINs répétés - SÉCURISÉ
        $stmt = $pdo->prepare("SET @zone_id = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ? LIMIT 1)");
        $stmt->execute([$zoneMapped]);

        // Définir aussi @zone_id_n1 pour les requêtes N-1
        $stmt = $pdo->prepare("SET @zone_id_n1 = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ? LIMIT 1)");
        $stmt->execute([$zoneMappedN1]);
        
        $stmt = $pdo->prepare("SET @cat_id = (SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = ? LIMIT 1)");
        $stmt->execute(['TOURISTE']);
        
        $stmt = $pdo->prepare("SET @prov_id = (SELECT id_provenance FROM dim_provenances WHERE nom_provenance = ? LIMIT 1)");
        $stmt->execute(['NONLOCAL']);
        
        // ✅ OPTIMISATION 2: Requête directe ultra-rapide avec variables pré-calculées
        $sql4 = "
            SELECT SUM(sub.volume_dept) as total_top15
            FROM (
                SELECT SUM(f.volume) as volume_dept
                FROM fact_nuitees_departements f
                INNER JOIN dim_departements d ON f.id_departement = d.id_departement
                WHERE f.date BETWEEN ? AND ?
                AND f.id_zone = @zone_id
                AND f.id_categorie = @cat_id
                AND f.id_provenance = @prov_id
                AND d.nom_departement NOT IN ('CUMUL', '')
                AND f.volume > 0
                GROUP BY f.id_departement
                ORDER BY volume_dept DESC
                LIMIT 15
            ) sub
        ";
        
        $stmt = $pdo->prepare($sql4);
        $stmt->execute([$dateRanges['start'], $dateRanges['end']]);
        $top15DeptSum = (int)($stmt->fetch()['total_top15'] ?? 0);

        // Calcul N-1 (redéfinir @zone_id pour N-1)
        $pdo->exec("SET @zone_id = @zone_id_n1");
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
        $top15DeptSumN1 = (int)($stmt->fetch()['total_top15'] ?? 0);

        // Remettre @zone_id à sa valeur originale
        $pdo->exec("SET @zone_id = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = '$zoneMapped' LIMIT 1)");
        
        $evolutionPct4 = $top15DeptSumN1 > 0 ? round((($top15DeptSum - $top15DeptSumN1) / $top15DeptSumN1) * 100, 1) : null;
    } else {
        $top15DeptSum = 0;
        $top15DeptSumN1 = 0;
        $evolutionPct4 = null;
    }
    
    $indicators[] = [
        'numero' => 4,
        'indicateur' => '4. Origine par départements (Top 15)',
        'N' => $top15DeptSum,
        'N_1' => $top15DeptSumN1,
        'evolution_pct' => $evolutionPct4,
        'unite' => 'Nuitées',
        'remarque' => 'Somme nuitées Top 15 Départements (FR)'
    ];
    
    // 5. Top 5 Pays (VERSION ULTRA-OPTIMISÉE)
    if (in_array('fact_nuitees_pays', $tables)) {
        // ✅ Réutiliser les variables déjà SET + ajouter provenance étrangère
        $pdo->exec("SET @prov_etr_id = (SELECT id_provenance FROM dim_provenances WHERE nom_provenance = 'ETRANGER' LIMIT 1)");
        
        $sql5 = "
            SELECT SUM(sub.volume_pays) as total_top5
            FROM (
                SELECT SUM(f.volume) as volume_pays
                FROM fact_nuitees_pays f
                INNER JOIN dim_pays p ON f.id_pays = p.id_pays
                WHERE f.date BETWEEN ? AND ?
                AND f.id_zone = @zone_id
                AND f.id_categorie = @cat_id
                AND f.id_provenance = @prov_etr_id
                AND p.nom_pays NOT IN ('CUMUL', '')
                AND f.volume > 0
                GROUP BY f.id_pays
                ORDER BY volume_pays DESC
                LIMIT 5
            ) sub
        ";
        
        $stmt = $pdo->prepare($sql5);
        $stmt->execute([$dateRanges['start'], $dateRanges['end']]);
        $top5PaysSum = (int)($stmt->fetch()['total_top5'] ?? 0);

        // Calcul N-1 (redéfinir @zone_id pour N-1)
        $pdo->exec("SET @zone_id = @zone_id_n1");
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
        $top5PaysSumN1 = (int)($stmt->fetch()['total_top5'] ?? 0);

        // Remettre @zone_id à sa valeur originale
        $pdo->exec("SET @zone_id = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = '$zoneMapped' LIMIT 1)");
        
        $evolutionPct5 = $top5PaysSumN1 > 0 ? round((($top5PaysSum - $top5PaysSumN1) / $top5PaysSumN1) * 100, 1) : null;
    } else {
        $top5PaysSum = 0;
        $top5PaysSumN1 = 0;
        $evolutionPct5 = null;
    }
    
    $indicators[] = [
        'numero' => 5,
        'indicateur' => '5. Origine par pays (Top 5)',
        'N' => $top5PaysSum,
        'N_1' => $top5PaysSumN1,
        'evolution_pct' => $evolutionPct5,
        'unite' => 'Nuitées',
        'remarque' => 'Somme nuitées Top 5 Pays (INTL)'
    ];

    // 21-23. Durée moyenne de séjour (Total ≠ Local, Français, International)
    $avgAll = null; $avgFR = null; $avgINTL = null;
    $avgAllN1 = null; $avgFRN1 = null; $avgINTLN1 = null;
    $stayDistribution = [];
    try {
        // Durée moyenne – Tous ≠ LOCAL
        $sqlAvgAll = "
            SELECT ROUND(SUM(d.nb_nuits * f.volume) / NULLIF(SUM(f.volume), 0), 2) AS avg_val
            FROM fact_sejours_duree f
            JOIN dim_durees_sejour d ON d.id_duree = f.id_duree
            JOIN dim_zones_observation z ON z.id_zone = f.id_zone
            JOIN dim_categories_visiteur c ON c.id_categorie = f.id_categorie
            JOIN dim_provenances pr ON pr.id_provenance = f.id_provenance
            WHERE f.date BETWEEN ? AND ?
              AND z.nom_zone = ?
              AND c.nom_categorie = 'TOURISTE'
              AND pr.nom_provenance <> 'LOCAL'
        ";
        $stmt = $pdo->prepare($sqlAvgAll);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $avgAll = (float)($stmt->fetch()['avg_val'] ?? 0);

        // Durée moyenne – Français (NONLOCAL)
        $sqlAvgFR = str_replace("pr.nom_provenance <> 'LOCAL'", "pr.nom_provenance = 'NONLOCAL'", $sqlAvgAll);
        $stmt = $pdo->prepare($sqlAvgFR);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $avgFR = (float)($stmt->fetch()['avg_val'] ?? 0);

        // Durée moyenne – International (ETRANGER)
        $sqlAvgINTL = str_replace("pr.nom_provenance <> 'LOCAL'", "pr.nom_provenance = 'ETRANGER'", $sqlAvgAll);
        $stmt = $pdo->prepare($sqlAvgINTL);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $avgINTL = (float)($stmt->fetch()['avg_val'] ?? 0);

        // N-1 Durées moyennes (mêmes requêtes, dates N-1)
        $stmt = $pdo->prepare($sqlAvgAll);
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $avgAllN1 = (float)($stmt->fetch()['avg_val'] ?? 0);

        $stmt = $pdo->prepare($sqlAvgFR);
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $avgFRN1 = (float)($stmt->fetch()['avg_val'] ?? 0);

        $stmt = $pdo->prepare($sqlAvgINTL);
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $avgINTLN1 = (float)($stmt->fetch()['avg_val'] ?? 0);

        // Distribution des durées FR et INTL (≠ LOCAL), et Total (ancienne logique)
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
        $sqlDistALL  = str_replace('%PROV_COND%', "pr.nom_provenance <> 'LOCAL'", $sqlDistBase);

        // Exécuter N et N-1 pour chaque distribution
        $stmt = $pdo->prepare($sqlDistFR);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $distFR_N = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $distFR_N1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare($sqlDistINTL);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $distINTL_N = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $distINTL_N1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare($sqlDistALL);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $distAll_N = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $distAll_N1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Helper pour calculer parts/deltas
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
        $stayDistributionALL  = $computeDist($distAll_N, $distAll_N1);
    } catch (Exception $e) {
        // Si les tables n'existent pas encore, ignorer silencieusement
        $avgAll = $avgAll ?? null;
        $avgFR = $avgFR ?? null;
        $avgINTL = $avgINTL ?? null;
        $stayDistribution = $stayDistribution ?? [];
    }

    // Ajouter les indicateurs KPI pour les durées moyennes
    $indicators[] = [
        'numero' => 21,
        'indicateur' => '21. Durée moyenne totale',
        'N' => $avgAll,
        'N_1' => $avgAllN1,
        'evolution_pct' => ($avgAllN1 && $avgAllN1 > 0) ? round((($avgAll - $avgAllN1) / $avgAllN1) * 100, 1) : null,
        'unite' => 'Nuit(s)',
        'remarque' => 'Tous ≠ Local (FR + INTL)'
    ];
    $indicators[] = [
        'numero' => 22,
        'indicateur' => '22. Durée moyenne Français',
        'N' => $avgFR,
        'N_1' => $avgFRN1,
        'evolution_pct' => ($avgFRN1 && $avgFRN1 > 0) ? round((($avgFR - $avgFRN1) / $avgFRN1) * 100, 1) : null,
        'unite' => 'Nuit(s)',
        'remarque' => 'NONLOCAL'
    ];
    $indicators[] = [
        'numero' => 23,
        'indicateur' => '23. Durée moyenne International',
        'N' => $avgINTL,
        'N_1' => $avgINTLN1,
        'evolution_pct' => ($avgINTLN1 && $avgINTLN1 > 0) ? round((($avgINTL - $avgINTLN1) / $avgINTLN1) * 100, 1) : null,
        'unite' => 'Nuit(s)',
        'remarque' => 'ETRANGER'
    ];
    
    // 10. Pic Excursionnistes - utilisation de la table optimisée
    if (in_array('fact_diurnes_departements', $tables)) {
        // ✅ Réutiliser @zone_id déjà SET + ajouter catégorie excursionniste
        $pdo->exec("SET @cat_exc_id = (SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = 'EXCURSIONNISTE' LIMIT 1)");
        
        // ✅ Réutiliser @prov_id (provenance NONLOCAL définie plus haut) 
        $sql10 = "
            SELECT f.date, SUM(f.volume) as volume_jour
            FROM fact_diurnes_departements f
            WHERE f.id_zone = @zone_id
              AND f.id_categorie = @cat_exc_id
              AND f.id_provenance = @prov_id
              AND f.date BETWEEN ? AND ?
              AND f.volume > 0
            GROUP BY f.date
            ORDER BY volume_jour DESC
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql10);
        $stmt->execute([$dateRanges['start'], $dateRanges['end']]);
        $picResult = $stmt->fetch();
        
        // Calcul pic N-1
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
        $picResultN1 = $stmt->fetch();
        
        $picN = (int)($picResult['volume_jour'] ?? 0);
        $picN1 = (int)($picResultN1['volume_jour'] ?? 0);
        $evolutionPct10 = $picN1 > 0 ? round((($picN - $picN1) / $picN1) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 10,
            'indicateur' => '10. Excursionnistes – journée pic activité',
            'N' => $picN,
            'N_1' => $picN1,
            'evolution_pct' => $evolutionPct10,
            'date' => $picResult['date'] ?? null,
            'unite' => 'Présences',
            'remarque' => 'Pic journalier Excursionnistes non-locaux'
        ];
    } else {
        $indicators[] = [
            'numero' => 10,
            'indicateur' => '10. Excursionnistes – journée pic activité',
            'N' => 0,
            'date' => null,
            'unite' => 'Présences',
            'remarque' => 'Table fact_diurnes_departements non disponible'
        ];
    }
    
    // 16. Total Excursionnistes - NOUVELLE REQUÊTE OPTIMISÉE
    if (in_array('fact_diurnes', $tables)) {
        // ✅ Requête selon vos spécifications avec optimisations
        $sql16 = "
            SELECT COALESCE(SUM(f.volume), 0) as total_excursionnistes_non_locaux
            FROM fact_diurnes f
            INNER JOIN dim_dates d ON f.date = d.date
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE d.date BETWEEN ? AND ?
              AND z.nom_zone = ?
              AND c.nom_categorie = 'EXCURSIONNISTE'
              AND p.nom_provenance != 'LOCAL'
              AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sql16);
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $totalExcursionnistes = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);

        // Calcul total N-1
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
        $totalExcursionnistesN1 = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);
        
        $evolutionPct16 = $totalExcursionnistesN1 > 0 ? round((($totalExcursionnistes - $totalExcursionnistesN1) / $totalExcursionnistesN1) * 100, 1) : null;
    } else {
        $totalExcursionnistes = 0;
        $totalExcursionnistesN1 = 0;
        $evolutionPct16 = null;
    }
    
    $indicators[] = [
        'numero' => 16,
        'indicateur' => '16. Excursionnistes totaux',
        'N' => $totalExcursionnistes,
        'N_1' => $totalExcursionnistesN1,
        'evolution_pct' => $evolutionPct16,
        'unite' => 'Présences',
        'remarque' => 'Total excursionnistes NonLocaux + Etrangers'
    ];
    
    // 17. Présences 2e samedi - si table fact_diurnes_departements disponible
    if (in_array('fact_diurnes_departements', $tables)) {
        // Calculer le 2e samedi de la période
        $startDate = new DateTime($dateRanges['start']);
        $endDate = new DateTime($dateRanges['end']);
        
        $secondSaturday = null;
        $saturdaysFound = 0;
        
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            if ($currentDate->format('w') == 6) { // 6 = samedi
                $saturdaysFound++;
                if ($saturdaysFound == 2) {
                    $secondSaturday = $currentDate->format('Y-m-d');
                    break;
                }
            }
            $currentDate->add(new DateInterval('P1D'));
        }
        
        if ($secondSaturday) {
            $sql17 = "
                SELECT COALESCE(SUM(f.volume), 0) as total
                FROM fact_diurnes_departements f
                WHERE f.id_zone = @zone_id
                  AND f.id_categorie = @cat_exc_id
                  AND f.id_provenance = @prov_id
                  AND f.date = ?
                  AND f.volume > 0
            ";
            
            $stmt = $pdo->prepare($sql17);
            $stmt->execute([$secondSaturday]);
            $presences2eSamedi = (int)($stmt->fetch()['total'] ?? 0);
            
            // Calcul N-1 (2e samedi de l'année précédente)
            $secondSaturdayN1 = date('Y-m-d', strtotime($secondSaturday . ' -1 year'));
            $stmt->execute([$secondSaturdayN1]);
            $presences2eSamediN1 = (int)($stmt->fetch()['total'] ?? 0);
            
            $evolutionPct17 = $presences2eSamediN1 > 0 ? round((($presences2eSamedi - $presences2eSamediN1) / $presences2eSamediN1) * 100, 1) : null;
        } else {
            $presences2eSamedi = 0;
            $presences2eSamediN1 = 0;
            $evolutionPct17 = null;
        }
    } else {
        $presences2eSamedi = 0;
        $presences2eSamediN1 = 0;
        $evolutionPct17 = null;
    }
    
    $indicators[] = [
        'numero' => 17,
        'indicateur' => '17. Présences 2e samedi',
        'N' => $presences2eSamedi,
        'N_1' => $presences2eSamediN1,
        'evolution_pct' => $evolutionPct17,
        'date' => $secondSaturday ?? null,
        'unite' => 'Présences',
        'remarque' => 'Excursionnistes non-locaux - 2e samedi'
    ];
    
    // 18. Présences 3e samedi
    if (in_array('fact_diurnes_departements', $tables)) {
        // Calculer le 3e samedi de la période
        $thirdSaturday = null;
        $saturdaysFound = 0;
        
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            if ($currentDate->format('w') == 6) { // 6 = samedi
                $saturdaysFound++;
                if ($saturdaysFound == 3) {
                    $thirdSaturday = $currentDate->format('Y-m-d');
                    break;
                }
            }
            $currentDate->add(new DateInterval('P1D'));
        }
        
        if ($thirdSaturday) {
            $sql18 = "
                SELECT COALESCE(SUM(f.volume), 0) as total
                FROM fact_diurnes_departements f
                WHERE f.id_zone = @zone_id
                  AND f.id_categorie = @cat_exc_id
                  AND f.id_provenance = @prov_id
                  AND f.date = ?
                  AND f.volume > 0
            ";
            
            $stmt = $pdo->prepare($sql18);
            $stmt->execute([$thirdSaturday]);
            $presences3eSamedi = (int)($stmt->fetch()['total'] ?? 0);
            
            // Calcul N-1 (3e samedi de l'année précédente)
            $thirdSaturdayN1 = date('Y-m-d', strtotime($thirdSaturday . ' -1 year'));
            $stmt->execute([$thirdSaturdayN1]);
            $presences3eSamediN1 = (int)($stmt->fetch()['total'] ?? 0);
            
            $evolutionPct18 = $presences3eSamediN1 > 0 ? round((($presences3eSamedi - $presences3eSamediN1) / $presences3eSamediN1) * 100, 1) : null;
        } else {
            $presences3eSamedi = 0;
            $presences3eSamediN1 = 0;
            $evolutionPct18 = null;
        }
    } else {
        $presences3eSamedi = 0;
        $presences3eSamediN1 = 0;
        $evolutionPct18 = null;
    }
    
    $indicators[] = [
        'numero' => 18,
        'indicateur' => '18. Présences 3e samedi',
        'N' => $presences3eSamedi,
        'N_1' => $presences3eSamediN1,
        'evolution_pct' => $evolutionPct18,
        'date' => $thirdSaturday ?? null,
        'unite' => 'Présences',
        'remarque' => 'Excursionnistes non-locaux - 3e samedi'
    ];
    
    // Résultat final
    $result = [
        'zone_observation' => $zoneMapped,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'bloc_a' => $indicators,
        'avg_stay_all' => $avgAll,
        'avg_stay_nonlocal' => $avgFR,
        'avg_stay_etranger' => $avgINTL,
        'avg_stay_all_n1' => $avgAllN1,
        'avg_stay_nonlocal_n1' => $avgFRN1,
        'avg_stay_etranger_n1' => $avgINTLN1,
        'stay_distribution' => $stayDistributionALL,
        'stay_distribution_fr' => $stayDistributionFR,
        'stay_distribution_intl' => $stayDistributionINTL,
        'status' => 'success'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API Working',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 