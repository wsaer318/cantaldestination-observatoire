<?php
/**
 * Import spécifique des données de durée de séjour
 */

require_once 'update_temp_tables.php';

echo "🚀 IMPORT DONNÉES DURÉE DE SÉJOUR\n";
echo "=================================\n\n";

try {
    $manager = new TempTablesManager();

    echo "🔄 Lancement de l'import...\n";
    $result = $manager->checkAndUpdate(true); // Force l'import

    echo "\n📊 RÉSULTATS DE L'IMPORT:\n";
    echo "========================\n";

    if (isset($result['results'])) {
        foreach ($result['results'] as $table_result) {
            if ($table_result['table'] === 'fact_sejours_duree_temp') {
                echo "✅ Table: {$table_result['table']}\n";
                echo "   📈 Supprimées: " . ($table_result['deleted'] ?? 0) . "\n";
                echo "   ➕ Insérées: " . ($table_result['inserted'] ?? 0) . "\n";
                echo "   📋 Status: " . ($table_result['status'] ?? 'inconnu') . "\n";
            }
        }
    }

    echo "\n🎯 IMPORT TERMINÉ!\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
