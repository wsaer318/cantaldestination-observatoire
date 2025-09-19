<?php
/**
 * Script de test pour vérifier les restrictions d'accès aux espaces partagés et infographies
 * Vérifie que seuls les administrateurs peuvent accéder à ces fonctionnalités
 */

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/SharedSpaceManager.php';
require_once __DIR__ . '/../classes/InfographicManager.php';
require_once __DIR__ . '/../classes/Database.php';

class AdminRestrictionsTest {
    private $db;
    private $spaceManager;
    private $infographicManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->spaceManager = new SharedSpaceManager();
        $this->infographicManager = new InfographicManager();
    }
    
    /**
     * Exécuter tous les tests
     */
    public function runAllTests() {
        echo "=== TESTS DE RESTRICTIONS ADMINISTRATEUR ===\n\n";
        
        $this->testAuthMethods();
        $this->testSharedSpaceRestrictions();
        $this->testInfographicRestrictions();
        
        echo "\n=== FIN DES TESTS ===\n";
    }
    
    /**
     * Tester les méthodes d'authentification
     */
    private function testAuthMethods() {
        echo "1. Test des méthodes d'authentification...\n";
        
        // Simuler un utilisateur non connecté
        session_destroy();
        
        try {
            Auth::isAdmin();
            echo "   ❌ isAdmin() devrait retourner false pour un utilisateur non connecté\n";
        } catch (Exception $e) {
            echo "   ✅ isAdmin() gère correctement l'utilisateur non connecté\n";
        }
        
        // Simuler un utilisateur connecté non-admin
        session_start();
        $_SESSION['authenticated'] = true;
        $_SESSION['user'] = [
            'id' => 2,
            'username' => 'user1',
            'role' => 'user',
            'name' => 'Utilisateur Test',
            'email' => 'user@test.com'
        ];
        
        if (!Auth::isAdmin()) {
            echo "   ✅ isAdmin() retourne false pour un utilisateur non-admin\n";
        } else {
            echo "   ❌ isAdmin() devrait retourner false pour un utilisateur non-admin\n";
        }
        
        // Simuler un administrateur
        $_SESSION['user']['role'] = 'admin';
        
        if (Auth::isAdmin()) {
            echo "   ✅ isAdmin() retourne true pour un administrateur\n";
        } else {
            echo "   ❌ isAdmin() devrait retourner true pour un administrateur\n";
        }
        
        echo "\n";
    }
    
    /**
     * Tester les restrictions sur les espaces partagés
     */
    private function testSharedSpaceRestrictions() {
        echo "2. Test des restrictions sur les espaces partagés...\n";
        
        // Test avec utilisateur non-admin
        $_SESSION['user']['role'] = 'user';
        
        $this->testSpaceMethod('getUserSpaces', [1], 'utilisateur non-admin');
        $this->testSpaceMethod('getSpace', [1, 1], 'utilisateur non-admin');
        $this->testSpaceMethod('createSpace', ['Test Space', 'Description', 1, []], 'utilisateur non-admin');
        $this->testSpaceMethod('updateSpace', [1, 'New Name', 'New Description', 1], 'utilisateur non-admin');
        $this->testSpaceMethod('deleteSpace', [1, 1], 'utilisateur non-admin');
        $this->testSpaceMethod('addMember', [1, 2, 'reader'], 'utilisateur non-admin');
        $this->testSpaceMethod('removeMember', [1, 2, 1], 'utilisateur non-admin');
        $this->testSpaceMethod('getSpaceMembers', [1], 'utilisateur non-admin');
        $this->testSpaceMethod('getSpaceStats', [1], 'utilisateur non-admin');
        
        // Test avec administrateur
        $_SESSION['user']['role'] = 'admin';
        
        $this->testSpaceMethod('getUserSpaces', [1], 'administrateur');
        $this->testSpaceMethod('getSpace', [1, 1], 'administrateur');
        $this->testSpaceMethod('createSpace', ['Test Space', 'Description', 1, []], 'administrateur');
        $this->testSpaceMethod('updateSpace', [1, 'New Name', 'New Description', 1], 'administrateur');
        $this->testSpaceMethod('deleteSpace', [1, 1], 'administrateur');
        $this->testSpaceMethod('addMember', [1, 2, 'reader'], 'administrateur');
        $this->testSpaceMethod('removeMember', [1, 2, 1], 'administrateur');
        $this->testSpaceMethod('getSpaceMembers', [1], 'administrateur');
        $this->testSpaceMethod('getSpaceStats', [1], 'administrateur');
        
        echo "\n";
    }
    
    /**
     * Tester les restrictions sur les infographies
     */
    private function testInfographicRestrictions() {
        echo "3. Test des restrictions sur les infographies...\n";
        
        // Test avec utilisateur non-admin
        $_SESSION['user']['role'] = 'user';
        
        $this->testInfographicMethod('getSpaceInfographics', [1, 1, []], 'utilisateur non-admin');
        $this->testInfographicMethod('getInfographic', [1, 1], 'utilisateur non-admin');
        $this->testInfographicMethod('createInfographic', [1, 'Test Infographic', 'Description', 1, ['data' => 'test'], []], 'utilisateur non-admin');
        $this->testInfographicMethod('createVersion', [1, 1, ['data' => 'test'], 2], 'utilisateur non-admin');
        $this->testInfographicMethod('updateStatus', [1, 1, 'validated'], 'utilisateur non-admin');
        $this->testInfographicMethod('addComment', [1, 1, ['type' => 'text'], 'Test comment'], 'utilisateur non-admin');
        $this->testInfographicMethod('getComments', [1, 1, []], 'utilisateur non-admin');
        $this->testInfographicMethod('resolveComment', [1, 1], 'utilisateur non-admin');
        $this->testInfographicMethod('deleteInfographic', [1, 1], 'utilisateur non-admin');
        $this->testInfographicMethod('getSpaceInfographicStats', [1], 'utilisateur non-admin');
        
        // Test avec administrateur
        $_SESSION['user']['role'] = 'admin';
        
        $this->testInfographicMethod('getSpaceInfographics', [1, 1, []], 'administrateur');
        $this->testInfographicMethod('getInfographic', [1, 1], 'administrateur');
        $this->testInfographicMethod('createInfographic', [1, 'Test Infographic', 'Description', 1, ['data' => 'test'], []], 'administrateur');
        $this->testInfographicMethod('createVersion', [1, 1, ['data' => 'test'], 2], 'administrateur');
        $this->testInfographicMethod('updateStatus', [1, 1, 'validated'], 'administrateur');
        $this->testInfographicMethod('addComment', [1, 1, ['type' => 'text'], 'Test comment'], 'administrateur');
        $this->testInfographicMethod('getComments', [1, 1, []], 'administrateur');
        $this->testInfographicMethod('resolveComment', [1, 1], 'administrateur');
        $this->testInfographicMethod('deleteInfographic', [1, 1], 'administrateur');
        $this->testInfographicMethod('getSpaceInfographicStats', [1], 'administrateur');
        
        echo "\n";
    }
    
    /**
     * Tester une méthode de SharedSpaceManager
     */
    private function testSpaceMethod($methodName, $params, $userType) {
        try {
            $result = call_user_func_array([$this->spaceManager, $methodName], $params);
            
            if ($userType === 'utilisateur non-admin') {
                echo "   ❌ $methodName() devrait être bloquée pour un $userType\n";
            } else {
                echo "   ✅ $methodName() fonctionne pour un $userType\n";
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Accès réservé aux administrateurs uniquement') !== false) {
                if ($userType === 'utilisateur non-admin') {
                    echo "   ✅ $methodName() correctement bloquée pour un $userType\n";
                } else {
                    echo "   ❌ $methodName() ne devrait pas être bloquée pour un $userType\n";
                }
            } else {
                echo "   ⚠️  $methodName() erreur inattendue pour un $userType: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Tester une méthode de InfographicManager
     */
    private function testInfographicMethod($methodName, $params, $userType) {
        try {
            $result = call_user_func_array([$this->infographicManager, $methodName], $params);
            
            if ($userType === 'utilisateur non-admin') {
                echo "   ❌ $methodName() devrait être bloquée pour un $userType\n";
            } else {
                echo "   ✅ $methodName() fonctionne pour un $userType\n";
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Accès réservé aux administrateurs uniquement') !== false) {
                if ($userType === 'utilisateur non-admin') {
                    echo "   ✅ $methodName() correctement bloquée pour un $userType\n";
                } else {
                    echo "   ❌ $methodName() ne devrait pas être bloquée pour un $userType\n";
                }
            } else {
                echo "   ⚠️  $methodName() erreur inattendue pour un $userType: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Exécuter les tests
if (php_sapi_name() === 'cli') {
    $test = new AdminRestrictionsTest();
    $test->runAllTests();
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php test_admin_restrictions.php\n";
}
?>
