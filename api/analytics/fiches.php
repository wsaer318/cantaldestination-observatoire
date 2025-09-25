<?php
// API pour récupérer les fiches techniques (version simplifiée)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

try {
    // Charger les données depuis le fichier JSON
    $jsonFilePath = dirname(__DIR__, 2) . '/static/data/fiches_data.json';
    
    if (!file_exists($jsonFilePath)) {
        throw new Exception("Fichier de données des fiches non trouvé : " . $jsonFilePath);
    }
    
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent === false) {
        throw new Exception("Impossible de lire le fichier de données des fiches");
    }
    
    $fiches = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur de décodage JSON : " . json_last_error_msg());
    }
    
    echo json_encode($fiches);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 
