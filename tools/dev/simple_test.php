<?php
require_once 'config/database.php';

echo "🔍 Test simple de connexion\n";
echo "===========================\n";

try {
    $config = DatabaseConfig::getConfig();
    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

    if ($db->connect_error) {
        echo "❌ Connexion échouée: " . $db->connect_error . "\n";
        exit(1);
    }

    echo "✅ Connexion réussie\n";

    // Test simple
    $result = $db->query("SELECT 1 as test");
    if ($result) {
        echo "✅ Requête simple OK\n";
    }

    $db->close();
    echo "✅ Test terminé avec succès\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
