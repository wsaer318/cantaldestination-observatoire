<?php
/**
 * API pour la gestion des membres des espaces partagés
 * Endpoints:
 * - GET /api/shared-spaces/members/{space_id} - Liste des membres d'un espace
 * - POST /api/shared-spaces/members/{space_id} - Ajouter un membre
 * - PUT /api/shared-spaces/members/{space_id}/{user_id} - Modifier le rôle d'un membre
 * - DELETE /api/shared-spaces/members/{space_id}/{user_id} - Retirer un membre
 */

require_once '../../classes/Database.php';
require_once '../../classes/SharedSpaceManager.php';
require_once '../../classes/Security.php';
require_once '../../classes/Auth.php';

// Configuration CORS pour les API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentification requise - ADMIN UNIQUEMENT
Auth::requireAdmin();
$currentUser = Auth::getUser();

// Initialisation des classes
$db = new Database();
$spaceManager = new SharedSpaceManager($db);

// Récupération de l'URL et de la méthode
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Extraction du chemin de l'API
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api/shared-spaces/members', '', $path);
$path = trim($path, '/');

// Récupération du body pour les requêtes POST/PUT
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Endpoints pour les membres d'un espace spécifique
    if (preg_match('/^(\d+)$/', $path, $matches)) {
        $spaceId = (int)$matches[1];
        
        // Vérifier l'accès à l'espace
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        
        // GET /api/shared-spaces/members/{space_id} - Liste des membres
        if ($method === 'GET') {
            $members = $spaceManager->getSpaceMembers($spaceId);
            
            echo json_encode([
                'success' => true,
                'data' => $members
            ]);
        }
        // POST /api/shared-spaces/members/{space_id} - Ajouter un membre
        elseif ($method === 'POST') {
            if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $userId = (int)($input['user_id'] ?? 0);
            $role = trim($input['role'] ?? 'reader');
            
            if ($userId <= 0) {
                throw new Exception('ID utilisateur invalide');
            }
            
            $spaceManager->addMember($spaceId, $userId, $role);
            
            echo json_encode([
                'success' => true,
                'message' => 'Membre ajouté avec succès'
            ]);
        }
        else {
            throw new Exception('Méthode non autorisée');
        }
    }
    // Endpoints pour un membre spécifique
    elseif (preg_match('/^(\d+)\/(\d+)$/', $path, $matches)) {
        $spaceId = (int)$matches[1];
        $memberId = (int)$matches[2];
        
        // Vérifier l'accès à l'espace
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        
        // PUT /api/shared-spaces/members/{space_id}/{user_id} - Modifier le rôle d'un membre
        if ($method === 'PUT') {
            if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $newRole = trim($input['role'] ?? '');
            
            if (empty($newRole)) {
                throw new Exception('Le nouveau rôle est requis');
            }
            
            $spaceManager->updateMemberRole($spaceId, $memberId, $newRole, $currentUser['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Rôle modifié avec succès'
            ]);
        }
        // DELETE /api/shared-spaces/members/{space_id}/{user_id} - Retirer un membre
        elseif ($method === 'DELETE') {
            if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $spaceManager->removeMember($spaceId, $memberId, $currentUser['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Membre retiré avec succès'
            ]);
        }
        else {
            throw new Exception('Méthode non autorisée');
        }
    }
    else {
        throw new Exception('Endpoint non trouvé');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
