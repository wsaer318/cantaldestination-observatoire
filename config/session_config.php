<?php
/**
 * Configuration sécurisée des sessions pour FluxVision
 * À inclure au début de chaque page
 */

if (session_status() === PHP_SESSION_NONE) {
    // Paramètres de sécurité
    ini_set('session.cookie_httponly', 1);

    $forceSecureEnv = getenv('FORCE_SECURE_COOKIE');
    if ($forceSecureEnv === false && isset($_ENV['FORCE_SECURE_COOKIE'])) {
        $forceSecureEnv = $_ENV['FORCE_SECURE_COOKIE'];
    }
    $cookieSecure = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forceSecureEnv === '1');
    ini_set('session.cookie_secure', $cookieSecure ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600); // 1 heure
    ini_set('session.cookie_lifetime', 0);   // Cookie de session

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
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_destroy();
        header('Location: /fluxvision_fin/login?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function isUserAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function requireAuthentication() {
    if (!isUserAuthenticated()) {
        header('Location: /fluxvision_fin/login');
        exit;
    }
}
?>
