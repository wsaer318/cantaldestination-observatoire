<?php
require_once __DIR__ . '/config/database.php';

try {
    $dbConfig = DatabaseConfig::getConfig();
    $db = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database']);

    if ($db->connect_error) {
        die("Erreur de connexion: " . $db->connect_error);
    }

    $db->set_charset("utf8mb4");

    // Vérifier toutes les zones
    $result = $db->query("SELECT id_zone, nom_zone FROM dim_zones_observation ORDER BY nom_zone");

    echo "=== ZONES DANS dim_zones_observation ===\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id_zone']} - NOM: {$row['nom_zone']}\n";
    }

    $result->free();

    // Tester les zones problématiques
    $zones_problematiques = ['Haut Cantal', 'Hautes Terres', 'Lioran', 'Pays d\'Aurillac'];

    echo "\n=== TEST DES ZONES PROBLÉMATIQUES ===\n";
    foreach ($zones_problematiques as $zone) {
        $normalized = strtoupper(trim($zone));
        $stmt = $db->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone = ?");
        $stmt->bind_param("s", $normalized);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "✅ '$zone' -> '$normalized' TROUVÉ: ID {$row['id_zone']}\n";
        } else {
            echo "❌ '$zone' -> '$normalized' NON TROUVÉ\n";

            // Chercher des variations possibles
            $stmt2 = $db->prepare("SELECT id_zone, nom_zone FROM dim_zones_observation WHERE nom_zone LIKE ?");
            $like_pattern = '%' . $db->real_escape_string($zone) . '%';
            $stmt2->bind_param("s", $like_pattern);
            $stmt2->execute();
            $result2 = $stmt2->get_result();

            if ($result2->num_rows > 0) {
                echo "   Variations trouvées:\n";
                while ($row2 = $result2->fetch_assoc()) {
                    echo "   - ID {$row2['id_zone']}: {$row2['nom_zone']}\n";
                }
            }
        }

        $stmt->close();
        if (isset($stmt2)) $stmt2->close();
    }

    $db->close();

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>



