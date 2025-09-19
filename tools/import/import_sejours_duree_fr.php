<?php
/**
 * Import spécifique des données de durée de séjour françaises détaillées
 * duree_sejour_fr.csv -> fact_sejours_duree_departements_temp
 */

require_once 'update_temp_tables.php';

echo "🚀 IMPORT DONNÉES DURÉE DE SÉJOUR FRANÇAISES DÉTAILLÉES\n";
echo "=====================================================\n\n";

try {
    $manager = new TempTablesManager();
    echo "🔄 Lancement de l'import...\n";
    $result = $manager->checkAndUpdate(true); // Force l'import

    echo "\n📊 RÉSULTATS DE L'IMPORT:\n";
    echo "========================\n";

    if (isset($result['results'])) {
        foreach ($result['results'] as $table_result) {
            if ($table_result['table'] === 'fact_sejours_duree_departements_temp') {
                echo "✅ Table: {$table_result['table']}\n";
                echo "   📈 Supprimées: " . ($table_result['deleted'] ?? 0) . "\n";
                echo "   ➕ Insérées: " . ($table_result['inserted'] ?? 0) . "\n";
                echo "   📋 Status: " . ($table_result['status'] ?? 'inconnu') . "\n";

                // Afficher quelques statistiques sur les départements
                if (($table_result['inserted'] ?? 0) > 0) {
                    echo "\n🔍 ANALYSE DES DONNÉES IMPORTÉES:\n";
                    echo "=================================\n";

                    $config = DatabaseConfig::getConfig();
                    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

                    if (!$db->connect_error) {
                        // Départements les plus représentés
                        $result = $db->query("
                            SELECT d.nom_departement, COUNT(*) as nombre_enregistrements, SUM(vol.volume) as volume_total
                            FROM fact_sejours_duree_departements_temp vol
                            JOIN dim_departements d ON vol.id_departement = d.id_departement
                            GROUP BY vol.id_departement, d.nom_departement
                            ORDER BY nombre_enregistrements DESC
                            LIMIT 10
                        ");

                        echo "🏛️ TOP 10 DÉPARTEMENTS PAR NOMBRE D'ENREGISTREMENTS:\n";
                        $rank = 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "   {$rank}. {$row['nom_departement']}: {$row['nombre_enregistrements']} enr. ({$row['volume_total']} volume)\n";
                            $rank++;
                        }

                        // Répartition par durée de séjour
                        echo "\n⏱️ RÉPARTITION PAR DURÉE DE SÉJOUR:\n";
                        $result = $db->query("
                            SELECT duree.libelle, COUNT(*) as nombre, SUM(vol.volume) as volume_total
                            FROM fact_sejours_duree_departements_temp vol
                            JOIN dim_durees_sejour duree ON vol.id_duree = duree.id_duree
                            GROUP BY vol.id_duree, duree.libelle
                            ORDER BY duree.nb_nuits
                        ");

                        while ($row = $result->fetch_assoc()) {
                            echo "   - {$row['libelle']}: {$row['nombre']} enr. ({$row['volume_total']} volume)\n";
                        }

                        // Répartition par région
                        echo "\n🏞️ RÉPARTITION PAR RÉGION:\n";
                        $result = $db->query("
                            SELECT d.nom_region, COUNT(*) as nombre, SUM(vol.volume) as volume_total
                            FROM fact_sejours_duree_departements_temp vol
                            JOIN dim_departements d ON vol.id_departement = d.id_departement
                            GROUP BY d.nom_region
                            ORDER BY nombre DESC
                        ");

                        while ($row = $result->fetch_assoc()) {
                            echo "   - {$row['nom_region']}: {$row['nombre']} enr. ({$row['volume_total']} volume)\n";
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
