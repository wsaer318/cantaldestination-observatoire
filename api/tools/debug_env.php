<?php
// DEBUG - Diagnostic de détection d'environnement - SÉCURISÉ
header('Content-Type: application/json; charset=utf-8');

// ⚠️ SÉCURITÉ : Vérifier l'environnement avant d'exposer des informations sensibles
require_once dirname(__DIR__, 2) . '/config/app.php';

if (!defined('DEBUG') || !DEBUG) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès interdit en production'], JSON_PRETTY_PRINT);
    exit;
}

// Vérifier l'accès local uniquement
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';

if (!in_array($client_ip, $allowed_ips) && $client_ip !== 'unknown') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès autorisé uniquement en local'], JSON_PRETTY_PRINT);
    exit;
}

// Inclure la configuration
require_once dirname(__DIR__, 2) . '/database.php';

// Collecter toutes les informations
$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_vars' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'non défini',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'non défini', 
        'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'non défini',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'non défini',
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'non défini'
    ],
    'environment_detection' => [
        'isProduction' => DatabaseConfig::isProduction(),
        'currentEnvironment' => DatabaseConfig::getCurrentEnvironment()
    ],
    'database_config' => [],
    'tests' => []
];

// Obtenir la config DB (sans mot de passe)
$config = DatabaseConfig::getConfig();
// ⚠️ SÉCURITÉ : Ne jamais exposer les vraies informations de connexion DB
$debug['database_config'] = [
    'host' => 'hidden_for_security',
    'port' => 'hidden_for_security', 
    'database' => 'hidden_for_security',
    'username' => 'hidden_for_security',
    'password' => 'hidden_for_security',
    'environment' => $config['environment'], // Seul l'environnement est safe
    'security_note' => 'Informations DB masquées pour la sécurité'
];

// Tests de détection
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$debug['tests']['host_value'] = $host;
$debug['tests']['cantal_destination_check'] = strpos($host, 'cantal-destination.com') !== false;
$debug['tests']['observatoire_check'] = strpos($host, 'observatoire.cantal-destination.com') !== false;

// Test de connexion DB
try {
    $db = getCantalDestinationDatabase();
    $debug['database_test'] = 'SUCCESS - Connexion réussie';
} catch (Exception $e) {
    $debug['database_test'] = 'ERROR - ' . $e->getMessage();
}

// Tenter une requête simple
try {
    $db = getCantalDestinationDatabase();
    $result = $db->query("SELECT 1 as test");
    $debug['query_test'] = 'SUCCESS - Requête réussie: ' . json_encode($result);
} catch (Exception $e) {
    $debug['query_test'] = 'ERROR - ' . $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?> 
