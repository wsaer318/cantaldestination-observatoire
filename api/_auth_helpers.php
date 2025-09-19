<?php
/**
 * Helpers d'authentification pour l'API
 * Gère l'authentification par Bearer token ou par session
 */

/**
 * Vérifie l'authentification API (Bearer token) ou session
 * @return bool True si authentifié, False sinon
 */
function require_api_auth_or_session() {
    // Vérifier d'abord l'authentification par Bearer token
    if (check_bearer_auth()) {
        return true;
    }
    
    // Sinon, vérifier l'authentification par session
    if (class_exists('Auth') && Auth::isAuthenticated()) {
        return true;
    }
    
    // Aucune authentification valide
    http_response_code(401);
    jsonResponse(['error' => 'Authentification requise'], 401);
    return false;
}

/**
 * Vérifie l'authentification par Bearer token
 * @return bool True si token valide, False sinon
 */
function check_bearer_auth() {
    // Récupérer le header Authorization
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // Vérifier le format "Bearer <token>"
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return false;
    }
    
    $token = $matches[1];
    
    // Récupérer les tokens autorisés depuis l'environnement
    $raw = $_ENV['API_TOKENS'] ?? $_SERVER['API_TOKENS'] ?? getenv('API_TOKENS') ?? '';
    $tokens = array_filter(array_map('trim', explode(',', $raw)));
    
    // Vérifier si le token est dans la liste autorisée
    return in_array($token, $tokens);
}

/**
 * Vérifie si l'utilisateur a les droits admin (par session ou API)
 * @return bool True si admin, False sinon
 */
function require_admin_auth() {
    // Vérifier d'abord l'authentification API
    if (!require_api_auth_or_session()) {
        return false;
    }
    
    // Pour l'API, on considère que les tokens valides ont les droits admin
    if (check_bearer_auth()) {
        return true;
    }
    
    // Sinon, vérifier les droits admin par session
    if (class_exists('Auth') && Auth::isAdmin()) {
        return true;
    }
    
    // Pas de droits admin
    http_response_code(403);
    jsonResponse(['error' => 'Accès refusé. Droits administrateur requis.'], 403);
    return false;
}
