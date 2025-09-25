<?php

/**
 * Script de sécurisation automatique des API legacy (blocs/dashboard).
 * Injecte le middleware de sécurité et remplace l’usage direct de $_GET.
 */

$apiFiles = [
    'bloc_a.php', 'bloc_d1.php', 'bloc_d2.php', 'bloc_d3.php',
    'bloc_d5.php', 'bloc_d6.php', 'bloc_d7.php',
    'bloc_d1_exc.php', 'bloc_d2_exc.php', 'bloc_d3_exc.php',
    'bloc_d5_exc.php', 'bloc_d6_exc.php'
];

foreach ($apiFiles as $apiFile) {
    $filepath = resolveApiFile($apiFile);

    if ($filepath === null) {
        echo "[WARN] Fichier introuvable : $apiFile\n";
        continue;
    }

    secureApiFile($apiFile, $filepath);
}

echo "[DONE] Sécurisation des API terminée.\n";

function resolveApiFile(string $filename): ?string
{
    $base = dirname(__DIR__);
    $candidates = [
        $base . '/legacy/blocks/' . $filename,
        $base . '/analytics/' . $filename,
        $base . '/filters/' . $filename,
        $base . '/' . $filename,
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function secureApiFile(string $filename, string $filepath): void
{
    $content = file_get_contents($filepath);

    $dir = dirname($filepath);
    $apiRoot = dirname(__DIR__);
    $relative = ltrim(str_replace($apiRoot, '', $dir), DIRECTORY_SEPARATOR);
    $segments = $relative === '' ? 0 : substr_count($relative, DIRECTORY_SEPARATOR) + 1;
    $levelsToRoot = $segments + 1;
    $levelsToApi = max($segments, 0);

    $configInclude = "require_once dirname(__DIR__, {$levelsToRoot}) . '/config/app.php';";
    $middlewareInclude = "require_once dirname(__DIR__, {$levelsToApi}) . '/security_middleware.php';";

    if (strpos($content, 'security_middleware.php') === false) {
        if (strpos($content, $configInclude) !== false) {
            $content = str_replace($configInclude, $configInclude . "\n" . $middlewareInclude, $content);
        } else {
            if (preg_match('/<\?php\s*/', $content) === 1) {
                $content = preg_replace('/<\?php\s*/', "<?php\n$configInclude\n$middlewareInclude\n", $content, 1);
            } else {
                $content = $configInclude . "\n" . $middlewareInclude . "\n" . $content;
            }
        }
    }

    $patterns = [
        '/\$annee = \$_GET\[\'annee\'\] \?\? null;/' => '$params = ApiSecurityMiddleware::sanitizeApiInput($_GET);' . "\n" . '$annee = $params[\'annee\'] ?? null;',
        '/\$periode = \$_GET\[\'periode\'\] \?\? null;/' => '$periode = $params[\'periode\'] ?? null;',
        '/\$zone = \$_GET\[\'zone\'\] \?\? null;/' => '$zone = $params[\'zone\'] ?? null;'
    ];

    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    $content = str_replace(
        "} catch (Exception $e) {\n    if (DEBUG) {\n        apiError('Erreur lors du chargement des données : ' . $e->getMessage(), 500);\n    } else {\n        apiError('Erreur interne du serveur', 500);\n    }",
        "} catch (Exception $e) {\n    ApiSecurityMiddleware::handleApiError($e, '/api/' . pathinfo($filename, PATHINFO_FILENAME) . '/');",
        $content
    );

    file_put_contents($filepath, $content);
    echo "[OK] Sécurisé : $filename\n";
}
