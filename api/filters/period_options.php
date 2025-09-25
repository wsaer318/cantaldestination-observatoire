<?php
/**
 * API Hybride - Options de périodes disponibles
 * 
 * Retourne les options selon le contexte :
 * - 'user' : 4 saisons simples
 * - 'business' : périodes métier de la base
 * - 'hybrid' : combinaison des deux
 */

// IMPORTANT: Capturer toute sortie indésirable
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Inclure les classes nécessaires avec suppression des messages de debug
require_once dirname(__DIR__, 2) . '/classes/PeriodMapper.php';

// Nettoyer la sortie de debug
ob_clean();

try {
    // Récupération des paramètres
    $annee = (int)($_GET['annee'] ?? date('Y'));
    $context = $_GET['context'] ?? 'user';
    
    // Validation du contexte
    $validContexts = ['user', 'business', 'hybrid', 'auto'];
    if (!in_array($context, $validContexts)) {
        $context = 'user';
    }
    
    // Récupérer les options via PeriodMapper
    $options = PeriodMapper::getAllAvailableOptions($annee, $context);
    
    // Enrichir avec des informations d'affichage
    $response = [
        'status' => 'success',
        'annee' => $annee,
        'context' => $context,
        'options' => $options,
        'meta' => [
            'total_seasons' => isset($options['seasons']) ? count($options['seasons']) : 0,
            'total_business' => isset($options['business']) ? count($options['business']) : 0,
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Si mode hybrid, ajouter des groupes organisés
    if ($context === 'hybrid' && isset($options['seasons']) && isset($options['business'])) {
        $response['organized'] = [
            'simple' => [
                'label' => 'Saisons (Interface simple)',
                'options' => $options['seasons']
            ],
            'expert' => [
                'label' => 'Périodes métier (Analyse précise)',
                'options' => $options['business']
            ]
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erreur API period_options: " . $e->getMessage());
    
    // Réponse d'erreur avec fallback
    $fallbackSeasons = [
        'printemps' => ['name' => 'Printemps', 'type' => 'season'],
        'ete' => ['name' => 'Été', 'type' => 'season'],
        'automne' => ['name' => 'Automne', 'type' => 'season'],
        'hiver' => ['name' => 'Hiver', 'type' => 'season']
    ];
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors du chargement des options',
        'fallback' => true,
        'options' => [
            'seasons' => $fallbackSeasons
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
