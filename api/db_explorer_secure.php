<?php
/**
 * Explorateur de base de données FluxVision - Version sécurisée
 * Les informations de connexion sont envoyées par requête HTTP
 */

// Configuration de sécurité
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Clé d'authentification simple (à changer en production)
$API_KEY = 'fluxvision_2024_secure_key';

// Vérification de l'authentification
if (!isset($_GET['key']) || $_GET['key'] !== $API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Clé d\'authentification invalide']);
    exit;
}

// Récupération des paramètres de connexion depuis la requête
$host = $_GET['host'] ?? $_POST['host'] ?? 'localhost';
$dbname = $_GET['dbname'] ?? $_POST['dbname'] ?? '';
$username = $_GET['username'] ?? $_POST['username'] ?? '';
$password = $_GET['password'] ?? $_POST['password'] ?? '';

// Validation des paramètres requis
if (empty($dbname) || empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Paramètres de connexion manquants',
        'required' => ['host', 'dbname', 'username', 'password']
    ]);
    exit;
}

try {
    // Connexion à la base de données avec les paramètres fournis
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'info';
    $response = [];
    
    switch ($action) {
        case 'info':
            // Informations générales de la base
            $stmt = $pdo->query("SELECT DATABASE() as db_name, VERSION() as version");
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = [
                'success' => true,
                'database' => $info['db_name'],
                'mysql_version' => $info['version'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
        case 'tables':
            // Lister toutes les tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response = [
                'success' => true,
                'tables' => $tables,
                'count' => count($tables)
            ];
            break;
            
        case 'structure':
            // Structure d'une table spécifique
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            if (empty($table)) {
                throw new Exception('Nom de table requis');
            }
            
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = [
                'success' => true,
                'table' => $table,
                'structure' => $columns
            ];
            break;
            
        case 'count':
            // Compter les enregistrements d'une table
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            if (empty($table)) {
                throw new Exception('Nom de table requis');
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = [
                'success' => true,
                'table' => $table,
                'count' => (int)$count['total']
            ];
            break;
            
        case 'sample':
            // Échantillon de données d'une table
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            $limit = min((int)($_GET['limit'] ?? $_POST['limit'] ?? 5), 50); // Max 50 lignes
            
            if (empty($table)) {
                throw new Exception('Nom de table requis');
            }
            
            $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $limit");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = [
                'success' => true,
                'table' => $table,
                'limit' => $limit,
                'data' => $data
            ];
            break;
            
        case 'fluxvision_stats':
            // Statistiques spécifiques FluxVision
            $stats = [];
            
            // Tables de dimensions
            $dimension_tables = [
                'dim_zones_observation', 'dim_provenances', 'dim_categories_visiteur',
                'dim_pays', 'dim_departements', 'dim_communes', 'dim_epci',
                'dim_dates', 'dim_durees_sejour'
            ];
            
            foreach ($dimension_tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['dimensions'][$table] = (int)$count['total'];
                } catch (Exception $e) {
                    $stats['dimensions'][$table] = 'inexistante';
                }
            }
            
            // Tables de faits
            $fact_tables = [
                'fact_diurnes', 'fact_nuitees', 'fact_diurnes_departements',
                'fact_nuitees_departements', 'fact_diurnes_pays', 'fact_nuitees_pays',
                'fact_sejours_duree', 'fact_sejours_duree_departements', 'fact_sejours_duree_pays'
            ];
            
            // Tables Lieu*
            $lieu_tables = [
                'fact_lieu_activite_soir', 'fact_lieu_activite_veille',
                'fact_lieu_nuitee_soir', 'fact_lieu_nuitee_veille'
            ];
            
            foreach (array_merge($fact_tables, $lieu_tables) as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['facts'][$table] = (int)$count['total'];
                } catch (Exception $e) {
                    $stats['facts'][$table] = 'inexistante';
                }
            }
            
            $response = [
                'success' => true,
                'fluxvision_stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de connexion à la base de données',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
