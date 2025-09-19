<?php
/**
 * Exemple d'API utilisant la colonne is_provisional
 * Au lieu de faire des UNION entre tables principales et temporaires
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/config.php';
require_once '../database.php';

try {
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Paramètres
    $mode = $_GET['mode'] ?? 'all'; // 'main', 'provisional', 'all'
    $periode_debut = $_GET['periode_debut'] ?? '';
    $periode_fin = $_GET['periode_fin'] ?? '';
    $zone_id = $_GET['zone_id'] ?? '';
    
    // Construction de la requête avec la nouvelle logique
    $whereConditions = ['1=1'];
    $params = [];
    
    // Filtre sur le mode de données
    switch ($mode) {
        case 'main':
            $whereConditions[] = 'f.is_provisional = FALSE';
            break;
        case 'provisional':
            $whereConditions[] = 'f.is_provisional = TRUE';
            break;
        case 'all':
        default:
            // Pas de filtre sur is_provisional
            break;
    }
    
    // Filtres classiques
    if (!empty($periode_debut)) {
        $whereConditions[] = 'f.date >= :periode_debut';
        $params['periode_debut'] = $periode_debut;
    }
    
    if (!empty($periode_fin)) {
        $whereConditions[] = 'f.date <= :periode_fin';
        $params['periode_fin'] = $periode_fin;
    }
    
    if (!empty($zone_id)) {
        $whereConditions[] = 'f.id_zone = :zone_id';
        $params['zone_id'] = $zone_id;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // EXEMPLE 1 : Top 5 Régions (avec la nouvelle approche)
    $sql = "
        SELECT 
            r.nom as region,
            SUM(f.volume) as total_volume,
            COUNT(*) as nb_enregistrements,
            SUM(CASE WHEN f.is_provisional = TRUE THEN f.volume ELSE 0 END) as volume_provisoire,
            SUM(CASE WHEN f.is_provisional = FALSE THEN f.volume ELSE 0 END) as volume_principal,
            f.is_provisional,
            GROUP_CONCAT(DISTINCT f.date ORDER BY f.date) as dates
        FROM fact_nuitees f
        JOIN dim_zones z ON f.id_zone = z.id
        JOIN dim_regions r ON z.id_region = r.id
        WHERE $whereClause
        GROUP BY r.id, r.nom, f.is_provisional
        ORDER BY total_volume DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // EXEMPLE 2 : Statistiques globales par type de données
    $statsSql = "
        SELECT 
            f.is_provisional,
            COUNT(*) as nb_enregistrements,
            SUM(f.volume) as total_volume,
            AVG(f.volume) as volume_moyen,
            MIN(f.date) as date_min,
            MAX(f.date) as date_max,
            COUNT(DISTINCT f.date) as nb_dates_distinctes
        FROM fact_nuitees f
        WHERE $whereClause
        GROUP BY f.is_provisional
    ";
    
    $stmt = $pdo->prepare($statsSql);
    $stmt->execute($params);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // EXEMPLE 3 : Évolution temporelle (toutes données confondues)
    $evolutionSql = "
        SELECT 
            f.date,
            SUM(f.volume) as volume_total,
            SUM(CASE WHEN f.is_provisional = FALSE THEN f.volume ELSE 0 END) as volume_principal,
            SUM(CASE WHEN f.is_provisional = TRUE THEN f.volume ELSE 0 END) as volume_provisoire,
            COUNT(*) as nb_enregistrements
        FROM fact_nuitees f
        WHERE $whereClause
        GROUP BY f.date
        ORDER BY f.date
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($evolutionSql);
    $stmt->execute($params);
    $evolution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatage de la réponse
    $response = [
        'success' => true,
        'mode' => $mode,
        'filtres' => [
            'periode_debut' => $periode_debut,
            'periode_fin' => $periode_fin,
            'zone_id' => $zone_id
        ],
        'statistiques_par_type' => $stats,
        'top_regions' => $regions,
        'evolution_temporelle' => $evolution,
        'metadata' => [
            'total_resultats' => count($regions),
            'generated_at' => date('Y-m-d H:i:s'),
            'sql_used' => [
                'regions' => $sql,
                'stats' => $statsSql,
                'evolution' => $evolutionSql
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

<!-- 
EXEMPLES D'UTILISATION :

1. Données principales uniquement :
   GET /api/example_with_provisional.php?mode=main

2. Données provisoires uniquement :  
   GET /api/example_with_provisional.php?mode=provisional

3. Toutes les données (principal + provisoire) :
   GET /api/example_with_provisional.php?mode=all

4. Avec filtres de période :
   GET /api/example_with_provisional.php?mode=all&periode_debut=2024-01-01&periode_fin=2024-12-31

AVANTAGES de cette approche :
✅ UNE SEULE requête au lieu de UNION
✅ Possibilité de mélanger ou séparer les données
✅ Statistiques par type de données (principal/provisoire)
✅ Plus de flexibilité pour les analyses
✅ Requêtes plus rapides et plus simples
✅ Index optimisés sur (date, is_provisional)
--> 