<?php
/**
 * Migration spécifique des données de durée de séjour
 * fact_sejours_duree_temp -> fact_sejours_duree
 */

require_once 'config/database.php';

echo "🚀 MIGRATION SPÉCIFIQUE - DURÉE DE SÉJOUR\n";
echo "==========================================\n\n";

try {
    $config = DatabaseConfig::getConfig();
    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

    if ($db->connect_error) {
        throw new Exception("Connexion échouée: " . $db->connect_error);
    }

    echo "✅ Connexion DB réussie\n\n";

    $temp_table = 'fact_sejours_duree_temp';
    $main_table = 'fact_sejours_duree';

    // Vérifier que les tables existent
    $result = $db->query("SHOW TABLES LIKE '$temp_table'");
    $temp_exists = $result && $result->num_rows > 0;

    $result = $db->query("SHOW TABLES LIKE '$main_table'");
    $main_exists = $result && $result->num_rows > 0;

    if (!$temp_exists) {
        throw new Exception("Table temporaire $temp_table n'existe pas");
    }

    if (!$main_exists) {
        throw new Exception("Table principale $main_table n'existe pas");
    }

    echo "✅ Tables vérifiées : $temp_table et $main_table existent\n\n";

    // Statistiques avant migration
    $result = $db->query("SELECT COUNT(*) as temp_count FROM $temp_table");
    $temp_count = $result->fetch_assoc()['temp_count'];

    $result = $db->query("SELECT COUNT(*) as main_count FROM $main_table");
    $main_count = $result->fetch_assoc()['main_count'];

    echo "📊 STATISTIQUES AVANT MIGRATION:\n";
    echo "  - Table temporaire: " . number_format($temp_count) . " enregistrements\n";
    echo "  - Table principale: " . number_format($main_count) . " enregistrements\n\n";

    if ($temp_count == 0) {
        echo "⚠️ Aucune donnée à migrer dans $temp_table\n";
        exit(0);
    }

    // Migration par lots pour éviter les timeouts
    echo "🔄 MIGRATION PAR LOTS...\n";

    $batch_size = 1000;
    $total_migrated = 0;
    $batch_num = 0;

    // Ajouter la colonne is_provisional si elle n'existe pas
    $result = $db->query("SHOW COLUMNS FROM $main_table LIKE 'is_provisional'");
    if ($result->num_rows == 0) {
        echo "➕ Ajout colonne is_provisional...\n";
        $db->query("ALTER TABLE $main_table ADD COLUMN is_provisional BOOLEAN DEFAULT FALSE NOT NULL");
        $db->query("CREATE INDEX idx_provisional ON $main_table(is_provisional)");
    }

    while (true) {
        $batch_num++;
        echo "📦 Lot $batch_num... ";

        // Récupérer un lot de données
        $stmt = $db->prepare("
            SELECT date, id_zone, id_provenance, id_categorie, id_duree, volume
            FROM $temp_table
            LIMIT ? OFFSET ?
        ");
        $offset = ($batch_num - 1) * $batch_size;
        $stmt->bind_param("ii", $batch_size, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            echo "terminé\n";
            break; // Plus de données
        }

        // Insérer le lot avec INSERT IGNORE
        $insert_stmt = $db->prepare("
            INSERT IGNORE INTO $main_table
            (date, id_zone, id_provenance, id_categorie, id_duree, volume, is_provisional)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        $batch_migrated = 0;
        while ($row = $result->fetch_assoc()) {
            $insert_stmt->bind_param(
                "siiiis",
                $row['date'],
                $row['id_zone'],
                $row['id_provenance'],
                $row['id_categorie'],
                $row['id_duree'],
                $row['volume']
            );
            if ($insert_stmt->execute()) {
                $batch_migrated++;
            }
        }

        $total_migrated += $batch_migrated;
        echo number_format($batch_migrated) . " enregistrements\n";

        $stmt->close();
        $insert_stmt->close();

        // Petit délai pour éviter la surcharge
        usleep(100000); // 0.1 seconde
    }

    // Statistiques après migration
    $result = $db->query("SELECT COUNT(*) as main_count FROM $main_table");
    $main_count_after = $result->fetch_assoc()['main_count'];

    $result = $db->query("SELECT COUNT(*) as prov_count FROM $main_table WHERE is_provisional = 1");
    $prov_count = $result->fetch_assoc()['prov_count'];

    echo "\n📊 STATISTIQUES APRÈS MIGRATION:\n";
    echo "  - Table principale: " . number_format($main_count_after) . " total (" . number_format($prov_count) . " provisoires)\n";
    echo "  - Nouveaux enregistrements: " . number_format($total_migrated) . "\n";

    // Vérification des données migrées
    if ($total_migrated > 0) {
        echo "\n🔍 VÉRIFICATION DES DONNÉES MIGRÉES:\n";
        $result = $db->query("
            SELECT duree.libelle, COUNT(*) as nombre, SUM(vol.volume) as total_volume
            FROM $main_table vol
            JOIN dim_durees_sejour duree ON vol.id_duree = duree.id_duree
            WHERE vol.is_provisional = 1
            GROUP BY vol.id_duree, duree.libelle
            ORDER BY duree.nb_nuits
        ");

        while ($row = $result->fetch_assoc()) {
            echo "  - {$row['libelle']}: {$row['nombre']} enr. ({$row['total_volume']} volume)\n";
        }
    }

    $db->close();

    echo "\n✅ MIGRATION TERMINÉE AVEC SUCCÈS!\n";
    echo "🎯 Résumé: " . number_format($total_migrated) . " enregistrements migrés vers $main_table\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
