<?php
/**
 * Script CRON pour mise à jour automatique des saisons astronomiques
 * 
 * Optimisé pour hébergement Net15
 * Exécution recommandée: quotidienne à minuit
 * 
 * Commande cron: 0 0 * * * /usr/bin/php /chemin/vers/votre/site/cron_update_saisons.php
 */

// Configuration pour environnement de production
ini_set('max_execution_time', 300); // 5 minutes max
ini_set('memory_limit', '128M');
error_reporting(E_ALL);

// Log des activités  
$logFile = dirname(dirname(__DIR__)) . '/logs/saisons_cron.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

function exitWithError($message, $code = 1) {
    writeLog("ERREUR: $message");
    exit($code);
}

try {
    writeLog("=== DÉBUT MISE À JOUR SAISONS CRON ===");
    
    // Vérifier que nous sommes dans le bon répertoire
    if (!file_exists(__DIR__ . '/scrap_date.js')) {
        exitWithError("Script scrap_date.js non trouvé dans " . __DIR__);
    }
    
    if (!file_exists(__DIR__ . '/import_saisons_simple.php')) {
        exitWithError("Script import_saisons_simple.php non trouvé");
    }
    
    // 1. Vérifier si une mise à jour est nécessaire
    require_once dirname(dirname(__DIR__)) . '/config/database.php';
    require_once dirname(dirname(__DIR__)) . '/classes/Database.php';
    
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Vérifier la date de dernière mise à jour
    $stmt = $db->query("
        SELECT MAX(updated_at) as derniere_maj 
        FROM dim_saisons 
        WHERE updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $derniereMaj = $stmt->fetch()['derniere_maj'];
    
    if ($derniereMaj && date('Y-m-d', strtotime($derniereMaj)) === date('Y-m-d')) {
        writeLog("Mise à jour déjà effectuée aujourd'hui ($derniereMaj). Arrêt.");
        exit(0);
    }
    
    writeLog("Dernière mise à jour: " . ($derniereMaj ?: 'jamais'));
    
    // 2. Exécuter le scraper JavaScript  
    writeLog("Récupération des données astronomiques...");
    
    // Changer vers le répertoire des scripts avant d'exécuter
    $oldDir = getcwd();
    chdir(__DIR__);
    
    $nodeCommand = 'node scrap_date.js';
    $output = [];
    $returnVar = 0;
    
    exec($nodeCommand . ' 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        $errorOutput = implode("\n", $output);
        exitWithError("Échec du scraper JavaScript (code $returnVar): $errorOutput");
    }
    
    writeLog("Scraper JavaScript exécuté avec succès");
    
    // 3. Vérifier que le fichier de données a été créé
    if (!file_exists('saisons_data.php')) {
        chdir($oldDir); // Restaurer le répertoire original en cas d'erreur
        exitWithError("Le fichier saisons_data.php n'a pas été généré");
    }
    
    writeLog("Fichier saisons_data.php généré");
    
    // 4. Importer en base de données
    writeLog("Import en base de données...");
    
    $phpCommand = 'php import_saisons_simple.php';
    $output = [];
    $returnVar = 0;
    
    exec($phpCommand . ' 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        $errorOutput = implode("\n", $output);
        exitWithError("Échec de l'import (code $returnVar): $errorOutput");
    }
    
    // Extraire les informations importantes de la sortie
    $nbSaisons = 0;
    $nbPeriodes = 0;
    
    foreach ($output as $line) {
        if (preg_match('/(\d+) saisons importées/', $line, $matches)) {
            $nbSaisons = $matches[1];
        }
        if (preg_match('/(\d+) périodes liées/', $line, $matches)) {
            $nbPeriodes = $matches[1];
        }
    }
    
    writeLog("Import terminé: $nbSaisons saisons, $nbPeriodes périodes liées");
    
    // 5. Nettoyage
    if (file_exists('saisons_data.php')) {
        unlink('saisons_data.php');
        writeLog("Fichier temporaire supprimé");
    }
    
    // Restaurer le répertoire original
    chdir($oldDir);
    
    // 6. Vérification finale
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_saisons");
    $totalSaisons = $stmt->fetch()['nb'];
    
    if ($totalSaisons < 20) {
        writeLog("ATTENTION: Seulement $totalSaisons saisons en base (attendu: ~24)");
    }
    
    writeLog("=== MISE À JOUR TERMINÉE AVEC SUCCÈS ===");
    writeLog("Total saisons en base: $totalSaisons");
    
    // 7. Optionnel: Notification par email en cas de succès (à configurer selon vos besoins)
    /*
    if (function_exists('mail')) {
        $subject = "FluxVision - Mise à jour saisons réussie";
        $message = "Mise à jour automatique des saisons astronomiques terminée avec succès.\n";
        $message .= "Saisons importées: $nbSaisons\n";
        $message .= "Périodes liées: $nbPeriodes\n";
        $message .= "Total en base: $totalSaisons\n";
        
        mail('admin@votredomaine.com', $subject, $message);
    }
    */
    
} catch (Exception $e) {
    $errorMsg = "Exception: " . $e->getMessage();
    writeLog($errorMsg);
    
    // Optionnel: Notification par email en cas d'erreur
    /*
    if (function_exists('mail')) {
        $subject = "FluxVision - ERREUR mise à jour saisons";
        $message = "Erreur lors de la mise à jour automatique des saisons:\n\n";
        $message .= $errorMsg . "\n\n";
        $message .= "Heure: " . date('Y-m-d H:i:s') . "\n";
        
        mail('admin@votredomaine.com', $subject, $message);
    }
    */
    
    exit(1);
}
?>