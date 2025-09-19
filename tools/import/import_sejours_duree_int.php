<?php
/**
 * Import spécifique des données de durée de séjour internationales
 * duree_sejour_int.csv -> fact_sejours_duree_pays_temp
 */

require_once 'update_temp_tables.php';

echo "🚀 IMPORT DONNÉES DURÉE DE SÉJOUR INTERNATIONALES\n";
echo "=================================================\n\n";

try {
    $manager = new TempTablesManager();
    echo "🔄 Lancement de l'import...\n";
    $result = $manager->checkAndUpdate(true); // Force l'import

    echo "\n📊 RÉSULTATS DE L'IMPORT:\n";
    echo "========================\n";

    if (isset($result['results'])) {
        foreach ($result['results'] as $table_result) {
            if ($table_result['table'] === 'fact_sejours_duree_pays_temp') {
                echo "✅ Table: {$table_result['table']}\n";
                echo "   📈 Supprimées: " . ($table_result['deleted'] ?? 0) . "\n";
                echo "   ➕ Insérées: " . ($table_result['inserted'] ?? 0) . "\n";
                echo "   📋 Status: " . ($table_result['status'] ?? 'inconnu') . "\n";

                // Afficher quelques statistiques sur les pays
                if (($table_result['inserted'] ?? 0) > 0) {
                    echo "\n🔍 ANALYSE DES DONNÉES IMPORTÉES:\n";
                    echo "=================================\n";

                    $config = DatabaseConfig::getConfig();
                    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

                    if (!$db->connect_error) {
                        // Pays les plus représentés
                        $result = $db->query("
                            SELECT p.nom_pays, COUNT(*) as nombre_enregistrements, SUM(vol.volume) as volume_total
                            FROM fact_sejours_duree_pays_temp vol
                            JOIN dim_pays p ON vol.id_pays = p.id_pays
                            GROUP BY vol.id_pays, p.nom_pays
                            ORDER BY nombre_enregistrements DESC
                            LIMIT 10
                        ");

                        echo "🌍 TOP 10 PAYS PAR NOMBRE D'ENREGISTREMENTS:\n";
                        $rank = 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "   {$rank}. {$row['nom_pays']}: {$row['nombre_enregistrements']} enr. ({$row['volume_total']} volume)\n";
                            $rank++;
                        }

                        // Statistiques par durée de séjour
                        echo "\n⏱️ RÉPARTITION PAR DURÉE DE SÉJOUR:\n";
                        $result = $db->query("
                            SELECT d.libelle, COUNT(*) as nombre, SUM(vol.volume) as volume_total
                            FROM fact_sejours_duree_pays_temp vol
                            JOIN dim_durees_sejour d ON vol.id_duree = d.id_duree
                            GROUP BY vol.id_duree, d.libelle
                            ORDER BY d.nb_nuits
                        ");

                        while ($row = $result->fetch_assoc()) {
                            echo "   - {$row['libelle']}: {$row['nombre']} enr. ({$row['volume_total']} volume)\n";
                        }

                        $db->close();
                    }
                }
            }
        }
    }
    echo "\n🎯 IMPORT TERMINÉ!\n";
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
