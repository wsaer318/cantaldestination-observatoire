<?php
/**
 * API pour la gestion des espaces partagés (CRUD)
 * Endpoints:
 * - GET /api/shared-spaces/spaces - Liste des espaces de l'utilisateur
 * - POST /api/shared-spaces/spaces - Créer un espace
 * - GET /api/shared-spaces/spaces/{id} - Détails d'un espace
 * - PUT /api/shared-spaces/spaces/{id} - Modifier un espace
 * - DELETE /api/shared-spaces/spaces/{id} - Supprimer un espace
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
$path = str_replace('/api/shared-spaces/spaces', '', $path);
$path = trim($path, '/');

// Récupération du body pour les requêtes POST/PUT
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Routing des endpoints
    if (empty($path)) {
        // GET /api/shared-spaces/spaces - Liste des espaces
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
        // POST /api/shared-spaces/spaces - Créer un espace
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
        
        // GET /api/shared-spaces/spaces/{id} - Détails d'un espace
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
        // PUT /api/shared-spaces/spaces/{id} - Modifier un espace
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
        // DELETE /api/shared-spaces/spaces/{id} - Supprimer un espace
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
