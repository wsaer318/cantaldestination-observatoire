<?php
// Gestion d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0); // Masqué par défaut, activé après chargement config
ini_set('log_errors', 1);

try {
    // Configuration sÃ©curisÃ©e des sessions
    require_once 'config/session_config.php';
    
    require_once 'config/app.php';

    if (defined('DEBUG') && DEBUG) {
        ini_set('display_errors', 1);
    } else {
        ini_set('display_errors', 0);
    }
    require_once 'classes/Database.php';
    require_once 'classes/Auth.php';
    require_once 'classes/Security.php';
    require_once 'classes/SecurityManager.php';
    require_once 'classes/AuthenticationEnhancer.php';
    require_once 'classes/FormValidator.php';
    
    // Initialiser les protections de sÃ©curitÃ© avancÃ©es
    SecurityManager::initialize();
    Security::initialize();
    
    // Initialiser les tables d'authentification (si possible)
    try {
        AuthenticationEnhancer::initializeTables();
    } catch (Exception $e) {
        // Si l'initialisation Ã©choue, continuer sans (mode dÃ©gradÃ©)
        error_log("Erreur initialisation AuthenticationEnhancer: " . $e->getMessage());
    }
} catch (Exception $e) {
    // En cas d'erreur critique, afficher une page d'erreur simple
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><title>Erreur</title></head><body>";
    echo "<h1>Erreur de chargement</h1>";
    echo "<p>Une erreur s'est produite lors du chargement de l'application.</p>";
    if (defined('DEBUG') && DEBUG) {
        echo "<p>DÃ©tails: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</body></html>";
    exit;
}

// Fonction pour inclure un template PHP
function renderTemplate($template, $variables = []) {
    extract($variables);
    $templatePath = TEMPLATES_PATH . '/' . $template;
    
    if (!file_exists($templatePath)) {
        throw new Exception("Template non trouvÃ© : " . $template);
    }
    
    ob_start();
    include $templatePath;
    return ob_get_clean();
}

// Fonction pour servir les templates
function serveTemplate($template, $variables = []) {
    try {
        echo renderTemplate($template, $variables);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Erreur de template : " . $e->getMessage();
    }
}

// Routeur simple
$request = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request, PHP_URL_PATH) ?? '/';

// Retirer le prÃ©fixe du dossier si prÃ©sent (pour XAMPP)
$basePath = '';
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/fluxvision_fin/') === 0) {
    $basePath = '/fluxvision_fin';
    $path = substr($path, strlen($basePath));
}

// Supprimer le slash final
$path = rtrim($path, '/');
if (empty($path)) $path = '/';

