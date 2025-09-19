<?php

/**
 * Middleware de sécurité pour les API
 * Applique rate limiting, validation des headers, et logging
 */

// Inclure les classes nécessaires
require_once __DIR__ . '/../classes/SecurityManager.php';

class ApiSecurityMiddleware {
    
    /**
     * Applique toutes les vérifications de sécurité pour les API
     */
    public static function apply(): bool {
        // 1. Vérifier le rate limiting pour les API
        if (!SecurityManager::checkApiRateLimit()) {
            http_response_code(429);
            SecurityManager::logSecurityEvent('API_RATE_LIMITED', [
                'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ], 'HIGH');
            
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
                'retry_after' => 3600
            ]);
            return false;
        }
        
        // 2. Enregistrer la tentative d'accès API
        SecurityManager::recordAttempt('api_requests');
        
        // 3. Valider les headers de sécurité
        if (!self::validateApiHeaders()) {
            http_response_code(400);
            SecurityManager::logSecurityEvent('API_INVALID_HEADERS', [
                'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                'headers' => self::getSafeHeaders()
            ], 'MEDIUM');
            
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid headers',
                'message' => 'Headers de requête invalides'
            ]);
            return false;
        }
        
        // 4. Vérifier le type de contenu pour les requêtes POST/PUT
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (!self::isValidContentType($contentType)) {
                http_response_code(400);
                SecurityManager::logSecurityEvent('API_INVALID_CONTENT_TYPE', [
                    'content_type' => $contentType,
                    'endpoint' => $_SERVER['REQUEST_URI'] ?? ''
                ], 'MEDIUM');
                
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Invalid content type',
                    'message' => 'Type de contenu non supporté'
                ]);
                return false;
            }
        }
        
        // 5. Logger l'accès API
        SecurityManager::logSecurityEvent('API_ACCESS', [
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_id' => Auth::getUser()['id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ], 'INFO');
        
        // 6. Définir les headers de réponse sécurisés
        self::setSecureApiHeaders();
        
        return true;
    }
    
    /**
     * Valide les headers de la requête API
     */
    private static function validateApiHeaders(): bool {
        // Vérifier la présence d'un User-Agent (protection contre les bots simples)
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        // Vérifier les headers suspects (outils d'attaque connus)
        $suspiciousPatterns = [
            'sqlmap', 'nikto', 'gobuster', 'dirb', 'hydra', 
            'nmap', 'masscan', 'zap', 'burp', 'curl/7.0'
        ];
        
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                SecurityManager::logSecurityEvent('SUSPICIOUS_USER_AGENT', [
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'pattern' => $pattern
                ], 'HIGH');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Vérifie si le type de contenu est valide
     */
    private static function isValidContentType(string $contentType): bool {
        $allowedTypes = [
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'text/plain'
        ];
        
        // Extraire le type principal (sans charset)
        $mainType = explode(';', $contentType)[0];
        
        return in_array(trim($mainType), $allowedTypes);
    }
    
    /**
     * Définit les headers de sécurité pour les réponses API
     */
    private static function setSecureApiHeaders(): void {
        // Content-Type par défaut pour les API
        header('Content-Type: application/json; charset=utf-8');
        
        // Headers de sécurité
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // Cache control pour les API
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // CORS sécurisé (si nécessaire)
        if (self::shouldAllowCors()) {
            header('Access-Control-Allow-Origin: ' . self::getAllowedOrigin());
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
        }
    }
    
    /**
     * Vérifie si CORS doit être autorisé
     */
    private static function shouldAllowCors(): bool {
        // Pour l'instant, pas de CORS externe
        return false;
    }
    
    /**
     * Retourne l'origine autorisée pour CORS
     */
    private static function getAllowedOrigin(): string {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }
    
    /**
     * Retourne les headers sécurisés pour le logging
     */
    private static function getSafeHeaders(): array {
        $safeHeaders = [];
        $allowedHeaders = [
            'HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE',
            'HTTP_ACCEPT_ENCODING', 'CONTENT_TYPE', 'CONTENT_LENGTH'
        ];
        
        foreach ($allowedHeaders as $header) {
            if (isset($_SERVER[$header])) {
                $safeHeaders[$header] = $_SERVER[$header];
            }
        }
        
        return $safeHeaders;
    }
    
    /**
     * Valide et sanitise les paramètres de requête
     */
    public static function sanitizeApiInput(array $input): array {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = SecurityManager::sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeApiInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Gère les erreurs API de manière sécurisée
     */
    public static function handleApiError(\Exception $e, string $endpoint = ''): void {
        // Logger l'erreur
        SecurityManager::logSecurityEvent('API_ERROR', [
            'endpoint' => $endpoint ?: ($_SERVER['REQUEST_URI'] ?? ''),
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'user_id' => Auth::getUser()['id'] ?? null
        ], 'HIGH');
        
        // Réponse d'erreur générique en production
        http_response_code(500);
        header('Content-Type: application/json');
        
        $response = [
            'error' => 'Internal server error',
            'message' => 'Une erreur interne s\'est produite'
        ];
        
        // En mode debug, ajouter plus de détails
        if (defined('DEBUG') && DEBUG) {
            $response['debug'] = [
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
        
        echo json_encode($response);
    }
}

// Appliquer automatiquement le middleware si ce fichier est inclus dans une API
if (basename($_SERVER['SCRIPT_NAME']) !== 'security_middleware.php') {
    if (!ApiSecurityMiddleware::apply()) {
        exit; // Arrêter l'exécution si la sécurité échoue
    }
}