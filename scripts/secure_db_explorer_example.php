<?php
/**
 * Exemple d'utilisation sécurisée des explorateurs de base de données
 * Ce script montre comment envoyer les informations de connexion via HTTP
 * de manière sécurisée
 */

// Configuration de sécurité
define('DEBUG', true); // Uniquement en développement

// Informations de connexion (à stocker de manière sécurisée)
$db_config = [
    'host' => 'localhost',
    'dbname' => 'observatoire',
    'username' => 'observatoire',
    'password' => 'Sf4d8gsfsdGsg59sqq54g'
];

// Clé d'authentification (change quotidiennement)
$api_key = 'fluxvision_2024_secure_key_' . date('Y-m-d');

/**
 * Fonction pour faire une requête sécurisée à l'explorateur de base de données
 */
function queryDatabaseExplorer($action, $params = [], $use_secure = false) {
    global $db_config, $api_key;
    
    // URL de l'explorateur à utiliser
    $explorer_url = $use_secure ? 
        'http://localhost' . getBasePath() . '/api/db_explorer_secure.php' :
        'http://localhost' . getBasePath() . '/api/db_explorer.php';
    
    // Paramètres de base
    $base_params = [
        'key' => $api_key,
        'action' => $action,
        'host' => $db_config['host'],
        'dbname' => $db_config['dbname'],
        'username' => $db_config['username'],
        'password' => $db_config['password']
    ];
    
    // Fusionner avec les paramètres spécifiques
    $all_params = array_merge($base_params, $params);
    
    // Construire l'URL avec les paramètres
    $url = $explorer_url . '?' . http_build_query($all_params);
    
    // Faire la requête HTTP
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'header' => [
                'User-Agent: FluxVision-Secure-Explorer/1.0',
                'Accept: application/json'
            ]
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [
            'success' => false,
            'error' => 'Erreur de connexion à l\'explorateur'
        ];
    }
    
    return json_decode($response, true);
}

/**
 * Exemples d'utilisation
 */
function runExamples() {
    echo "=== EXEMPLES D'UTILISATION SÉCURISÉE ===\n\n";
    
    // 1. Informations générales
    echo "1. Informations générales de la base de données:\n";
    $result = queryDatabaseExplorer('info');
    if ($result['success']) {
        echo "   ✅ Connexion réussie\n";
        echo "   📅 Timestamp: " . $result['timestamp'] . "\n";
        echo "   🔒 Sécurité: " . $result['security_note'] . "\n";
    } else {
        echo "   ❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
    }
    echo "\n";
    
    // 2. Lister les tables
    echo "2. Liste des tables:\n";
    $result = queryDatabaseExplorer('tables');
    if ($result['success']) {
        echo "   ✅ Nombre de tables: " . $result['count'] . "\n";
        echo "   📋 Tables trouvées:\n";
        foreach (array_slice($result['tables'], 0, 5) as $table) {
            echo "      - $table\n";
        }
        if (count($result['tables']) > 5) {
            echo "      ... et " . (count($result['tables']) - 5) . " autres\n";
        }
    } else {
        echo "   ❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
    }
    echo "\n";
    
    // 3. Structure d'une table (si elle existe)
    echo "3. Structure d'une table (exemple avec 'dim_dates'):\n";
    $result = queryDatabaseExplorer('structure', ['table' => 'dim_dates']);
    if ($result['success']) {
        echo "   ✅ Table: " . $result['table'] . "\n";
        echo "   📊 Colonnes: " . count($result['structure']) . "\n";
        foreach (array_slice($result['structure'], 0, 3) as $column) {
            echo "      - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        if (count($result['structure']) > 3) {
            echo "      ... et " . (count($result['structure']) - 3) . " autres colonnes\n";
        }
    } else {
        echo "   ❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
    }
    echo "\n";
    
    // 4. Compter les enregistrements
    echo "4. Nombre d'enregistrements (exemple avec 'dim_dates'):\n";
    $result = queryDatabaseExplorer('count', ['table' => 'dim_dates']);
    if ($result['success']) {
        echo "   ✅ Table: " . $result['table'] . "\n";
        echo "   📊 Nombre d'enregistrements: " . $result['count'] . "\n";
    } else {
        echo "   ❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
    }
    echo "\n";
    
    // 5. Échantillon de données
    echo "5. Échantillon de données (exemple avec 'dim_dates', max 3):\n";
    $result = queryDatabaseExplorer('sample', [
        'table' => 'dim_dates',
        'limit' => 3
    ]);
    if ($result['success']) {
        echo "   ✅ Table: " . $result['table'] . "\n";
        echo "   📊 Échantillon (" . count($result['sample']) . " lignes):\n";
        foreach ($result['sample'] as $index => $row) {
            echo "      Ligne " . ($index + 1) . ": " . json_encode($row) . "\n";
        }
    } else {
        echo "   ❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
    }
    echo "\n";
}

/**
 * Fonction pour tester la sécurité
 */
function testSecurity() {
    echo "=== TESTS DE SÉCURITÉ ===\n\n";
    
    // Test 1: Sans clé d'authentification
    echo "1. Test sans clé d'authentification:\n";
    $url = 'http://localhost' . getBasePath() . '/api/db_explorer.php?action=info';
    $response = file_get_contents($url);
    $result = json_decode($response, true);
    
    if (isset($result['error']) && strpos($result['error'], 'authentification') !== false) {
        echo "   ✅ Accès correctement refusé sans clé\n";
    } else {
        echo "   ❌ Accès non sécurisé possible\n";
    }
    echo "\n";
    
    // Test 2: Clé d'authentification invalide
    echo "2. Test avec clé d'authentification invalide:\n";
    $url = 'http://localhost' . getBasePath() . '/api/db_explorer.php?key=invalid_key&action=info';
    $response = file_get_contents($url);
    $result = json_decode($response, true);
    
    if (isset($result['error']) && strpos($result['error'], 'authentification') !== false) {
        echo "   ✅ Accès correctement refusé avec clé invalide\n";
    } else {
        echo "   ❌ Accès non sécurisé possible\n";
    }
    echo "\n";
    
    // Test 3: Sans paramètres de connexion
    echo "3. Test sans paramètres de connexion:\n";
    $result = queryDatabaseExplorer('info', [], false);
    if (isset($result['error']) && strpos($result['error'], 'Paramètres de connexion') !== false) {
        echo "   ✅ Paramètres de connexion requis\n";
    } else {
        echo "   ❌ Connexion possible sans paramètres\n";
    }
    echo "\n";
}

// Exécution
if (php_sapi_name() === 'cli') {
    if (isset($argv[1]) && $argv[1] === 'security') {
        testSecurity();
    } else {
        runExamples();
    }
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php secure_db_explorer_example.php [security]\n";
    echo "  - Sans argument: exemples d'utilisation\n";
    echo "  - Avec 'security': tests de sécurité\n";
}
?>
