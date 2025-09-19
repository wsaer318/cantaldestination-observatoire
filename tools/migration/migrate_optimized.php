<?php
/**
 * Migration optimisée - UNIQUEMENT fact_lieu_activite_soir_temp
 */

// Configuration pour éviter le timeout
set_time_limit(900); // 15 minutes
ini_set('memory_limit', '512M');
ini_set('mysql.connect_timeout', 60);

require_once __DIR__ . '/config/database.php';

echo "🚀 MIGRATION OPTIMISÉE - fact_lieu_activite_soir_temp uniquement\n";
echo "==========================================================\n\n";

try {
    $config = DatabaseConfig::getConfig();
    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

    if ($db->connect_error) {
        throw new Exception("Connexion échouée: " . $db->connect_error);
    }

    echo "✅ Connexion DB réussie\n\n";

    // Vérifier/ajouter la colonne is_provisional
    echo "🔧 Vérification colonne is_provisional...\n";
    $result = $db->query("SHOW COLUMNS FROM fact_lieu_activite_soir LIKE 'is_provisional'");
    if ($result->num_rows == 0) {
        echo "➕ Ajout colonne is_provisional...\n";
        $db->query("ALTER TABLE fact_lieu_activite_soir ADD COLUMN is_provisional BOOLEAN DEFAULT FALSE NOT NULL");
        $db->query("CREATE INDEX idx_provisional ON fact_lieu_activite_soir(is_provisional)");
    } else {
        echo "✅ Colonne is_provisional existe\n";
    }

    // Statistiques avant migration
    echo "\n📊 STATISTIQUES AVANT MIGRATION:\n";
    $result = $db->query("SELECT COUNT(*) as temp_count FROM fact_lieu_activite_soir_temp");
    $tempCount = $result->fetch_assoc()['temp_count'];

    $result = $db->query("SELECT COUNT(*) as main_count FROM fact_lieu_activite_soir");
    $mainCount = $result->fetch_assoc()['main_count'];

    $result = $db->query("SELECT COUNT(*) as prov_count FROM fact_lieu_activite_soir WHERE is_provisional = 1");
    $provCount = $result->fetch_assoc()['prov_count'];

    echo "  - Table temporaire: " . number_format($tempCount) . " enregistrements\n";
    echo "  - Table principale: " . number_format($mainCount) . " total (" . number_format($provCount) . " provisoires)\n\n";

    // Migration par lots pour éviter les timeouts
    echo "🔄 MIGRATION PAR LOTS...\n";

    $batchSize = 1000;
    $totalMigrated = 0;
    $batchNum = 0;

    while (true) {
        $batchNum++;
        echo "📦 Lot $batchNum... ";

        // Récupérer un lot de données
        $stmt = $db->prepare("
            SELECT date, id_zone, id_provenance, id_categorie, volume, id_commune, id_epci, jour_semaine
            FROM fact_lieu_activite_soir_temp
            LIMIT ? OFFSET ?
        ");
        $offset = ($batchNum - 1) * $batchSize;
        $stmt->bind_param("ii", $batchSize, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            echo "terminé\n";
            break; // Plus de données
        }

        // Insérer le lot
        $insertStmt = $db->prepare("
            INSERT INTO fact_lieu_activite_soir
            (date, id_zone, id_provenance, id_categorie, volume, id_commune, id_epci, jour_semaine, is_provisional)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE is_provisional = VALUES(is_provisional)
        ");

        $batchMigrated = 0;
        while ($row = $result->fetch_assoc()) {
            $insertStmt->bind_param(
                "siiiisss",
                $row['date'],
                $row['id_zone'],
                $row['id_provenance'],
                $row['id_categorie'],
                $row['volume'],
                $row['id_commune'],
                $row['id_epci'],
                $row['jour_semaine']
            );
            $insertStmt->execute();
            $batchMigrated++;
        }

        $totalMigrated += $batchMigrated;
        echo number_format($batchMigrated) . " enregistrements\n";

        // Petit délai pour éviter la surcharge
        usleep(100000); // 0.1 seconde
    }

    // Statistiques après migration
    echo "\n📊 STATISTIQUES APRÈS MIGRATION:\n";
    $result = $db->query("SELECT COUNT(*) as main_count FROM fact_lieu_activite_soir");
    $mainCountAfter = $result->fetch_assoc()['main_count'];

    $result = $db->query("SELECT COUNT(*) as prov_count FROM fact_lieu_activite_soir WHERE is_provisional = 1");
    $provCountAfter = $result->fetch_assoc()['prov_count'];

    echo "  - Table principale: " . number_format($mainCountAfter) . " total (" . number_format($provCountAfter) . " provisoires)\n";
    echo "  - Nouveaux enregistrements: " . number_format($totalMigrated) . "\n";

    $db->close();

    echo "\n✅ MIGRATION TERMINÉE AVEC SUCCÈS!\n";
    echo "🎯 Résumé: " . number_format($totalMigrated) . " enregistrements migrés\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
