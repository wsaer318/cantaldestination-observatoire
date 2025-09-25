<?php
/**
 * Test de l'API check_import_progress.php
 * Vérifie que l'API fonctionne correctement
 */

echo "🧪 TEST DE L'API CHECK_IMPORT_PROGRESS\n";
echo "=====================================\n\n";

// URL de l'API à tester
$api_url = 'http://localhost' . getBasePath() . '/tools/import/check_import_progress.php';

echo "📡 Test de l'API : $api_url\n\n";

// Test avec curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

echo "📊 Résultat du test :\n";
echo "===================\n\n";
echo "📞 Code HTTP : $http_code\n";
echo "📝 Content-Type : $content_type\n";

if ($error) {
    echo "❌ Erreur cURL : $error\n";
} else {
    echo "✅ Requête réussie\n\n";

    // Vérifier si la réponse est du JSON valide
    $json_data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Réponse JSON valide\n\n";
        echo "📋 Contenu de la réponse :\n";
        echo json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Réponse n'est pas du JSON valide\n\n";
        echo "📄 Contenu brut (premiers 500 caractères) :\n";
        echo substr($response, 0, 500) . "\n";
    }
}

echo "\n🎯 Vérifications supplémentaires :\n";
echo "=================================\n\n";

// Vérifier que les fichiers de progression existent
$progress_file = __DIR__ . '/../../data/temp/temp_import_progress.json';
$log_file = __DIR__ . '/../../data/logs/temp_tables_update.log';

echo "📁 Fichier de progression : $progress_file\n";
if (file_exists($progress_file)) {
    echo "   ✅ Existe (" . filesize($progress_file) . " octets)\n";
    $progress_content = json_decode(file_get_contents($progress_file), true);
    if ($progress_content) {
        echo "   📊 Contenu : " . json_encode($progress_content, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "   ⚠️ N'existe pas (normal si aucun import en cours)\n";
}

echo "\n📝 Fichier de log : $log_file\n";
if (file_exists($log_file)) {
    echo "   ✅ Existe (" . filesize($log_file) . " octets)\n";
    $log_lines = file($log_file);
    $last_lines = array_slice($log_lines, -5);
    echo "   📋 Dernières lignes :\n";
    foreach ($last_lines as $line) {
        echo "      " . trim($line) . "\n";
    }
} else {
    echo "   ⚠️ N'existe pas (normal si aucun import n'a été fait)\n";
}

echo "\n🏁 Test terminé !\n";
?>
