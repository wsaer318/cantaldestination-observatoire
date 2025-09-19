<?php

/**
 * CantalDestination - Application configuration
 * Fichier: config/app.php
 */

// Configuration principale de l'application CantalDestination PHP

// Charger le fichier .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            if (!empty($key)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Inclure la configuration de base de données
require_once dirname(__DIR__) . '/database.php';

// Chemins de base
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('STATIC_PATH', BASE_PATH . '/static'); 
define('TEMPLATES_PATH', BASE_PATH . '/templates');
if (!defined('DATA_TEMP_PRIMARY_PATH')) {
    define('DATA_TEMP_PRIMARY_PATH', DATA_PATH . '/data_temp');
}

if (!defined('DATA_TEMP_LEGACY_PATH')) {
    define('DATA_TEMP_LEGACY_PATH', BASE_PATH . '/fluxvision_automation/data/data_temp');
}

if (!function_exists('resolve_data_temp_dir')) {
    function resolve_data_temp_dir(bool $ensureExists = false): string
    {
        $candidates = [DATA_TEMP_PRIMARY_PATH, DATA_TEMP_LEGACY_PATH];
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        if ($ensureExists) {
            $primary = DATA_TEMP_PRIMARY_PATH;
            if (!is_dir($primary)) {
                @mkdir($primary, 0755, true);
            }
            if (is_dir($primary)) {
                return $primary;
            }
        }

        return DATA_TEMP_PRIMARY_PATH;
    }
}

if (!function_exists('data_temp_file')) {
    function data_temp_file(string $relativePath, bool $ensureBase = false): string
    {
        $relativePath = ltrim($relativePath, '\/');
        $base = resolve_data_temp_dir($ensureBase);
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
    }
}


// Configuration de l'application
define('APP_NAME', 'Cantal Destination - Observatoire Touristique');
define('APP_VERSION', '2.0.0');

// Mode debug selon l'environnement
define('DEBUG', !DatabaseConfig::isProduction());

// Configuration JSON
define('JSON_OPTIONS', JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Fonction d'autoload simple
spl_autoload_register(function ($class) {
    if (file_exists(BASE_PATH . '/classes/' . $class . '.php')) {
        require_once BASE_PATH . '/classes/' . $class . '.php';
    }
});

// Gestion des erreurs
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Headers pour les API (CORS géré par .htaccess)
function setCorsHeaders() {
    // Laisse .htaccess gérer CORS pour éviter les doublons
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

// Fonction pour répondre en JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    setCorsHeaders();
    echo json_encode($data, JSON_OPTIONS);
    exit;
}

// Fonction pour gérer les erreurs API
function apiError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

// Fonction slugify (équivalent de la fonction Python)
function slugify($value) {
    $value = (string)$value;
    // Supprime les accents
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    // Remplace les caractères non alphanumériques par des underscores
    $value = preg_replace('/[^\w\-]+/', '_', $value);
    return strtolower($value);
}

// Fonction pour générer les URLs correctes
function asset($path) {
    // Détecter l'environnement
    $basePath = '';
    
    // Si on est en local (XAMPP ou développement)
    if (isset($_SERVER['HTTP_HOST']) && 
        (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
         strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
         strpos($_SERVER['SERVER_NAME'], 'localhost') !== false)) {
        
        $scriptName = $_SERVER['SCRIPT_NAME'];
        if (strpos($scriptName, '/fluxvision_fin/') !== false) {
            $basePath = '/fluxvision_fin';
        }
    }
    // En production, pas de préfixe nécessaire si c'est à la racine du domaine
    // Ou adapter selon la structure du serveur de production
    
    return $basePath . $path;
}

// Fonction pour générer les URLs de routes
function url($path) {
    return asset($path);
}

// Fonction pour mapper les périodes aux noms de fichiers
function mapPeriode($periode) {
    // Décoder l'URL d'abord pour gérer les caractères encodés
    $periode = urldecode($periode);
    
    $periodeMap = [
        'année' => 'annee',
        'annee' => 'annee',
        'week-end de pâques' => 'week-end_de_paques',
        'week-end_de_pâques' => 'week-end_de_paques',
        'week-end_de_paques' => 'week-end_de_paques',
        'vacances d\'hiver' => 'vacances_d_hiver',
        "vacances d'hiver" => 'vacances_d_hiver',
        'vacances_d\'hiver' => 'vacances_d_hiver',
        'vacances_d_hiver' => 'vacances_d_hiver'
    ];
    
    $mapped = $periodeMap[strtolower($periode)] ?? slugify($periode);
    
    // Debug en mode développement
    if (DEBUG) {
        error_log("mapPeriode: '$periode' -> '$mapped'");
    }
    
    return $mapped;
} 