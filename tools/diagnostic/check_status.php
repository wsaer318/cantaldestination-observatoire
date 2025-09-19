<?php
require_once 'update_temp_tables.php';

echo "🔍 VÉRIFICATION DU STATUT DES TABLES TEMPORAIRES\n";
echo "===============================================\n\n";

try {
    $manager = new TempTablesManager();

    echo "📊 Status des tables temporaires diurnes :\n";
    echo "==========================================\n";

    $tables_to_check = [
        'fact_diurnes_temp',
        'fact_diurnes_departements_temp',
        'fact_diurnes_pays_temp',
        'fact_sejours_duree_temp',
        'fact_sejours_duree_departements_temp',
        'fact_sejours_duree_pays_temp'
    ];

    foreach ($tables_to_check as $table) {
        $status = $manager->getStatus($table);
        $count = $status['count'] ?? 0;
        echo "✅ $table: $count enregistrements\n";
    }

    echo "\n📁 Vérification des fichiers CSV :\n";
    echo "=================================\n";

    $csv_files = [
        'frequentation_journee.csv',
        'frequentation_journee_fr.csv',
        'frequentation_journee_int.csv',
        'duree_sejour.csv',
        'duree_sejour_fr.csv',
        'duree_sejour_int.csv'
    ];

    foreach ($csv_files as $filename) {
        $filepath = data_temp_file($filename);
        if (file_exists($filepath)) {
            $lines = count(file($filepath)) - 1; // -1 pour l'en-tête
            echo "📄 $filename: $lines lignes de données\n";
        } else {
            echo "❌ $filename: Fichier introuvable\n";
        }
    }

    echo "\n🎯 DIAGNOSTIC :\n";
    echo "==============\n";

    // Vérifier s'il y a eu des imports récents
    echo "Vérification des imports récents...\n";
    $log_file = __DIR__ . '/data/logs/temp_tables_update.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $last_import = strrpos($log_content, '=== FIN MISE À JOUR ===');
        if ($last_import !== false) {
            $last_part = substr($log_content, $last_import);
            $lines = explode("\n", $last_part);
            foreach ($lines as $line) {
                if (strpos($line, 'Total erreurs:') !== false || strpos($line, 'Taux succès global:') !== false) {
                    echo "📋 " . trim($line) . "\n";
                }
            }
        }
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
