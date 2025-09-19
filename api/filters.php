<?php
// API pour récupérer les filtres disponibles
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/security_middleware.php';

try {
    // Définir les données de filtres disponibles basées sur les fichiers existants
    $periodes = [
        ['text' => 'Année', 'value' => 'annee'],
        ['text' => 'Vacances d\'hiver', 'value' => 'vacances_d_hiver'],
        ['text' => 'Week-end de Pâques', 'value' => 'week-end_de_paques']
    ];
    
    $filters = [
        'zones' => ['Cantal'],
        'periodes' => $periodes,
        'annees' => ['2025', '2024', '2023', '2022', '2021', '2020', '2019'] // Ordre décroissant
    ];
    
    jsonResponse($filters);
    
} catch (Exception $e) {
    ApiSecurityMiddleware::handleApiError($e, '/api/filters');
} 