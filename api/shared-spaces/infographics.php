<?php
/**
 * API pour la gestion des infographies partagées
 * POST /api/shared-spaces/infographics/{space_id} - Partager une infographie
 * GET /api/shared-spaces/infographics/{space_id} - Liste des infographies d'un espace
 */

// Configuration de session sécurisée
require_once __DIR__ . '/../../config/session_config.php';

// Headers de sécurité
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

error_log("API Infographics - FICHIER CHARGÉ - URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("API Infographics - METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
error_log("API Infographics - CONTENT_TYPE: " . ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'N/A'));
error_log("API Infographics - QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'N/A'));
error_log("API Infographics - SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A'));
error_log("API Infographics - PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A'));

require_once __DIR__ . '/../../classes/Database.php';
error_log("API Infographics - Database.php chargé");
require_once __DIR__ . '/../../classes/SharedSpaceManager.php';
error_log("API Infographics - SharedSpaceManager.php chargé");
require_once __DIR__ . '/../../classes/Security.php';
error_log("API Infographics - Security.php chargé");
require_once __DIR__ . '/../../classes/Auth.php';
error_log("API Infographics - Auth.php chargé");

error_log("API Infographics - CLASSES CHARGÉES");

// Vérification de l'authentification - ADMIN UNIQUEMENT
Auth::requireAdmin();
$currentUser = Auth::getUser();

if (!$currentUser) {
    error_log("API Infographics - UTILISATEUR NON AUTHENTIFIÉ");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise']);
    exit;
}

error_log("API Infographics - AUTH OK - User ID: " . ($currentUser['id'] ?? 'N/A'));
error_log("API Infographics - Session ID: " . session_id());
error_log("API Infographics - Session data: " . json_encode($_SESSION));
error_log("API Infographics - POST data: " . json_encode($_POST));
error_log("API Infographics - GET data: " . json_encode($_GET));
error_log("API Infographics - php://input: " . file_get_contents('php://input'));

// Initialisation des classes
error_log("API Infographics - Initialisation Database...");
$db = Database::getInstance();
error_log("API Infographics - Database initialisée");
error_log("API Infographics - Initialisation SharedSpaceManager...");
$spaceManager = new SharedSpaceManager($db);
error_log("API Infographics - SharedSpaceManager initialisé");

// Récupération de l'URL et de la méthode
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

error_log("API Infographics - Request URI: $requestUri");
error_log("API Infographics - Method: $method");

// Extraction du chemin de l'API
$path = parse_url($requestUri, PHP_URL_PATH);
error_log("API Infographics - Path original: '$path'");
error_log("API Infographics - Request URI complet: '$requestUri'");

// Extraire l'ID de l'espace depuis les paramètres GET ou l'URL
$spaceId = null;

// Essayer d'abord le paramètre GET (nouveau .htaccess)
if (!empty($_GET['space_id'])) {
    $spaceId = (int)$_GET['space_id'];
    error_log("API Infographics - Space ID extrait depuis GET: $spaceId");
} else {
    // Fallback: essayer d'extraire depuis l'URL (ancien système)
    error_log("API Infographics - Test pattern 1: /\/api\/shared-spaces\/infographics\/(\d+)/");
    if (preg_match('/\/api\/shared-spaces\/infographics\/(\d+)/', $path, $matches)) {
        $spaceId = (int)$matches[1];
        error_log("API Infographics - Space ID extrait (pattern 1): $spaceId");
        error_log("API Infographics - Matches: " . json_encode($matches));
    } else {
        error_log("API Infographics - Pattern 1 ne correspond pas");
        error_log("API Infographics - Test pattern 2: /\/fluxvision_fin\/api\/shared-spaces\/infographics\/(\d+)/");
        if (preg_match('/\/fluxvision_fin\/api\/shared-spaces\/infographics\/(\d+)/', $path, $matches)) {
            $spaceId = (int)$matches[1];
            error_log("API Infographics - Space ID extrait (pattern 2): $spaceId");
            error_log("API Infographics - Matches: " . json_encode($matches));
        } else {
            error_log("API Infographics - Pattern 2 ne correspond pas non plus");
            error_log("API Infographics - Aucun ID d'espace trouvé dans: $path");
            error_log("API Infographics - GET params: " . json_encode($_GET));
            error_log("API Infographics - Longueur du path: " . strlen($path));
            error_log("API Infographics - Caractères du path: " . bin2hex($path));
            echo json_encode(['success' => false, 'error' => 'Endpoint non trouvé']);
            exit;
        }
    }
}

if (!$spaceId || $spaceId <= 0) {
    error_log("API Infographics - Space ID invalide: $spaceId");
    echo json_encode(['success' => false, 'error' => 'ID d\'espace invalide']);
    exit;
}

error_log("API Infographics - Space ID final: $spaceId");

// Récupération du body pour les requêtes POST/PUT
$rawInput = file_get_contents('php://input');
error_log("API Infographics - Raw input: $rawInput");
$input = json_decode($rawInput, true);
error_log("API Infographics - Decoded input: " . json_encode($input));

try {
    // Log pour le débogage
    error_log("API Infographics - Method: $method, Space ID: $spaceId, Input: " . json_encode($input));
    
    // Vérifier l'accès à l'espace
    $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
    if (!$space) {
        throw new Exception('Espace non trouvé ou accès refusé');
    }
    
    // GET /api/shared-spaces/infographics/{space_id} - Liste des infographies
    if ($method === 'GET') {
        require_once __DIR__ . '/../../classes/InfographicManager.php';
        $infographicManager = new InfographicManager();
        
        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        
        $infographics = $infographicManager->getSpaceInfographics($spaceId, $currentUser['id'], $filters);
        
        echo json_encode([
            'success' => true,
            'data' => $infographics,
            'message' => count($infographics) . ' infographie(s) trouvée(s)'
        ]);
    }
    // POST /api/shared-spaces/infographics/{space_id} - Partager une infographie
    elseif ($method === 'POST') {
        error_log("API Infographics POST - Space ID: $spaceId");
        error_log("API Infographics POST - User ID: " . $currentUser['id']);
        error_log("API Infographics POST - Input: " . json_encode($input));
        
        // Vérifier l'accès à l'espace
        error_log("API Infographics POST - Vérification accès espace $spaceId pour utilisateur " . $currentUser['id']);
        $space = $spaceManager->getSpace($spaceId, $currentUser['id']);
        if (!$space) {
            error_log("API Infographics POST - Espace non trouvé ou accès refusé");
            throw new Exception('Espace non trouvé ou accès refusé');
        }
        error_log("API Infographics POST - Espace trouvé: " . json_encode($space));
        
        // Accepte header, JSON body, ou form-data
        $receivedToken =
            ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') ?:
            ($input['csrf_token'] ?? '') ?:
            ($_POST['csrf_token'] ?? '');
        error_log("API Infographics POST - Token reçu: $receivedToken");
        error_log("API Infographics POST - Session CSRF token: " . ($_SESSION['csrf_token'] ?? 'N/A'));
        error_log("API Infographics POST - Session complète: " . json_encode($_SESSION));
        
        if (!Security::validateCSRFToken($receivedToken)) {
            error_log("API Infographics POST - CSRF Token invalide");
            error_log("API Infographics POST - Validation échouée");
            error_log("API Infographics POST - Token reçu: $receivedToken");
            error_log("API Infographics POST - Session CSRF: " . ($_SESSION['csrf_token'] ?? 'N/A'));
            throw new Exception('Token CSRF invalide');
        }
        error_log("API Infographics POST - CSRF Token valide");
        
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $infographicData = $input['infographic_data'] ?? null;
        
        error_log("API Infographics POST - Title: $title");
        error_log("API Infographics POST - Description: $description");
        error_log("API Infographics POST - InfographicData: " . json_encode($infographicData));
        
        if (empty($title)) {
            error_log("API Infographics POST - Titre vide");
            throw new Exception('Le titre de l\'infographie est requis');
        }
        
        if (empty($infographicData)) {
            error_log("API Infographics POST - Données infographie vides");
            throw new Exception('Les données de l\'infographie sont requises');
        }
        
        require_once __DIR__ . '/../../classes/InfographicManager.php';
        $infographicManager = new InfographicManager();
        
        // Vérifier que les tables existent
        $connection = $db->getConnection();
        $stmt = $connection->prepare("SHOW TABLES LIKE 'shared_infographics'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            throw new Exception('Tables des infographies partagées non créées. Veuillez exécuter le script de migration.');
        }
        
        error_log("API Infographics POST - Création infographie...");
        $infographicId = $infographicManager->createInfographic(
            $spaceId,
            $title,
            $description,
            $currentUser['id'],
            $infographicData,
            $input['tags'] ?? []
        );
        error_log("API Infographics POST - Infographie créée avec ID: $infographicId");
        
        echo json_encode([
            'success' => true,
            'data' => ['id' => $infographicId],
            'message' => 'Infographie créée avec succès'
        ]);
    }
    else {
        throw new Exception('Méthode non autorisée');
    }
    
} catch (Exception $e) {
    error_log("API Infographics - ERREUR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