// Debug temporaire (Ã  supprimer en production)
if (DEBUG) {
    error_log("DEBUG ROUTE - Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("DEBUG ROUTE - Parsed path: $path");
    if ($path === '/infographie') {
        error_log("Route infographie dÃ©tectÃ©e. Path: $path");
    }
}

// Routes principales
switch ($path) {
    case '/':
        // Rediriger vers login si pas connectÃ©, sinon vers la page d'accueil
        if (!Auth::isAuthenticated()) {
            header('Location: ' . url('/login'));
            exit;
        }
        serveTemplate('index.php');
        break;
    
    case '/dashboard':
        Auth::requireAuth();
        $dashboardType = $_GET['type'] ?? 'General';
        
        if ($dashboardType === 'Excursionnistes') {
            serveTemplate('tdb_excursionnistes.php', ['dashboard_type' => $dashboardType]);
        } else {
            serveTemplate('tdb.php', ['dashboard_type' => $dashboardType]);
        }
        break;
    

    
    case '/tdb_comparaison':
        Auth::requireAuth();
        serveTemplate('tdb_comparaison.php', ['dashboard_type' => 'Comparaison']);
        break;
    
    case '/infographie':
        Auth::requireAuth();
        serveTemplate('infographie.php', ['dashboard_type' => 'Infographie']);
        break;
    
    case '/fiches':
        Auth::requireAuth();
        serveTemplate('fiches_metho.php');
        break;
    
    case '/tables':
        Auth::requireAuth();
        serveTemplate('tables_indicateurs.php');
        break;
    
    case '/help':
        Auth::requireAuth();
        serveTemplate('help.php');
        break;
    
    case '/shared-spaces':
        Auth::requireAdmin();
        serveTemplate('shared_spaces.php');
        break;
        
    case '/shared-spaces/create':
        Auth::requireAdmin();
        serveTemplate('shared_spaces_create.php');
        break;
        
    case '/shared-spaces/select':
        Auth::requireAdmin();
        
        require_once 'classes/SharedSpaceManager.php';
        require_once 'classes/UserDataManager.php';
        
        $spaceManager = new SharedSpaceManager();
        $userDataManager = new UserDataManager();
        $user = Auth::getUser();
        
        // RÃ©cupÃ©rer les paramÃ¨tres de l'infographie depuis l'URL
        $infographicParams = [
            'year' => $_GET['year'] ?? '',
            'period' => $_GET['period'] ?? '',
            'zone' => $_GET['zone'] ?? 'CANTAL',
            'debut' => $_GET['debut'] ?? null,
            'fin' => $_GET['fin'] ?? null,
            'unique_id' => $_GET['unique_id'] ?? null,
            'preview_id' => $_GET['preview_id'] ?? null
        ];
        
        // RÃ©cupÃ©rer les espaces de l'utilisateur
        $userSpaces = $spaceManager->getUserSpaces($user['id']);
        
        // Ajouter les statistiques pour chaque espace
        foreach ($userSpaces as &$space) {
            $space['stats'] = $spaceManager->getSpaceStats($space['id']);
        }
        
        serveTemplate('shared_spaces_select.php', compact('userSpaces', 'infographicParams'));
        break;
    
    case '/methodologie':
        Auth::requireAuth();
        serveTemplate('methodologie.php');
        break;
    
    case '/env-info':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        serveTemplate('env_info.php');
        break;
        
    case '/admin/periodes':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        serveTemplate('admin_periodes.php');
        break;
        
    case '/admin/email-test':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        require_once 'classes/EmailManager.php';
        
        $message = null;
        $error = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VÃ©rification CSRF
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                serveTemplate('403.php');
                break;
            }
            
            $action = $_POST['action'] ?? '';
            
            if ($action === 'test_config') {
                $config = EmailManager::getConfiguration();
                $testResults = EmailManager::testConfiguration();
                $message = [
                    'type' => 'config',
                    'config' => $config,
                    'results' => $testResults
                ];
            } elseif ($action === 'send_test') {
                try {
                    $success = EmailManager::sendUserCreationNotification(
                        'utilisateur_test',
                        'user',
                        $user['username']
                    );
                    
                    if ($success) {
                        $message = ['type' => 'success', 'text' => 'Email de test envoyÃ© avec succÃ¨s !'];
                    } else {
                        $error = 'Ã‰chec de l\'envoi du test email';
                    }
                } catch (Exception $e) {
                    $error = 'Erreur : ' . $e->getMessage();
                }
            }
        }
        
        serveTemplate('admin_email_test.php', compact('message', 'error'));
        break;
        
    case '/admin/repair-users':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        include 'repair_users.php';
        break;
        
    case '/admin/temp-tables':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        serveTemplate('admin_temp_tables.php');
        break;
        
    case '/admin/temp-tables/download-logs':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        $log_file = BASE_PATH . '/data/logs/temp_tables_update.log';
        if (file_exists($log_file)) {
            $filename = 'temp_tables_logs_' . date('Y-m-d_H-i-s') . '.log';
            
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($log_file));
            
            readfile($log_file);
            exit;
        } else {
            http_response_code(404);
            echo "Fichier de log non trouvÃ©";
        }
        break;
        
    case '/admin':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        // GÃ©rer les actions POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VÃ©rification CSRF
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                Security::logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => $_POST['action'] ?? 'unknown'], 'HIGH');
                serveTemplate('403.php');
                break;
            }
            
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'create':
                    try {
                        $validatedData = FormValidator::validateUserCreation($_POST);
                        
                        if (Auth::createUser(
                            $validatedData['username'], 
                            $validatedData['password'], 
                            $validatedData['name'], 
                            $validatedData['role'], 
                            $validatedData['email'] ?? null
                        )) {
                            $success = "Utilisateur '{$validatedData['username']}' crÃ©Ã© avec succÃ¨s.";
                        } else {
                            $error = "Erreur lors de la crÃ©ation de l'utilisateur. Le nom d'utilisateur existe peut-Ãªtre dÃ©jÃ .";
                        }
                    } catch (InvalidArgumentException $e) {
                        $error = $e->getMessage();
                    } catch (SecurityException $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'deactivate':
                    $userId = $_POST['user_id'] ?? '';
                    if (!empty($userId)) {
                        if (Auth::deactivateUser($userId)) {
                            $success = "Utilisateur dÃ©sactivÃ© avec succÃ¨s.";
                        } else {
                            $error = "Erreur lors de la dÃ©sactivation de l'utilisateur.";
                        }
                    }
                    break;
                    
                case 'delete':
                    try {
                        $userId = $_POST['user_id'] ?? '';
                        if (!empty($userId)) {
                            if (Auth::deleteUser($userId)) {
                                $success = "Utilisateur supprimÃ© dÃ©finitivement avec succÃ¨s.";
                            } else {
                                $error = "Erreur lors de la suppression de l'utilisateur.";
                            }
                        } else {
                            $error = "ID utilisateur manquant.";
                        }
                    } catch (SecurityException $e) {
                        $error = $e->getMessage();
                    } catch (Exception $e) {
                        $error = "Erreur lors de la suppression de l'utilisateur.";
                    }
                    break;
            }
        }
        
        // RÃ©cupÃ©rer tous les utilisateurs avec dÃ©chiffrement automatique
        require_once 'classes/UserDataManager.php';
        $users = UserDataManager::getAllUsers();
        
        // Initialiser les variables si elles n'existent pas
        $success = $success ?? null;
        $error = $error ?? null;
        
        serveTemplate('admin.php', compact('users', 'success', 'error'));
        break;
    
    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // VÃ©rifier le rate limiting AVANT tout traitement
                if (!SecurityManager::checkLoginRateLimit()) {
                    SecurityManager::logSecurityEvent('LOGIN_RATE_LIMITED', ['ip' => $_SERVER['REMOTE_ADDR']], 'HIGH');
                    serveTemplate('login.php', ['error' => 'Trop de tentatives de connexion. Veuillez rÃ©essayer dans 15 minutes.']);
                    break;
                }
                
                // VÃ©rification CSRF
                if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    Security::logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => 'login'], 'HIGH');
                    serveTemplate('login.php', ['error' => 'Token de sÃ©curitÃ© invalide']);
                    break;
                }
                
                // Valider les donnÃ©es du formulaire
                $validatedData = FormValidator::validateLogin($_POST);
                
                // Enregistrer la tentative de connexion
                SecurityManager::recordAttempt('login_attempts');
                
                if (Auth::login($validatedData['username'], $validatedData['password'])) {
                    SecurityManager::logSecurityEvent('LOGIN_SUCCESS', ['username' => $validatedData['username']], 'INFO');
                    
                    // VÃ©rifier si une URL de redirection sÃ©curisÃ©e a Ã©tÃ© fournie
                    $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '/';
                    if (!Security::validateRedirectURL($redirect)) {
                        $redirect = '/';
                    }
                    header('Location: ' . url($redirect));
                    exit;
                } else {
                    SecurityManager::logSecurityEvent('LOGIN_FAILED', ['username' => $validatedData['username']], 'MEDIUM');
                    serveTemplate('login.php', ['error' => 'Nom d\'utilisateur ou mot de passe incorrect']);
                }
            } catch (InvalidArgumentException $e) {
                SecurityManager::logSecurityEvent('LOGIN_VALIDATION_ERROR', ['error' => $e->getMessage()], 'MEDIUM');
                serveTemplate('login.php', ['error' => $e->getMessage()]);
            } catch (SecurityException $e) {
                SecurityManager::logSecurityEvent('LOGIN_SECURITY_ERROR', ['error' => $e->getMessage()], 'HIGH');
                serveTemplate('login.php', ['error' => $e->getMessage()]);
            }
        } else {
            // Rediriger si dÃ©jÃ  connectÃ©
            Auth::redirectIfAuthenticated();
            serveTemplate('login.php');
        }
        break;
    
    case '/logout':
        Auth::logout();
        header('Location: ' . url('/login'));
        exit;
        break;
    
    // Routes API (toutes protÃ©gÃ©es par authentification)
    case '/api/filters':
        Auth::requireAuth();
        include 'api/filters.php';
        break;
    
    case '/api/fiches':
        Auth::requireAuth();
        include 'api/fiches.php';
        break;
    
    case '/api/periodes_dates':
        Auth::requireAuth();
        include 'api/periodes_dates.php';
        break;
    
    case '/api/bloc_a':
        Auth::requireAuth();
        include 'api/bloc_a.php';
        break;
    
    case '/api/bloc_d1':
        Auth::requireAuth();
        include 'api/bloc_d1.php';
        break;
    
    case '/api/bloc_d2':
        Auth::requireAuth();
        include 'api/bloc_d2.php';
        break;
    
    case '/api/bloc_d3':
        Auth::requireAuth();
        include 'api/bloc_d3.php';
        break;
    
    case '/api/bloc_d5':
        Auth::requireAuth();
        include 'api/bloc_d5.php';
        break;
    
    case '/api/bloc_d6':
        Auth::requireAuth();
        include 'api/bloc_d6.php';
        break;
    
    case '/api/bloc_d7':
        Auth::requireAuth();
        include 'api/bloc_d7.php';
        break;
    
    case '/api/bloc_d1_exc':
        Auth::requireAuth();
        include 'api/bloc_d1_exc.php';
        break;
    
    case '/api/bloc_d2_exc':
        Auth::requireAuth();
        include 'api/bloc_d2_exc.php';
        break;
    
    case '/api/bloc_d3_exc':
        Auth::requireAuth();
        include 'api/bloc_d3_exc.php';
        break;
    
    case '/api/bloc_d5_exc':
        Auth::requireAuth();
        include 'api/bloc_d5_exc.php';
        break;
    
    case '/api/bloc_d6_exc':
        Auth::requireAuth();
        include 'api/bloc_d6_exc.php';
        break;
    
    case '/api/periodes_dates':
        Auth::requireAuth();
        include 'api/periodes_dates.php';
        break;
    
    case '/api/fiches':
        Auth::requireAuth();
        include 'api/fiches.php';
        break;
    
    // Gestion des espaces partagÃ©s
    case '/admin/shared-spaces':
        Auth::requireAuth();
        $user = Auth::getUser();
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        // Inclure les classes nÃ©cessaires
        require_once 'classes/SharedSpaceManager.php';
        require_once 'classes/UserDataManager.php';
        
        $spaceManager = new SharedSpaceManager();
        $success = null;
        $error = null;
        
        // GÃ©rer les actions POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VÃ©rification CSRF
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                serveTemplate('403.php');
                break;
            }
            
            $action = $_POST['action'] ?? '';
            
            if ($action === 'create') {
                try {
                    $spaceName = trim($_POST['space_name'] ?? '');
                    $spaceDescription = trim($_POST['space_description'] ?? '');
                    $members = $_POST['members'] ?? [];
                    
                    if (empty($spaceName)) {
                        throw new Exception('Le nom de l\'espace est requis');
                    }
                    
                    // PrÃ©parer les membres initiaux
                    $initialMembers = [];
                    foreach ($members as $memberId) {
                        $role = $_POST['member_role_' . $memberId] ?? 'reader';
                        $initialMembers[] = [
                            'user_id' => (int)$memberId,
                            'role' => $role
                        ];
                    }
                    
                    // CrÃ©er l'espace
                    $spaceId = $spaceManager->createSpace($spaceName, $spaceDescription, $user['id'], $initialMembers);
                    $success = "Espace '$spaceName' crÃ©Ã© avec succÃ¨s !";
                    
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
        
        // RÃ©cupÃ©rer les donnÃ©es pour l'affichage (inclure les espaces dÃ©sactivÃ©s pour l'admin)
        $userSpaces = $spaceManager->getUserSpaces($user['id'], true); // true = inclure les dÃ©sactivÃ©s
        $availableUsers = UserDataManager::getAllUsers();
        
        // Calculer les statistiques
        $stats = [
            'total_spaces' => count($userSpaces),
            'total_memberships' => 0,
            'total_infographics' => 0,
            'total_comments' => 0
        ];
        
        // Compter les membres et infographies
        $spaceStats = [];
        foreach ($userSpaces as $space) {
            $spaceStats[$space['id']] = $spaceManager->getSpaceStats($space['id']);
            $stats['total_memberships'] += $spaceStats[$space['id']]['member_count'];
            $stats['total_infographics'] += $spaceStats[$space['id']]['infographic_count'];
            $stats['total_comments'] += $spaceStats[$space['id']]['comment_count'];
        }
        
        serveTemplate('admin_shared_spaces.php', compact('userSpaces', 'availableUsers', 'stats', 'spaceStats', 'success', 'error'));
        break;

    // Gestion d'un espace spÃ©cifique
    case (preg_match('/^\/admin\/shared-spaces\/(\d+)\/manage$/', $path, $matches) ? true : false):
        Auth::requireAuth();
        $user = Auth::getUser();
        $spaceId = (int)$matches[1];
        
        // VÃ©rifier que l'utilisateur est administrateur
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            serveTemplate('403.php');
            break;
        }
        
        // Inclure les classes nÃ©cessaires
        require_once 'classes/SharedSpaceManager.php';
        require_once 'classes/UserDataManager.php';
        
        $spaceManager = new SharedSpaceManager();
        $success = null;
        $error = null;
        
        // RÃ©cupÃ©rer l'espace
        $space = $spaceManager->getSpace($spaceId, $user['id']);
        if (!$space) {
            http_response_code(404);
            serveTemplate('404.php');
            break;
        }
        
        // GÃ©rer les actions POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VÃ©rification CSRF
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                serveTemplate('403.php');
                break;
            }
            
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'update':
                    try {
                        $spaceName = trim($_POST['space_name'] ?? '');
                        $spaceDescription = trim($_POST['space_description'] ?? '');
                        
                        if (empty($spaceName)) {
                            throw new Exception('Le nom de l\'espace est requis');
                        }
                        
                        $spaceManager->updateSpace($spaceId, $spaceName, $spaceDescription, $user['id']);
                        $success = "Espace mis Ã  jour avec succÃ¨s !";
                        
                        // Mettre Ã  jour les donnÃ©es de l'espace
                        $space = $spaceManager->getSpace($spaceId, $user['id']);
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'add_member':
                    try {
                        $memberId = (int)($_POST['user_id'] ?? 0);
                        $role = $_POST['role'] ?? 'reader';
                        
                        if ($memberId <= 0) {
                            throw new Exception('Utilisateur invalide');
                        }
                        
                        $spaceManager->addMember($spaceId, $memberId, $role);
                        $success = "Membre ajoutÃ© avec succÃ¨s !";
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'update_role':
                    try {
                        $memberId = (int)($_POST['member_id'] ?? 0);
                        $newRole = $_POST['new_role'] ?? 'reader';
                        
                        if ($memberId <= 0) {
                            throw new Exception('Membre invalide');
                        }
                        
                        $spaceManager->updateMemberRole($spaceId, $memberId, $newRole, $user['id']);
                        $success = "RÃ´le mis Ã  jour avec succÃ¨s !";
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'remove_member':
                    try {
                        $memberId = (int)($_POST['member_id'] ?? 0);
                        
                        if ($memberId <= 0) {
                            throw new Exception('Membre invalide');
                        }
                        
                        $spaceManager->removeMember($spaceId, $memberId, $user['id']);
                        $success = "Membre retirÃ© avec succÃ¨s !";
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'delete':
                    try {
                        $spaceManager->deleteSpace($spaceId, $user['id'], true); // Suppression dÃ©finitive
                        header('Location: ' . url('/admin/shared-spaces'));
                        exit;
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'restore':
                    try {
                        $spaceManager->restoreSpace($spaceId, $user['id']);
                        header('Location: ' . url('/admin/shared-spaces'));
                        exit;
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'disable':
                    try {
                        $spaceManager->deleteSpace($spaceId, $user['id'], false); // DÃ©sactivation (soft delete)
                        header('Location: ' . url('/admin/shared-spaces'));
                        exit;
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'add_multiple_members':
                    try {
                        $users = $_POST['users'] ?? [];
                        $defaultRole = $_POST['default_role'] ?? 'reader';
                        
                        if (empty($users)) {
                            throw new Exception('Aucun utilisateur sÃ©lectionnÃ©');
                        }
                        
                        $addedCount = 0;
                        foreach ($users as $userId) {
                            $userId = (int)$userId;
                            if ($userId > 0) {
                                try {
                                    $spaceManager->addMember($spaceId, $userId, $defaultRole);
                                    $addedCount++;
                                } catch (Exception $e) {
                                    // Continuer avec les autres utilisateurs mÃªme si un Ã©choue
                                    error_log("Erreur ajout membre $userId: " . $e->getMessage());
                                }
                            }
                        }
                        
                        if ($addedCount > 0) {
                            $success = "$addedCount membre" . ($addedCount > 1 ? 's' : '') . " ajoutÃ©" . ($addedCount > 1 ? 's' : '') . " avec succÃ¨s !";
                        } else {
                            throw new Exception('Aucun membre n\'a pu Ãªtre ajoutÃ©');
                        }
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'update_multiple_roles':
                    try {
                        $membersToUpdate = $_POST['members_to_update'] ?? [];
                        $newRole = $_POST['new_role'] ?? '';
                        
                        if (empty($membersToUpdate)) {
                            throw new Exception('Aucun membre sÃ©lectionnÃ©');
                        }
                        
                        if (empty($newRole)) {
                            throw new Exception('Nouveau rÃ´le requis');
                        }
                        
                        $updatedCount = 0;
                        foreach ($membersToUpdate as $memberId) {
                            $memberId = (int)$memberId;
                            if ($memberId > 0) {
                                try {
                                    $spaceManager->updateMemberRole($spaceId, $memberId, $newRole, $user['id']);
                                    $updatedCount++;
                                } catch (Exception $e) {
                                    // Continuer avec les autres membres mÃªme si un Ã©choue
                                    error_log("Erreur mise Ã  jour rÃ´le membre $memberId: " . $e->getMessage());
                                }
                            }
                        }
                        
                        if ($updatedCount > 0) {
                            $success = "$updatedCount rÃ´le" . ($updatedCount > 1 ? 's' : '') . " mis" . ($updatedCount > 1 ? '' : '') . " Ã  jour avec succÃ¨s !";
                        } else {
                            throw new Exception('Aucun rÃ´le n\'a pu Ãªtre mis Ã  jour');
                        }
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
                    
                case 'remove_multiple_members':
                    try {
                        $membersToRemove = $_POST['members_to_remove'] ?? [];
                        
                        if (empty($membersToRemove)) {
                            throw new Exception('Aucun membre sÃ©lectionnÃ©');
                        }
                        
                        $removedCount = 0;
                        foreach ($membersToRemove as $memberId) {
                            $memberId = (int)$memberId;
                            if ($memberId > 0) {
                                try {
                                    $spaceManager->removeMember($spaceId, $memberId, $user['id']);
                                    $removedCount++;
                                } catch (Exception $e) {
                                    // Continuer avec les autres membres mÃªme si un Ã©choue
                                    error_log("Erreur suppression membre $memberId: " . $e->getMessage());
                                }
                            }
                        }
                        
                        if ($removedCount > 0) {
                            $success = "$removedCount membre" . ($removedCount > 1 ? 's' : '') . " retirÃ©" . ($removedCount > 1 ? 's' : '') . " avec succÃ¨s !";
                        } else {
                            throw new Exception('Aucun membre n\'a pu Ãªtre retirÃ©');
                        }
                        
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    break;
            }
        }
        
        // RÃ©cupÃ©rer les donnÃ©es pour l'affichage
        $members = $spaceManager->getSpaceMembers($spaceId);
        $availableUsers = UserDataManager::getAllUsers();
        $currentUser = $user;
        
        serveTemplate('admin_shared_space_manage.php', compact('space', 'members', 'availableUsers', 'currentUser', 'success', 'error'));
        break;

    // Favicon
    case '/favicon.ico':
        header('Content-Type: image/x-icon');
        header('Cache-Control: public, max-age=86400'); // Cache 24h
        // Envoyer un favicon 1x1 transparent pour Ã©viter l'erreur 404
        echo base64_decode('AAABAAEAEBAAAAEAIABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
        exit;
        break;

    // Servir les fichiers statiques (CSS, JS, images)
    default:
        if (preg_match('/^\/static\//', $path)) {
            $filePath = BASE_PATH . $path;
            
            if (file_exists($filePath)) {
                // DÃ©terminer le type MIME
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                $mimeTypes = [
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'json' => 'application/json',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    'pdf' => 'application/pdf'
                ];
                
                $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
                header('Content-Type: ' . $mimeType);
                
                // Cache pour les ressources statiques
                header('Cache-Control: public, max-age=3600');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
                
                readfile($filePath);
            } else {
                http_response_code(404);
                echo "Fichier non trouvÃ© : " . $path;
            }
        } else {
            // Page 404
            http_response_code(404);
            serveTemplate('404.php');
        }
        break;
} 


