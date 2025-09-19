<?php
/**
 * Script de vérification de sécurité après nettoyage
 * Vérifie qu'il ne reste plus de fichiers avec des informations sensibles hardcodées
 */

class SecurityCleanupCheck {
    
    /**
     * Exécuter toutes les vérifications
     */
    public function runAllChecks() {
        echo "=== VÉRIFICATION DE SÉCURITÉ APRÈS NETTOYAGE ===\n\n";
        
        $this->checkDeletedFiles();
        $this->checkRemainingFiles();
        $this->checkSensitiveData();
        
        echo "\n=== FIN DE LA VÉRIFICATION ===\n";
    }
    
    /**
     * Vérifier que les fichiers dangereux ont été supprimés
     */
    private function checkDeletedFiles() {
        echo "1. Vérification des fichiers supprimés...\n";
        
        $deleted_files = [
            'api/db_explorer.php',
            'api/db_explorer_secure.php',
            'explore_remote_db.py',
            'remote_db_explorer.py',
            'secure_remote_explorer.py',
            'test_connection.py',
            'explore_db.ps1'
        ];
        
        foreach ($deleted_files as $file) {
            if (file_exists($file)) {
                echo "   ❌ Fichier dangereux encore présent: $file\n";
            } else {
                echo "   ✅ Fichier supprimé: $file\n";
            }
        }
        echo "\n";
    }
    
    /**
     * Vérifier les fichiers restants
     */
    private function checkRemainingFiles() {
        echo "2. Vérification des fichiers restants...\n";
        
        $safe_files = [
            'api/remote_db_explorer.php' => 'Explorateur sécurisé pour accès distant',
            'scripts/remote_db_client.php' => 'Client d\'exploration sécurisé',
            'scripts/secure_db_explorer_example.php' => 'Exemple d\'utilisation sécurisée'
        ];
        
        foreach ($safe_files as $file => $description) {
            if (file_exists($file)) {
                echo "   ✅ Fichier sécurisé présent: $file ($description)\n";
            } else {
                echo "   ⚠️  Fichier sécurisé manquant: $file\n";
            }
        }
        echo "\n";
    }
    
    /**
     * Vérifier qu'il n'y a plus d'informations sensibles hardcodées
     */
    private function checkSensitiveData() {
        echo "3. Recherche d'informations sensibles hardcodées...\n";
        
        $sensitive_patterns = [
            'Sf4d8gsfsdGsg59sqq54g' => 'Mot de passe de base de données',
            'observatoire' => 'Nom d\'utilisateur de base de données',
            'srv.cantal-destination.com' => 'Serveur de base de données'
        ];
        
        $files_to_check = [
            'api/remote_db_explorer.php',
            'scripts/remote_db_client.php',
            'scripts/secure_db_explorer_example.php'
        ];
        
        $found_sensitive = false;
        
        foreach ($files_to_check as $file) {
            if (!file_exists($file)) continue;
            
            $content = file_get_contents($file);
            
            foreach ($sensitive_patterns as $pattern => $description) {
                if (strpos($content, $pattern) !== false) {
                    // Vérifier si c'est dans un commentaire ou une chaîne de caractères
                    $lines = explode("\n", $content);
                    foreach ($lines as $line_num => $line) {
                        if (strpos($line, $pattern) !== false) {
                            // Vérifier si c'est dans un commentaire
                            $trimmed_line = trim($line);
                            if (!preg_match('/^\s*\/\//', $trimmed_line) && 
                                !preg_match('/^\s*#/', $trimmed_line) &&
                                !preg_match('/^\s*\*/', $trimmed_line)) {
                                
                                // Vérifier si c'est dans une variable d'assignation
                                if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*[\'"]' . preg_quote($pattern, '/') . '[\'"]/', $line)) {
                                    echo "   ❌ Information sensible trouvée dans $file ligne " . ($line_num + 1) . ": $description\n";
                                    $found_sensitive = true;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (!$found_sensitive) {
            echo "   ✅ Aucune information sensible hardcodée trouvée\n";
        }
        echo "\n";
    }
}

// Exécuter les vérifications
if (php_sapi_name() === 'cli') {
    $check = new SecurityCleanupCheck();
    $check->runAllChecks();
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php security_cleanup_check.php\n";
}
?>
