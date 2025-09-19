<?php
/**
 * PURGE QUOTIDIENNE AUTOMATIQUE - FLUXVISION
 * ===========================================
 * 
 * Script à exécuter à minuit pour supprimer tous les caches 
 * de l'année actuelle et garantir des données fraîches.
 * 
 * Usage:
 * - Manuel: php cache_purge_daily.php
 * - Web sécurisé: ?key=daily_purge_2024_secure
 * - Cron: 0 0 * * * php /path/to/cache_purge_daily.php
 */

// Configuration de sécurité
$SECURE_KEY = 'daily_purge_2024_secure';
$LOG_FILE = '../logs/cache_purge_daily.log';

// Vérification d'accès sécurisé pour usage web
if (isset($_GET['key']) && $_GET['key'] !== $SECURE_KEY) {
    http_response_code(403);
    exit('❌ Accès refusé - Clé invalide');
}

// Headers de sécurité
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

// Démarrage
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "PURGE QUOTIDIENNE CACHE FLUXVISION\n";
echo str_repeat("=", 45) . "\n";
echo "Demarrage: $timestamp\n\n";

try {
    require_once __DIR__ . '/infographie/CacheManager.php';
    $cache = new CantalDestinationCacheManager();
    
    // 1. État avant purge
    echo "ETAT AVANT PURGE:\n";
    $statsBefore = $cache->getStats();
    $currentYear = date('Y');
    
    echo "   Total fichiers: {$statsBefore['total_files']}\n";
    echo "   Taille totale: " . formatBytes($statsBefore['total_size']) . "\n";
    echo "   Annee cible: $currentYear\n\n";
    
    // 2. Analyse des fichiers de l'année actuelle
    echo "ANALYSE FICHIERS $currentYear:\n";
    $yearFiles = 0;
    $yearSize = 0;
    
    // Analyser directement les fichiers dans le dossier cache
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('cache', RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $filename = basename($file);
            if (strpos($filename, "_{$currentYear}_") !== false || 
                strpos($filename, "_{$currentYear}.json") !== false ||
                preg_match("/_{$currentYear}[^0-9]/", $filename)) {
                $yearFiles++;
                $yearSize += filesize($file);
            }
        }
    }
    
    echo "   Total fichiers $currentYear: $yearFiles\n";
    echo "   Taille fichiers $currentYear: " . formatBytes($yearSize) . "\n\n";
    
    // 3. Purge quotidienne
    if ($yearFiles > 0) {
        echo "PURGE EN COURS...\n";
        $purged = $cache->dailyPurge($currentYear);
        
        echo "   $purged fichiers supprimes\n\n";
        
        // 4. État après purge
        echo "ETAT APRES PURGE:\n";
        $statsAfter = $cache->getStats();
        $reduction = $statsBefore['total_files'] - $statsAfter['total_files'];
        $sizeReduction = $statsBefore['total_size'] - $statsAfter['total_size'];
        
        echo "   Fichiers restants: {$statsAfter['total_files']}\n";
        echo "   Taille restante: " . formatBytes($statsAfter['total_size']) . "\n";
        echo "   Fichiers supprimes: $reduction\n";
        echo "   Espace libere: " . formatBytes($sizeReduction) . "\n\n";
        
        if ($purged > 0) {
            echo "PURGE REUSSIE - Cache $currentYear vide!\n";
            $logMessage = "SUCCESS: Purged $purged files for year $currentYear";
        } else {
            echo "ANOMALIE: Detection incoherente\n";
            $logMessage = "WARNING: Detection inconsistency for year $currentYear";
        }
    } else {
        echo "Aucun cache $currentYear trouve - Purge non necessaire\n";
        $logMessage = "INFO: No cache files found for year $currentYear";
    }
    
    // 5. Nettoyage des dossiers vides
    echo "\nNETTOYAGE DOSSIERS VIDES...\n";
    $emptyDirs = cleanEmptyDirectories($cache);
    if ($emptyDirs > 0) {
        echo "   $emptyDirs dossiers vides supprimes\n";
    } else {
        echo "   Aucun dossier vide trouve\n";
    }
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    $logMessage = "ERROR: " . $e->getMessage();
}

// Durée d'exécution
$duration = round((microtime(true) - $startTime) * 1000, 2);
echo "\nDuree: {$duration}ms\n";

// Logging
$logMessage = isset($logMessage) ? $logMessage : "UNKNOWN: Script completed";
$logEntry = "[$timestamp] DAILY_PURGE - $logMessage (Duration: {$duration}ms)\n";

if (!is_dir(dirname($LOG_FILE))) {
    mkdir(dirname($LOG_FILE), 0755, true);
}
file_put_contents($LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);

/**
 * Fonctions utilitaires
 */
function formatBytes($size) {
    if ($size >= 1024 * 1024) {
        return round($size / (1024 * 1024), 2) . ' MB';
    } elseif ($size >= 1024) {
        return round($size / 1024, 2) . ' KB';
    }
    return $size . ' B';
}

function cleanEmptyDirectories($cache) {
    $cleaned = 0;
    $cacheBase = 'cache';
    
    // Parcourir récursivement les dossiers
    $dirs = new RecursiveDirectoryIterator($cacheBase, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dirs, RecursiveIteratorIterator::CHILD_FIRST);
    
    foreach ($iterator as $dir) {
        if ($dir->isDir()) {
            $dirPath = $dir->getPathname();
            $files = scandir($dirPath);
            
            // Vérifier si le dossier est vide (seulement . et ..)
            if (count($files) == 2) {
                rmdir($dirPath);
                $cleaned++;
            }
        }
    }
    
    return $cleaned;
}
?> 