<?php
/**
 * API Hybride - Informations sur la période actuelle
 * 
 * Retourne les informations détaillées sur la période actuelle,
 * incluant le mapping intelligent vers les périodes métier
 */

// IMPORTANT: Capturer toute sortie indésirable et désactiver l'affichage d'erreurs
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Inclure les classes nécessaires avec suppression des messages de debug
require_once dirname(__DIR__) . '/classes/PeriodMapper.php';

// Nettoyer la sortie de debug préalable
ob_clean();

try {
    // Récupérer les informations de la période actuelle
    $periodInfo = PeriodMapper::getCurrentPeriodInfo();
    
    // Enrichir avec des informations additionnelles
    $response = [
        'status' => 'success',
        'current_season' => $periodInfo['current_season'],
        'current_year' => $periodInfo['current_year'],
        'display_name' => $periodInfo['display_name'],
        'resolved_period' => $periodInfo['resolved_period'],
        'meta' => [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'day_of_year' => date('z'),
            'week_of_year' => date('W')
        ]
    ];
    
    // Ajouter des informations saisonnières
    if ($periodInfo['resolved_period']) {
        $resolvedPeriod = $periodInfo['resolved_period'];
        
        $response['period_details'] = [
            'type' => $resolvedPeriod['type'],
            'code_periode' => $resolvedPeriod['code_periode'],
            'nom_periode' => $resolvedPeriod['nom_periode'],
            'date_debut' => $resolvedPeriod['date_debut'],
            'date_fin' => $resolvedPeriod['date_fin'],
            'is_synthetic' => isset($resolvedPeriod['is_synthetic']) ? $resolvedPeriod['is_synthetic'] : false
        ];
        
        // Calculer les jours restants dans la période
        $now = new DateTime();
        $endDate = new DateTime($resolvedPeriod['date_fin']);
        $interval = $now->diff($endDate);
        
        $response['period_progress'] = [
            'days_remaining' => $interval->days,
            'is_past' => $now > $endDate,
            'progress_info' => $now > $endDate ? 'Période terminée' : "{$interval->days} jours restants"
        ];
    }
    
    // Suggestions de périodes alternatives (pour interfaces expertes)
    $currentYear = $periodInfo['current_year'];
    $alternativeOptions = PeriodMapper::getAllAvailableOptions($currentYear, 'hybrid');
    
    if (isset($alternativeOptions['business'])) {
        $response['alternative_periods'] = array_slice($alternativeOptions['business'], 0, 3, true);
    }
    
    // Nettoyer une dernière fois tout output parasite avant réponse
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erreur API current_period_info: " . $e->getMessage());
    
    // Fallback basique
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    
    // Logique de saison simple
    if ($currentMonth >= 3 && $currentMonth <= 5) {
        $season = 'printemps';
    } elseif ($currentMonth >= 6 && $currentMonth <= 8) {
        $season = 'ete';
    } elseif ($currentMonth >= 9 && $currentMonth <= 11) {
        $season = 'automne';
    } else {
        $season = 'hiver';
    }
    
    // Nettoyer le tampon avant le fallback
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors du calcul de la période actuelle',
        'fallback' => true,
        'current_season' => $season,
        'current_year' => $currentYear,
        'display_name' => ucfirst($season) . " $currentYear"
    ], JSON_UNESCAPED_UNICODE);
}
?>