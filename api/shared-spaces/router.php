<?php
/**
 * API pour la gestion des espaces partagés
 * Endpoints:
 * - GET /api/shared-spaces - Liste des espaces de l'utilisateur
 * - POST /api/shared-spaces - Créer un espace
 * - GET /api/shared-spaces/{id} - Détails d'un espace
 * - PUT /api/shared-spaces/{id} - Modifier un espace
 * - DELETE /api/shared-spaces/{id} - Supprimer un espace
 * - GET /api/shared-spaces/{id}/members - Liste des membres
 * - POST /api/shared-spaces/{id}/members - Ajouter un membre
 * - PUT /api/shared-spaces/{id}/members/{user_id} - Modifier le rôle d'un membre
 * - DELETE /api/shared-spaces/{id}/members/{user_id} - Retirer un membre
 */

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SharedSpaceManager.php';
require_once __DIR__ . '/../../classes/Security.php';
require_once __DIR__ . '/../../classes/Auth.php';

// Démarrer la session de manière uniforme
require_once __DIR__ . '/../../config/session_config.php';

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
$db = Database::getInstance();
$spaceManager = new SharedSpaceManager($db);

// Récupération de l'URL et de la méthode
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Extraction du chemin de l'API
$path = parse_url($requestUri, PHP_URL_PATH);

// Logs de débogage
error_log("Router - FICHIER ROUTER CHARGÉ");
error_log("Router - Request URI: $requestUri");
error_log("Router - Path original: '$path'");
error_log("Router - Method: $method");
error_log("Router - Script name: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A'));

// Nettoyer le chemin pour correspondre aux patterns
$path = str_replace('/fluxvision_fin/api/shared-spaces', '', $path);
$path = str_replace('/api/shared-spaces', '', $path);
$path = str_replace('/shared-spaces', '', $path);
$path = str_replace('/router.php', '', $path);
$path = trim($path, '/');

error_log("Router - Path nettoyé: '$path'");

// Récupération du body pour les requêtes POST/PUT
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Routing des endpoints
    if (empty($path)) {
        // GET /api/shared-spaces - Liste des espaces
        if ($method === 'GET') {
            $spaces = $spaceManager->getUserSpaces($currentUser['id']);
            
            // Ajouter les statistiques pour chaque espace
            $spacesWithStats = [];
            foreach ($spaces as $space) {
                $stats = $spaceManager->getSpaceStats($space['id']);
                $spacesWithStats[] = array_merge($space, ['stats' => $stats]);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $spacesWithStats
            ]);
        }
        // POST /api/shared-spaces - Créer un espace
        elseif ($method === 'POST') {
            if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $initialMembers = $input['members'] ?? [];
            
            if (empty($name)) {
                throw new Exception('Le nom de l\'espace est requis');
            }
            
            $spaceId = $spaceManager->createSpace($name, $description, $currentUser['id'], $initialMembers);
            
            echo json_encode([
                'success' => true,
                'data' => ['id' => $spaceId],
                'message' => 'Espace créé avec succès'
            ]);
        }
        else {
            throw new Exception('Méthode non autorisée');
        }
    }
    // Endpoints avec ID d'espace
    elseif (preg_match('/^(\d+)$/', $path, $matches)) {
        $spaceId = (int)$matches[1];
        
        // Vérifier l'accès à l'espace
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        
        // GET /api/shared-spaces/{id} - Détails d'un espace
        if ($method === 'GET') {
            $members = $spaceManager->getSpaceMembers($spaceId);
            $stats = $spaceManager->getSpaceStats($spaceId);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'space' => $space,
                    'members' => $members,
                    'stats' => $stats
                ]
            ]);
        }
        // PUT /api/shared-spaces/{id} - Modifier un espace
        elseif ($method === 'PUT') {
            if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Le nom de l\'espace est requis');
            }
            
            $spaceManager->updateSpace($spaceId, $name, $description, $currentUser['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Espace modifié avec succès'
            ]);
        }
        // DELETE /api/shared-spaces/{id} - Supprimer un espace
        elseif ($method === 'DELETE') {
            if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $spaceManager->deleteSpace($spaceId, $currentUser['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Espace supprimé avec succès'
            ]);
        }
        else {
            throw new Exception('Méthode non autorisée');
        }
    }
    // Endpoints pour les infographies
    elseif (preg_match('/^infographics\/(\d+)$/', $path, $matches)) {
        error_log("Router - Infographics endpoint détecté - Path: $path, Matches: " . json_encode($matches));
        error_log("Router - Pattern correspond: /^infographics\/(\d+)$/");
        $spaceId = (int)$matches[1];
        error_log("Router - Space ID: $spaceId");
        
        // Vérifier l'accès à l'espace
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            error_log("Router - Espace non trouvé ou accès refusé pour l'utilisateur " . $currentUser['id']);
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        error_log("Router - Espace trouvé: " . json_encode($space));
        
        // Inclure et exécuter le fichier infographics.php
        error_log("Router - Inclusion du fichier infographics.php");
        require_once __DIR__ . '/infographics.php';
        error_log("Router - Fichier infographics.php inclus");
        exit;
    }
    // Endpoints pour les membres
    elseif (preg_match('/^(\d+)\/members$/', $path, $matches)) {
        $spaceId = (int)$matches[1];
        
        // Vérifier l'accès à l'espace
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        
        // GET /api/shared-spaces/{id}/members - Liste des membres
        if ($method === 'GET') {
            $members = $spaceManager->getSpaceMembers($spaceId);
            
            echo json_encode([
                'success' => true,
                'data' => $members
            ]);
        }
        // POST /api/shared-spaces/{id}/members - Ajouter un membre
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
    elseif (preg_match('/^(\d+)\/members\/(\d+)$/', $path, $matches)) {
        $spaceId = (int)$matches[1];
        $memberId = (int)$matches[2];
        
        // Vérifier l'accès à l'espace
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        
        // PUT /api/shared-spaces/{id}/members/{user_id} - Modifier le rôle d'un membre
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
        // DELETE /api/shared-spaces/{id}/members/{user_id} - Retirer un membre
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
        error_log("Router - AUCUN PATTERN NE CORRESPOND - Path: '$path'");
        error_log("Router - Patterns testés:");
        error_log("Router - - /^infographics\/(\d+)$/");
        error_log("Router - - /^(\d+)\/members$/");
        error_log("Router - - /^(\d+)\/members\/(\d+)$/");
        throw new Exception('Endpoint non trouvé');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
