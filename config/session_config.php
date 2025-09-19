<?php
/**
 * Configuration sécurisée des sessions pour FluxVision
 * À inclure au début de chaque page
 */

// Configuration sécurisée des sessions
if (session_status() === PHP_SESSION_NONE) {
    // Paramètres de sécurité
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // 0 pour HTTP local, 1 pour HTTPS en production
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600); // 1 heure
    ini_set('session.cookie_lifetime', 0); // Session cookie
    
    // Nom de session personnalisé
    session_name('CANTALDESTINATION_' . substr(md5(__DIR__), 0, 8));
    
    // Démarrer la session
    session_start();
    
    // Régénération périodique de l'ID de session
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Timeout de session
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > 3600) { // 1 heure
            session_destroy();
            header('Location: /fluxvision_fin/login?timeout=1');
            exit;
        }
    }
    
    $_SESSION['last_activity'] = time();
}

// Fonction pour vérifier si l'utilisateur est authentifié
function isUserAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Fonction pour obtenir les informations utilisateur
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

// Fonction pour rediriger si non authentifié
function requireAuthentication() {
    if (!isUserAuthenticated()) {
        header('Location: /fluxvision_fin/login');
        exit;
    }
}
?>
