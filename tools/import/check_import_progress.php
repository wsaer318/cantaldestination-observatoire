<?php
/**
 * Script de vérification de la progression de l'import
 * Utilisé par l'interface web pour suivre l'avancement
 */

header('Content-Type: application/json');

// Configuration
require_once '../../config/database.php';
$dbConfig = DatabaseConfig::getConfig();

// Chemins des fichiers
$progress_file = __DIR__ . '/../../data/temp/temp_import_progress.json';
$log_file = __DIR__ . '/../../logs/temp_tables_update.log';

// Créer les dossiers s'ils n'existent pas
$progress_dir = dirname($progress_file);
$log_dir = dirname($log_file);

if (!is_dir($progress_dir)) {
    if (!mkdir($progress_dir, 0755, true)) {
        error_log("Impossible de créer le dossier de progression : $progress_dir");
    }
}

if (!is_dir($log_dir)) {
    if (!mkdir($log_dir, 0755, true)) {
        error_log("Impossible de créer le dossier de logs : $log_dir");
    }
}

$response = [
    'status' => 'unknown',
    'message' => 'Aucun import en cours',
    'progress' => 0,
    'details' => []
];

// Vérifier si un fichier de progression existe
if (file_exists($progress_file)) {
    $progress_data = json_decode(file_get_contents($progress_file), true);

    if ($progress_data) {
        $response['status'] = $progress_data['status'];
        $response['message'] = $progress_data['message'];
        $response['start_time'] = $progress_data['start_time'];

        // Calculer le temps écoulé
        $elapsed = time() - $progress_data['start_time'];
        $response['elapsed_time'] = $elapsed;

        // Vérifier les logs pour voir la progression
        if (file_exists($log_file)) {
            $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recent_logs = array_slice($logs, -10); // Derniers 10 logs

            $response['details'] = $recent_logs;

            // Analyser les logs pour déterminer la progression
            $last_log = end($logs);
            if (strpos($last_log, '🎯 Mise à jour terminée') !== false) {
                $response['status'] = 'completed';
                $response['message'] = 'Import terminé avec succès';
                $response['progress'] = 100;

                // Supprimer le fichier de progression
                unlink($progress_file);
            } elseif (strpos($last_log, '❌ ERREUR') !== false) {
                $response['status'] = 'error';
                $response['message'] = 'Erreur lors de l\'import';
            } else {
                $response['status'] = 'running';
                $response['message'] = 'Import en cours...';
                $response['progress'] = 50; // Estimation
            }
        }
    }
}

// Vérifier le nombre de lignes dans la table pour voir si des données ont été importées
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM fact_diurnes_departements_temp WHERE is_provisional = 1");
    $count = $stmt->fetch()['total'];
    $response['imported_rows'] = (int)$count;

    if ($count > 0) {
        $response['has_data'] = true;
    }

} catch (Exception $e) {
    $response['database_error'] = $e->getMessage();
}

echo json_encode($response);
?>
