<?php
/**\n * API Bloc D3 - Données des Pays (SUPER-OPTIMISÉE) - Version avec support des tables temporaires\n * Bascule automatiquement vers les tables temporaires si les données ne sont pas dans les tables principales\n * Version ultra-rapide avec une seule requête complexe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure le gestionnaire intelligent des périodes
require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../../classes/ZoneMapper.php';

// Récupération directe des paramètres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debutOverride = $_GET['debut'] ?? null;
$finOverride = $_GET['fin'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$limit = intval($_GET['limit'] ?? 5);

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

try {
    // Utiliser notre système de configuration de base de données
    require_once dirname(dirname(__DIR__)) . '/database.php';
    $pdo = DatabaseConfig::getConnection();
    
    // Tables temporaires supprimées - utilisation directe des tables principales
    
    $zoneMapped = ZoneMapper::displayToBase($zone);
    if ($debutOverride && $finOverride) {
        $start = new DateTime($debutOverride . ' 00:00:00');
        $end = new DateTime($finOverride . ' 23:59:59');
        $dateRanges = [ 'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s') ];
        $s1 = (clone $start)->modify('-1 year');
        $e1 = (clone $end)->modify('-1 year');
        $dateRangesN1 = [ 'start' => $s1->format('Y-m-d H:i:s'), 'end' => $e1->format('Y-m-d H:i:s') ];
    } else {
        $dateRanges = calculateWorkingDateRanges($annee, $periode);
        $dateRangesN1 = calculateWorkingDateRanges($annee - 1, $periode);
    }
    
    // Vérifier les tables disponibles
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('fact_nuitees_pays', $tables)) {
        // === REQUÊTE HYPER-OPTIMISÉE : Double requête avec sous-requêtes ===
        $sql = "
        SELECT 
            pays_data.nom_pays,
            pays_data.n_nuitees,
            COALESCE(pays_n1.n_nuitees_n1, 0) as n_nuitees_n1,
            ROUND(
                CASE 
                    WHEN COALESCE(pays_n1.n_nuitees_n1, 0) > 0 AND pays_data.n_nuitees > 0 
                    THEN ((pays_data.n_nuitees - pays_n1.n_nuitees_n1) / pays_n1.n_nuitees_n1) * 100
                    ELSE 0 
                END, 1
            ) as delta_pct,
            ROUND(
                (pays_data.n_nuitees * 100.0) / total_pays.total_nuitees, 1
            ) as part_pct
        FROM (
            -- Sous-requête 1: Données année N
            SELECT 
                dp.nom_pays,
                dp.id_pays,
                ROUND(SUM(fnp.volume)) as n_nuitees
            FROM fact_nuitees_pays fnp
            INNER JOIN dim_pays dp ON fnp.id_pays = dp.id_pays
            INNER JOIN dim_zones_observation dzo ON fnp.id_zone = dzo.id_zone
            INNER JOIN dim_provenances dp_prov ON fnp.id_provenance = dp_prov.id_provenance
            INNER JOIN dim_categories_visiteur dcv ON fnp.id_categorie = dcv.id_categorie
            WHERE fnp.date BETWEEN ? AND ?
            AND dzo.nom_zone = ?
            AND dcv.nom_categorie = 'TOURISTE'
            AND dp_prov.nom_provenance = 'ETRANGER'
            AND dp.nom_pays NOT IN ('CUMUL')
            GROUP BY dp.id_pays, dp.nom_pays
            HAVING n_nuitees > 0
            ORDER BY n_nuitees DESC
            LIMIT " . intval($limit) . "
        ) pays_data
        LEFT JOIN (
            -- Sous-requête 2: Données année N-1
            SELECT 
                dp.id_pays,
                ROUND(SUM(fnp.volume)) as n_nuitees_n1
            FROM fact_nuitees_pays fnp
            INNER JOIN dim_pays dp ON fnp.id_pays = dp.id_pays
            INNER JOIN dim_zones_observation dzo ON fnp.id_zone = dzo.id_zone
            INNER JOIN dim_provenances dp_prov ON fnp.id_provenance = dp_prov.id_provenance
            INNER JOIN dim_categories_visiteur dcv ON fnp.id_categorie = dcv.id_categorie
            WHERE fnp.date BETWEEN ? AND ?
            AND dzo.nom_zone = ?
            AND dcv.nom_categorie = 'TOURISTE'
            AND dp_prov.nom_provenance = 'ETRANGER'
            AND dp.nom_pays NOT IN ('CUMUL')
            GROUP BY dp.id_pays
        ) pays_n1 ON pays_data.id_pays = pays_n1.id_pays
        CROSS JOIN (
            -- Sous-requête 3: Total pour les pourcentages
            SELECT SUM(fnp.volume) as total_nuitees
            FROM fact_nuitees_pays fnp
            INNER JOIN dim_zones_observation dzo ON fnp.id_zone = dzo.id_zone
            INNER JOIN dim_provenances dp_prov ON fnp.id_provenance = dp_prov.id_provenance
            INNER JOIN dim_categories_visiteur dcv ON fnp.id_categorie = dcv.id_categorie
            WHERE fnp.date BETWEEN ? AND ?
            AND dzo.nom_zone = ?
            AND dcv.nom_categorie = 'TOURISTE'
            AND dp_prov.nom_provenance = 'ETRANGER'
        ) total_pays
        ORDER BY pays_data.n_nuitees DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            // Pour pays_data (année N)
            $dateRanges['start'], $dateRanges['end'], $zoneMapped,
            // Pour pays_n1 (année N-1)
            $dateRangesN1['start'], $dateRangesN1['end'], $zoneMapped,
            // Pour total_pays
            $dateRanges['start'], $dateRanges['end'], $zoneMapped
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ✅ GESTION DES DONNÉES VIDES (comme dashboard temps réel)
        if (empty($results)) {
            $results = [['nom_pays' => 'Aucune donnée', 'n_nuitees' => 0, 'n_nuitees_n1' => 0, 'delta_pct' => null, 'part_pct' => 0]];
        } else {
            // Création d'index pour optimiser les performances futures
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_nuitees_pays_date_zone ON fact_nuitees_pays (date, id_zone)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_nuitees_pays_pays_date ON fact_nuitees_pays (id_pays, date)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_nuitees_pays_composite ON fact_nuitees_pays (id_zone, id_provenance, id_categorie, date)");
        }
        
    } else {
        // ✅ GESTION PROPRE : Table inexistante (cas d'erreur système)
        $results = [['nom_pays' => 'Erreur système', 'n_nuitees' => 0, 'n_nuitees_n1' => 0, 'delta_pct' => null, 'part_pct' => 0]];
    }
    
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API Pays (Super-optimisée)',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 