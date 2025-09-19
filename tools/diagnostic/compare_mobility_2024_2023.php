<?php
/**
 * Comparaison de la mobilité interne entre 2024 et 2023
 */

echo "📊 COMPARAISON MOBILITÉ INTERNE 2024 vs 2023\n";
echo "===========================================\n\n";

// Fonction pour récupérer les données d'une année
function getMobilityData($year) {
    $url = "http://localhost/fluxvision_fin/api/infographie/infographie_communes_excursion.php?annee={$year}&periode=vacances_ete&zone=CANTAL&limit=20";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error];
    }

    $cleanResponse = preg_replace('/^.*?(?=\{)/s', '', $response);
    $jsonData = json_decode($cleanResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Erreur JSON: ' . json_last_error_msg()];
    }

    return $jsonData;
}

// Récupérer les données pour 2024 et 2023
echo "📥 Récupération des données...\n\n";

$data2024 = getMobilityData(2024);
$data2023 = getMobilityData(2023);

if (isset($data2024['error'])) {
    echo "❌ Erreur 2024: {$data2024['error']}\n";
    $data2024 = ['destinations' => []];
}

if (isset($data2023['error'])) {
    echo "❌ Erreur 2023: {$data2023['error']}\n";
    $data2023 = ['destinations' => []];
}

$dest2024 = $data2024['destinations'] ?? [];
$dest2023 = $data2023['destinations'] ?? [];

echo "✅ Données 2024: " . count($dest2024) . " destinations\n";
echo "✅ Données 2023: " . count($dest2023) . " destinations\n\n";

// Créer une map pour faciliter la comparaison
$map2024 = [];
foreach ($dest2024 as $dest) {
    $map2024[$dest['nom_commune']] = $dest['total_visiteurs'];
}

$map2023 = [];
foreach ($dest2023 as $dest) {
    $map2023[$dest['nom_commune']] = $dest['total_visiteurs'];
}

// Calculer les statistiques globales
$total2024 = array_sum($map2024);
$total2023 = array_sum($map2023);
$evolutionPct = $total2023 > 0 ? round((($total2024 - $total2023) / $total2023) * 100, 1) : 0;

echo "📈 STATISTIQUES GLOBALES\n";
echo "=======================\n";
echo "2024: " . number_format($total2024) . " visiteurs\n";
echo "2023: " . number_format($total2023) . " visiteurs\n";
echo "Évolution: " . ($evolutionPct >= 0 ? '+' : '') . "{$evolutionPct}%\n\n";

// Comparaison détaillée par destination
echo "🏆 COMPARAISON PAR DESTINATION\n";
echo "==============================\n";

// Toutes les destinations uniques
$allCommunes = array_unique(array_merge(array_keys($map2024), array_keys($map2023)));
sort($allCommunes);

$comparison = [];
foreach ($allCommunes as $commune) {
    $visiteurs2024 = $map2024[$commune] ?? 0;
    $visiteurs2023 = $map2023[$commune] ?? 0;
    $evolution = $visiteurs2023 > 0 ? round((($visiteurs2024 - $visiteurs2023) / $visiteurs2023) * 100, 1) : ($visiteurs2024 > 0 ? 100 : 0);

    $comparison[] = [
        'commune' => $commune,
        '2024' => $visiteurs2024,
        '2023' => $visiteurs2023,
        'evolution' => $evolution
    ];
}

// Trier par évolution décroissante
usort($comparison, function($a, $b) {
    return $b['evolution'] <=> $a['evolution'];
});

printf("%-25s %-12s %-12s %-8s\n", "COMMUNE", "2024", "2023", "EVOL.");
echo str_repeat("-", 60) . "\n";

foreach ($comparison as $item) {
    printf("%-25s %-12s %-12s %-8s\n",
        $item['commune'],
        number_format($item['2024']),
        number_format($item['2023']),
        ($item['evolution'] >= 0 ? '+' : '') . $item['evolution'] . '%'
    );
}

echo "\n📊 ANALYSE DE L'ÉVOLUTION\n";
echo "========================\n";

// Statistiques d'évolution
$gains = array_filter($comparison, function($item) { return $item['evolution'] > 0; });
$pertes = array_filter($comparison, function($item) { return $item['evolution'] < 0; });
$stable = array_filter($comparison, function($item) { return $item['evolution'] == 0 && ($item['2024'] > 0 || $item['2023'] > 0); });

echo "📈 Destinations en progression: " . count($gains) . "\n";
echo "📉 Destinations en baisse: " . count($pertes) . "\n";
echo "➡️  Destinations stables: " . count($stable) . "\n\n";

// Top évolutions positives
echo "🎯 TOP 5 PROGRESSIONS\n";
echo "===================\n";
$topGains = array_slice($gains, 0, 5);
foreach ($topGains as $item) {
    echo "📈 {$item['commune']}: " . ($item['evolution'] >= 0 ? '+' : '') . "{$item['evolution']}% ";
    echo "(" . number_format($item['2023']) . " → " . number_format($item['2024']) . ")\n";
}

echo "\n";

// Top évolutions négatives
echo "⚠️  TOP 5 BAISSES\n";
echo "===============\n";
$topLosses = array_slice($pertes, 0, 5);
foreach ($topLosses as $item) {
    echo "📉 {$item['commune']}: {$item['evolution']}% ";
    echo "(" . number_format($item['2023']) . " → " . number_format($item['2024']) . ")\n";
}

echo "\n📋 RÉSUMÉ\n";
echo "=========\n";
if ($evolutionPct > 0) {
    echo "✅ La mobilité interne a augmenté de {$evolutionPct}% entre 2023 et 2024\n";
} elseif ($evolutionPct < 0) {
    echo "❌ La mobilité interne a diminué de " . abs($evolutionPct) . "% entre 2023 et 2024\n";
} else {
    echo "➡️ La mobilité interne est restée stable entre 2023 et 2024\n";
}

echo "\nDestinations analysées: " . count($comparison) . "\n";
echo "Total visiteurs 2024: " . number_format($total2024) . "\n";
echo "Total visiteurs 2023: " . number_format($total2023) . "\n";

echo "\n✅ Analyse terminée\n";
?>
