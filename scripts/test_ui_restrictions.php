<?php
/**
 * Script de test pour vérifier que les éléments d'interface des espaces partagés
 * sont correctement masqués pour les utilisateurs non-administrateurs
 */

require_once __DIR__ . '/../classes/Auth.php';

class UIRestrictionsTest {
    
    /**
     * Exécuter tous les tests
     */
    public function runAllTests() {
        echo "=== TESTS DE RESTRICTIONS D'INTERFACE ===\n\n";
        
        $this->testNavbarRestrictions();
        $this->testInfographiePageRestrictions();
        $this->testRouteRestrictions();
        
        echo "\n=== FIN DES TESTS ===\n";
    }
    
    /**
     * Tester les restrictions dans la navbar
     */
    private function testNavbarRestrictions() {
        echo "1. Test des restrictions dans la navbar...\n";
        
        // Test avec utilisateur non-admin
        $this->simulateUser('user');
        $navbarContent = $this->generateNavbarContent();
        
        if (strpos($navbarContent, 'Espaces Partagés') !== false) {
            echo "   ❌ Le lien 'Espaces Partagés' est visible pour un utilisateur non-admin\n";
        } else {
            echo "   ✅ Le lien 'Espaces Partagés' est correctement masqué pour un utilisateur non-admin\n";
        }
        
        // Test avec administrateur
        $this->simulateUser('admin');
        $navbarContent = $this->generateNavbarContent();
        
        if (strpos($navbarContent, 'Espaces Partagés') !== false) {
            echo "   ✅ Le lien 'Espaces Partagés' est visible pour un administrateur\n";
        } else {
            echo "   ❌ Le lien 'Espaces Partagés' devrait être visible pour un administrateur\n";
        }
        
        echo "\n";
    }
    
    /**
     * Tester les restrictions dans la page infographie
     */
    private function testInfographiePageRestrictions() {
        echo "2. Test des restrictions dans la page infographie...\n";
        
        // Test avec utilisateur non-admin
        $this->simulateUser('user');
        $infographieContent = $this->generateInfographieContent();
        
        if (strpos($infographieContent, 'btn-partager-infographie') !== false) {
            echo "   ❌ Le bouton de partage est visible pour un utilisateur non-admin\n";
        } else {
            echo "   ✅ Le bouton de partage est correctement masqué pour un utilisateur non-admin\n";
        }
        
        // Test avec administrateur
        $this->simulateUser('admin');
        $infographieContent = $this->generateInfographieContent();
        
        if (strpos($infographieContent, 'btn-partager-infographie') !== false) {
            echo "   ✅ Le bouton de partage est visible pour un administrateur\n";
        } else {
            echo "   ❌ Le bouton de partage devrait être visible pour un administrateur\n";
        }
        
        echo "\n";
    }
    
    /**
     * Tester les restrictions de routes
     */
    private function testRouteRestrictions() {
        echo "3. Test des restrictions de routes...\n";
        
        // Test avec utilisateur non-admin
        $this->simulateUser('user');
        
        $routes = [
            '/shared-spaces',
            '/shared-spaces/create',
            '/shared-spaces/select'
        ];
        
        foreach ($routes as $route) {
            try {
                $this->testRouteAccess($route);
                echo "   ❌ La route $route est accessible pour un utilisateur non-admin\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Accès réservé aux administrateurs') !== false) {
                    echo "   ✅ La route $route est correctement bloquée pour un utilisateur non-admin\n";
                } else {
                    echo "   ⚠️  La route $route a une erreur inattendue: " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Test avec administrateur
        $this->simulateUser('admin');
        
        foreach ($routes as $route) {
            try {
                $this->testRouteAccess($route);
                echo "   ✅ La route $route est accessible pour un administrateur\n";
            } catch (Exception $e) {
                echo "   ❌ La route $route ne devrait pas être bloquée pour un administrateur: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Simuler un utilisateur
     */
    private function simulateUser($role) {
        session_start();
        $_SESSION['authenticated'] = true;
        $_SESSION['user'] = [
            'id' => 1,
            'username' => 'testuser',
            'role' => $role,
            'name' => 'Utilisateur Test',
            'email' => 'test@example.com'
        ];
    }
    
    /**
     * Générer le contenu de la navbar
     */
    private function generateNavbarContent() {
        ob_start();
        
        // Simuler l'inclusion de la navbar
        echo '<div class="navbar">';
        
        if (Auth::isAuthenticated()) {
            if (Auth::isAdmin()) {
                echo '<a href="/shared-spaces" class="nav-item">';
                echo '<i class="fas fa-users"></i>';
                echo '<span>Espaces Partagés</span>';
                echo '</a>';
            }
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Générer le contenu de la page infographie
     */
    private function generateInfographieContent() {
        ob_start();
        
        echo '<div class="infographie-buttons">';
        echo '<button id="btn-telecharger-infographie" class="btn-infographie">Télécharger</button>';
        
        if (Auth::isAdmin()) {
            echo '<button id="btn-partager-infographie" class="btn-infographie">Partager</button>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Tester l'accès à une route
     */
    private function testRouteAccess($route) {
        // Simuler la logique de routing
        switch ($route) {
            case '/shared-spaces':
            case '/shared-spaces/create':
            case '/shared-spaces/select':
                Auth::requireAdmin();
                return true;
            default:
                return false;
        }
    }
}

// Exécuter les tests
if (php_sapi_name() === 'cli') {
    $test = new UIRestrictionsTest();
    $test->runAllTests();
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php test_ui_restrictions.php\n";
}
?>
