<?php
/**
 * Script de nettoyage des anciens caches du tableau de bord
 * Supprime les fichiers de cache avec des noms MD5 illisibles
 */

echo "🧹 Nettoyage des anciens caches du tableau de bord\n";
echo "=" . str_repeat("=", 50) . "\n";

$startTime = microtime(true);
$totalDeleted = 0;
$totalSize = 0;

// Dossiers contenant les anciens caches
$oldCacheDirs = [
    __DIR__ . '/cache',
    __DIR__ . '/infographie/cache',
    __DIR__ . '/infographie/cache_infographie'
];

// Patterns de fichiers à supprimer (noms avec hash MD5)
$patterns = [
    // Anciens caches départements
    '/cache/exc_departements_[a-f0-9]{32}\.json$/',
    '/cache/departements/[a-f0-9]{32}\.json$/',
    
    // Anciens caches régions
    '/cache/exc_regions_[a-f0-9]{32}\.json$/',
    '/cache/regions/[a-f0-9]{32}\.json$/',
    
    // Anciens caches pays
    '/cache/exc_pays_[a-f0-9]{32}\.json$/',
    '/cache/pays/[a-f0-9]{32}\.json$/',
    
    // Anciens caches âge
    '/cache/exc_ages_[a-f0-9]{32}\.json$/',
    '/cache/age/[a-f0-9]{32}\.json$/',
    
    // Anciens caches CSP/GeoLife
    '/cache/exc_csp_[a-f0-9]{32}\.json$/',
    '/cache/geolife/[a-f0-9]{32}\.json$/',
    
    // Patterns généralistes pour les hash MD5
    '/[a-f0-9]{32}\.json$/',
    '/[a-f0-9]{40}\.json$/' // SHA1 au cas où
];

function cleanDirectory($dir, $patterns) {
    global $totalDeleted, $totalSize;
    
    if (!is_dir($dir)) {
        return 0;
    }
    
    echo "\n📁 Nettoyage du dossier: $dir\n";
    
    $deleted = 0;
    $dirSize = 0;
    
    // Scanner récursivement
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        $filePath = $file->getPathname();
        $relativePath = str_replace($dir, '', $filePath);
        
        // Vérifier si le fichier correspond aux patterns d'anciens caches
        $shouldDelete = false;
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $relativePath)) {
                $shouldDelete = true;
                break;
            }
        }
        
        // Aussi supprimer les fichiers qui ont des noms très courts (hash uniquement)
        $filename = basename($filePath);
        if (preg_match('/^[a-f0-9]{8,40}\.json$/', $filename)) {
            $shouldDelete = true;
        }
        
        if ($shouldDelete && $file->isFile()) {
            $size = $file->getSize();
            
            echo "  🗑️  $relativePath (" . formatSize($size) . ")\n";
            
            if (unlink($filePath)) {
                $deleted++;
                $dirSize += $size;
            } else {
                echo "  ❌ Erreur lors de la suppression de $relativePath\n";
            }
        }
    }
    
    // Supprimer les dossiers vides
    cleanEmptyDirectories($dir);
    
    echo "  ✅ $deleted fichiers supprimés (" . formatSize($dirSize) . ")\n";
    
    $totalDeleted += $deleted;
    $totalSize += $dirSize;
    
    return $deleted;
}

function cleanEmptyDirectories($dir) {
    if (!is_dir($dir)) {
        return;
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
                echo "  📂 Suppression dossier vide: " . str_replace($dir, '', $dirPath) . "\n";
                rmdir($dirPath);
            }
        }
    }
}

function formatSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    
    return round($size, 2) . ' ' . $units[$unitIndex];
}

// Nettoyer tous les dossiers
foreach ($oldCacheDirs as $dir) {
    cleanDirectory($dir, $patterns);
}

// Afficher le résumé
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 NETTOYAGE TERMINÉ\n";
echo "   📊 Total fichiers supprimés: $totalDeleted\n";
echo "   💾 Espace libéré: " . formatSize($totalSize) . "\n";
echo "   ⏱️  Durée: {$duration}s\n";

if ($totalDeleted > 0) {
    echo "\n✅ Les anciens caches ont été supprimés avec succès!\n";
    echo "   Le nouveau système de cache unifié est maintenant opérationnel.\n";
    echo "   Les nouveaux caches seront créés avec des noms lisibles dans:\n";
    echo "   📁 /cache/tableau_bord/\n";
} else {
    echo "\n✨ Aucun ancien cache trouvé - le système est déjà propre!\n";
}

echo "\n";
?> 