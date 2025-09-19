<?php
/**
 * API pour récupérer les utilisateurs disponibles
 * Endpoint: GET /api/users/available
 */

require_once '../functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Security.php';

// Configuration CORS pour les API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentification requise
Auth::requireAuth();
$currentUser = Auth::getUser();

// Initialisation de la base de données
$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    // Récupérer tous les utilisateurs actifs (sauf l'utilisateur courant)
    $query = "
        SELECT id, username 
        FROM users 
        WHERE active = 1 
        AND id != :user_id 
        ORDER BY username ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $currentUser['id']]);
    
    $users = [];
    while ($row = $stmt->fetch()) {
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()
    ]);
}
