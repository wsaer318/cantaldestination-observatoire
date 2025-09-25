<?php
/**
 * Fonctions utilitaires pour les APIs
 */

/**
 * Génère une URL relative
 */
function api_url($path = '') {
    $baseUrl = getBasePath();
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * Redirige vers une URL
 */
function api_redirect($path) {
    header('Location: ' . api_url($path));
    exit;
}
