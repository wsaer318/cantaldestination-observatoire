<?php
/**
 * Script pour appliquer automatiquement le ZoneMapper à toutes les APIs
 * Remplace les mappings manuels par l'utilisation de la classe ZoneMapper
 */

// Liste des APIs qui ont besoin du ZoneMapper
$apiFiles = [
    'bloc_d1_cached.php',
    'bloc_d1_exc_cached.php', 
    'bloc_d2_exc_cached.php',
    'bloc_d3_exc_cached.php',
    'bloc_d5_exc_cached.php',
    'bloc_d6_exc_cached.php',
    'bloc_d2_simple.php',
    'bloc_d3_simple.php',
    'bloc_d6_simple.php',
    'bloc_d_advanced_mysql.php',
    'comparison_departements.php',
    'comparison_detailed.php'
];

$appliedCount = 0;
$errors = [];

foreach ($apiFiles as $apiFile) {
    $filepath = __DIR__ . '/' . $apiFile;
    
    if (!file_exists($filepath)) {
        $errors[] = "Fichier non trouvé: $apiFile";
        continue;
    }
    
    try {
        $content = file_get_contents($filepath);
        $originalContent = $content;
        
        // 1. Ajouter l'include de ZoneMapper si pas déjà présent
        if (strpos($content, 'ZoneMapper.php') === false) {
            $content = str_replace(
                "require_once __DIR__ . '/periodes_manager_db.php';",
                "require_once __DIR__ . '/periodes_manager_db.php';\nrequire_once __DIR__ . '/../classes/ZoneMapper.php';",
                $content
            );
            
            // Si pas de periodes_manager_db.php, ajouter après database.php
            if (strpos($content, 'periodes_manager_db.php') === false) {
                $content = str_replace(
                    "require_once __DIR__ . '/../database.php';",
                    "require_once __DIR__ . '/../database.php';\nrequire_once __DIR__ . '/../classes/ZoneMapper.php';",
                    $content
                );
            }
        }
        
        // 2. Remplacer les mappings manuels par ZoneMapper::displayToBase()
        $patterns = [
            // Pattern pour remplacer les mappings manuels
            '/\$zoneMapping\s*=\s*\[[\s\S]*?\];/m' => '// Mapping géré par ZoneMapper',
            '/\$zoneToSearch\s*=\s*\$zoneMapping\[\$zoneMapped\]\s*\?\?\s*\$zoneMapped;/' => '$zoneToSearch = ZoneMapper::displayToBase($zoneMapped);',
            '/\$stmt->execute\(\[\$zoneToSearch\]\);/' => '$stmt->execute([$zoneToSearch]);',
            
            // Pattern pour remplacer les recherches directes de zones
            '/\$stmt\s*=\s*\$pdo->prepare\("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = \?"\);\s*\$stmt->execute\(\[\$zoneMapped\]\);\s*\$id_zone\s*=\s*\$stmt->fetch\(\)\[\'id_zone\'\]\s*\?\?\s*null;/' => '$zoneId = ZoneMapper::getZoneId($zoneMapped, $pdo);',
            
            // Pattern pour remplacer les normalisations manuelles
            '/\$zoneMapped\s*=\s*strtoupper\(trim\(\$zone\)\);/' => '$zoneMapped = ZoneMapper::displayToBase($zone);'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // 3. Mettre à jour les références à $id_zone
        $content = str_replace('$id_zone', '$zoneId', $content);
        
        // 4. Écrire le fichier modifié
        if ($content !== $originalContent) {
            file_put_contents($filepath, $content);
            $appliedCount++;
            echo "✅ Appliqué ZoneMapper à: $apiFile\n";
        } else {
            echo "ℹ️  Aucun changement nécessaire pour: $apiFile\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors du traitement de $apiFile: " . $e->getMessage();
    }
}

// Résumé
echo "\n" . str_repeat("=", 50) . "\n";
echo "RÉSUMÉ DE L'APPLICATION DU ZONEMAPPER\n";
echo str_repeat("=", 50) . "\n";
echo "APIs traitées: " . count($apiFiles) . "\n";
echo "APIs modifiées: $appliedCount\n";
echo "Erreurs: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nERREURS:\n";
    foreach ($errors as $error) {
        echo "❌ $error\n";
    }
}

echo "\n✅ Le ZoneMapper a été appliqué avec succès !\n";
echo "Toutes les APIs utilisent maintenant le mapping centralisé.\n";
?>
