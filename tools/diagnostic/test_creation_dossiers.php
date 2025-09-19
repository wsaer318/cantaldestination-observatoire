<?php
/**
 * Test de création automatique des dossiers
 */

echo "🧪 TEST DE CRÉATION AUTOMATIQUE DES DOSSIERS\n";
echo "============================================\n\n";

// Simuler la logique de check_import_progress.php
$progress_file = __DIR__ . '/../../data/temp/temp_import_progress.json';
$log_file = __DIR__ . '/../../data/logs/temp_tables_update.log';

// Créer les dossiers s'ils n'existent pas
$progress_dir = dirname($progress_file);
$log_dir = dirname($log_file);

echo "📁 Dossier de progression : $progress_dir\n";
if (!is_dir($progress_dir)) {
    if (mkdir($progress_dir, 0755, true)) {
        echo "   ✅ Créé avec succès\n";
    } else {
        echo "   ❌ Échec de création\n";
    }
} else {
    echo "   ✅ Existe déjà\n";
}

echo "\n📝 Dossier de logs : $log_dir\n";
if (!is_dir($log_dir)) {
    if (mkdir($log_dir, 0755, true)) {
        echo "   ✅ Créé avec succès\n";
    } else {
        echo "   ❌ Échec de création\n";
    }
} else {
    echo "   ✅ Existe déjà\n";
}

// Tester la création d'un fichier de test
echo "\n📄 Test de création de fichier :\n";
$test_content = json_encode([
    'test' => 'creation_dossier_auto',
    'timestamp' => time(),
    'message' => 'Test réussi'
], JSON_PRETTY_PRINT);

if (file_put_contents($progress_file . '.test', $test_content)) {
    echo "   ✅ Fichier de test créé : " . basename($progress_file) . ".test\n";
    echo "   📊 Contenu : " . substr($test_content, 0, 50) . "...\n";

    // Supprimer le fichier de test
    unlink($progress_file . '.test');
    echo "   🗑️ Fichier de test supprimé\n";
} else {
    echo "   ❌ Échec de création du fichier de test\n";
}

// Vérifier les permissions
echo "\n🔐 Vérification des permissions :\n";
echo "   📁 Progress dir permissions : " . substr(sprintf('%o', fileperms($progress_dir)), -4) . "\n";
echo "   📝 Log dir permissions : " . substr(sprintf('%o', fileperms($log_dir)), -4) . "\n";

echo "\n🏁 Test terminé !\n";
?>
