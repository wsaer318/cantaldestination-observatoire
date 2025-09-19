<?php
/**
 * Explorateur de base de données distant sécurisé
 * Permet d'explorer la base de données depuis un ordinateur distant
 * ⚠️ ACCÈS RÉSERVÉ AUX ADMINISTRATEURS UNIQUEMENT
 */

// Configuration de sécurité
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Vérification de l'authentification
session_start();

// Configuration de la base de données
$db_config = [
    'host' => 'localhost',
    'dbname' => 'observatoire',
    'username' => 'observatoire',
    'password' => 'Sf4d8gsfsdGsg59sqq54g'
];

// Clé d'authentification pour accès distant
$REMOTE_API_KEY = 'observatoire_remote_2024_' . date('Y-m-d');

// Vérification de la clé d'authentification
$provided_key = $_GET['key'] ?? $_POST['key'] ?? '';
if ($provided_key !== $REMOTE_API_KEY) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Clé d\'authentification invalide',
        'note' => 'La clé change quotidiennement'
    ]);
    exit;
}

// Vérification de l'adresse IP (optionnel - pour plus de sécurité)
$allowed_ips = [
    // Ajoutez ici vos adresses IP autorisées
    // '123.456.789.012', // Votre IP fixe
    // '098.765.432.101'  // Autre IP autorisée
];

$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Accès interdit depuis cette adresse IP',
        'your_ip' => $client_ip,
        'note' => 'Contactez l\'administrateur pour autoriser votre IP'
    ]);
    exit;
}

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'info';
    $response = [];
    
    switch ($action) {
        case 'info':
            // Informations générales de la base
            $stmt = $pdo->query("SELECT DATABASE() as db_name, VERSION() as version");
            $info = $stmt->fetch();
            $response = [
                'success' => true,
                'database' => $info['db_name'],
                'mysql_version' => $info['version'],
                'timestamp' => date('Y-m-d H:i:s'),
                'server_timezone' => date_default_timezone_get()
            ];
            break;
            
        case 'tables':
            // Lister toutes les tables avec informations
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $tables_info = [];
            foreach ($tables as $table) {
                // Compter les enregistrements
                $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
                $count = $count_stmt->fetch();
                
                // Informations sur la table
                $info_stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
                $table_info = $info_stmt->fetch();
                
                $tables_info[] = [
                    'name' => $table,
                    'records' => (int)$count['total'],
                    'engine' => $table_info['Engine'] ?? 'Unknown',
                    'size' => $table_info['Data_length'] ?? 0,
                    'index_size' => $table_info['Index_length'] ?? 0
                ];
            }
            
            $response = [
                'success' => true,
                'tables' => $tables_info,
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
            $columns = $stmt->fetchAll();
            
            // Informations supplémentaires sur la table
            $info_stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
            $table_info = $info_stmt->fetch();
            
            $response = [
                'success' => true,
                'table' => $table,
                'structure' => $columns,
                'table_info' => [
                    'engine' => $table_info['Engine'] ?? 'Unknown',
                    'collation' => $table_info['Collation'] ?? 'Unknown',
                    'rows' => $table_info['Rows'] ?? 0,
                    'avg_row_length' => $table_info['Avg_row_length'] ?? 0,
                    'data_length' => $table_info['Data_length'] ?? 0,
                    'index_length' => $table_info['Index_length'] ?? 0
                ]
            ];
            break;
            
        case 'count':
            // Compter les enregistrements d'une table
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            if (empty($table)) {
                throw new Exception('Nom de table requis');
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
            $count = $stmt->fetch();
            $response = [
                'success' => true,
                'table' => $table,
                'count' => (int)$count['total']
            ];
            break;
            
        case 'sample':
            // Échantillon de données d'une table
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            $limit = min((int)($_GET['limit'] ?? $_POST['limit'] ?? 10), 100); // Max 100 lignes
            $offset = (int)($_GET['offset'] ?? $_POST['offset'] ?? 0);
            
            if (empty($table)) {
                throw new Exception('Nom de table requis');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM `$table` LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $data = $stmt->fetchAll();
            
            // Compter le total pour la pagination
            $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
            $total = $count_stmt->fetch();
            
            $response = [
                'success' => true,
                'table' => $table,
                'sample' => $data,
                'limit' => $limit,
                'offset' => $offset,
                'total' => (int)$total['total'],
                'has_more' => ($offset + $limit) < $total['total']
            ];
            break;
            
        case 'search':
            // Recherche dans une table
            $table = $_GET['table'] ?? $_POST['table'] ?? '';
            $column = $_GET['column'] ?? $_POST['column'] ?? '';
            $search = $_GET['search'] ?? $_POST['search'] ?? '';
            $limit = min((int)($_GET['limit'] ?? $_POST['limit'] ?? 50), 100);
            
            if (empty($table) || empty($column) || empty($search)) {
                throw new Exception('Table, colonne et terme de recherche requis');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$column` LIKE ? LIMIT ?");
            $stmt->execute(['%' . $search . '%', $limit]);
            $data = $stmt->fetchAll();
            
            $response = [
                'success' => true,
                'table' => $table,
                'column' => $column,
                'search' => $search,
                'results' => $data,
                'count' => count($data),
                'limit' => $limit
            ];
            break;
            
        case 'stats':
            // Statistiques générales de la base
            $stats = [];
            
            // Taille totale de la base
            $size_stmt = $pdo->query("
                SELECT 
                    SUM(data_length + index_length) as total_size,
                    SUM(data_length) as data_size,
                    SUM(index_length) as index_size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $size_info = $size_stmt->fetch();
            
            // Nombre total d'enregistrements
            $tables_stmt = $pdo->query("SHOW TABLES");
            $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $total_records = 0;
            foreach ($tables as $table) {
                $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
                $count = $count_stmt->fetch();
                $total_records += $count['total'];
            }
            
            $response = [
                'success' => true,
                'stats' => [
                    'tables_count' => count($tables),
                    'total_records' => $total_records,
                    'total_size_bytes' => (int)$size_info['total_size'],
                    'data_size_bytes' => (int)$size_info['data_size'],
                    'index_size_bytes' => (int)$size_info['index_size'],
                    'total_size_mb' => round($size_info['total_size'] / 1024 / 1024, 2),
                    'data_size_mb' => round($size_info['data_size'] / 1024 / 1024, 2),
                    'index_size_mb' => round($size_info['index_size'] / 1024 / 1024, 2)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
        default:
            throw new Exception('Action non reconnue. Actions disponibles: info, tables, structure, count, sample, search, stats');
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de base de données',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
