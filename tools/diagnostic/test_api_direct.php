<?php
/**
 * Test direct de l'API infographie_departements_excursionnistes.php
 */

echo "🔍 TEST DIRECT API DÉPARTEMENTS EXCURSIONNISTES\n";
echo "===============================================\n\n";

// Simuler les paramètres GET
$_GET = [
    'annee' => '2024',
    'periode' => 'annee_complete', 
    'zone' => 'HAUTES TERRES',
    'limit' => '15'
];

echo "📊 Paramètres simulés :\n";
foreach ($_GET as $key => $value) {
    echo "   $key = $value\n";
}
echo "\n";

// Capturer la sortie et les erreurs
ob_start();
$error_output = '';

// Rediriger les erreurs vers une variable
set_error_handler(function($severity, $message, $file, $line) use (&$error_output) {
    $error_output .= "Erreur PHP: $message dans $file ligne $line\n";
});

try {
    echo "🚀 Exécution de l'API...\n";
    include __DIR__ . '/../../api/infographie/infographie_departements_excursionnistes.php';
    echo "✅ API exécutée sans exception\n";
} catch (Exception $e) {
    echo "❌ Exception capturée : " . $e->getMessage() . "\n";
    echo "📍 Fichier : " . $e->getFile() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ Erreur fatale : " . $e->getMessage() . "\n";
    echo "📍 Fichier : " . $e->getFile() . "\n"; 
    echo "📍 Ligne : " . $e->getLine() . "\n";
}

// Restaurer le gestionnaire d'erreurs
restore_error_handler();

$output = ob_get_clean();

echo "\n📄 SORTIE DE L'API :\n";
echo "===================\n\n";

if (!empty($error_output)) {
    echo "❌ ERREURS DÉTECTÉES :\n";
    echo $error_output . "\n";
}

if (!empty($output)) {
    echo "📋 Contenu retourné :\n";
    echo substr($output, 0, 500) . "\n";
    
    // Vérifier si c'est du JSON
    $json_data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\n✅ JSON valide\n";
        echo "📊 Type : " . gettype($json_data) . "\n";
        if (is_array($json_data)) {
            echo "📊 Nombre d'éléments : " . count($json_data) . "\n";
        }
    } else {
        echo "\n❌ JSON invalide : " . json_last_error_msg() . "\n";
    }
} else {
    echo "⚠️ Aucune sortie générée\n";
}

// Nettoyer
$_GET = [];

echo "\n🏁 Test terminé !\n";
?>
