<?php
/**
 * Sauvegarde d'urgence des tables critiques
 */

require_once 'config/database.php';

echo "🛡️  SAUVEGARDE D'URGENCE DES DONNÉES CRITIQUES\n";
echo "==============================================\n\n";

try {
    $config = DatabaseConfig::getConfig();
    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

    if ($db->connect_error) {
        throw new Exception("Connexion échouée: " . $db->connect_error);
    }

    echo "✅ Connexion DB réussie\n\n";

    // Tables à sauvegarder en priorité
    $criticalTables = [
        'fact_lieu_activite_soir',
        'fact_lieu_activite_soir_temp',
        'dim_communes',
        'dim_zones_observation',
        'dim_provenances',
        'dim_categories_visiteur'
    ];

    $backupDir = __DIR__ . '/data/emergency_backup_' . date('Y-m-d_H-i-s');
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    echo "📁 Sauvegarde vers : $backupDir\n\n";

    foreach ($criticalTables as $table) {
        echo "💾 Sauvegarde $table... ";

        // Vérifier si la table existe
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            echo "❌ Table inexistante\n";
            continue;
        }

        // Compter les enregistrements
        $countResult = $db->query("SELECT COUNT(*) as total FROM `$table`");
        $count = $countResult->fetch_assoc()['total'];

        // Sauvegarder en CSV
        $filename = $backupDir . "/{$table}_" . date('Y-m-d_H-i-s') . ".csv";

        $query = "SELECT * FROM `$table` INTO OUTFILE '$filename'
                  FIELDS TERMINATED BY ';'
                  ENCLOSED BY '\"'
                  LINES TERMINATED BY '\\n'";

        if ($db->query($query)) {
            echo "✅ $count enregistrements sauvegardés\n";
        } else {
            echo "❌ Erreur sauvegarde: " . $db->error . "\n";
        }
    }

    // Créer un fichier d'informations
    $infoFile = $backupDir . "/BACKUP_INFO.txt";
    $info = "Sauvegarde d'urgence créée le " . date('Y-m-d H:i:s') . "\n";
    $info .= "Mode récupération InnoDB: 2 (urgence)\n";
    $info .= "Base de données: fluxvision\n\n";
    $info .= "Tables sauvegardées:\n";
    foreach ($criticalTables as $table) {
        $info .= "- $table\n";
    }

    file_put_contents($infoFile, $info);

    echo "\n✅ SAUVEGARDE TERMINÉE\n";
    echo "📍 Fichiers sauvegardés dans : $backupDir\n";
    echo "📋 Informations dans : $infoFile\n\n";

    $db->close();

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
