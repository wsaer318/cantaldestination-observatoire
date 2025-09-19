<?php
// API de comparaison détaillée pour les Indicateurs Clés
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Inclusions sécurisées
    require_once '../config/app.php';
    require_once '../database.php';
    require_once __DIR__ . '/periodes_manager_db.php';
require_once __DIR__ . '/../classes/ZoneMapper.php';
    
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
    
    // Obtenir les données complètes via l'API bloc_a.php pour chaque période
    $data_a = callBlocAAPI($annee_a, $periode_a, $zone_a);
    $data_b = callBlocAAPI($annee_b, $periode_b, $zone_b);
    
    // Structurer les données pour les indicateurs clés
    $comparison_summary = buildComparisonSummary($data_a['bloc_a'], $data_b['bloc_a']);
    
    // Réponse finale structurée
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
        'comparison_summary' => $comparison_summary,
        'raw_data_a' => $data_a['bloc_a'],
        'raw_data_b' => $data_b['bloc_a']
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
 * Structure les données en indicateurs clés comparatifs
 */
function buildComparisonSummary($indicators_a, $indicators_b) {
    // Fonction helper pour trouver un indicateur
    $findIndicator = function($indicators, $numero) {
        foreach ($indicators as $indicator) {
            if ($indicator['numero'] == $numero) {
                return $indicator;
            }
        }
        return null;
    };
    
    // Calculer les évolutions
    $calculateEvolution = function($val_a, $val_b) {
        if ($val_b == 0) return 0;
        return round((($val_a - $val_b) / $val_b) * 100, 1);
    };
    
    // Extraire les indicateurs clés
    $nuitees_totales_a = $findIndicator($indicators_a, 1);
    $nuitees_totales_b = $findIndicator($indicators_b, 1);
    
    $nuitees_fr_a = $findIndicator($indicators_a, 2);
    $nuitees_fr_b = $findIndicator($indicators_b, 2);
    
    $nuitees_intl_a = $findIndicator($indicators_a, 3);
    $nuitees_intl_b = $findIndicator($indicators_b, 3);
    
    $excursionnistes_totaux_a = $findIndicator($indicators_a, 16);
    $excursionnistes_totaux_b = $findIndicator($indicators_b, 16);
    
    $pic_journalier_a = $findIndicator($indicators_a, 10);
    $pic_journalier_b = $findIndicator($indicators_b, 10);
    
    $presences_2e_samedi_a = $findIndicator($indicators_a, 17);
    $presences_2e_samedi_b = $findIndicator($indicators_b, 17);
    
    $presences_3e_samedi_a = $findIndicator($indicators_a, 18);
    $presences_3e_samedi_b = $findIndicator($indicators_b, 18);
    
    return [
        'nuitees' => [
            'totales' => [
                'periode_a' => intval($nuitees_totales_a['N'] ?? 0),
                'periode_b' => intval($nuitees_totales_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($nuitees_totales_a['N'] ?? 0), 
                    intval($nuitees_totales_b['N'] ?? 0)
                ),
                'unite' => 'Nuitées',
                'libelle' => 'Nuitées totales (FR + INTL)'
            ],
            'francaises' => [
                'periode_a' => intval($nuitees_fr_a['N'] ?? 0),
                'periode_b' => intval($nuitees_fr_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($nuitees_fr_a['N'] ?? 0), 
                    intval($nuitees_fr_b['N'] ?? 0)
                ),
                'unite' => 'Nuitées',
                'libelle' => 'Nuitées françaises'
            ],
            'internationales' => [
                'periode_a' => intval($nuitees_intl_a['N'] ?? 0),
                'periode_b' => intval($nuitees_intl_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($nuitees_intl_a['N'] ?? 0), 
                    intval($nuitees_intl_b['N'] ?? 0)
                ),
                'unite' => 'Nuitées',
                'libelle' => 'Nuitées internationales'
            ]
        ],
        'presences' => [
            'totales' => [
                'periode_a' => intval($excursionnistes_totaux_a['N'] ?? 0),
                'periode_b' => intval($excursionnistes_totaux_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($excursionnistes_totaux_a['N'] ?? 0), 
                    intval($excursionnistes_totaux_b['N'] ?? 0)
                ),
                'unite' => 'Présences',
                'libelle' => 'Excursionnistes totaux'
            ],
            'pic_journalier' => [
                'periode_a' => intval($pic_journalier_a['N'] ?? 0),
                'periode_b' => intval($pic_journalier_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($pic_journalier_a['N'] ?? 0), 
                    intval($pic_journalier_b['N'] ?? 0)
                ),
                'unite' => 'Présences',
                'libelle' => 'Pic journalier',
                'date_a' => $pic_journalier_a['date'] ?? null,
                'date_b' => $pic_journalier_b['date'] ?? null
            ],
            'deuxieme_samedi' => [
                'periode_a' => intval($presences_2e_samedi_a['N'] ?? 0),
                'periode_b' => intval($presences_2e_samedi_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($presences_2e_samedi_a['N'] ?? 0), 
                    intval($presences_2e_samedi_b['N'] ?? 0)
                ),
                'unite' => 'Présences',
                'libelle' => '2e samedi',
                'date_a' => $presences_2e_samedi_a['date'] ?? null,
                'date_b' => $presences_2e_samedi_b['date'] ?? null
            ],
            'troisieme_samedi' => [
                'periode_a' => intval($presences_3e_samedi_a['N'] ?? 0),
                'periode_b' => intval($presences_3e_samedi_b['N'] ?? 0),
                'evolution' => $calculateEvolution(
                    intval($presences_3e_samedi_a['N'] ?? 0), 
                    intval($presences_3e_samedi_b['N'] ?? 0)
                ),
                'unite' => 'Présences',
                'libelle' => '3e samedi',
                'date_a' => $presences_3e_samedi_a['date'] ?? null,
                'date_b' => $presences_3e_samedi_b['date'] ?? null
            ]
        ]
    ];
}
?> 