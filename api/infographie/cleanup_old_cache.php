<?php
/**
 * Script de nettoyage des anciens fichiers de cache
 * Traite les fichiers restants non migrés et les organise
 */

require_once __DIR__ . '/CacheManager.php';

echo "🧹 Nettoyage des anciens fichiers de cache\n";
echo "==========================================\n";

$cacheManager = getInfographieCacheManager();
$oldCacheDir = __DIR__ . '/cache_infographie';
$migratedCount = 0;
$deletedCount = 0;

// Scanner tous les fichiers JSON dans le dossier racine cache
$files = glob($oldCacheDir . '/*.json');

if (empty($files)) {
    echo "✅ Aucun ancien fichier à nettoyer\n";
    exit;
}

echo "📊 " . count($files) . " anciens fichiers trouvés\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    echo "🔍 Analyse de: $filename\n";
    
    try {
        // Analyser le nom pour déterminer le type et la catégorie
        if (preg_match('/^(exc|tour)_([a-z_]+)_([a-f0-9]{32})\.json$/', $filename, $matches)) {
            $category = $matches[1] === 'exc' ? 'excursionnistes' : 'touristes';
            $type = $matches[2];
            
            // Mapping des anciens noms vers les nouveaux types
            $typeMapping = [
                'departements' => 'departements',
                'regions' => 'regions', 
                'pays' => 'pays',
                'indicateurs' => 'indicateurs'
            ];
            
            if (isset($typeMapping[$type])) {
                $newType = $typeMapping[$type];
                
                // Lire le contenu
                $content = file_get_contents($file);
                
                if ($content !== false) {
                    // Analyser le contenu pour déterminer les paramètres
                    $data = json_decode($content, true);
                    
                    if ($data && is_array($data)) {
                        // Déterminer les paramètres à partir du contenu ou utiliser des valeurs par défaut
                        $params = [
                            'zone' => 'CANTAL',
                            'annee' => 2024,
                            'periode' => 'hiver', 
                            'limit' => count($data) // Utiliser le nombre d'éléments comme limite
                        ];
                        
                        // Essayer de sauvegarder avec le nouveau système
                        if ($cacheManager->setCacheData($newType, $category, $params, $content)) {
                            $newFileName = $cacheManager->generateReadableFileName($newType, $category, $params);
                            echo "   ✅ Migré vers: $newType/$newFileName\n";
                            
                            // Supprimer l'ancien fichier
                            unlink($file);
                            echo "   🗑️  Ancien fichier supprimé\n";
                            
                            $migratedCount++;
                        } else {
                            echo "   ❌ Erreur lors de la migration\n";
                        }
                    } else {
                        echo "   ⚠️  Contenu JSON invalide, suppression\n";
                        unlink($file);
                        $deletedCount++;
                    }
                } else {
                    echo "   ❌ Impossible de lire le fichier\n";
                }
            } else {
                echo "   ⚠️  Type '$type' non reconnu, suppression\n";
                unlink($file);
                $deletedCount++;
            }
        } else {
            echo "   ⚠️  Format non reconnu, suppression\n";
            unlink($file);
            $deletedCount++;
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erreur: " . $e->getMessage() . "\n";
        echo "   🗑️  Suppression du fichier corrompu\n";
        unlink($file);
        $deletedCount++;
    }
    
    echo "\n";
}

echo "📊 Résumé du nettoyage:\n";
echo "   ✅ Fichiers migrés: $migratedCount\n";
echo "   🗑️  Fichiers supprimés: $deletedCount\n";

// Vérifier que le dossier racine est maintenant propre
$remainingFiles = glob($oldCacheDir . '/*.json');
if (empty($remainingFiles)) {
    echo "   🎉 Dossier cache racine maintenant propre!\n";
} else {
    echo "   ⚠️  " . count($remainingFiles) . " fichiers restants:\n";
    foreach ($remainingFiles as $file) {
        echo "      - " . basename($file) . "\n";
    }
}

// Afficher les statistiques finales
echo "\n📈 Statistiques du cache organisé:\n";
$stats = $cacheManager->getCacheStats();
echo "   📁 Catégories: " . count($stats['folders']) . "\n";
echo "   📄 Total fichiers: " . $stats['total_files'] . "\n";
echo "   💾 Taille totale: " . $stats['total_size_mb'] . " MB\n";

foreach ($stats['folders'] as $type => $folderStats) {
    if ($folderStats['files'] > 0) {
        echo "      📂 $type: {$folderStats['files']} fichiers ({$folderStats['size_mb']} MB)\n";
    }
}

echo "\n✨ Nettoyage terminé!\n";
?> 