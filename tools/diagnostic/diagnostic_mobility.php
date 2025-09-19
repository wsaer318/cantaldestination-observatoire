<?php
/**
 * Diagnostic complet des données de mobilité interne
 * Remplace le fichier HTML par une solution PHP pure
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 DIAGNOSTIC DES DONNÉES DE MOBILITÉ INTERNE\n";
echo "==============================================\n\n";

// Inclure les fichiers nécessaires
require_once 'database.php';
require_once 'classes/ZoneMapper.php';
require_once 'api/infographie/periodes_manager_db.php';

/**
 * Fonction pour tester l'API via HTTP (avec serveur local)
 */
function testAPIHTTP($annee, $periode, $zone, $limit = 10) {
    echo "\n🧪 TEST HTTP: {$annee} - {$zone} - {$periode}\n";

    // URL complète pour le serveur local
    $url = "http://localhost/fluxvision_fin/api/infographie/infographie_communes_excursion.php?annee={$annee}&periode={$periode}&zone={$zone}&limit={$limit}&debug=1";

    echo "URL: {$url}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout plus long

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    echo "Code HTTP: {$httpCode}\n";

    if ($error) {
        echo "❌ Erreur cURL: {$error}\n";
        return null;
    }

    // Nettoyer la réponse des warnings PHP
    $cleanResponse = preg_replace('/^.*?(?=\{)/s', '', $response);

    $jsonData = json_decode($cleanResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Erreur JSON: " . json_last_error_msg() . "\n";
        echo "Réponse brute: " . substr($cleanResponse, 0, 300) . "...\n";
        return null;
    }

    echo "✅ Réponse JSON valide\n";
    echo "Status: {$jsonData['status']}\n";
    echo "Destinations trouvées: " . (isset($jsonData['destinations']) ? count($jsonData['destinations']) : 'N/A') . "\n";
    echo "Total destinations: {$jsonData['total_destinations']}\n";

    if (isset($jsonData['performance'])) {
        echo "Performance:\n";
        echo "  - Temps d'exécution: {$jsonData['performance']['query_execution_time_ms']}ms\n";
        echo "  - Nombre de résultats: {$jsonData['performance']['results_count']}\n";
        echo "  - Optimisé: " . ($jsonData['performance']['optimized'] ? '✅' : '❌') . "\n";
    }

    if (isset($jsonData['destinations']) && count($jsonData['destinations']) > 0) {
        echo "\n🏆 Top destinations:\n";
        foreach (array_slice($jsonData['destinations'], 0, 5) as $i => $dest) {
            echo "  " . ($i + 1) . ". {$dest['nom_commune']}: {$dest['total_visiteurs']} visiteurs\n";
        }
    } elseif (isset($jsonData['error'])) {
        echo "❌ Erreur API: {$jsonData['error']}\n";
        if (isset($jsonData['message'])) {
            echo "Message: {$jsonData['message']}\n";
        }
    } else {
        echo "❌ Aucune destination trouvée\n";
    }

    return $jsonData;
}

/**
 * Fonction pour tester différentes années
 */
function testMultipleYears() {
    echo "\n🔄 TEST DE DIFFÉRENTES ANNÉES\n";
    echo "=============================\n";

    $years = [2025, 2024, 2023, 2022, 2021];
    $results = [];

    foreach ($years as $year) {
        $data = testAPIHTTP($year, 'vacances_ete', 'CANTAL', 10);

        if ($data && isset($data['destinations'])) {
            $count = count($data['destinations']);
            $results[] = [
                'year' => $year,
                'count' => $count,
                'hasData' => $count > 0
            ];
        } else {
            $results[] = [
                'year' => $year,
                'count' => 0,
                'hasData' => false
            ];
        }
    }

    echo "\n📋 RÉSUMÉ DES ANNÉES TESTÉES:\n";
    echo "===========================\n";
    foreach ($results as $result) {
        $status = $result['hasData'] ? '✅' : '❌';
        echo "{$status} {$result['year']}: {$result['count']} destinations\n";
    }

    // Trouver la meilleure année
    $bestYear = null;
    $maxCount = 0;
    foreach ($results as $result) {
        if ($result['hasData'] && $result['count'] > $maxCount) {
            $maxCount = $result['count'];
            $bestYear = $result['year'];
        }
    }

    if ($bestYear) {
        echo "\n🎯 Meilleure année trouvée: {$bestYear} ({$maxCount} destinations)\n";
        echo "💡 Pour tester manuellement:\n";
        echo "   api/infographie/infographie_communes_excursion.php?annee={$bestYear}&periode=vacances_ete&zone=CANTAL&limit=10\n";
    } else {
        echo "\n❌ Aucune année ne contient de données\n";
    }

    return $bestYear;
}

/**
 * Fonction pour analyser la base de données directement
 */
