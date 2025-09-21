﻿<?php
/**
 * Test simple des APIs d'infographie via cURL
 */

echo "ðŸ§ª TEST SIMPLE DES APIS D'INFOGRAPHIE\n";
echo "====================================\n\n";

$base_url = "http://localhost/fluxvision_fin/";

$apis = [
    'departements' => 'api/infographie/infographie_departements_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=15',
    'regions' => 'api/v2/infographie/regions-excursionnistes?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=10',
    'pays' => 'api/infographie/infographie_pays_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=5'
];

foreach ($apis as $name => $endpoint) {
    echo "ðŸ” Test API $name :\n";
    echo str_repeat("-", 20) . "\n";
    
    $url = $base_url . $endpoint;
    echo "ðŸ“¡ URL : $url\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        echo "âŒ Erreur de connexion\n";
        
        // Essayer avec plus de dÃ©tails d'erreur
        $error = error_get_last();
        if ($error) {
            echo "ðŸ“„ DÃ©tail erreur : " . $error['message'] . "\n";
        }
    } else {
        echo "âœ… RÃ©ponse reÃ§ue (" . strlen($result) . " caractÃ¨res)\n";
        
        // VÃ©rifier si c'est du JSON
        $json = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "âœ… JSON valide\n";
            if (is_array($json)) {
                echo "ðŸ“Š Ã‰lÃ©ments : " . count($json) . "\n";
            }
        } else {
            echo "âŒ Pas du JSON valide\n";
            echo "ðŸ“„ DÃ©but rÃ©ponse : " . substr($result, 0, 200) . "...\n";
        }
    }
    
    echo "\n";
}

echo "ðŸ Test terminÃ© !\n";
?>

