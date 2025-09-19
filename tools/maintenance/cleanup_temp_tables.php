<?php
require_once 'config/database.php';

echo "🧹 Nettoyage Tables Temporaires\n";
echo "==============================\n\n";

try {
    $config = DatabaseConfig::getConfig();
    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

    if ($db->connect_error) {
        throw new Exception("Connexion échouée: " . $db->connect_error);
    }

    echo "✅ Connexion DB réussie\n\n";

    $temp_tables = [
        'fact_nuitees_temp',
        'fact_nuitees_departements_temp',
        'fact_nuitees_pays_temp',
        'fact_diurnes_temp',
        'fact_diurnes_departements_temp',
        'fact_diurnes_pays_temp',
        'fact_lieu_activite_soir_temp',
        'fact_sejours_duree_temp',
        'fact_sejours_duree_departements_temp',
        'fact_sejours_duree_pays_temp'
    ];

    $total_cleaned = 0;

    foreach ($temp_tables as $table) {
        // Vérifier si la table existe
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            // Compter les enregistrements avant suppression
            $count_result = $db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_result->fetch_assoc()['count'];

            if ($count > 0) {
                // Supprimer tous les enregistrements
                $db->query("TRUNCATE TABLE `$table`");
                echo "🗑️ $table: $count enregistrements supprimés\n";
                $total_cleaned += $count;
            } else {
                echo "📭 $table: déjà vide\n";
            }
        } else {
            echo "❌ $table: table inexistante\n";
        }
    }

    echo "\n✅ Nettoyage terminé!\n";
    echo "📊 Total nettoyé: " . number_format($total_cleaned) . " enregistrements\n";

    $db->close();
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
