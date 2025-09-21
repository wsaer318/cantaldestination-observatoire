﻿<?php
/**
 * Test des APIs d'infographie pour diagnostiquer les erreurs 500
 */

echo "ðŸ” TEST DES APIS D'INFOGRAPHIE POUR HAUTES TERRES\n";
echo "=================================================\n\n";

// URLs Ã  tester
$apis_to_test = [
    'departements' => 'api/infographie/infographie_departements_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=15',
    'regions' => 'api/v2/infographie/regions-excursionnistes?annee=2024&periode=annee_complete&zone=HAUTES%20TERRES&limit=10',
    'pays' => 'api/infographie/infographie_pays_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=5'
];

$base_url = 'http://localhost/fluxvision_fin/';

foreach ($apis_to_test as $api_name => $api_path) {
    echo "ðŸ§ª Test API $api_name :\n";
    echo "======================\n\n";

    $full_url = $base_url . $api_path;
    echo "ðŸ“¡ URL : $full_url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    echo "ðŸ“Š Code HTTP : $http_code\n";

    if ($http_code == 200) {
        echo "âœ… API fonctionne\n";
        
        // VÃ©rifier si c'est du JSON valide
        $json_data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "âœ… RÃ©ponse JSON valide\n";
            echo "ðŸ“‹ Nombre d'Ã©lÃ©ments : " . (is_array($json_data) ? count($json_data) : 'N/A') . "\n";
        } else {
            echo "âŒ RÃ©ponse n'est pas du JSON valide\n";
            echo "ðŸ“„ DÃ©but de la rÃ©ponse : " . substr($body, 0, 200) . "...\n";
        }
    } else {
        echo "âŒ Erreur HTTP $http_code\n";
        echo "ðŸ“„ RÃ©ponse d'erreur :\n";
        echo substr($body, 0, 1000) . "\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

// Test direct du fichier PHP pour voir les erreurs
echo "ðŸ”§ TEST DIRECT DES FICHIERS PHP :\n";
echo "=================================\n\n";

$php_files = [
    'api/infographie/infographie_departements_excursionnistes.php',
    'api/v2/infographie/regions-excursionnistes',
    'api/infographie/infographie_pays_excursionnistes.php'
];

foreach ($php_files as $php_file) {
    echo "ðŸ“„ Test de $php_file :\n";

    if (file_exists($php_file)) {
        echo "âœ… Fichier existe\n";

        // VÃ©rifier la syntaxe PHP
        $syntax_check = shell_exec("C:\\xampp\\php\\php.exe -l \"$php_file\" 2>&1");
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            echo "âœ… Syntaxe PHP correcte\n";
        } else {
            echo "âŒ Erreur de syntaxe PHP :\n";
            echo $syntax_check . "\n";
        }

        // Essayer d'exÃ©cuter le fichier avec les paramÃ¨tres
        echo "ðŸ”§ Test d'exÃ©cution avec paramÃ¨tres...\n";
        
        // Simuler les paramÃ¨tres GET
        $_GET = [
            'annee' => '2024',
            'periode' => 'annee_complete',
            'zone' => 'HAUTES TERRES',
            'limit' => '15'
        ];

        ob_start();
        $error_occurred = false;
        
        try {
            include $php_file;
        } catch (Exception $e) {
            $error_occurred = true;
            echo "âŒ Erreur d'exÃ©cution : " . $e->getMessage() . "\n";
        } catch (Error $e) {
            $error_occurred = true;
            echo "âŒ Erreur fatale : " . $e->getMessage() . "\n";
        }

        $output = ob_get_clean();
        
        if (!$error_occurred) {
            echo "âœ… ExÃ©cution rÃ©ussie\n";
            if (!empty($output)) {
                echo "ðŸ“„ Sortie (200 premiers caractÃ¨res) : " . substr($output, 0, 200) . "...\n";
            }
        }

        // Nettoyer les paramÃ¨tres GET
        $_GET = [];

    } else {
        echo "âŒ Fichier n'existe pas\n";
    }

    echo "\n";
}

echo "ðŸ Test terminÃ© !\n";
?>

