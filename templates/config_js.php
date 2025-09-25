<?php
/**
 * Configuration JavaScript dynamique
 * Ce fichier génère automatiquement la configuration JS basée sur l'environnement PHP
 */

// Récupérer la configuration d'environnement depuis PHP
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/classes/Security.php';

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Générer le token CSRF si nécessaire (version simplifiée)
if (!isset($_SESSION['csrf_token']) ||
    !isset($_SESSION['csrf_token_time']) ||
    time() - $_SESSION['csrf_token_time'] > 3600) { // 1 heure

    // Conserver l'ancien pour une courte période de grâce
    if (!empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token_prev'] = $_SESSION['csrf_token'];
        $_SESSION['csrf_token_prev_time'] = $_SESSION['csrf_token_time'] ?? (time()-1);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

$environment = DatabaseConfig::isProduction() ? 'production' : 'local';
$basePath = getBasePath();
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<script>
// Configuration d'environnement injectée par PHP (plus fiable)
window.CANTALDESTINATION_ENV = {
    environment: '<?= $environment ?>',
    basePath: '<?= $basePath ?>',
    csrfToken: '<?= $csrfToken ?>',
    isProduction: <?= DatabaseConfig::isProduction() ? 'true' : 'false' ?>,
    isLocal: <?= DatabaseConfig::isProduction() ? 'false' : 'true' ?>
};

// Fonction utilitaire pour générer les URLs
window.CantalDestinationConfig = {
    // Environnement actuel
    environment: window.CANTALDESTINATION_ENV.environment,
    
    // Base path selon l'environnement (détecté automatiquement)
    basePath: window.CANTALDESTINATION_ENV.basePath,
    
    // Génération des URLs
    url: function(path) {
        return this.basePath + path;
    },
    
    // Génération des URLs d'assets
    asset: function(path) {
        return this.basePath + path;
    },
    
    // Vérification si on est en production
    isProduction: function() {
        return window.CANTALDESTINATION_ENV.isProduction;
    },
    
    // Vérification si on est en local
    isLocal: function() {
        return window.CANTALDESTINATION_ENV.isLocal;
    }
};

// Fonctions utilitaires globales pour la compatibilité
window.asset = function(path) {
    return CantalDestinationConfig.asset(path);
};

window.url = function(path) {
    return CantalDestinationConfig.url(path);
};

// Fonction pour générer les URLs d'API
window.getApiUrl = function(endpoint) {
    return CantalDestinationConfig.url('/api/' + endpoint);
};

// Log de configuration pour le debug
if (window.CANTALDESTINATION_ENV.isLocal) {
    console.log('🔧 Configuration CantalDestination chargée:', {
        environment: window.CANTALDESTINATION_ENV.environment,
        basePath: window.CANTALDESTINATION_ENV.basePath,
        isProduction: window.CANTALDESTINATION_ENV.isProduction
    });
}
</script>
