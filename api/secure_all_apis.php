<?php

/**
 * Script de sécurisation automatique de toutes les API
 * Remplace l'usage direct de $_GET par la validation SecurityManager
 */

$apiFiles = [
    'bloc_a.php', 'bloc_d1.php', 'bloc_d2.php', 'bloc_d3.php', 
    'bloc_d5.php', 'bloc_d6.php', 'bloc_d7.php',
    'bloc_d1_exc.php', 'bloc_d2_exc.php', 'bloc_d3_exc.php', 
    'bloc_d5_exc.php', 'bloc_d6_exc.php'
];

foreach ($apiFiles as $apiFile) {
    if (file_exists(__DIR__ . '/' . $apiFile)) {
        secureApiFile($apiFile);
    }
}

function secureApiFile(string $filename): void {
    $filepath = __DIR__ . '/' . $filename;
    $content = file_get_contents($filepath);
    
    // Ajouter l'include du middleware si pas déjà présent
    if (strpos($content, 'security_middleware.php') === false) {
        $content = str_replace(
            "require_once __DIR__ . '/../config/app.php';",
            "require_once __DIR__ . '/../config/app.php';\nrequire_once __DIR__ . '/security_middleware.php';",
            $content
        );
    }
    
    // Remplacer l'utilisation directe de $_GET
    $patterns = [
        '/\$annee = \$_GET\[\'annee\'\] \?\? null;/' => '$params = ApiSecurityMiddleware::sanitizeApiInput($_GET);' . "\n" . '$annee = $params[\'annee\'] ?? null;',
        '/\$periode = \$_GET\[\'periode\'\] \?\? null;/' => '$periode = $params[\'periode\'] ?? null;',
        '/\$zone = \$_GET\[\'zone\'\] \?\? null;/' => '$zone = $params[\'zone\'] ?? null;'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    // Remplacer la gestion d'erreurs
    $content = str_replace(
        '} catch (Exception $e) {
    if (DEBUG) {
        apiError(\'Erreur lors du chargement des données : \' . $e->getMessage(), 500);
    } else {
        apiError(\'Erreur interne du serveur\', 500);
    }',
        '} catch (Exception $e) {
    ApiSecurityMiddleware::handleApiError($e, \'/api/' . pathinfo($filename, PATHINFO_FILENAME) . '\');',
        $content
    );
    
    file_put_contents($filepath, $content);
    echo "✅ Sécurisé : $filename\n";
}

echo "🔒 Sécurisation des API terminée.\n"; 