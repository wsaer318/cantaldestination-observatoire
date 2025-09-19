<?php
/**
 * Script de nettoyage automatique des caches FluxVision
 * Exécution programmée à minuit
 * 
 * @author FluxVision Team
 * @version 1.0
 */

// Configuration
$logFile = __DIR__ . '/../logs/cache_cleanup.log';
$cacheDirectories = [
    __DIR__ . '/../api/cache',
    __DIR__ . '/../cache',
    __DIR__ . '/../cache_test'
];

/**
 * Fonction de logging avec timestamp
 */
function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    
    // Créer le répertoire de logs s'il n'existe pas
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

/**
 * Supprime récursivement tous les fichiers d'un répertoire
 */
function clearDirectory($directory) {
    $totalFiles = 0;
    $totalSize = 0;
    
    if (!is_dir($directory)) {
        return ['files' => 0, 'size' => 0, 'error' => "Répertoire non trouvé: $directory"];
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $fileSize = $file->getSize();
            if (unlink($file->getPathname())) {
                $totalFiles++;
                $totalSize += $fileSize;
            }
        }
    }
    
    return ['files' => $totalFiles, 'size' => $totalSize];
}

/**
 * Formate la taille en octets en format lisible
 */
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

// Début du nettoyage
logMessage("=== DÉBUT NETTOYAGE CACHE AUTOMATIQUE ===", $logFile);
logMessage("🕛 Nettoyage programmé à minuit", $logFile);

$grandTotal = ['files' => 0, 'size' => 0];
$errors = [];

foreach ($cacheDirectories as $cacheDir) {
    logMessage("🔄 Nettoyage: $cacheDir", $logFile);
    
    $result = clearDirectory($cacheDir);
    
    if (isset($result['error'])) {
        $errors[] = $result['error'];
        logMessage("❌ Erreur: " . $result['error'], $logFile);
    } else {
        $grandTotal['files'] += $result['files'];
        $grandTotal['size'] += $result['size'];
        
        $sizeFormatted = formatBytes($result['size']);
        logMessage("✅ Supprimé: {$result['files']} fichiers ({$sizeFormatted})", $logFile);
    }
}

// Résumé final
logMessage("", $logFile);
logMessage("📊 RÉSUMÉ DU NETTOYAGE:", $logFile);
logMessage("• Total fichiers supprimés: {$grandTotal['files']}", $logFile);
logMessage("• Espace libéré: " . formatBytes($grandTotal['size']), $logFile);

if (!empty($errors)) {
    logMessage("⚠️ Erreurs rencontrées:", $logFile);
    foreach ($errors as $error) {
        logMessage("  - $error", $logFile);
    }
}

logMessage("🎯 Nettoyage terminé avec succès", $logFile);
logMessage("=== FIN NETTOYAGE CACHE ===", $logFile);
logMessage("", $logFile);

// Nettoyage des anciens logs (garder seulement les 30 derniers jours)
$logRetentionDays = 30;
$cutoffTime = time() - ($logRetentionDays * 24 * 60 * 60);

if (file_exists($logFile) && filemtime($logFile) < $cutoffTime) {
    // Archiver l'ancien log
    $archiveFile = dirname($logFile) . '/cache_cleanup_' . date('Y-m-d', filemtime($logFile)) . '.log';
    rename($logFile, $archiveFile);
    logMessage("📁 Log archivé: $archiveFile", $logFile);
}

exit(0);
?> 