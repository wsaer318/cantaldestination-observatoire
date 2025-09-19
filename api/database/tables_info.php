<?php
/**
 * CantalDestination — API: informations des tables (inventaire + stats + structure)
 * Fichier : api/database/tables_info.php
 * Méthode : GET
 * Auth : Bearer token OU session (voir _auth_helpers.php)
 *
 * Paramètres (query string):
 *   - type=dim|fact|temp|system|all   (défaut: all)
 *   - stats=true|false                (inclure agrégats globaux)
 *   - structure=true|false            (inclure colonnes + index des tables retournées)
 *   - limit=1..1000                   (défaut: 100)
 *   - offset>=0                       (défaut: 0)
 *   - suffix=_test | (test=1)         (filtre tables suffixées _test)
 *
 * Réponse : JSON
 *   {
 *     success: true,
 *     timestamp: "YYYY-mm-dd HH:ii:ss",
 *     endpoint: "/api/database/tables_info.php",
 *     database: { name, environment, host },
 *     request: {type, include_stats, include_structure, limit, offset, only_test},
 *     tables: [ { table_name, type_table, nb_lignes_approx, taille_mb, commentaire, create_time, update_time }, ...],
 *     count: N,
 *     next_offset: <int|null>,
 *     global_stats?: { ... },
 *     table_structures?: { <table_name>: {columns: [...], indexes: [...]} }
 *   }
 */

declare(strict_types=1);

// Sécurité & type de sortie
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Bootstrap
require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once dirname(dirname(__DIR__)) . '/classes/Auth.php';
require_once dirname(dirname(__DIR__)) . '/classes/Security.php';
require_once dirname(dirname(__DIR__)) . '/classes/Database.php';
// ✅ Helper d’auth API (Bearer ou session)
require_once __DIR__ . '/_auth_helpers.php';

Security::initialize();

// Méthode
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez GET.'], 405);
}

// ✅ Auth API Bearer OU session utilisateur (avec rate-limit scope)
require_api_auth_or_session(['scope' => 'tables.info']);

