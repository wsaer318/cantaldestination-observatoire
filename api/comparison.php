<?php
/**\n * API de Comparaison Avancée - Version avec support des tables temporaires\n * Bascule automatiquement vers les tables temporaires si les données ne sont pas dans les tables principales\n * Permet de comparer deux périodes/zones différentes
 */

// API de comparaison avancée pour le TDB
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Méthode non autorisée']);
    exit;
}

// Les fonctions apiError() et jsonResponse() sont déjà définies dans app.php

// Récupération et validation des paramètres
$requiredParams = ['annee_a', 'periode_a', 'zone_a', 'annee_b', 'periode_b', 'zone_b'];
foreach ($requiredParams as $param) {
    if (!isset($_GET[$param]) || empty($_GET[$param])) {
        apiError("Paramètre manquant : $param");
    }
}

try {
    // Inclusions sécurisées
    require_once '../config/app.php';
    require_once '../database.php';
    require_once __DIR__ . '/periodes_manager_db.php';
    
    // Récupération des paramètres
    $annee_a = $_GET['annee_a'] ?? '2024';
    $periode_a = $_GET['periode_a'] ?? 'hiver';
    $zone_a = $_GET['zone_a'] ?? 'CANTAL';
    
    $annee_b = $_GET['annee_b'] ?? '2023';
    $periode_b = $_GET['periode_b'] ?? 'hiver';
    $zone_b = $_GET['zone_b'] ?? 'CANTAL';
    
    // Mapping des noms de périodes
    $periodMapping = [
        'hiver' => 'Vacances d\'hiver',
        'paques' => 'week-end de Pâques',
        'annee' => 'Année'
    ];
    
    // Créer le gestionnaire de périodes
    // Utilisation de la classe statique PeriodesManagerDB
    
    // Mapper les noms de périodes
    $periode_a_mapped = $periodMapping[$periode_a] ?? $periode_a;
    $periode_b_mapped = $periodMapping[$periode_b] ?? $periode_b;
    
    // Récupérer les informations des périodes A et B
    $period_info_a = PeriodesManagerDB::calculateDateRanges($annee_a, $periode_a_mapped);
    $period_info_b = PeriodesManagerDB::calculateDateRanges($annee_b, $periode_b_mapped);
    
    // Obtenir les données via l'API bloc_a.php pour chaque période
    $data_a = callBlocAAPI($annee_a, $periode_a, $zone_a);
    $data_b = callBlocAAPI($annee_b, $periode_b, $zone_b);
    
    // Extraire les valeurs des indicateurs
    $touristes_a = findIndicatorValue($data_a['bloc_a'], 1); // Nuitées totales (FR + INTL)
    $touristes_b = findIndicatorValue($data_b['bloc_a'], 1);
    
    $excursionnistes_a = findIndicatorValue($data_a['bloc_a'], 16); // Excursionnistes totaux
    $excursionnistes_b = findIndicatorValue($data_b['bloc_a'], 16);
    
    // Calculs de comparaison
    $comparaisons = [
        'touristes' => [
            'valeur_a' => $touristes_a,
            'valeur_b' => $touristes_b,
            'difference' => $touristes_a - $touristes_b,
            'evolution_pct' => $touristes_b > 0 ? round((($touristes_a - $touristes_b) / $touristes_b) * 100, 1) : 0
        ],
        'excursionnistes' => [
            'valeur_a' => $excursionnistes_a,
            'valeur_b' => $excursionnistes_b,
            'difference' => $excursionnistes_a - $excursionnistes_b,
            'evolution_pct' => $excursionnistes_b > 0 ? round((($excursionnistes_a - $excursionnistes_b) / $excursionnistes_b) * 100, 1) : 0
        ]
    ];
    
    // Réponse finale
    $response = [
        'periode_a' => [
            'annee' => $annee_a,
            'periode' => $periode_a_mapped,
            'zone' => $zone_a,
            'debut' => $period_info_a['debut'],
            'fin' => $period_info_a['fin']
        ],
        'periode_b' => [
            'annee' => $annee_b,
            'periode' => $periode_b_mapped,
            'zone' => $zone_b,
            'debut' => $period_info_b['debut'],
            'fin' => $period_info_b['fin']
        ],
        'comparaisons' => $comparaisons
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Appelle l'API bloc_a.php via HTTP pour récupérer les données
 */
function callBlocAAPI($annee, $periode, $zone) {
    // Construire l'URL de l'API
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host . dirname($_SERVER['REQUEST_URI']) . '/bloc_a.php';
    
    $url = $baseUrl . '?' . http_build_query([
        'annee' => $annee,
        'periode' => $periode,
        'zone' => $zone
    ]);
    
    // Faire l'appel HTTP
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => "Accept: application/json\r\n"
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("Impossible de contacter l'API bloc_a.php à l'URL: $url");
    }
    
    // Parser le JSON retourné
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur parsing JSON de bloc_a.php: " . json_last_error_msg() . ". Réponse: " . substr($response, 0, 200));
    }
    
    if (isset($data['error'])) {
        throw new Exception("Erreur dans bloc_a.php: " . ($data['message'] ?? 'Erreur inconnue'));
    }
    
    return $data;
}

/**
 * Trouve la valeur d'un indicateur par son numéro
 */
function findIndicatorValue($indicators, $numero) {
    foreach ($indicators as $indicator) {
        if ($indicator['numero'] == $numero) {
            return intval($indicator['N'] ?? 0);
        }
    }
    return 0;
}
?> 