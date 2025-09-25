<?php
/**
 * Script de maintenance automatique des caches FluxVision
 * 
 * Ce script doit être exécuté périodiquement via cron pour nettoyer
 * automatiquement les fichiers de cache expirés.
 * 
 * Exemple de configuration cron (toutes les heures) :
 * 0 HEURE JOUR MOIS SEMAINE php /path/to/fluxvision/api/cache_maintenance_auto.php
 * 
 * Exemple de configuration cron (toutes les 6 heures) :
 * 0 TOUTES6H JOUR MOIS SEMAINE php /path/to/fluxvision/api/cache_maintenance_auto.php
 */

// Détecter si exécuté en ligne de commande ou via web
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // Si accédé via web, vérifier les permissions
    header('Content-Type: text/plain; charset=utf-8');
    
    // Optionnel : ajouter une vérification de sécurité
    $secretKey = $_GET['key'] ?? '';
    $expectedKey = 'fluxvision_maintenance_2024'; // À changer en production
    
    if ($secretKey !== $expectedKey) {
        http_response_code(403);
        echo "❌ Accès refusé - Clé de maintenance requise\n";
        echo "Usage: ?key=fluxvision_maintenance_2024\n";
        exit;
    }
}

// Inclure le gestionnaire de cache unifié
require_once dirname(__DIR__) . '/infographie/CacheManager.php';

echo "🧹 MAINTENANCE AUTOMATIQUE CACHE FLUXVISION\n";
echo "==========================================\n";
echo "📅 Démarrage: " . date('Y-m-d H:i:s') . "\n";

$startTime = microtime(true);

try {
    // Initialiser le gestionnaire de cache
    $cacheManager = new CantalDestinationCacheManager();
    
    // 1. Obtenir les statistiques avant nettoyage
    echo "\n📊 ÉTAT AVANT NETTOYAGE:\n";
    $statsBefore = $cacheManager->getStats();
    
    $totalFilesBefore = $statsBefore['total_files'];
    $totalSizeBefore = $statsBefore['total_size'];
    $totalExpiredBefore = array_sum(array_column($statsBefore['categories'], 'expired'));
    
    echo "   📄 Total fichiers: $totalFilesBefore\n";
    echo "   💾 Taille totale: " . formatBytes($totalSizeBefore) . "\n";
    echo "   ⏰ Fichiers expirés: $totalExpiredBefore\n";
    
    if ($totalExpiredBefore === 0) {
        echo "\n✨ Aucun fichier expiré - Cache propre!\n";
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        echo "⏱️  Durée: {$executionTime}ms\n";
        exit;
    }
    
    // 2. Nettoyer les caches expirés
    echo "\n🧹 NETTOYAGE EN COURS...\n";
    $cleanedFiles = $cacheManager->cleanup();
    
    // 3. Obtenir les statistiques après nettoyage
    echo "\n📊 ÉTAT APRÈS NETTOYAGE:\n";
    $statsAfter = $cacheManager->getStats();
    
    $totalFilesAfter = $statsAfter['total_files'];
    $totalSizeAfter = $statsAfter['total_size'];
    $spaceSaved = $totalSizeBefore - $totalSizeAfter;
    
    echo "   📄 Total fichiers: $totalFilesAfter\n";
    echo "   💾 Taille totale: " . formatBytes($totalSizeAfter) . "\n";
    echo "   🗑️  Fichiers supprimés: $cleanedFiles\n";
    echo "   💾 Espace libéré: " . formatBytes($spaceSaved) . "\n";
    
    // 4. Détail par catégorie (si en mode CLI ou debug)
    if ($isCli || isset($_GET['debug'])) {
        echo "\n📋 DÉTAIL PAR CATÉGORIE:\n";
        foreach ($statsAfter['categories'] as $category => $stats) {
            if ($stats['files'] > 0) {
                $ttlFormatted = formatDuration($stats['ttl']);
                echo "   📂 $category: {$stats['files']} fichiers, TTL: $ttlFormatted\n";
            }
        }
    }
    
    // 5. Nettoyage des dossiers vides (optionnel)
    echo "\n🗂️  NETTOYAGE DOSSIERS VIDES...\n";
    $emptyDirsRemoved = cleanEmptyDirectories(__DIR__ . '/../cache');
    if ($emptyDirsRemoved > 0) {
        echo "   📂 $emptyDirsRemoved dossiers vides supprimés\n";
    } else {
        echo "   ✅ Aucun dossier vide trouvé\n";
    }
    
    // 6. Résumé final
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ MAINTENANCE TERMINÉE\n";
    echo "   🕒 Durée: {$executionTime}ms\n";
    echo "   📊 Fichiers nettoyés: $cleanedFiles\n";
    echo "   💾 Espace libéré: " . formatBytes($spaceSaved) . "\n";
    echo "   📅 Fin: " . date('Y-m-d H:i:s') . "\n";
    
    // 7. Log pour monitoring (optionnel)
    if ($cleanedFiles > 0) {
        $logEntry = date('Y-m-d H:i:s') . " - Cache maintenance: $cleanedFiles fichiers supprimés, " . formatBytes($spaceSaved) . " libérés\n";
        file_put_contents(__DIR__ . '/../logs/cache_maintenance.log', $logEntry, FILE_APPEND | LOCK_EX);
    }

} catch (Exception $e) {
    echo "\n❌ ERREUR DURANT LA MAINTENANCE:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Log l'erreur
    $errorLog = date('Y-m-d H:i:s') . " - ERREUR: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/../logs/cache_maintenance_errors.log', $errorLog, FILE_APPEND | LOCK_EX);
    
    exit(1);
}

/**
 * Formate les bytes en unités lisibles
 */
function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    
    return round($size, 2) . ' ' . $units[$unitIndex];
}

/**
 * Formate la durée TTL en format lisible
 */
function formatDuration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return round($seconds / 60) . 'min';
    if ($seconds < 86400) return round($seconds / 3600) . 'h';
    return round($seconds / 86400) . 'j';
}

/**
 * Supprime les dossiers vides récursivement
 */
function cleanEmptyDirectories($dir) {
    $removed = 0;
    
    if (!is_dir($dir)) {
        return $removed;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            $dirPath = $file->getPathname();
            
            // Vérifier si le dossier est vide
            $files = array_diff(scandir($dirPath), ['.', '..']);
            if (empty($files)) {
                if (rmdir($dirPath)) {
                    $removed++;
                }
            }
        }
    }
    
    return $removed;
}
?> 
