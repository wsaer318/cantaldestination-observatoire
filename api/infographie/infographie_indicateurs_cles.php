<?php
/**
 * API Bloc A - Version Fonctionnelle - Version avec support des tables temporaires
 * Bascule automatiquement vers les tables temporaires si les données ne sont pas dans les tables principales
 * Copie exacte de la logique qui marchait dans bloc_a_fixed.php
 */

// IMPORTANT: Capturer toute sortie indésirable (erreurs, debug, etc.)
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure le gestionnaire hybride des périodes
require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';
require_once dirname(dirname(__DIR__)) . '/classes/PeriodMapper.php';

// Nettoyer la sortie de debug avant de continuer
ob_clean();

// Définir les headers JSON immédiatement
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Récupération des paramètres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debutOverride = $_GET['debut'] ?? null;
$finOverride = $_GET['fin'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if (!$annee || !$periode || !$zone) {
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
    exit;
}

/**
 * Calcule les plages de dates selon la période - VERSION SIMPLIFIÉE
 * Utilise la même logique que bloc_a_working.php qui fonctionne
 */
function calculateWorkingDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    // Utiliser notre système de configuration de base de données
    require_once dirname(dirname(__DIR__)) . '/database.php';
    $pdo = DatabaseConfig::getConnection();
    
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

    // Mappings simplifiés pour chaque année historique
    $zoneMappedN1 = ZoneMapper::displayToBase($zone);
    $zoneMappedN2 = ZoneMapper::displayToBase($zone);
    $zoneMappedN3 = ZoneMapper::displayToBase($zone);
    $zoneMappedN4 = ZoneMapper::displayToBase($zone);
    
    // Calcul des plages de dates (avec override facultatif debut/fin)
    if ($debutOverride && $finOverride) {
        $start = new DateTime($debutOverride . ' 00:00:00');
        $end = new DateTime($finOverride . ' 23:59:59');
        $dateRanges = [ 'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s') ];
        $s1 = (clone $start)->modify('-1 year');
        $e1 = (clone $end)->modify('-1 year');
        $s2 = (clone $start)->modify('-2 year');
        $e2 = (clone $end)->modify('-2 year');
        $s3 = (clone $start)->modify('-3 year');
        $e3 = (clone $end)->modify('-3 year');
        $s4 = (clone $start)->modify('-4 year');
        $e4 = (clone $end)->modify('-4 year');
        $dateRangesN1 = [ 'start' => $s1->format('Y-m-d H:i:s'), 'end' => $e1->format('Y-m-d H:i:s') ];
        $dateRangesN2 = [ 'start' => $s2->format('Y-m-d H:i:s'), 'end' => $e2->format('Y-m-d H:i:s') ];
        $dateRangesN3 = [ 'start' => $s3->format('Y-m-d H:i:s'), 'end' => $e3->format('Y-m-d H:i:s') ];
        $dateRangesN4 = [ 'start' => $s4->format('Y-m-d H:i:s'), 'end' => $e4->format('Y-m-d H:i:s') ];
    } else {
        // Calcul des plages de dates pour les 5 dernières années
        $dateRanges = calculateWorkingDateRanges($annee, $periode);
        $dateRangesN1 = calculateWorkingDateRanges($annee - 1, $periode);
        $dateRangesN2 = calculateWorkingDateRanges($annee - 2, $periode);
        $dateRangesN3 = calculateWorkingDateRanges($annee - 3, $periode);
        $dateRangesN4 = calculateWorkingDateRanges($annee - 4, $periode);
    }
    
    // Récupérer le code_periode pour vérifier si c'est les vacances d'hiver
    $sqlCodePeriode = "SELECT code_periode FROM dim_periodes WHERE nom_periode = ? AND annee = ? LIMIT 1";
    $stmtCodePeriode = $pdo->prepare($sqlCodePeriode);
    $stmtCodePeriode->execute([$periode, $annee]);
    $codePeriodeResult = $stmtCodePeriode->fetch();
    $codePeriode = $codePeriodeResult['code_periode'] ?? null;

    // ✅ NOUVELLE FONCTION : Calcul des évolutions avec année sélectionnée comme référence
    function calculateEvolutionFromReference($referenceValue, $compareValue) {
        if ($compareValue <= 0) {
            return null; // Pas de pourcentage si valeur nulle ou manquante
        }
        return round((($referenceValue - $compareValue) / $compareValue) * 100, 1);
    }

    // ====== INDICATEURS HIVER - NUITÉES (1-5) ======
    $isVacancesHiver = ($codePeriode === 'vacances_hiver'); // Code réel des vacances d'hiver
    $isPrintemps = ($codePeriode === 'printemps'); // Code réel du printemps
    
    $indicators = [];
    
    // 1. Nuitées totales - Touristes NonLocaux + Etrangers
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
    
    // Calcul pour les 5 années
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $totalNuitees = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
    $totalNuiteesN1 = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN2['start'], $dateRangesN2['end'], $zoneMappedN2]);
    $totalNuiteesN2 = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN3['start'], $dateRangesN3['end'], $zoneMappedN3]);
    $totalNuiteesN3 = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN4['start'], $dateRangesN4['end'], $zoneMappedN4]);
    $totalNuiteesN4 = (int)($stmt->fetch()['total'] ?? 0);
    
    // ✅ NOUVEAU CALCUL : Évolutions avec année sélectionnée comme référence
    $evolutionPct1 = calculateEvolutionFromReference($totalNuitees, $totalNuiteesN1);
    $evolutionPctN1 = calculateEvolutionFromReference($totalNuitees, $totalNuiteesN2);
    $evolutionPctN2 = calculateEvolutionFromReference($totalNuitees, $totalNuiteesN3);
    $evolutionPctN3 = calculateEvolutionFromReference($totalNuitees, $totalNuiteesN4);
    
    $indicators[] = [
        'numero' => 1,
        'indicateur' => '1. Nuitées totales (FR + INTL)',
        'N' => $totalNuitees,
        'N_1' => $totalNuiteesN1,
        'N_2' => $totalNuiteesN2,
        'N_3' => $totalNuiteesN3,
        'evolution_pct' => $evolutionPct1,
        'evolution_pct_N1' => $evolutionPctN1,
        'evolution_pct_N2' => $evolutionPctN2,
        'evolution_pct_N3' => $evolutionPctN3,
        'unite' => 'Nuitées',
        'remarque' => 'Touristes NonLocaux + Etrangers',
        'annee_reference' => $annee // ✅ Ajouter l'année de référence
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
    
    // Calcul pour les 5 années
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $nuiteesF2 = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
    $nuiteesF2N1 = (int)($stmt->fetch()['total'] ?? 0);

    $stmt->execute([$dateRangesN2['start'], $dateRangesN2['end'], $zoneMappedN2]);
    $nuiteesF2N2 = (int)($stmt->fetch()['total'] ?? 0);

    $stmt->execute([$dateRangesN3['start'], $dateRangesN3['end'], $zoneMappedN3]);
    $nuiteesF2N3 = (int)($stmt->fetch()['total'] ?? 0);

    $stmt->execute([$dateRangesN4['start'], $dateRangesN4['end'], $zoneMappedN4]);
    $nuiteesF2N4 = (int)($stmt->fetch()['total'] ?? 0);
    
    // Calcul des évolutions
    $evolutionPct2 = calculateEvolutionFromReference($nuiteesF2, $nuiteesF2N1);
    $evolutionPct2N1 = calculateEvolutionFromReference($nuiteesF2, $nuiteesF2N2);
    $evolutionPct2N2 = calculateEvolutionFromReference($nuiteesF2, $nuiteesF2N3);
    $evolutionPct2N3 = calculateEvolutionFromReference($nuiteesF2, $nuiteesF2N4);
    
    $indicators[] = [
        'numero' => 2,
        'indicateur' => '2. Nuitées françaises',
        'N' => $nuiteesF2,
        'N_1' => $nuiteesF2N1,
        'N_2' => $nuiteesF2N2,
        'N_3' => $nuiteesF2N3,
        'evolution_pct' => $evolutionPct2,
        'evolution_pct_N1' => $evolutionPct2N1,
        'evolution_pct_N2' => $evolutionPct2N2,
        'evolution_pct_N3' => $evolutionPct2N3,
        'unite' => 'Nuitées',
        'remarque' => 'Touristes NonLocaux',
        'annee_reference' => $annee // ✅ Ajouter l'année de référence
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
    
    // Calcul pour les 5 années
    $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
    $nuiteesIntl = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMappedN1]);
    $nuiteesIntlN1 = (int)($stmt->fetch()['total'] ?? 0);

    $stmt->execute([$dateRangesN2['start'], $dateRangesN2['end'], $zoneMappedN2]);
    $nuiteesIntlN2 = (int)($stmt->fetch()['total'] ?? 0);

    $stmt->execute([$dateRangesN3['start'], $dateRangesN3['end'], $zoneMappedN3]);
    $nuiteesIntlN3 = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt->execute([$dateRangesN4['start'], $dateRangesN4['end'], $zoneMappedN4]);
    $nuiteesIntlN4 = (int)($stmt->fetch()['total'] ?? 0);
    
    // ✅ NOUVEAU CALCUL : Évolutions avec année sélectionnée comme référence
    $evolutionPct3 = calculateEvolutionFromReference($nuiteesIntl, $nuiteesIntlN1);
    $evolutionPct3N1 = calculateEvolutionFromReference($nuiteesIntl, $nuiteesIntlN2);
    $evolutionPct3N2 = calculateEvolutionFromReference($nuiteesIntl, $nuiteesIntlN3);
    $evolutionPct3N3 = calculateEvolutionFromReference($nuiteesIntl, $nuiteesIntlN4);
    
    $indicators[] = [
        'numero' => 3,
        'indicateur' => '3. Nuitées internationales',
        'N' => $nuiteesIntl,
        'N_1' => $nuiteesIntlN1,
        'N_2' => $nuiteesIntlN2,
        'N_3' => $nuiteesIntlN3,
        'evolution_pct' => $evolutionPct3,
        'evolution_pct_N1' => $evolutionPct3N1,
        'evolution_pct_N2' => $evolutionPct3N2,
        'evolution_pct_N3' => $evolutionPct3N3,
        'unite' => 'Nuitées',
        'remarque' => 'Touristes Etrangers',
        'annee_reference' => $annee // ✅ Ajouter l'année de référence
    ];
    
    // 4. Top 15 Départements (VERSION ULTRA-OPTIMISÉE)
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('fact_nuitees_departements', $tables)) {
        // ✅ OPTIMISATION 1: Pré-résoudre les IDs pour éviter les JOINs répétés - SÉCURISÉ
        $stmt = $pdo->prepare("SET @zone_id = (SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ? LIMIT 1)");
        $stmt->execute([$zoneMapped]);
        
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
        
        // Calcul N-1 (réutiliser les variables déjà SET)
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
        $top15DeptSumN1 = (int)($stmt->fetch()['total_top15'] ?? 0);
        
        $evolutionPct4 = calculateEvolutionFromReference($top15DeptSum, $top15DeptSumN1);
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
        'remarque' => 'Somme nuitées Top 15 Départements (FR)',
        'annee_reference' => $annee // ✅ Ajouter l'année de référence
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
        
        // Calcul N-1 (réutiliser les variables déjà SET)
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end']]);
        $top5PaysSumN1 = (int)($stmt->fetch()['total_top5'] ?? 0);
        
        $evolutionPct5 = calculateEvolutionFromReference($top5PaysSum, $top5PaysSumN1);
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
        'remarque' => 'Somme nuitées Top 5 Pays (INTL)',
        'annee_reference' => $annee // ✅ Ajouter l'année de référence
    ];
    
    // ====== INDICATEURS PRINTEMPS - NUITÉES (6-9) ======
    if ($isPrintemps) {
        // Préparer les requêtes pour les weekends/périodes spécifiques de printemps
        
        // 6. Weekend de Pâques (nuitées)
        $sqlPeriodeBase = "
            SELECT COALESCE(SUM(fact_nuitees.volume), 0) as total
            FROM fact_nuitees
            INNER JOIN dim_zones_observation ON fact_nuitees.id_zone = dim_zones_observation.id_zone
            INNER JOIN dim_provenances ON fact_nuitees.id_provenance = dim_provenances.id_provenance
            INNER JOIN dim_categories_visiteur ON fact_nuitees.id_categorie = dim_categories_visiteur.id_categorie
            INNER JOIN dim_periodes ON fact_nuitees.date BETWEEN dim_periodes.date_debut AND dim_periodes.date_fin
            WHERE dim_zones_observation.nom_zone = ?
            AND dim_categories_visiteur.nom_categorie = 'TOURISTE'
            AND dim_provenances.nom_provenance IN ('NONLOCAL', 'ETRANGER')
            AND dim_periodes.code_periode = ?
            AND dim_periodes.annee = ?
        ";
        
        $stmt = $pdo->prepare($sqlPeriodeBase);
        
        // Weekend de Pâques
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee]);
        $nuiteesWeekendPaques = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 1]);
        $nuiteesWeekendPaquesN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 2]);
        $nuiteesWeekendPaquesN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 3]);
        $nuiteesWeekendPaquesN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 4]);
        $nuiteesWeekendPaquesN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct6 = $nuiteesWeekendPaquesN1 > 0 ? round((($nuiteesWeekendPaques - $nuiteesWeekendPaquesN1) / $nuiteesWeekendPaquesN1) * 100, 1) : null;
        $evolutionPct6N1 = $nuiteesWeekendPaquesN2 > 0 ? round((($nuiteesWeekendPaquesN1 - $nuiteesWeekendPaquesN2) / $nuiteesWeekendPaquesN2) * 100, 1) : null;
        $evolutionPct6N2 = $nuiteesWeekendPaquesN3 > 0 ? round((($nuiteesWeekendPaquesN2 - $nuiteesWeekendPaquesN3) / $nuiteesWeekendPaquesN3) * 100, 1) : null;
        $evolutionPct6N3 = $nuiteesWeekendPaquesN4 > 0 ? round((($nuiteesWeekendPaquesN3 - $nuiteesWeekendPaquesN4) / $nuiteesWeekendPaquesN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 6,
            'indicateur' => '6. Weekend de Pâques',
            'N' => $nuiteesWeekendPaques,
            'N_1' => $nuiteesWeekendPaquesN1,
            'N_2' => $nuiteesWeekendPaquesN2,
            'N_3' => $nuiteesWeekendPaquesN3,
            'evolution_pct' => $evolutionPct6,
            'evolution_pct_N1' => $evolutionPct6N1,
            'evolution_pct_N2' => $evolutionPct6N2,
            'evolution_pct_N3' => $evolutionPct6N3,
            'unite' => 'Nuitées',
            'remarque' => 'Nuitées weekend de Pâques'
        ];
        
        // 7. 1er mai (nuitées) - Date fixe
        $sql1erMai = "
            SELECT COALESCE(SUM(fact_nuitees.volume), 0) as total
            FROM fact_nuitees
            INNER JOIN dim_zones_observation ON fact_nuitees.id_zone = dim_zones_observation.id_zone
            INNER JOIN dim_provenances ON fact_nuitees.id_provenance = dim_provenances.id_provenance
            INNER JOIN dim_categories_visiteur ON fact_nuitees.id_categorie = dim_categories_visiteur.id_categorie
            WHERE fact_nuitees.date = ?
            AND dim_zones_observation.nom_zone = ?
            AND dim_categories_visiteur.nom_categorie = 'TOURISTE'
            AND dim_provenances.nom_provenance IN ('NONLOCAL', 'ETRANGER')
        ";
        
        $stmt = $pdo->prepare($sql1erMai);
        $stmt->execute(["$annee-05-01", $zoneMapped]);
        $nuitees1erMai = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 1) . "-05-01", $zoneMapped]);
        $nuitees1erMaiN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 2) . "-05-01", $zoneMapped]);
        $nuitees1erMaiN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 3) . "-05-01", $zoneMapped]);
        $nuitees1erMaiN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 4) . "-05-01", $zoneMapped]);
        $nuitees1erMaiN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct7 = $nuitees1erMaiN1 > 0 ? round((($nuitees1erMai - $nuitees1erMaiN1) / $nuitees1erMaiN1) * 100, 1) : null;
        $evolutionPct7N1 = $nuitees1erMaiN2 > 0 ? round((($nuitees1erMaiN1 - $nuitees1erMaiN2) / $nuitees1erMaiN2) * 100, 1) : null;
        $evolutionPct7N2 = $nuitees1erMaiN3 > 0 ? round((($nuitees1erMaiN2 - $nuitees1erMaiN3) / $nuitees1erMaiN3) * 100, 1) : null;
        $evolutionPct7N3 = $nuitees1erMaiN4 > 0 ? round((($nuitees1erMaiN3 - $nuitees1erMaiN4) / $nuitees1erMaiN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 7,
            'indicateur' => '7. 1er mai',
            'N' => $nuitees1erMai,
            'N_1' => $nuitees1erMaiN1,
            'N_2' => $nuitees1erMaiN2,
            'N_3' => $nuitees1erMaiN3,
            'evolution_pct' => $evolutionPct7,
            'evolution_pct_N1' => $evolutionPct7N1,
            'evolution_pct_N2' => $evolutionPct7N2,
            'evolution_pct_N3' => $evolutionPct7N3,
            'date' => "$annee-05-01",
            'unite' => 'Nuitées',
            'remarque' => 'Nuitées 1er mai'
        ];
        
        // 8. Weekend de l'Ascension (nuitées)
        $stmt = $pdo->prepare($sqlPeriodeBase);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee]);
        $nuiteesWeekendAscension = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 1]);
        $nuiteesWeekendAscensionN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 2]);
        $nuiteesWeekendAscensionN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 3]);
        $nuiteesWeekendAscensionN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 4]);
        $nuiteesWeekendAscensionN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct8 = $nuiteesWeekendAscensionN1 > 0 ? round((($nuiteesWeekendAscension - $nuiteesWeekendAscensionN1) / $nuiteesWeekendAscensionN1) * 100, 1) : null;
        $evolutionPct8N1 = $nuiteesWeekendAscensionN2 > 0 ? round((($nuiteesWeekendAscensionN1 - $nuiteesWeekendAscensionN2) / $nuiteesWeekendAscensionN2) * 100, 1) : null;
        $evolutionPct8N2 = $nuiteesWeekendAscensionN3 > 0 ? round((($nuiteesWeekendAscensionN2 - $nuiteesWeekendAscensionN3) / $nuiteesWeekendAscensionN3) * 100, 1) : null;
        $evolutionPct8N3 = $nuiteesWeekendAscensionN4 > 0 ? round((($nuiteesWeekendAscensionN3 - $nuiteesWeekendAscensionN4) / $nuiteesWeekendAscensionN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 8,
            'indicateur' => '8. Weekend de l\'Ascension',
            'N' => $nuiteesWeekendAscension,
            'N_1' => $nuiteesWeekendAscensionN1,
            'N_2' => $nuiteesWeekendAscensionN2,
            'N_3' => $nuiteesWeekendAscensionN3,
            'evolution_pct' => $evolutionPct8,
            'evolution_pct_N1' => $evolutionPct8N1,
            'evolution_pct_N2' => $evolutionPct8N2,
            'evolution_pct_N3' => $evolutionPct8N3,
            'unite' => 'Nuitées',
            'remarque' => 'Nuitées weekend de l\'Ascension'
        ];
        
        // 9. Weekend de la Pentecôte (nuitées)
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee]);
        $nuiteesWeekendPentecote = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 1]);
        $nuiteesWeekendPentecoteN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 2]);
        $nuiteesWeekendPentecoteN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 3]);
        $nuiteesWeekendPentecoteN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 4]);
        $nuiteesWeekendPentecoteN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct9 = $nuiteesWeekendPentecoteN1 > 0 ? round((($nuiteesWeekendPentecote - $nuiteesWeekendPentecoteN1) / $nuiteesWeekendPentecoteN1) * 100, 1) : null;
        $evolutionPct9N1 = $nuiteesWeekendPentecoteN2 > 0 ? round((($nuiteesWeekendPentecoteN1 - $nuiteesWeekendPentecoteN2) / $nuiteesWeekendPentecoteN2) * 100, 1) : null;
        $evolutionPct9N2 = $nuiteesWeekendPentecoteN3 > 0 ? round((($nuiteesWeekendPentecoteN2 - $nuiteesWeekendPentecoteN3) / $nuiteesWeekendPentecoteN3) * 100, 1) : null;
        $evolutionPct9N3 = $nuiteesWeekendPentecoteN4 > 0 ? round((($nuiteesWeekendPentecoteN3 - $nuiteesWeekendPentecoteN4) / $nuiteesWeekendPentecoteN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 9,
            'indicateur' => '9. Weekend de la Pentecôte',
            'N' => $nuiteesWeekendPentecote,
            'N_1' => $nuiteesWeekendPentecoteN1,
            'N_2' => $nuiteesWeekendPentecoteN2,
            'N_3' => $nuiteesWeekendPentecoteN3,
            'evolution_pct' => $evolutionPct9,
            'evolution_pct_N1' => $evolutionPct9N1,
            'evolution_pct_N2' => $evolutionPct9N2,
            'evolution_pct_N3' => $evolutionPct9N3,
            'unite' => 'Nuitées',
            'remarque' => 'Nuitées weekend de la Pentecôte'
        ];
    }
    
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
    
    // 15. Excursionnistes français - NOUVELLE REQUÊTE OPTIMISÉE
    if (in_array('fact_diurnes', $tables)) {
        // ✅ Requête pour les excursionnistes français (NONLOCAL)
        $sql15 = "
            SELECT COALESCE(SUM(f.volume), 0) as total_excursionnistes_francais
            FROM fact_diurnes f
            INNER JOIN dim_dates d ON f.date = d.date
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE d.date BETWEEN ? AND ?
              AND z.nom_zone = ?
              AND c.nom_categorie = 'EXCURSIONNISTE'
              AND p.nom_provenance = 'NONLOCAL'
              AND f.volume > 0
        ";

        $stmt = $pdo->prepare($sql15);

        // Calcul pour les 5 années
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $excursionnistesFrancais = (int)($stmt->fetch()['total_excursionnistes_francais'] ?? 0);

        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMapped]);
        $excursionnistesFrancaisN1 = (int)($stmt->fetch()['total_excursionnistes_francais'] ?? 0);

        $stmt->execute([$dateRangesN2['start'], $dateRangesN2['end'], $zoneMapped]);
        $excursionnistesFrancaisN2 = (int)($stmt->fetch()['total_excursionnistes_francais'] ?? 0);

        $stmt->execute([$dateRangesN3['start'], $dateRangesN3['end'], $zoneMapped]);
        $excursionnistesFrancaisN3 = (int)($stmt->fetch()['total_excursionnistes_francais'] ?? 0);

        $stmt->execute([$dateRangesN4['start'], $dateRangesN4['end'], $zoneMapped]);
        $excursionnistesFrancaisN4 = (int)($stmt->fetch()['total_excursionnistes_francais'] ?? 0);

        // Calcul des évolutions
        $evolutionPct15 = $excursionnistesFrancaisN1 > 0 ? round((($excursionnistesFrancais - $excursionnistesFrancaisN1) / $excursionnistesFrancaisN1) * 100, 1) : null;
        $evolutionPct15N1 = $excursionnistesFrancaisN2 > 0 ? round((($excursionnistesFrancaisN1 - $excursionnistesFrancaisN2) / $excursionnistesFrancaisN2) * 100, 1) : null;
        $evolutionPct15N2 = $excursionnistesFrancaisN3 > 0 ? round((($excursionnistesFrancaisN2 - $excursionnistesFrancaisN3) / $excursionnistesFrancaisN3) * 100, 1) : null;
        $evolutionPct15N3 = $excursionnistesFrancaisN4 > 0 ? round((($excursionnistesFrancaisN3 - $excursionnistesFrancaisN4) / $excursionnistesFrancaisN4) * 100, 1) : null;
    } else {
        $excursionnistesFrancais = 0;
        $excursionnistesFrancaisN1 = 0;
        $excursionnistesFrancaisN2 = 0;
        $excursionnistesFrancaisN3 = 0;
        $excursionnistesFrancaisN4 = 0;
        $evolutionPct15 = null;
        $evolutionPct15N1 = null;
        $evolutionPct15N2 = null;
        $evolutionPct15N3 = null;
    }

    $indicators[] = [
        'numero' => 15,
        'indicateur' => '15. Excursionnistes français',
        'N' => $excursionnistesFrancais,
        'N_1' => $excursionnistesFrancaisN1,
        'N_2' => $excursionnistesFrancaisN2,
        'N_3' => $excursionnistesFrancaisN3,
        'evolution_pct' => $evolutionPct15,
        'evolution_pct_N1' => $evolutionPct15N1,
        'evolution_pct_N2' => $evolutionPct15N2,
        'evolution_pct_N3' => $evolutionPct15N3,
        'unite' => 'Présences',
        'remarque' => 'Excursionnistes NonLocaux (Français)'
    ];

    // 15.5. Excursionnistes internationaux - NOUVELLE REQUÊTE OPTIMISÉE
    if (in_array('fact_diurnes', $tables)) {
        // ✅ Requête pour les excursionnistes internationaux (ETRANGER)
        $sql15_5 = "
            SELECT COALESCE(SUM(f.volume), 0) as total_excursionnistes_internationaux
            FROM fact_diurnes f
            INNER JOIN dim_dates d ON f.date = d.date
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE d.date BETWEEN ? AND ?
              AND z.nom_zone = ?
              AND c.nom_categorie = 'EXCURSIONNISTE'
              AND p.nom_provenance = 'ETRANGER'
              AND f.volume > 0
        ";

        $stmt = $pdo->prepare($sql15_5);

        // Calcul pour les 5 années
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $excursionnistesInternationaux = (int)($stmt->fetch()['total_excursionnistes_internationaux'] ?? 0);

        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMapped]);
        $excursionnistesInternationauxN1 = (int)($stmt->fetch()['total_excursionnistes_internationaux'] ?? 0);

        $stmt->execute([$dateRangesN2['start'], $dateRangesN2['end'], $zoneMapped]);
        $excursionnistesInternationauxN2 = (int)($stmt->fetch()['total_excursionnistes_internationaux'] ?? 0);

        $stmt->execute([$dateRangesN3['start'], $dateRangesN3['end'], $zoneMapped]);
        $excursionnistesInternationauxN3 = (int)($stmt->fetch()['total_excursionnistes_internationaux'] ?? 0);

        $stmt->execute([$dateRangesN4['start'], $dateRangesN4['end'], $zoneMapped]);
        $excursionnistesInternationauxN4 = (int)($stmt->fetch()['total_excursionnistes_internationaux'] ?? 0);

        // Calcul des évolutions
        $evolutionPct15_5 = $excursionnistesInternationauxN1 > 0 ? round((($excursionnistesInternationaux - $excursionnistesInternationauxN1) / $excursionnistesInternationauxN1) * 100, 1) : null;
        $evolutionPct15_5N1 = $excursionnistesInternationauxN2 > 0 ? round((($excursionnistesInternationauxN1 - $excursionnistesInternationauxN2) / $excursionnistesInternationauxN2) * 100, 1) : null;
        $evolutionPct15_5N2 = $excursionnistesInternationauxN3 > 0 ? round((($excursionnistesInternationauxN2 - $excursionnistesInternationauxN3) / $excursionnistesInternationauxN3) * 100, 1) : null;
        $evolutionPct15_5N3 = $excursionnistesInternationauxN4 > 0 ? round((($excursionnistesInternationauxN3 - $excursionnistesInternationauxN4) / $excursionnistesInternationauxN4) * 100, 1) : null;
    } else {
        $excursionnistesInternationaux = 0;
        $excursionnistesInternationauxN1 = 0;
        $excursionnistesInternationauxN2 = 0;
        $excursionnistesInternationauxN3 = 0;
        $excursionnistesInternationauxN4 = 0;
        $evolutionPct15_5 = null;
        $evolutionPct15_5N1 = null;
        $evolutionPct15_5N2 = null;
        $evolutionPct15_5N3 = null;
    }

    $indicators[] = [
        'numero' => 15.5,
        'indicateur' => '15.5. Excursionnistes internationaux',
        'N' => $excursionnistesInternationaux,
        'N_1' => $excursionnistesInternationauxN1,
        'N_2' => $excursionnistesInternationauxN2,
        'N_3' => $excursionnistesInternationauxN3,
        'evolution_pct' => $evolutionPct15_5,
        'evolution_pct_N1' => $evolutionPct15_5N1,
        'evolution_pct_N2' => $evolutionPct15_5N2,
        'evolution_pct_N3' => $evolutionPct15_5N3,
        'unite' => 'Présences',
        'remarque' => 'Excursionnistes Etrangers'
    ];

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
        
        // Calcul pour les 5 années
        $stmt->execute([$dateRanges['start'], $dateRanges['end'], $zoneMapped]);
        $totalExcursionnistes = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);
        
        $stmt->execute([$dateRangesN1['start'], $dateRangesN1['end'], $zoneMapped]);
        $totalExcursionnistesN1 = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);
        
        $stmt->execute([$dateRangesN2['start'], $dateRangesN2['end'], $zoneMapped]);
        $totalExcursionnistesN2 = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);
        
        $stmt->execute([$dateRangesN3['start'], $dateRangesN3['end'], $zoneMapped]);
        $totalExcursionnistesN3 = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);
        
        $stmt->execute([$dateRangesN4['start'], $dateRangesN4['end'], $zoneMapped]);
        $totalExcursionnistesN4 = (int)($stmt->fetch()['total_excursionnistes_non_locaux'] ?? 0);
        
        // Calcul des évolutions
        $evolutionPct16 = $totalExcursionnistesN1 > 0 ? round((($totalExcursionnistes - $totalExcursionnistesN1) / $totalExcursionnistesN1) * 100, 1) : null;
        $evolutionPct16N1 = $totalExcursionnistesN2 > 0 ? round((($totalExcursionnistesN1 - $totalExcursionnistesN2) / $totalExcursionnistesN2) * 100, 1) : null;
        $evolutionPct16N2 = $totalExcursionnistesN3 > 0 ? round((($totalExcursionnistesN2 - $totalExcursionnistesN3) / $totalExcursionnistesN3) * 100, 1) : null;
        $evolutionPct16N3 = $totalExcursionnistesN4 > 0 ? round((($totalExcursionnistesN3 - $totalExcursionnistesN4) / $totalExcursionnistesN4) * 100, 1) : null;
    } else {
        $totalExcursionnistes = 0;
        $totalExcursionnistesN1 = 0;
        $totalExcursionnistesN2 = 0;
        $totalExcursionnistesN3 = 0;
        $totalExcursionnistesN4 = 0;
        $evolutionPct16 = null;
        $evolutionPct16N1 = null;
        $evolutionPct16N2 = null;
        $evolutionPct16N3 = null;
    }
    
    $indicators[] = [
        'numero' => 16,
        'indicateur' => '16. Excursionnistes totaux',
        'N' => $totalExcursionnistes,
        'N_1' => $totalExcursionnistesN1,
        'N_2' => $totalExcursionnistesN2,
        'N_3' => $totalExcursionnistesN3,
        'evolution_pct' => $evolutionPct16,
        'evolution_pct_N1' => $evolutionPct16N1,
        'evolution_pct_N2' => $evolutionPct16N2,
        'evolution_pct_N3' => $evolutionPct16N3,
        'unite' => 'Présences',
        'remarque' => 'Total excursionnistes NonLocaux + Etrangers'
    ];
    
    // 17. Présences 2e samedi - si table fact_diurnes disponible ET si vacances d'hiver
    if (in_array('fact_diurnes', $tables) && $isVacancesHiver) {
        // Calculer les 2e samedis pour toutes les années (y compris N-4)
        $dateRangesN4 = calculateWorkingDateRanges($annee - 4, $periode);
        
        $saturdays = [
            'N' => null, 'N1' => null, 'N2' => null, 'N3' => null, 'N4' => null
        ];
        
        foreach ([
            'N' => [$dateRanges['start'], $dateRanges['end']],
            'N1' => [$dateRangesN1['start'], $dateRangesN1['end']],
            'N2' => [$dateRangesN2['start'], $dateRangesN2['end']],
            'N3' => [$dateRangesN3['start'], $dateRangesN3['end']],
            'N4' => [$dateRangesN4['start'], $dateRangesN4['end']]
        ] as $key => $dates) {
            $start = new DateTime($dates[0]);
            $end = new DateTime($dates[1]);
            $saturdaysFound = 0;
            
            $currentDate = clone $start;
            while ($currentDate <= $end) {
                if ($currentDate->format('w') == 6) { // 6 = samedi
                    $saturdaysFound++;
                    if ($saturdaysFound == 2) {
                        $saturdays[$key] = $currentDate->format('Y-m-d');
                        break;
                    }
                }
                $currentDate->add(new DateInterval('P1D'));
            }
        }
        
        $secondSaturday = $saturdays['N'];
        $secondSaturdayN1 = $saturdays['N1'];
        $secondSaturdayN2 = $saturdays['N2'];
        $secondSaturdayN3 = $saturdays['N3'];
        $secondSaturdayN4 = $saturdays['N4'];
        
        $sql17 = "
            SELECT COALESCE(SUM(f.volume), 0) as total
            FROM fact_diurnes f
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date = ?
              AND z.nom_zone = ?
              AND c.nom_categorie = 'EXCURSIONNISTE'
              AND p.nom_provenance != 'LOCAL'
              AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sql17);
        
        // Calcul pour toutes les années
        $presences2eSamedi = 0;
        $presences2eSamediN1 = 0;
        $presences2eSamediN2 = 0;
        $presences2eSamediN3 = 0;
        $presences2eSamediN4 = 0;
        
        if ($secondSaturday) {
            $stmt->execute([$secondSaturday, $zoneMapped]);
            $presences2eSamedi = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($secondSaturdayN1) {
            $stmt->execute([$secondSaturdayN1, $zoneMapped]);
            $presences2eSamediN1 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($secondSaturdayN2) {
            $stmt->execute([$secondSaturdayN2, $zoneMapped]);
            $presences2eSamediN2 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($secondSaturdayN3) {
            $stmt->execute([$secondSaturdayN3, $zoneMapped]);
            $presences2eSamediN3 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($secondSaturdayN4) {
            $stmt->execute([$secondSaturdayN4, $zoneMapped]);
            $presences2eSamediN4 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        // Calcul des évolutions
        $evolutionPct17 = $presences2eSamediN1 > 0 ? round((($presences2eSamedi - $presences2eSamediN1) / $presences2eSamediN1) * 100, 1) : null;
        $evolutionPct17N1 = $presences2eSamediN2 > 0 ? round((($presences2eSamediN1 - $presences2eSamediN2) / $presences2eSamediN2) * 100, 1) : null;
        $evolutionPct17N2 = $presences2eSamediN3 > 0 ? round((($presences2eSamediN2 - $presences2eSamediN3) / $presences2eSamediN3) * 100, 1) : null;
        $evolutionPct17N3 = $presences2eSamediN4 > 0 ? round((($presences2eSamediN3 - $presences2eSamediN4) / $presences2eSamediN4) * 100, 1) : null;
        
    } else {
        $presences2eSamedi = 0;
        $presences2eSamediN1 = 0;
        $presences2eSamediN2 = 0;
        $presences2eSamediN3 = 0;
        $presences2eSamediN4 = 0;
        $evolutionPct17 = null;
        $evolutionPct17N1 = null;
        $evolutionPct17N2 = null;
        $evolutionPct17N3 = null;
        $secondSaturday = null;
    }
    
    // N'ajouter l'indicateur 17 que si c'est les vacances d'hiver
    if ($isVacancesHiver) {
        $indicators[] = [
            'numero' => 17,
            'indicateur' => '17. Présences 2e samedi',
            'N' => $presences2eSamedi,
            'N_1' => $presences2eSamediN1,
            'N_2' => $presences2eSamediN2,
            'N_3' => $presences2eSamediN3,
            'evolution_pct' => $evolutionPct17,
            'evolution_pct_N1' => $evolutionPct17N1,
            'evolution_pct_N2' => $evolutionPct17N2,
            'evolution_pct_N3' => $evolutionPct17N3,
            'date' => $secondSaturday ?? null,
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes non-locaux - 2e samedi'
        ];
    }
    
    // 18. Présences 3e samedi - seulement pour les vacances d'hiver
    if (in_array('fact_diurnes', $tables) && $isVacancesHiver) {
        // Calculer les 3e samedis pour toutes les années (y compris N-4)
        $thirdSaturdays = [
            'N' => null, 'N1' => null, 'N2' => null, 'N3' => null, 'N4' => null
        ];
        
        foreach ([
            'N' => [$dateRanges['start'], $dateRanges['end']],
            'N1' => [$dateRangesN1['start'], $dateRangesN1['end']],
            'N2' => [$dateRangesN2['start'], $dateRangesN2['end']],
            'N3' => [$dateRangesN3['start'], $dateRangesN3['end']],
            'N4' => [$dateRangesN4['start'], $dateRangesN4['end']]
        ] as $key => $dates) {
            $start = new DateTime($dates[0]);
            $end = new DateTime($dates[1]);
            $saturdaysFound = 0;
            
            $currentDate = clone $start;
            while ($currentDate <= $end) {
                if ($currentDate->format('w') == 6) { // 6 = samedi
                    $saturdaysFound++;
                    if ($saturdaysFound == 3) {
                        $thirdSaturdays[$key] = $currentDate->format('Y-m-d');
                        break;
                    }
                }
                $currentDate->add(new DateInterval('P1D'));
            }
        }
        
        $thirdSaturday = $thirdSaturdays['N'];
        $thirdSaturdayN1 = $thirdSaturdays['N1'];
        $thirdSaturdayN2 = $thirdSaturdays['N2'];
        $thirdSaturdayN3 = $thirdSaturdays['N3'];
        $thirdSaturdayN4 = $thirdSaturdays['N4'];
        
        $sql18 = "
            SELECT COALESCE(SUM(f.volume), 0) as total
            FROM fact_diurnes f
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date = ?
              AND z.nom_zone = ?
              AND c.nom_categorie = 'EXCURSIONNISTE'
              AND p.nom_provenance != 'LOCAL'
              AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sql18);
        
        // Calcul pour toutes les années
        $presences3eSamedi = 0;
        $presences3eSamediN1 = 0;
        $presences3eSamediN2 = 0;
        $presences3eSamediN3 = 0;
        $presences3eSamediN4 = 0;
        
        if ($thirdSaturday) {
            $stmt->execute([$thirdSaturday, $zoneMapped]);
            $presences3eSamedi = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($thirdSaturdayN1) {
            $stmt->execute([$thirdSaturdayN1, $zoneMapped]);
            $presences3eSamediN1 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($thirdSaturdayN2) {
            $stmt->execute([$thirdSaturdayN2, $zoneMapped]);
            $presences3eSamediN2 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($thirdSaturdayN3) {
            $stmt->execute([$thirdSaturdayN3, $zoneMapped]);
            $presences3eSamediN3 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        if ($thirdSaturdayN4) {
            $stmt->execute([$thirdSaturdayN4, $zoneMapped]);
            $presences3eSamediN4 = (int)($stmt->fetch()['total'] ?? 0);
        }
        
        // Calcul des évolutions
        $evolutionPct18 = $presences3eSamediN1 > 0 ? round((($presences3eSamedi - $presences3eSamediN1) / $presences3eSamediN1) * 100, 1) : null;
        $evolutionPct18N1 = $presences3eSamediN2 > 0 ? round((($presences3eSamediN1 - $presences3eSamediN2) / $presences3eSamediN2) * 100, 1) : null;
        $evolutionPct18N2 = $presences3eSamediN3 > 0 ? round((($presences3eSamediN2 - $presences3eSamediN3) / $presences3eSamediN3) * 100, 1) : null;
        $evolutionPct18N3 = $presences3eSamediN4 > 0 ? round((($presences3eSamediN3 - $presences3eSamediN4) / $presences3eSamediN4) * 100, 1) : null;
        
    } else {
        $presences3eSamedi = 0;
        $presences3eSamediN1 = 0;
        $presences3eSamediN2 = 0;
        $presences3eSamediN3 = 0;
        $presences3eSamediN4 = 0;
        $evolutionPct18 = null;
        $evolutionPct18N1 = null;
        $evolutionPct18N2 = null;
        $evolutionPct18N3 = null;
        $thirdSaturday = null;
    }
    
    // N'ajouter l'indicateur 18 que si c'est les vacances d'hiver
    if ($isVacancesHiver) {
        $indicators[] = [
            'numero' => 18,
            'indicateur' => '18. Présences 3e samedi',
            'N' => $presences3eSamedi,
            'N_1' => $presences3eSamediN1,
            'N_2' => $presences3eSamediN2,
            'N_3' => $presences3eSamediN3,
            'evolution_pct' => $evolutionPct18,
            'evolution_pct_N1' => $evolutionPct18N1,
            'evolution_pct_N2' => $evolutionPct18N2,
            'evolution_pct_N3' => $evolutionPct18N3,
            'date' => $thirdSaturday ?? null,
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes non-locaux - 3e samedi'
        ];
    }
    
    // ====== INDICATEURS PRINTEMPS - EXCURSIONNISTES (19-22) ======
    if ($isPrintemps && in_array('fact_diurnes', $tables)) {
        // Préparer les requêtes pour les weekends/périodes spécifiques de printemps (excursionnistes)
        
        // 19. Weekend de Pâques (excursionnistes)
        $sqlPeriodeExcBase = "
            SELECT COALESCE(SUM(f.volume), 0) as total
            FROM fact_diurnes f
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            INNER JOIN dim_periodes dp ON f.date BETWEEN dp.date_debut AND dp.date_fin
            WHERE z.nom_zone = ?
            AND c.nom_categorie = 'EXCURSIONNISTE'
            AND p.nom_provenance != 'LOCAL'
            AND dp.code_periode = ?
            AND dp.annee = ?
            AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sqlPeriodeExcBase);
        
        // Weekend de Pâques (excursionnistes)
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee]);
        $presencesWeekendPaques = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 1]);
        $presencesWeekendPaquesN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 2]);
        $presencesWeekendPaquesN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 3]);
        $presencesWeekendPaquesN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_paques', $annee - 4]);
        $presencesWeekendPaquesN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct19 = $presencesWeekendPaquesN1 > 0 ? round((($presencesWeekendPaques - $presencesWeekendPaquesN1) / $presencesWeekendPaquesN1) * 100, 1) : null;
        $evolutionPct19N1 = $presencesWeekendPaquesN2 > 0 ? round((($presencesWeekendPaquesN1 - $presencesWeekendPaquesN2) / $presencesWeekendPaquesN2) * 100, 1) : null;
        $evolutionPct19N2 = $presencesWeekendPaquesN3 > 0 ? round((($presencesWeekendPaquesN2 - $presencesWeekendPaquesN3) / $presencesWeekendPaquesN3) * 100, 1) : null;
        $evolutionPct19N3 = $presencesWeekendPaquesN4 > 0 ? round((($presencesWeekendPaquesN3 - $presencesWeekendPaquesN4) / $presencesWeekendPaquesN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 19,
            'indicateur' => '19. Weekend de Pâques',
            'N' => $presencesWeekendPaques,
            'N_1' => $presencesWeekendPaquesN1,
            'N_2' => $presencesWeekendPaquesN2,
            'N_3' => $presencesWeekendPaquesN3,
            'evolution_pct' => $evolutionPct19,
            'evolution_pct_N1' => $evolutionPct19N1,
            'evolution_pct_N2' => $evolutionPct19N2,
            'evolution_pct_N3' => $evolutionPct19N3,
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes weekend de Pâques'
        ];
        
        // 20. 1er mai (excursionnistes) - Date fixe
        $sql1erMaiExc = "
            SELECT COALESCE(SUM(f.volume), 0) as total
            FROM fact_diurnes f
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date = ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'EXCURSIONNISTE'
            AND p.nom_provenance != 'LOCAL'
            AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sql1erMaiExc);
        $stmt->execute(["$annee-05-01", $zoneMapped]);
        $presences1erMai = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 1) . "-05-01", $zoneMapped]);
        $presences1erMaiN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 2) . "-05-01", $zoneMapped]);
        $presences1erMaiN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 3) . "-05-01", $zoneMapped]);
        $presences1erMaiN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 4) . "-05-01", $zoneMapped]);
        $presences1erMaiN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct20 = $presences1erMaiN1 > 0 ? round((($presences1erMai - $presences1erMaiN1) / $presences1erMaiN1) * 100, 1) : null;
        $evolutionPct20N1 = $presences1erMaiN2 > 0 ? round((($presences1erMaiN1 - $presences1erMaiN2) / $presences1erMaiN2) * 100, 1) : null;
        $evolutionPct20N2 = $presences1erMaiN3 > 0 ? round((($presences1erMaiN2 - $presences1erMaiN3) / $presences1erMaiN3) * 100, 1) : null;
        $evolutionPct20N3 = $presences1erMaiN4 > 0 ? round((($presences1erMaiN3 - $presences1erMaiN4) / $presences1erMaiN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 20,
            'indicateur' => '20. 1er mai',
            'N' => $presences1erMai,
            'N_1' => $presences1erMaiN1,
            'N_2' => $presences1erMaiN2,
            'N_3' => $presences1erMaiN3,
            'evolution_pct' => $evolutionPct20,
            'evolution_pct_N1' => $evolutionPct20N1,
            'evolution_pct_N2' => $evolutionPct20N2,
            'evolution_pct_N3' => $evolutionPct20N3,
            'date' => "$annee-05-01",
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes 1er mai'
        ];
        
        // 21. Weekend de l'Ascension (excursionnistes)
        $stmt = $pdo->prepare($sqlPeriodeExcBase);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee]);
        $presencesWeekendAscension = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 1]);
        $presencesWeekendAscensionN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 2]);
        $presencesWeekendAscensionN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 3]);
        $presencesWeekendAscensionN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_ascension', $annee - 4]);
        $presencesWeekendAscensionN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct21 = $presencesWeekendAscensionN1 > 0 ? round((($presencesWeekendAscension - $presencesWeekendAscensionN1) / $presencesWeekendAscensionN1) * 100, 1) : null;
        $evolutionPct21N1 = $presencesWeekendAscensionN2 > 0 ? round((($presencesWeekendAscensionN1 - $presencesWeekendAscensionN2) / $presencesWeekendAscensionN2) * 100, 1) : null;
        $evolutionPct21N2 = $presencesWeekendAscensionN3 > 0 ? round((($presencesWeekendAscensionN2 - $presencesWeekendAscensionN3) / $presencesWeekendAscensionN3) * 100, 1) : null;
        $evolutionPct21N3 = $presencesWeekendAscensionN4 > 0 ? round((($presencesWeekendAscensionN3 - $presencesWeekendAscensionN4) / $presencesWeekendAscensionN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 21,
            'indicateur' => '21. Weekend de l\'Ascension',
            'N' => $presencesWeekendAscension,
            'N_1' => $presencesWeekendAscensionN1,
            'N_2' => $presencesWeekendAscensionN2,
            'N_3' => $presencesWeekendAscensionN3,
            'evolution_pct' => $evolutionPct21,
            'evolution_pct_N1' => $evolutionPct21N1,
            'evolution_pct_N2' => $evolutionPct21N2,
            'evolution_pct_N3' => $evolutionPct21N3,
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes weekend de l\'Ascension'
        ];
        
        // 22. Weekend de la Pentecôte (excursionnistes)
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee]);
        $presencesWeekendPentecote = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 1]);
        $presencesWeekendPentecoteN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 2]);
        $presencesWeekendPentecoteN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 3]);
        $presencesWeekendPentecoteN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([$zoneMapped, 'weekend_pentecote', $annee - 4]);
        $presencesWeekendPentecoteN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        $evolutionPct22 = $presencesWeekendPentecoteN1 > 0 ? round((($presencesWeekendPentecote - $presencesWeekendPentecoteN1) / $presencesWeekendPentecoteN1) * 100, 1) : null;
        $evolutionPct22N1 = $presencesWeekendPentecoteN2 > 0 ? round((($presencesWeekendPentecoteN1 - $presencesWeekendPentecoteN2) / $presencesWeekendPentecoteN2) * 100, 1) : null;
        $evolutionPct22N2 = $presencesWeekendPentecoteN3 > 0 ? round((($presencesWeekendPentecoteN2 - $presencesWeekendPentecoteN3) / $presencesWeekendPentecoteN3) * 100, 1) : null;
        $evolutionPct22N3 = $presencesWeekendPentecoteN4 > 0 ? round((($presencesWeekendPentecoteN3 - $presencesWeekendPentecoteN4) / $presencesWeekendPentecoteN4) * 100, 1) : null;
        
        $indicators[] = [
            'numero' => 22,
            'indicateur' => '22. Weekend de la Pentecôte',
            'N' => $presencesWeekendPentecote,
            'N_1' => $presencesWeekendPentecoteN1,
            'N_2' => $presencesWeekendPentecoteN2,
            'N_3' => $presencesWeekendPentecoteN3,
            'evolution_pct' => $evolutionPct22,
            'evolution_pct_N1' => $evolutionPct22N1,
            'evolution_pct_N2' => $evolutionPct22N2,
            'evolution_pct_N3' => $evolutionPct22N3,
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes weekend de la Pentecôte'
        ];
    }
    
    // ====== INDICATEURS JOURS FÉRIÉS - EXCURSIONNISTES (23-24) ======
    // Vérifier si les dates sont dans la plage sélectionnée
    $startDate = new DateTime($dateRanges['start']);
    $endDate = new DateTime($dateRanges['end']);
    
    // 23. 14 juillet (excursionnistes) - Date fixe
    $date14Juillet = new DateTime("$annee-07-14");
    if ($date14Juillet >= $startDate && $date14Juillet <= $endDate && in_array('fact_diurnes', $tables)) {
        $sql14Juillet = "
            SELECT COALESCE(SUM(f.volume), 0) as total
            FROM fact_diurnes f
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date = ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'EXCURSIONNISTE'
            AND p.nom_provenance != 'LOCAL'
            AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sql14Juillet);
        $stmt->execute(["$annee-07-14", $zoneMapped]);
        $presences14Juillet = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 1) . "-07-14", $zoneMapped]);
        $presences14JuilletN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 2) . "-07-14", $zoneMapped]);
        $presences14JuilletN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 3) . "-07-14", $zoneMapped]);
        $presences14JuilletN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 4) . "-07-14", $zoneMapped]);
        $presences14JuilletN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        // ✅ NOUVEAU CALCUL : Évolutions avec année sélectionnée comme référence
        $evolutionPct23 = calculateEvolutionFromReference($presences14Juillet, $presences14JuilletN1);
        $evolutionPct23N1 = calculateEvolutionFromReference($presences14Juillet, $presences14JuilletN2);
        $evolutionPct23N2 = calculateEvolutionFromReference($presences14Juillet, $presences14JuilletN3);
        $evolutionPct23N3 = calculateEvolutionFromReference($presences14Juillet, $presences14JuilletN4);
        
        $indicators[] = [
            'numero' => 23,
            'indicateur' => '23. 14 juillet',
            'N' => $presences14Juillet,
            'N_1' => $presences14JuilletN1,
            'N_2' => $presences14JuilletN2,
            'N_3' => $presences14JuilletN3,
            'evolution_pct' => $evolutionPct23,
            'evolution_pct_N1' => $evolutionPct23N1,
            'evolution_pct_N2' => $evolutionPct23N2,
            'evolution_pct_N3' => $evolutionPct23N3,
            'date' => "$annee-07-14",
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes 14 juillet',
            'annee_reference' => $annee
        ];
    }
    
    // 24. 15 août (excursionnistes) - Date fixe
    $date15Aout = new DateTime("$annee-08-15");
    if ($date15Aout >= $startDate && $date15Aout <= $endDate && in_array('fact_diurnes', $tables)) {
        $sql15Aout = "
            SELECT COALESCE(SUM(f.volume), 0) as total
            FROM fact_diurnes f
            INNER JOIN dim_zones_observation z ON f.id_zone = z.id_zone
            INNER JOIN dim_categories_visiteur c ON f.id_categorie = c.id_categorie
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date = ?
            AND z.nom_zone = ?
            AND c.nom_categorie = 'EXCURSIONNISTE'
            AND p.nom_provenance != 'LOCAL'
            AND f.volume > 0
        ";
        
        $stmt = $pdo->prepare($sql15Aout);
        $stmt->execute(["$annee-08-15", $zoneMapped]);
        $presences15Aout = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 1) . "-08-15", $zoneMapped]);
        $presences15AoutN1 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 2) . "-08-15", $zoneMapped]);
        $presences15AoutN2 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 3) . "-08-15", $zoneMapped]);
        $presences15AoutN3 = (int)($stmt->fetch()['total'] ?? 0);
        $stmt->execute([($annee - 4) . "-08-15", $zoneMapped]);
        $presences15AoutN4 = (int)($stmt->fetch()['total'] ?? 0);
        
        // ✅ NOUVEAU CALCUL : Évolutions avec année sélectionnée comme référence
        $evolutionPct24 = calculateEvolutionFromReference($presences15Aout, $presences15AoutN1);
        $evolutionPct24N1 = calculateEvolutionFromReference($presences15Aout, $presences15AoutN2);
        $evolutionPct24N2 = calculateEvolutionFromReference($presences15Aout, $presences15AoutN3);
        $evolutionPct24N3 = calculateEvolutionFromReference($presences15Aout, $presences15AoutN4);
        
        $indicators[] = [
            'numero' => 24,
            'indicateur' => '24. 15 août',
            'N' => $presences15Aout,
            'N_1' => $presences15AoutN1,
            'N_2' => $presences15AoutN2,
            'N_3' => $presences15AoutN3,
            'evolution_pct' => $evolutionPct24,
            'evolution_pct_N1' => $evolutionPct24N1,
            'evolution_pct_N2' => $evolutionPct24N2,
            'evolution_pct_N3' => $evolutionPct24N3,
            'date' => "$annee-08-15",
            'unite' => 'Présences',
            'remarque' => 'Excursionnistes 15 août',
            'annee_reference' => $annee
        ];
    }
    
    // Résultat final
    $result = [
        'zone_observation' => $zoneMapped,
        'annee' => $annee,
        'periode' => $periode,
        'debut' => $dateRanges['start'],
        'fin' => $dateRanges['end'],
        'bloc_a' => $indicators,
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