<?php
require_once 'update_temp_tables.php';

echo "🚀 IMPORT OPTIMISÉ POUR GROS VOLUMES\n";
echo "=====================================\n\n";

// Activer le mode silencieux pour de meilleures performances
$silentMode = true;

try {
    echo "🔧 Initialisation en mode silencieux (optimisé pour gros volumes)...\n";
    $manager = new TempTablesManager($silentMode);

    echo "⏳ Lancement de l'import (logs réduits pour optimiser les performances)...\n";
    $start_time = microtime(true);

    $result = $manager->checkAndUpdate(true); // Force l'import

    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 2);

    echo "\n📊 RÉSULTATS DE L'IMPORT OPTIMISÉ:\n";
    echo "===================================\n";
    echo "⏱️ Durée totale: {$duration}s\n";

    if (isset($result['results'])) {
        $total_inserted = 0;
        $total_deleted = 0;

        foreach ($result['results'] as $table_result) {
            echo "✅ Table: {$table_result['table']}\n";
            if (isset($table_result['deleted'])) {
                echo "   📈 Supprimées: " . number_format($table_result['deleted']) . "\n";
                $total_deleted += $table_result['deleted'];
            }
            if (isset($table_result['inserted'])) {
                echo "   ➕ Insérées: " . number_format($table_result['inserted']) . "\n";
                $total_inserted += $table_result['inserted'];
            }
            if (isset($table_result['status'])) {
                echo "   📋 Status: {$table_result['status']}\n";
            }
            echo "\n";
        }

        echo "🎯 TOTAUX:\n";
        echo "=========\n";
        echo "📊 Lignes supprimées: " . number_format($total_deleted) . "\n";
        echo "📊 Lignes insérées: " . number_format($total_inserted) . "\n";
        echo "⚡ Performance: " . round($total_inserted / $duration, 0) . " lignes/seconde\n";
    }

    echo "\n🎉 IMPORT OPTIMISÉ TERMINÉ AVEC SUCCÈS!\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
?>