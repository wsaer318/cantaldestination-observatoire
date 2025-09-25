<?php
/**
 * API Bloc D6 - Données par Âge
 * Basée sur la logique de bloc_a_working.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Récupération directe des paramètres
$annee = $_GET['annee'] ?? null;
$periode = $_GET['periode'] ?? null;
$zone = $_GET['zone'] ?? null;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$limit = intval($_GET['limit'] ?? 3); // Top 3 pour l'âge

if (!$annee || !$periode || !$zone) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants: annee, periode, zone requis']);
            // Informations sur les tables temporaires disponibles
            $response['temp_tables_available'] = $tempManager->getAvailableTempTables();
    exit;
}

// Inclure le gestionnaire intelligent des périodes
require_once dirname(__DIR__, 2) . '/periodes_manager_db.php';
require_once dirname(__DIR__, 3) . '/classes/ZoneMapper.php';

/**
 * Calcule les plages de dates selon la période - VERSION INTELLIGENTE
 * Utilise les vraies dates depuis la base de données
 */
function calculateWorkingDateRanges($annee, $periode) {
    return PeriodesManagerDB::calculateDateRanges($annee, $periode);
}

try {
    // Utiliser notre système de configuration de base de données
    require_once dirname(__DIR__, 3) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // ✅ Vérifier si la table fact_nuitees_age existe
    $tables = $pdo->query("SHOW TABLES LIKE 'fact_nuitees_age'")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erreur API Age',
            'message' => 'La table fact_nuitees_age n\'existe pas dans la base de données',
            'available_tables' => $pdo->query("SHOW TABLES LIKE 'fact_%'")->fetchAll(PDO::FETCH_COLUMN)
        ]);
        exit;
    }
    
    // Tables temporaires supprimées - utilisation directe des tables principales
    
    // ✅ Optimisation : Créer les index si ils n'existent pas
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_age_date_zone_prov ON fact_nuitees_age (date, id_zone, id_provenance)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fact_age_age_date ON fact_nuitees_age (id_age, date)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_dim_zones_nom ON dim_zones_observation (nom_zone)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_dim_prov_nom ON dim_provenances (nom_provenance)");
    } catch (Exception $e) {
        // Ignorer les erreurs d'index (ils existent peut-être déjà)
    }
    
    // Normalisation des paramètres
    $zoneMapped = ZoneMapper::displayToBase($zone);
    
    // Calcul des plages de dates
    $dateRanges = calculateWorkingDateRanges($annee, $periode);
    $prevYear = (int)$annee - 1;
    $prevDateRanges = calculateWorkingDateRanges($prevYear, $periode);
    
    // ✅ APPROCHE ULTRA-OPTIMISÉE : requêtes directes sans JOIN multiples
    
    // 1. Récupérer les IDs nécessaires en une fois
    $sqlIds = "
        SELECT z.id_zone, p.id_provenance 
        FROM dim_zones_observation z, dim_provenances p 
        WHERE z.nom_zone = ? AND p.nom_provenance = 'NONLOCAL'
        LIMIT 1
    ";
    $stmtIds = $pdo->prepare($sqlIds);
    $stmtIds->execute([$zoneMapped]);
    $ids = $stmtIds->fetch(PDO::FETCH_ASSOC);
    
    if (!$ids) {
        throw new Exception("Zone ou provenance non trouvée");
    }
    
    $idZone = $ids['id_zone'];
    $idProvenance = $ids['id_provenance'];
    
    // 2. Requête ultra-rapide année courante (sans JOIN sur dimensions)
    // Prendre plus de résultats initialement pour compenser le filtrage
    $sqlN = "
        SELECT id_age, SUM(volume) as n_nuitees
        FROM fact_nuitees_age 
        WHERE date BETWEEN ? AND ? 
        AND id_zone = ? 
        AND id_provenance = ?
        GROUP BY id_age 
        ORDER BY n_nuitees DESC 
        LIMIT " . intval($limit + 2) . "
    ";
    
    $stmtN = $pdo->prepare($sqlN);
    $stmtN->execute([$dateRanges['start'], $dateRanges['end'], $idZone, $idProvenance]);
    $dataRawN = $stmtN->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dataRawN)) {
        $results = [['rang' => 1, 'age' => 'Aucune donnée', 'n_nuitees' => 0, 'n_nuitees_n1' => 0, 'delta_pct' => null, 'part_pct' => 0]];
        header('Content-Type: application/json');
        echo json_encode($results, JSON_PRETTY_PRINT);
        exit;
    }
    
    // 3. Récupérer les noms des tranches d'âge pour les ID trouvés
    $ageIds = array_column($dataRawN, 'id_age');
    $placeholders = str_repeat('?,', count($ageIds) - 1) . '?';
    $sqlAges = "SELECT id_age, tranche_age FROM dim_tranches_age WHERE id_age IN ($placeholders)";
    $stmtAges = $pdo->prepare($sqlAges);
    $stmtAges->execute($ageIds);
    $ageNames = [];
    foreach ($stmtAges->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ageNames[$row['id_age']] = $row['tranche_age'];
    }
    
    // 4. Filtrer les données pour exclure les âges 'CUMUL' ou non trouvés
    $dataRawNFiltered = [];
    foreach ($dataRawN as $row) {
        $idAge = $row['id_age'];
        $ageName = $ageNames[$idAge] ?? null;
        
        // Exclure les âges CUMUL, nulls, ou non trouvés
        if ($ageName && $ageName !== 'CUMUL' && trim($ageName) !== '') {
            $dataRawNFiltered[] = $row;
        }
    }
    
    // Si pas de données valides après filtrage
    if (empty($dataRawNFiltered)) {
        $results = [['rang' => 1, 'age' => 'Aucune donnée', 'n_nuitees' => 0, 'n_nuitees_n1' => 0, 'delta_pct' => null, 'part_pct' => 0]];
        header('Content-Type: application/json');
        echo json_encode($results, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Utiliser les données filtrées
    $dataRawN = $dataRawNFiltered;
    $ageIds = array_column($dataRawN, 'id_age');
    
    // 5. Requête ultra-rapide année précédente (seulement pour les IDs valides)
    $placeholders = str_repeat('?,', count($ageIds) - 1) . '?'; // Recalculer avec les IDs filtrés
    $sqlN1 = "
        SELECT id_age, SUM(volume) as n_nuitees_n1
        FROM fact_nuitees_age 
        WHERE date BETWEEN ? AND ? 
        AND id_zone = ? 
        AND id_provenance = ?
        AND id_age IN ($placeholders)
        GROUP BY id_age
    ";
    
    $paramsN1 = array_merge(
        [$prevDateRanges['start'], $prevDateRanges['end'], $idZone, $idProvenance],
        $ageIds
    );
    
    $stmtN1 = $pdo->prepare($sqlN1);
    $stmtN1->execute($paramsN1);
    $dataN1 = [];
    foreach ($stmtN1->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dataN1[$row['id_age']] = (int)$row['n_nuitees_n1'];
    }
    
    // Calculer le total pour les pourcentages (avec données filtrées)
    $totalNuitees = array_sum(array_column($dataRawN, 'n_nuitees'));
    
    // Formater les résultats avec calcul des pourcentages
    $results = [];
    $rang = 1;
    foreach ($dataRawN as $row) {
        $idAge = $row['id_age'];
        $ageName = $ageNames[$idAge] ?? 'Inconnu'; // Sécuriser l'accès
        
        // Double vérification pour éviter les âges invalides
        if (!$ageName || $ageName === 'CUMUL' || trim($ageName) === '') {
            continue; // Ignorer cette entrée
        }
        
        $nuitees = (int)$row['n_nuitees'];
        $nuiteesN1 = $dataN1[$idAge] ?? 0;
        $partPct = $totalNuitees > 0 ? round(($nuitees / $totalNuitees) * 100, 1) : 0;
        
        // Calcul de l'évolution
        $deltaPct = null;
        if ($nuiteesN1 > 0) {
            $deltaPct = round((($nuitees - $nuiteesN1) / $nuiteesN1) * 100, 1);
        } elseif ($nuitees > 0) {
            $deltaPct = 100; // Nouvelle donnée par rapport à N-1
        }
        
        $results[] = [
            'rang' => $rang,
            'age' => $ageName,
            'n_nuitees' => $nuitees,
            'n_nuitees_n1' => $nuiteesN1,
            'delta_pct' => $deltaPct,
            'part_pct' => $partPct
        ];
        $rang++;
        
        // Respecter la limite après filtrage
        if ($rang > $limit) {
            break;
        }
    }
    
    // Retourner les résultats dans le même format que bloc_a_working.php
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur API Age',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 
