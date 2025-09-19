<?php
/**
 * Test simple des APIs d'infographie via cURL
 */

echo "🧪 TEST SIMPLE DES APIS D'INFOGRAPHIE\n";
echo "====================================\n\n";

$base_url = "http://localhost/fluxvision_fin/";

$apis = [
    'departements' => 'api/infographie/infographie_departements_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=15',
    'regions' => 'api/infographie/infographie_regions_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=10',
    'pays' => 'api/infographie/infographie_pays_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=5'
];

foreach ($apis as $name => $endpoint) {
    echo "🔍 Test API $name :\n";
    echo str_repeat("-", 20) . "\n";
    
    $url = $base_url . $endpoint;
    echo "📡 URL : $url\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        echo "❌ Erreur de connexion\n";
        
        // Essayer avec plus de détails d'erreur
        $error = error_get_last();
        if ($error) {
            echo "📄 Détail erreur : " . $error['message'] . "\n";
        }
    } else {
        echo "✅ Réponse reçue (" . strlen($result) . " caractères)\n";
        
        // Vérifier si c'est du JSON
        $json = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ JSON valide\n";
            if (is_array($json)) {
                echo "📊 Éléments : " . count($json) . "\n";
            }
        } else {
            echo "❌ Pas du JSON valide\n";
            echo "📄 Début réponse : " . substr($result, 0, 200) . "...\n";
        }
    }
    
    echo "\n";
}

echo "🏁 Test terminé !\n";
?>
