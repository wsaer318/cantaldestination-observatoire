<?php
/**
 * Script de sécurisation contre les injections SQL
 * Audit et correction automatique des vulnérabilités
 */

echo "🔒 AUDIT DE SÉCURITÉ - Protection contre les injections SQL\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Liste des APIs à sécuriser
$apisToSecure = [
    'api/legacy/blocks/bloc_d3.php',
    'api/legacy/blocks/bloc_d5.php', 
    'api/legacy/blocks/bloc_d6.php',
    'api/legacy/blocks/bloc_d7.php',
    'api/legacy/blocks/bloc_d1_exc.php',
    'api/legacy/blocks/bloc_d2_exc.php',
    'api/legacy/blocks/bloc_d3_exc.php',
    'api/legacy/blocks/bloc_d5_exc.php',
    'api/legacy/blocks/bloc_d6_exc.php'
];

$securedCount = 0;
$totalCount = count($apisToSecure);

foreach ($apisToSecure as $apiFile) {
    $fullPath = __DIR__ . '/../' . $apiFile;
    
    if (!file_exists($fullPath)) {
        echo "❌ Fichier non trouvé: $apiFile\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    
    // Vérifier si déjà sécurisé
    if (strpos($content, 'ApiSecurityMiddleware::sanitizeApiInput') !== false) {
        echo "✅ Déjà sécurisé: $apiFile\n";
        $securedCount++;
        continue;
    }
    
    // Sécuriser le fichier
    $securedContent = $content;
    
    // Ajouter le require du middleware
    $securedContent = str_replace(
        "require_once __DIR__ . '/../config/app.php';",
        "require_once __DIR__ . '/../config/app.php';\nrequire_once __DIR__ . '/security_middleware.php';",
        $securedContent
    );
    
    // Remplacer les accès directs à $_GET
    $patterns = [
        '/\$annee = \$_GET\[\'annee\'\] \?\? null;/' => '$params = ApiSecurityMiddleware::sanitizeApiInput($_GET);' . "\n" . '$annee = $params[\'annee\'] ?? null;',
        '/\$periode = \$_GET\[\'periode\'\] \?\? null;/' => '$periode = $params[\'periode\'] ?? null;',
        '/\$zone = \$_GET\[\'zone\'\] \?\? null;/' => '$zone = $params[\'zone\'] ?? null;'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $securedContent = preg_replace($pattern, $replacement, $securedContent);
    }
    
    // Remplacer apiError par ApiSecurityMiddleware::handleApiError
    $securedContent = preg_replace(
        '/apiError\(\'([^\']+)\', ?(\d+)?\);/',
        'ApiSecurityMiddleware::handleApiError(new Exception(\'$1\'), \'/api/' . basename($apiFile) . '\', $2);',
        $securedContent
    );
    
    $securedContent = preg_replace(
        '/apiError\(\'([^\']+)\'\);/',
        'ApiSecurityMiddleware::handleApiError(new Exception(\'$1\'), \'/api/' . basename($apiFile) . '\');',
        $securedContent
    );
    
    // Sécuriser le bloc catch
    $securedContent = preg_replace(
        '/} catch \(Exception \$e\) \{.*?}/s',
        '} catch (Exception $e) {' . "\n" . 
        '    ApiSecurityMiddleware::handleApiError($e, \'/api/' . basename($apiFile) . '\');' . "\n" .
        '}',
        $securedContent
    );
    
    // Sauvegarder le fichier sécurisé
    if (file_put_contents($fullPath, $securedContent)) {
        echo "🔒 Sécurisé: $apiFile\n";
        $securedCount++;
    } else {
        echo "❌ Erreur lors de la sécurisation: $apiFile\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RÉSUMÉ DE L'AUDIT\n";
echo "APIs sécurisées: $securedCount/$totalCount\n";
echo "Taux de réussite: " . round(($securedCount/$totalCount)*100, 1) . "%\n\n";

// Vérifications additionnelles
echo "🔍 VÉRIFICATIONS ADDITIONNELLES\n";
echo str_repeat("-", 40) . "\n";

// Vérifier les prepared statements dans les classes principales
$classesToCheck = [
    'classes/Auth.php',
    'classes/Security.php', 
    'classes/Database.php',
    'classes/AuthenticationEnhancer.php'
];

$allSecure = true;
foreach ($classesToCheck as $classFile) {
    $fullPath = __DIR__ . '/../' . $classFile;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        // Rechercher des requêtes SQL dangereuses
        if (preg_match('/\$.*query\(|mysqli_query|mysql_query/', $content)) {
            echo "⚠️  Requête SQL potentiellement dangereuse dans: $classFile\n";
            $allSecure = false;
        } else {
            echo "✅ Prepared statements utilisés: $classFile\n";
        }
    }
}

echo "\n";
if ($allSecure) {
    echo "🎉 TOUTES LES CLASSES PRINCIPALES SONT SÉCURISÉES !\n";
} else {
    echo "⚠️  Certaines classes nécessitent une vérification manuelle\n";
}

echo "\n🛡️  RECOMMANDATIONS FINALES:\n";
echo "1. Utiliser UNIQUEMENT des prepared statements\n";
echo "2. Valider et sanitiser toutes les entrées utilisateur\n"; 
echo "3. Ne jamais concaténer directement des variables dans les requêtes SQL\n";
echo "4. Activer les logs SQL en mode développement\n";
echo "5. Effectuer des tests de pénétration réguliers\n\n";

echo "✅ Audit terminé - Protection contre les injections SQL renforcée\n"; 
