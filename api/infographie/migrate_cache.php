<?php
/**
 * Script de migration pour organiser les caches d'infographie
 * Déplace les anciens caches vers la nouvelle structure organisée
 */

require_once __DIR__ . '/CacheManager.php';

echo "🚀 Migration des caches d'infographie\n";
echo "=====================================\n";

$cacheManager = getInfographieCacheManager();
$oldCacheDir = __DIR__ . '/cache_infographie';
$migratedCount = 0;
$errors = 0;

// Créer la nouvelle structure de dossiers
echo "📁 Création de la structure de dossiers...\n";

// Scanner les anciens fichiers de cache
if (is_dir($oldCacheDir)) {
    $files = glob($oldCacheDir . '/*.json');
    echo "📊 " . count($files) . " fichiers de cache trouvés\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "🔄 Traitement de: $filename\n";
        
        try {
            // Analyser le nom de fichier pour extraire les informations
            if (preg_match('/^(exc|tour)_([a-z]+)_([a-f0-9]{32})\.json$/', $filename, $matches)) {
                $category = $matches[1] === 'exc' ? 'excursionnistes' : 'touristes';
                $type = $matches[2];
                
                // Lire le contenu du fichier
                $content = file_get_contents($file);
                
                // Créer un nouveau nom basé sur la date actuelle
                $params = [
                    'zone' => 'CANTAL',
                    'annee' => 2024,
                    'periode' => 'hiver',
                    'limit' => 15
                ];
                
                // Sauvegarder avec le nouveau système
                if ($cacheManager->setCacheData($type, $category, $params, $content)) {
                    echo "   ✅ Migré vers: " . $cacheManager->generateReadableFileName($type, $category, $params) . "\n";
                    $migratedCount++;
                    
                    // Optionnel: supprimer l'ancien fichier
                    // unlink($file);
                } else {
                    echo "   ❌ Erreur lors de la migration\n";
                    $errors++;
                }
            } else {
                echo "   ⚠️  Format de fichier non reconnu, ignoré\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Erreur: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
} else {
    echo "📂 Dossier cache non trouvé, création...\n";
    mkdir($oldCacheDir, 0755, true);
}

echo "\n📊 Résultats de la migration:\n";
echo "   ✅ Fichiers migrés: $migratedCount\n";
echo "   ❌ Erreurs: $errors\n";

// Afficher les statistiques du nouveau système
echo "\n📈 Statistiques du cache organisé:\n";
$stats = $cacheManager->getCacheStats();
echo "   📁 Dossiers créés: " . count($stats['folders']) . "\n";
echo "   📄 Total fichiers: " . $stats['total_files'] . "\n";
echo "   💾 Taille totale: " . $stats['total_size_mb'] . " MB\n";

foreach ($stats['folders'] as $type => $folderStats) {
    echo "      📂 $type: {$folderStats['files']} fichiers ({$folderStats['size_mb']} MB)\n";
}

echo "\n🎉 Migration terminée!\n";
echo "\nUtilisation:\n";
echo "   - Les nouveaux caches utilisent des noms lisibles\n";
echo "   - Structure organisée par catégorie dans des sous-dossiers\n";
echo "   - Administration disponible via cache_admin.php\n";

// Test du système
echo "\n🧪 Test du nouveau système...\n";

$testParams = [
    'zone' => 'CANTAL',
    'annee' => 2024,
    'periode' => 'hiver',
    'limit' => 10
];

$testData = json_encode(['test' => 'data', 'timestamp' => time()]);

// Test d'écriture
if ($cacheManager->setCacheData('departements', 'excursionnistes', $testParams, $testData)) {
    echo "   ✅ Test d'écriture: OK\n";
    
    // Test de lecture
    $cacheResult = $cacheManager->getCacheData('departements', 'excursionnistes', $testParams);
    if ($cacheResult['data'] !== null) {
        echo "   ✅ Test de lecture: OK\n";
        echo "   📄 Fichier de cache: " . $cacheResult['file'] . "\n";
    } else {
        echo "   ❌ Test de lecture: ÉCHEC\n";
    }
} else {
    echo "   ❌ Test d'écriture: ÉCHEC\n";
}

echo "\n✨ Système prêt à l'emploi!\n";
?> 