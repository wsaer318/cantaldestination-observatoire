<?php
/**
 * Script de test pour vérifier la sécurité des explorateurs de base de données
 * Vérifie que les fichiers db_explorer.php et db_explorer_secure.php sont sécurisés
 */

class DBExplorerSecurityTest {
    
    /**
     * Exécuter tous les tests
     */
    public function runAllTests() {
        echo "=== TESTS DE SÉCURITÉ DES EXPLORATEURS DE BASE DE DONNÉES ===\n\n";
        
        $this->testFileSecurity('api/db_explorer.php');
        $this->testFileSecurity('api/db_explorer_secure.php');
        $this->testPythonFiles();
        
        echo "\n=== FIN DES TESTS ===\n";
    }
    
    /**
     * Tester la sécurité d'un fichier PHP
     */
    private function testFileSecurity($filePath) {
        echo "Test de sécurité pour $filePath...\n";
        
        if (!file_exists($filePath)) {
            echo "   ❌ Fichier non trouvé: $filePath\n";
            return;
        }
        
        $content = file_get_contents($filePath);
        
        // Test 1: Vérification de la restriction d'environnement
        if (strpos($content, '!defined(\'DEBUG\') || !DEBUG') !== false) {
            echo "   ✅ Restriction d'environnement DEBUG présente\n";
        } else {
            echo "   ❌ Restriction d'environnement DEBUG manquante\n";
        }
        
        // Test 2: Vérification de la restriction IP
        if (strpos($content, '127.0.0.1') !== false && strpos($content, 'localhost') !== false) {
            echo "   ✅ Restriction IP localhost présente\n";
        } else {
            echo "   ❌ Restriction IP localhost manquante\n";
        }
        
        // Test 3: Vérification du masquage des informations DB
        if (strpos($content, 'hidden_for_security') !== false) {
            echo "   ✅ Masquage des informations DB présent\n";
        } else {
            echo "   ❌ Masquage des informations DB manquant\n";
        }
        
        // Test 4: Vérification de la clé d'authentification renforcée
        if (strpos($content, 'date(\'Y-m-d\')') !== false) {
            echo "   ✅ Clé d'authentification renforcée présente\n";
        } else {
            echo "   ❌ Clé d'authentification renforcée manquante\n";
        }
        
        // Test 5: Vérification des messages d'erreur sécurisés
        if (strpos($content, 'Accès interdit') !== false) {
            echo "   ✅ Messages d'erreur sécurisés présents\n";
        } else {
            echo "   ❌ Messages d'erreur sécurisés manquants\n";
        }
        
        // Test 6: Vérification que les informations de connexion ne sont pas hardcodées
        $hardcoded_patterns = [
            'localhost',
            'observatoire',
            'Sf4d8gsfsdGsg59sqq54g'
        ];
        
        $has_hardcoded = false;
        foreach ($hardcoded_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                // Vérifier si c'est dans un commentaire ou une chaîne de caractères
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, $pattern) !== false && 
                        !preg_match('/^\s*\/\//', $line) && // Pas un commentaire
                        !preg_match('/^\s*\*/', $line) && // Pas un commentaire multi-lignes
                        !preg_match('/^\s*#/', $line)) { // Pas un commentaire #
                        
                        // Vérifier si c'est dans une variable d'assignation directe
                        if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*[\'"]' . preg_quote($pattern, '/') . '[\'"]/', $line)) {
                            $has_hardcoded = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        if (!$has_hardcoded) {
            echo "   ✅ Aucune information de connexion hardcodée\n";
        } else {
            echo "   ❌ Informations de connexion hardcodées détectées\n";
        }
        
        // Test 7: Vérification de la récupération des paramètres via HTTP
        if (strpos($content, '$_GET[\'host\']') !== false || strpos($content, '$_POST[\'host\']') !== false) {
            echo "   ✅ Paramètres de connexion récupérés via HTTP\n";
        } else {
            echo "   ❌ Paramètres de connexion non récupérés via HTTP\n";
        }
        
        // Test 8: Vérification de la validation des paramètres
        if (strpos($content, 'empty($dbname)') !== false || strpos($content, 'empty($username)') !== false) {
            echo "   ✅ Validation des paramètres de connexion présente\n";
        } else {
            echo "   ❌ Validation des paramètres de connexion manquante\n";
        }
        
        echo "\n";
    }
    
    /**
     * Tester les fichiers Python
     */
    private function testPythonFiles() {
        echo "Test des fichiers Python d'exploration de base de données...\n";
        
        $pythonFiles = [
            'remote_db_explorer.py',
            'secure_remote_explorer.py',
            'explore_remote_db.py'
        ];
        
        foreach ($pythonFiles as $file) {
            if (file_exists($file)) {
                echo "   ⚠️  Fichier Python trouvé: $file (à vérifier manuellement)\n";
                
                // Vérifier rapidement le contenu pour les informations hardcodées
                $content = file_get_contents($file);
                if (strpos($content, 'localhost') !== false || strpos($content, 'observatoire') !== false) {
                    echo "      ⚠️  Informations de connexion potentiellement hardcodées\n";
                } else {
                    echo "      ✅ Aucune information de connexion hardcodée détectée\n";
                }
            } else {
                echo "   ✅ Fichier Python non trouvé: $file\n";
            }
        }
        
        echo "\n";
    }
}

// Exécuter les tests
if (php_sapi_name() === 'cli') {
    $test = new DBExplorerSecurityTest();
    $test->runAllTests();
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php test_db_explorer_security.php\n";
}
?>