try {
    // Configuration DB
    $db = DatabaseConfig::getConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Paramètres
    $tableType         = $_GET['type'] ?? 'all';              // dim|fact|temp|system|all
    $includeStats      = isset($_GET['stats']) && $_GET['stats'] === 'true';
    $includeStructure  = isset($_GET['structure']) && $_GET['structure'] === 'true';
    $limit             = (int)($_GET['limit'] ?? 100);
    if ($limit < 1)   $limit = 1;
    if ($limit > 1000) $limit = 1000;
    $offset            = max(0, (int)($_GET['offset'] ?? 0));

    $suffix   = $_GET['suffix'] ?? null;            // ex: _test
    $onlyTest = isset($_GET['test']) && $_GET['test'] === '1';

    // Filtrage commun (WHERE)
    $conds = ["table_schema = :database"];

    // Suffixe _test
    if ($suffix === '_test' || $onlyTest) {
        $conds[] = "table_name LIKE '%\\_test'";
    }

    // Type de tables
    switch ($tableType) {
        case 'dim':    $conds[] = "table_name LIKE 'dim\\_%'"; break;
        case 'fact':   $conds[] = "table_name LIKE 'fact\\_%'"; break;
        case 'temp':   $conds[] = "table_name LIKE '%\\_temp'"; break;
        case 'system':
            $conds[] = "table_name NOT LIKE 'dim\\_%'";
            $conds[] = "table_name NOT LIKE 'fact\\_%'";
            $conds[] = "table_name NOT LIKE '%\\_temp'";
            break;
        case 'all':
        default:       /* no-op */
    }

    $where = implode(' AND ', $conds);

    // Liste des tables (paginée)
    $sql = "
        SELECT
          table_name,
          CASE
            WHEN table_name LIKE 'dim\\_%'  THEN 'Dimension'
            WHEN table_name LIKE 'fact\\_%' THEN 'Fait'
            WHEN table_name LIKE '%\\_temp' THEN 'Temporaire'
            ELSE 'Système'
          END AS type_table,
          table_rows AS nb_lignes_approx,
          ROUND(((data_length + index_length) / 1024 / 1024), 2) AS taille_mb,
          table_comment AS commentaire,
          create_time,
          update_time
        FROM information_schema.tables
        WHERE $where
        ORDER BY type_table, table_name
        LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':database', $db['database'], PDO::PARAM_STR);
    $stmt->bindValue(':limit',    $limit,         PDO::PARAM_INT);
    $stmt->bindValue(':offset',   $offset,        PDO::PARAM_INT);
    $stmt->execute();
    $tables = $stmt->fetchAll();

    // Statistiques globales (indicatives)
    $globalStats = null;
    if ($includeStats) {
        $statsSql = "
            SELECT
              COUNT(*) AS total_tables,
              SUM(CASE WHEN table_name LIKE 'dim\\_%'  THEN 1 ELSE 0 END)  AS nb_dimensions,
              SUM(CASE WHEN table_name LIKE 'fact\\_%' THEN 1 ELSE 0 END)  AS nb_faits,
              SUM(CASE WHEN table_name LIKE '%\\_temp' THEN 1 ELSE 0 END)  AS nb_temporaires,
              SUM(CASE WHEN table_name NOT LIKE 'dim\\_%' AND table_name NOT LIKE 'fact\\_%' AND table_name NOT LIKE '%\\_temp' THEN 1 ELSE 0 END) AS nb_systeme,
              SUM(table_rows) AS total_lignes_approx,
              ROUND(SUM((data_length + index_length) / 1024 / 1024), 2) AS taille_totale_mb
            FROM information_schema.tables
            WHERE $where";
        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->bindValue(':database', $db['database'], PDO::PARAM_STR);
        $statsStmt->execute();
        $globalStats = $statsStmt->fetch();
    }

    // Structure (colonnes + index) sur les tables retournées
    $tableStructures = null;
    if ($includeStructure && !empty($tables)) {
        $tableStructures = [];
        $columnsSql = "SELECT column_name, data_type, character_maximum_length, is_nullable, column_default, column_key, extra, column_comment, ordinal_position FROM information_schema.columns WHERE table_schema = :database AND table_name = :table_name ORDER BY ordinal_position";
        $indexesSql = "SELECT index_name, column_name, non_unique, seq_in_index, cardinality, sub_part, packed, nullable, index_type FROM information_schema.statistics WHERE table_schema = :database AND table_name = :table_name ORDER BY index_name, seq_in_index";
        $columnsStmt = $pdo->prepare($columnsSql);
        $indexesStmt = $pdo->prepare($indexesSql);
        foreach ($tables as $t) {
            $tn = $t['table_name'];
            $columnsStmt->execute([':database'=>$db['database'], ':table_name'=>$tn]);
            $indexesStmt->execute([':database'=>$db['database'], ':table_name'=>$tn]);
            $tableStructures[$tn] = [
                'columns' => $columnsStmt->fetchAll(),
                'indexes' => $indexesStmt->fetchAll(),
            ];
        }
    }

    $response = [
        'success'   => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint'  => '/api/database/tables_info.php',
        'database'  => [
            'name'        => $db['database'],
            'environment' => $db['environment'],
            'host'        => $db['host'].':'.$db['port'],
        ],
        'request' => [
            'type'              => $tableType,
            'include_stats'     => $includeStats,
            'include_structure' => $includeStructure,
            'limit'             => $limit,
            'offset'            => $offset,
            'only_test'         => (bool)$onlyTest || ($suffix === '_test'),
        ],
        'tables' => $tables,
        'count'  => count($tables),
        // Heuristique simple: s'il y a autant d'items que limit, on propose un next_offset
        'next_offset' => (count($tables) === $limit) ? ($offset + $limit) : null,
    ];

    if ($globalStats !== null)     $response['global_stats']     = $globalStats;
    if ($tableStructures !== null) $response['table_structures'] = $tableStructures;

    // Log d'accès
    error_log(sprintf(
        'DatabaseTablesInfo API by user=%s type=%s ip=%s',
        Auth::getUser()['username'] ?? 'unknown',
        $tableType,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ));

    jsonResponse($response);

} catch (PDOException $e) {
    error_log('DatabaseTablesInfo API PDO Error: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur de base de données', 'details' => (defined('DEBUG') && DEBUG) ? $e->getMessage() : 'Erreur interne'], 500);
} catch (Throwable $e) {
    error_log('DatabaseTablesInfo API Error: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur interne du serveur', 'details' => (defined('DEBUG') && DEBUG) ? $e->getMessage() : 'Erreur interne'], 500);
}
