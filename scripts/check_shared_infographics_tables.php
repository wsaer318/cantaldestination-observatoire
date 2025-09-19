<?php
/**
 * Script de vérification et création des tables pour les infographies partagées
 */

require_once __DIR__ . '/../classes/Database.php';

echo "=== Vérification des tables infographies partagées ===\n";

try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Liste des tables à vérifier
    $requiredTables = [
        'shared_spaces',
        'space_memberships', 
        'shared_infographics',
        'infographic_versions',
        'infographic_comments',
        'infographic_attachments'
    ];
    
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $connection->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        
        if ($stmt->fetch()) {
            echo "✓ Table '$table' existe\n";
        } else {
            echo "✗ Table '$table' manquante\n";
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "\n=== Création des tables manquantes ===\n";
        
        // Lire le fichier SQL
        $sqlFile = __DIR__ . '/../docs/database_shared_infographies_mysql.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Fichier SQL non trouvé: $sqlFile");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Diviser en requêtes individuelles
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($queries as $query) {
            if (empty($query) || strpos($query, '--') === 0) {
                continue;
            }
            
            // Vérifier si la requête concerne une table manquante
            $shouldExecute = false;
            foreach ($missingTables as $table) {
                if (stripos($query, "CREATE TABLE $table") !== false || 
                    stripos($query, "CREATE TABLE `$table`") !== false) {
                    $shouldExecute = true;
                    break;
                }
            }
            
            if ($shouldExecute) {
                echo "Exécution: " . substr($query, 0, 50) . "...\n";
                $connection->exec($query);
                echo "✓ Table créée\n";
            }
        }
        
        echo "\n=== Vérification finale ===\n";
        foreach ($requiredTables as $table) {
            $stmt = $connection->prepare("SHOW TABLES LIKE '$table'");
            $stmt->execute();
            
            if ($stmt->fetch()) {
                echo "✓ Table '$table' existe\n";
            } else {
                echo "✗ Table '$table' toujours manquante\n";
            }
        }
    } else {
        echo "\n✓ Toutes les tables sont présentes\n";
    }
    
    echo "\n=== Test de connexion à la base ===\n";
    
    // Test simple d'insertion dans shared_spaces
    $stmt = $connection->prepare("SELECT COUNT(*) FROM shared_spaces");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✓ Nombre d'espaces partagés: $count\n";
    
    echo "\n=== Vérification terminée avec succès ===\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