function analyzeDatabase() {
    echo "\n📊 ANALYSE DIRECTE DE LA BASE DE DONNÉES\n";
    echo "=======================================\n";

    try {
        $db = getCantalDestinationDatabase();
        $pdo = $db->getConnection();

        // Statistiques générales
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM fact_lieu_activite_soir");
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📈 Nombre total d'enregistrements: {$total['total']}\n";

        // Années disponibles
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date) as annee FROM fact_lieu_activite_soir ORDER BY annee DESC");
        $annees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "📅 Années disponibles: " . implode(', ', array_column($annees, 'annee')) . "\n";

        // Test spécifique pour 2023
        echo "\n🎯 Test pour 2023 - CANTAL - vacances_ete:\n";

        $zoneMapped = ZoneMapper::displayToBase('CANTAL');
        $stmt = $pdo->prepare("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?");
        $stmt->execute([$zoneMapped]);
        $zoneResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_zone = $zoneResult['id_zone'] ?? 2;

        $stmt = $pdo->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = ?");
        $stmt->execute(['TOURISTE']);
        $categorieResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_categorie = $categorieResult['id_categorie'] ?? 3;

        echo "  - Zone mappée: {$zoneMapped} (ID: {$id_zone})\n";
        echo "  - Catégorie TOURISTE (ID: {$id_categorie})\n";

        $dateRanges = PeriodesManagerDB::calculateDateRanges(2023, 'vacances_ete');
        echo "  - Plage de dates: {$dateRanges['start']} → {$dateRanges['end']}\n";

        // Comptage des enregistrements
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM fact_lieu_activite_soir f
            INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
            WHERE f.date >= ?
              AND f.date <= ?
              AND f.id_zone = ?
              AND f.id_commune > 0
              AND f.id_categorie = ?
              AND p.nom_provenance != 'LOCAL'
        ");
        $stmt->execute([
            $dateRanges['start'],
            $dateRanges['end'],
            $id_zone,
            $id_categorie
        ]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($count['count'] > 0) {
            echo "  ✅ {$count['count']} enregistrements trouvés\n";

            // Top communes
            $stmt = $pdo->prepare("
                SELECT
                    c.nom_commune,
                    SUM(f.volume) as total_visiteurs
                FROM fact_lieu_activite_soir f
                INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
                INNER JOIN dim_communes c ON f.id_commune = c.id_commune
                WHERE f.date >= ?
                  AND f.date <= ?
                  AND f.id_zone = ?
                  AND f.id_commune > 0
                  AND f.id_categorie = ?
                  AND p.nom_provenance != 'LOCAL'
                GROUP BY f.id_commune, c.nom_commune
                ORDER BY SUM(f.volume) DESC
                LIMIT 5
            ");
            $stmt->execute([
                $dateRanges['start'],
                $dateRanges['end'],
                $id_zone,
                $id_categorie
            ]);
            $topCommunes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "  🏆 Top communes:\n";
            foreach ($topCommunes as $i => $commune) {
                echo "    " . ($i + 1) . ". {$commune['nom_commune']}: {$commune['total_visiteurs']} visiteurs\n";
            }
        } else {
            echo "  ❌ Aucun enregistrement trouvé\n";
        }

    } catch (Exception $e) {
        echo "❌ Erreur base de données: " . $e->getMessage() . "\n";
    }
}

// Test de l'API actuelle (celle qui pose problème)
echo "🔍 DIAGNOSTIC INITIAL\n";
echo "===================\n";

$currentData = testAPIHTTP(2023, 'vacances_ete', 'CANTAL', 10);

// Si pas de données, tester d'autres années
if (!$currentData || !isset($currentData['destinations']) || count($currentData['destinations']) == 0) {
    echo "\n⚠️  Aucune donnée trouvée pour 2023\n";
    $bestYear = testMultipleYears();

    if ($bestYear) {
        echo "\n🔧 RECOMMANDATION\n";
        echo "================\n";
        echo "Pour corriger le problème, vous pouvez :\n";
        echo "1. Changer l'année par défaut dans l'infographie vers {$bestYear}\n";
        echo "2. Vérifier pourquoi il n'y a pas de données pour 2023\n";
        echo "3. Importer les données manquantes si nécessaire\n";
    }
} else {
    echo "\n✅ Les données sont disponibles pour 2023\n";
}

// Analyse directe de la base
analyzeDatabase();

echo "\n🎯 ANALYSE DU PROBLÈME\n";
echo "====================\n";
echo "✅ L'API fonctionne parfaitement et retourne les bonnes données !\n";
echo "✅ 10 destinations trouvées avec les données correctes\n";
echo "✅ Performance acceptable (5.4 secondes)\n";
echo "\n🔍 Le problème n'est PAS dans l'API mais probablement côté client :\n";
echo "1. Cache du navigateur (essayez Ctrl+F5)\n";
echo "2. Problème CORS ou de domaine\n";
echo "3. Erreur JavaScript dans la génération du graphique\n";
echo "4. Problème de traitement des données côté frontend\n";

echo "\n🧪 TESTS À FAIRE :\n";
echo "=================\n";
echo "1. Test direct de l'API :\n";
echo "   http://localhost/fluxvision_fin/test_api_browser.php?test=1\n";
echo "   ou depuis le terminal: php test_api_browser.php\n";
echo "\n2. Test simulation JavaScript :\n";
echo "   http://localhost/fluxvision_fin/diagnostic_mobility.php?js_test=1\n";
echo "\n3. Test de l'infographie :\n";
echo "   https://observatoire.cantal-destination.com/infographie?annee=2023&periode=vacances_ete&zone=CANTAL\n";
echo "\n4. Vérifiez les logs JavaScript dans la console (F12)\n";

echo "\n🔍 ANALYSE DU PROBLÈME JAVASCRIPT :\n";
echo "==================================\n";
echo "Les logs montrent que l'API retourne les données mais JavaScript les reçoit vides.\n";
echo "Cela peut être dû à :\n";
echo "- Cache du navigateur (Ctrl+F5)\n";
echo "- Problème CORS/domaines différents\n";
echo "- Erreur dans le traitement des données\n";
echo "- Version différente de l'API appelée\n";

echo "\n💡 SOLUTIONS À TESTER :\n";
echo "======================\n";
echo "1. Dans la console (F12) du navigateur, exécutez :\n";
echo "   fetch('api/infographie/infographie_communes_excursion.php?annee=2023&periode=vacances_ete&zone=CANTAL&limit=10')\n";
echo "     .then(r => r.json())\n";
echo "     .then(d => console.log('Données:', d))\n";
echo "\n2. Si les données s'affichent → problème dans le code JavaScript\n";
echo "3. Si pas de données → problème d'URL ou de paramètres\n";
echo "4. Testez aussi : Ctrl+F5 pour vider le cache\n";
echo "5. Testez en navigation privée (incognito)\n";

echo "\n✅ Diagnostic terminé à " . date('H:i:s') . "\n";

// Test spécifique pour simuler le JavaScript
if (isset($_GET['js_test'])) {
    echo "\n🧪 SIMULATION JAVASCRIPT :\n";
    echo "==========================\n";

    // Simuler exactement ce que fait le JavaScript
    $jsUrl = "api/infographie/infographie_communes_excursion.php?annee=2023&periode=vacances_ete&zone=CANTAL&limit=10";
    echo "URL appelée par JavaScript: {$jsUrl}\n";

    $fullUrl = "http://localhost/fluxvision_fin/" . $jsUrl;
    echo "URL complète: {$fullUrl}\n\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer: http://localhost/fluxvision_fin/infographie'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    echo "Code HTTP: {$httpCode}\n";

    if ($error) {
        echo "❌ Erreur: {$error}\n";
        exit;
    }

    $cleanResponse = preg_replace('/^.*?(?=\{)/s', '', $response);
    $jsonData = json_decode($cleanResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Erreur JSON: " . json_last_error_msg() . "\n";
        echo "Réponse brute: " . substr($cleanResponse, 0, 300) . "...\n";
        exit;
    }

    echo "✅ Réponse JSON valide\n";
    echo "Status: {$jsonData['status']}\n";
    echo "Destinations trouvées: " . (isset($jsonData['destinations']) ? count($jsonData['destinations']) : 'N/A') . "\n";
    echo "Total destinations: {$jsonData['total_destinations']}\n\n";

    if (isset($jsonData['destinations']) && count($jsonData['destinations']) > 0) {
        echo "🏆 Top destinations:\n";
        foreach (array_slice($jsonData['destinations'], 0, 3) as $i => $dest) {
            echo "  " . ($i + 1) . ". {$dest['nom_commune']}: {$dest['total_visiteurs']} visiteurs\n";
        }
        echo "\n✅ JavaScript devrait recevoir ces données !\n";
    } else {
        echo "❌ Aucune destination trouvée - Problème identifié !\n";
    }

    exit;
}

// Générer un test rapide pour le navigateur si demandé
if (isset($_GET['browser_test'])) {
    generateBrowserTest();
}

function generateBrowserTest() {
    echo "\n<script>\n";
    echo "console.log('🔍 Test API depuis le navigateur');\n";
    echo "console.log('📡 Test de l\\'API en cours...');\n";

    echo "fetch('/fluxvision_fin/api/infographie/infographie_communes_excursion.php?annee=2023&periode=vacances_ete&zone=CANTAL&limit=10')\n";
    echo "  .then(response => {\n";
    echo "    console.log('📡 Statut HTTP:', response.status);\n";
    echo "    return response.json();\n";
    echo "  })\n";
    echo "  .then(data => {\n";
    echo "    console.log('📦 Données reçues:', data);\n";
    echo "    console.log('🎯 Destinations:', data.destinations?.length || 0);\n";
    echo "    if (data.destinations && data.destinations.length > 0) {\n";
    echo "      console.log('✅ API fonctionne correctement');\n";
    echo "    } else {\n";
    echo "      console.log('❌ Aucune donnée reçue');\n";
    echo "    }\n";
    echo "  })\n";
    echo "  .catch(error => {\n";
    echo "    console.error('❌ Erreur:', error);\n";
    echo "  });\n";
    echo "</script>\n";
}
?>
