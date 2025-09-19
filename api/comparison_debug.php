<?php

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers CORS basiques
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Structure de debug
$debug = [
    'status' => 'debug',
    'steps' => [],
    'error' => null,
    'data' => null
];

try {
    $debug['steps'][] = 'API de debug démarrée';
    
    // Inclusions sécurisées
    require_once '../config/app.php';
    $debug['steps'][] = 'app.php inclus avec succès';
    
    require_once '../database.php';
    $debug['steps'][] = 'database.php inclus avec succès';
    
    require_once __DIR__ . '/periodes_manager_db.php';
    $debug['steps'][] = 'PeriodManager.php inclus avec succès';
    
    // Récupération des paramètres
    $annee_a = $_GET['annee_a'] ?? '2024';
    $periode_a = $_GET['periode_a'] ?? 'hiver';
    $zone_a = $_GET['zone_a'] ?? 'CANTAL';
    
    $annee_b = $_GET['annee_b'] ?? '2023';
    $periode_b = $_GET['periode_b'] ?? 'hiver';
    $zone_b = $_GET['zone_b'] ?? 'CANTAL';
    
    $debug['steps'][] = 'Paramètres récupérés';
    $debug['params'] = [
        'annee_a' => $annee_a,
        'periode_a' => $periode_a,
        'zone_a' => $zone_a,
        'annee_b' => $annee_b,
        'periode_b' => $periode_b,
        'zone_b' => $zone_b
    ];
    
    // Mapping des noms de périodes
    $periodMapping = [
        'hiver' => 'Vacances d\'hiver',
        'paques' => 'week-end de Pâques',
        'annee' => 'Année'
    ];
    
    $debug['steps'][] = 'Mapping des périodes défini';
    
    // Connexion à la base de données
    $db = getCantalDestinationDatabase();
    $debug['steps'][] = 'Connexion DB réussie';
    
    // Créer le gestionnaire de périodes
    // Utilisation de la classe statique PeriodesManagerDB
    $debug['steps'][] = 'PeriodManager créé avec succès';
    
    // Mapper les noms de périodes
    $periode_a_mapped = $periodMapping[$periode_a] ?? $periode_a;
    $periode_b_mapped = $periodMapping[$periode_b] ?? $periode_b;
    
    $debug['steps'][] = "Périodes mappées : $periode_a -> $periode_a_mapped, $periode_b -> $periode_b_mapped";
    
    // Récupérer les informations des périodes A et B
    $period_info_a = PeriodesManagerDB::calculateDateRanges($annee_a, $periode_a_mapped);
    $debug['steps'][] = 'Période A récupérée avec succès';
    
    $period_info_b = PeriodesManagerDB::calculateDateRanges($annee_b, $periode_b_mapped);
    $debug['steps'][] = 'Période B récupérée avec succès';
    
    // Calculer les données pour chaque période
    $touristes_a = calculateTouristes($db, $annee_a, $periode_a_mapped, $zone_a, $period_info_a);
    $excursionnistes_a = calculateExcursionnistes($db, $annee_a, $periode_a_mapped, $zone_a, $period_info_a);
    $debug['steps'][] = 'Données période A calculées';
    
    $touristes_b = calculateTouristes($db, $annee_b, $periode_b_mapped, $zone_b, $period_info_b);
    $excursionnistes_b = calculateExcursionnistes($db, $annee_b, $periode_b_mapped, $zone_b, $period_info_b);
    $debug['steps'][] = 'Données période B calculées';
    
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
    
    $debug['steps'][] = 'Comparaisons calculées';
    
    // Données finales
    $debug['data'] = [
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
    
    $debug['status'] = 'success';
    $debug['steps'][] = 'Traitement terminé avec succès';
    
} catch (Exception $e) {
    $debug['status'] = 'error';
    $debug['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    $debug['steps'][] = 'Erreur capturée : ' . $e->getMessage();
}

// Fonctions de calcul (versions simplifiées pour le debug)
function calculateTouristes($db, $annee, $periode, $zone, $period_info) {
    // Version simplifiée qui retourne une valeur factice pour tester
    // En production, cela ferait des requêtes SQL réelles
    return rand(10000, 50000);
}

function calculateExcursionnistes($db, $annee, $periode, $zone, $period_info) {
    // Version simplifiée qui retourne une valeur factice pour tester
    // En production, cela ferait des requêtes SQL réelles
    return rand(5000, 25000);
}

// Retourner un seul JSON valide
echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?> 