<?php
/**\n * API de Comparaison des Départements d'Origine - Version avec support des tables temporaires\n * Bascule automatiquement vers les tables temporaires si les données ne sont pas dans les tables principales\n * Compare les départements d'origine entre deux périodes/zones différentes
 */

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
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupération et validation des paramètres
$requiredParams = ['annee_a', 'periode_a', 'zone_a', 'annee_b', 'periode_b', 'zone_b'];
foreach ($requiredParams as $param) {
    if (!isset($_GET[$param]) || empty($_GET[$param])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Paramètre manquant : $param"]);
        exit;
    }
}

try {
    // Récupération des paramètres
    $annee_a = $_GET['annee_a'] ?? '2024';
    $periode_a = $_GET['periode_a'] ?? 'hiver';
    $zone_a = $_GET['zone_a'] ?? 'CANTAL';
    
    $annee_b = $_GET['annee_b'] ?? '2023';
    $periode_b = $_GET['periode_b'] ?? 'hiver';
    $zone_b = $_GET['zone_b'] ?? 'CANTAL';
    
    $limit = (int)($_GET['limit'] ?? 15);
    
    // Inclusions
    require_once '../config/app.php';
    require_once dirname(__DIR__) . '/periodes_manager_db.php';
require_once dirname(__DIR__, 2) . '/classes/ZoneMapper.php';
    
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
    
    // Obtenir les données des départements pour chaque période
    $data_a = callDepartementsAPI($annee_a, $periode_a, $zone_a, $limit);
    $data_b = callDepartementsAPI($annee_b, $periode_b, $zone_b, $limit);
    
    // Créer un mapping combiné de tous les départements
    $departements_map = [];
    
    // Ajouter les départements de la période A
    foreach ($data_a as $dept) {
        $nom = $dept['nom_departement'];
        $departements_map[$nom] = [
            'nom_departement' => $nom,
            'nom_region' => $dept['nom_region'],
            'nom_nouvelle_region' => $dept['nom_nouvelle_region'],
            'periode_a' => $dept['n_nuitees'],
            'periode_b' => 0,
            'part_a' => $dept['part_pct'],
            'part_b' => 0
        ];
    }
    
    // Ajouter/fusionner les départements de la période B
    foreach ($data_b as $dept) {
        $nom = $dept['nom_departement'];
        if (isset($departements_map[$nom])) {
            $departements_map[$nom]['periode_b'] = $dept['n_nuitees'];
            $departements_map[$nom]['part_b'] = $dept['part_pct'];
        } else {
            $departements_map[$nom] = [
                'nom_departement' => $nom,
                'nom_region' => $dept['nom_region'],
                'nom_nouvelle_region' => $dept['nom_nouvelle_region'],
                'periode_a' => 0,
                'periode_b' => $dept['n_nuitees'],
                'part_a' => 0,
                'part_b' => $dept['part_pct']
            ];
        }
    }
    
    // Calculer les évolutions et trier
    $departements_comparison = [];
    foreach ($departements_map as $dept) {
        // Calcul de l'évolution
        $evolution = 0;
        if ($dept['periode_b'] > 0) {
            $evolution = round((($dept['periode_a'] - $dept['periode_b']) / $dept['periode_b']) * 100, 1);
        } elseif ($dept['periode_a'] > 0) {
            $evolution = 100;
        }
        
        // Différence absolue
        $difference = $dept['periode_a'] - $dept['periode_b'];
        
        $departements_comparison[] = [
            'nom_departement' => $dept['nom_departement'],
            'nom_region' => $dept['nom_region'],
            'nom_nouvelle_region' => $dept['nom_nouvelle_region'],
            'periode_a' => $dept['periode_a'],
            'periode_b' => $dept['periode_b'],
            'evolution' => $evolution,
            'difference' => $difference,
            'part_a' => $dept['part_a'],
            'part_b' => $dept['part_b']
        ];
    }
    
    // Trier par volume de la période A (décroissant)
    usort($departements_comparison, function($a, $b) {
        return $b['periode_a'] <=> $a['periode_a'];
    });
    
    // Limiter les résultats
    $departements_comparison = array_slice($departements_comparison, 0, $limit);
    
    // Calculer quelques statistiques globales
    $total_a = array_sum(array_column($data_a, 'n_nuitees'));
    $total_b = array_sum(array_column($data_b, 'n_nuitees'));
    $evolution_globale = $total_b > 0 ? round((($total_a - $total_b) / $total_b) * 100, 1) : 0;
    
    // Réponse finale
    $response = [
        'status' => 'success',
        'data' => [
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
            'statistiques' => [
                'total_a' => $total_a,
                'total_b' => $total_b,
                'evolution_globale' => $evolution_globale,
                'nombre_departements' => count($departements_comparison)
            ],
            'departements' => $departements_comparison
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Appelle l'API bloc_d1_cached.php via HTTP pour récupérer les données des départements
 */
function callDepartementsAPI($annee, $periode, $zone, $limit) {
    // Construire l'URL de l'API
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host . dirname($_SERVER['REQUEST_URI']) . '/bloc_d1_cached.php';
    
    $url = $baseUrl . '?' . http_build_query([
        'annee' => $annee,
        'periode' => $periode,
        'zone' => $zone,
        'limit' => $limit
    ]);
    
    // Timeout adapté selon la période (60s pour année, 30s pour autres)
    $timeout = ($periode === 'annee') ? 60 : 30;
    
    // Faire l'appel HTTP
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'method' => 'GET',
            'header' => "Accept: application/json\r\n"
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("Impossible de contacter l'API bloc_d1_cached.php à l'URL: $url (timeout: {$timeout}s)");
    }
    
    // Parser le JSON retourné
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur parsing JSON de bloc_d1_cached.php: " . json_last_error_msg() . ". Réponse: " . substr($response, 0, 200));
    }
    
    if (isset($data['error'])) {
        throw new Exception("Erreur dans bloc_d1_cached.php: " . ($data['message'] ?? 'Erreur inconnue'));
    }
    
    return $data;
}
?> 
