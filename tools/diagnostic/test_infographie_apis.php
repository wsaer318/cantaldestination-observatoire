<?php
/**
 * Test des APIs d'infographie pour diagnostiquer les erreurs 500
 */

echo "🔍 TEST DES APIS D'INFOGRAPHIE POUR HAUTES TERRES\n";
echo "=================================================\n\n";

// URLs à tester
$apis_to_test = [
    'departements' => 'api/infographie/infographie_departements_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=15',
    'regions' => 'api/infographie/infographie_regions_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES%20TERRES&limit=10',
    'pays' => 'api/infographie/infographie_pays_excursionnistes.php?annee=2024&periode=annee_complete&zone=HAUTES+TERRES&limit=5'
];

$base_url = 'http://localhost/fluxvision_fin/';

foreach ($apis_to_test as $api_name => $api_path) {
    echo "🧪 Test API $api_name :\n";
    echo "======================\n\n";

    $full_url = $base_url . $api_path;
    echo "📡 URL : $full_url\n";

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

    echo "📊 Code HTTP : $http_code\n";

    if ($http_code == 200) {
        echo "✅ API fonctionne\n";
        
        // Vérifier si c'est du JSON valide
        $json_data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Réponse JSON valide\n";
            echo "📋 Nombre d'éléments : " . (is_array($json_data) ? count($json_data) : 'N/A') . "\n";
        } else {
            echo "❌ Réponse n'est pas du JSON valide\n";
            echo "📄 Début de la réponse : " . substr($body, 0, 200) . "...\n";
        }
    } else {
        echo "❌ Erreur HTTP $http_code\n";
        echo "📄 Réponse d'erreur :\n";
        echo substr($body, 0, 1000) . "\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

// Test direct du fichier PHP pour voir les erreurs
echo "🔧 TEST DIRECT DES FICHIERS PHP :\n";
echo "=================================\n\n";

$php_files = [
    'api/infographie/infographie_departements_excursionnistes.php',
    'api/infographie/infographie_regions_excursionnistes.php',
    'api/infographie/infographie_pays_excursionnistes.php'
];

foreach ($php_files as $php_file) {
    echo "📄 Test de $php_file :\n";

    if (file_exists($php_file)) {
        echo "✅ Fichier existe\n";

        // Vérifier la syntaxe PHP
        $syntax_check = shell_exec("C:\\xampp\\php\\php.exe -l \"$php_file\" 2>&1");
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            echo "✅ Syntaxe PHP correcte\n";
        } else {
            echo "❌ Erreur de syntaxe PHP :\n";
            echo $syntax_check . "\n";
        }

        // Essayer d'exécuter le fichier avec les paramètres
        echo "🔧 Test d'exécution avec paramètres...\n";
        
        // Simuler les paramètres GET
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
            echo "❌ Erreur d'exécution : " . $e->getMessage() . "\n";
        } catch (Error $e) {
            $error_occurred = true;
            echo "❌ Erreur fatale : " . $e->getMessage() . "\n";
        }

        $output = ob_get_clean();
        
        if (!$error_occurred) {
            echo "✅ Exécution réussie\n";
            if (!empty($output)) {
                echo "📄 Sortie (200 premiers caractères) : " . substr($output, 0, 200) . "...\n";
            }
        }

        // Nettoyer les paramètres GET
        $_GET = [];

    } else {
        echo "❌ Fichier n'existe pas\n";
    }

    echo "\n";
}

echo "🏁 Test terminé !\n";
?>
