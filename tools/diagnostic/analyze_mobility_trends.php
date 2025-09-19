<?php
/**
 * Analyse détaillée des tendances de mobilité interne 2023-2024
 */

echo "📈 ANALYSE DÉTAILLÉE DES TENDANCES DE MOBILITÉ INTERNE\n";
echo "====================================================\n\n";

// Récupérer les données des deux années
function getMobilityData($year) {
    $url = "http://localhost/fluxvision_fin/api/infographie/infographie_communes_excursion.php?annee={$year}&periode=vacances_ete&zone=CANTAL&limit=50";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);

    $cleanResponse = preg_replace('/^.*?(?=\{)/s', '', $response);
    return json_decode($cleanResponse, true);
}

$data2024 = getMobilityData(2024);
$data2023 = getMobilityData(2023);

$dest2024 = $data2024['destinations'] ?? [];
$dest2023 = $data2023['destinations'] ?? [];

// Créer les maps
$map2024 = [];
foreach ($dest2024 as $dest) {
    $map2024[$dest['nom_commune']] = $dest;
}

$map2023 = [];
foreach ($dest2023 as $dest) {
    $map2023[$dest['nom_commune']] = $dest;
}

// Analyse par catégories
echo "📊 ANALYSE PAR CATÉGORIES\n";
echo "========================\n\n";

// Catégoriser les évolutions
$categories = [
    'forte_croissance' => ['label' => 'Forte Croissance (+20% et +)', 'items' => []],
    'croissance' => ['label' => 'Croissance (+5% à +20%)', 'items' => []],
    'stable' => ['label' => 'Stable (-5% à +5%)', 'items' => []],
    'declin' => ['label' => 'Déclin (-5% à -20%)', 'items' => []],
    'fort_declin' => ['label' => 'Fort Déclin (-20% et -)', 'items' => []],
    'nouveau' => ['label' => 'Nouveaux (+100%)', 'items' => []],
    'disparu' => ['label' => 'Disparus (-100%)', 'items' => []]
];

$allCommunes = array_unique(array_merge(array_keys($map2024), array_keys($map2023)));

foreach ($allCommunes as $commune) {
    $d2024 = $map2024[$commune]['total_visiteurs'] ?? 0;
    $d2023 = $map2023[$commune]['total_visiteurs'] ?? 0;

    if ($d2023 == 0 && $d2024 > 0) {
        $categories['nouveau']['items'][] = ['commune' => $commune, '2024' => $d2024, 'evolution' => 100];
    } elseif ($d2024 == 0 && $d2023 > 0) {
        $categories['disparu']['items'][] = ['commune' => $commune, '2023' => $d2023, 'evolution' => -100];
    } elseif ($d2023 > 0) {
        $evolution = round((($d2024 - $d2023) / $d2023) * 100, 1);

        if ($evolution >= 20) {
            $categories['forte_croissance']['items'][] = ['commune' => $commune, '2024' => $d2024, '2023' => $d2023, 'evolution' => $evolution];
        } elseif ($evolution >= 5) {
            $categories['croissance']['items'][] = ['commune' => $commune, '2024' => $d2024, '2023' => $d2023, 'evolution' => $evolution];
        } elseif ($evolution >= -5) {
            $categories['stable']['items'][] = ['commune' => $commune, '2024' => $d2024, '2023' => $d2023, 'evolution' => $evolution];
        } elseif ($evolution >= -20) {
            $categories['declin']['items'][] = ['commune' => $commune, '2024' => $d2024, '2023' => $d2023, 'evolution' => $evolution];
        } else {
            $categories['fort_declin']['items'][] = ['commune' => $commune, '2024' => $d2024, '2023' => $d2023, 'evolution' => $evolution];
        }
    }
}

// Afficher les catégories
foreach ($categories as $key => $category) {
    if (count($category['items']) > 0) {
        echo "🏆 {$category['label']} (" . count($category['items']) . " destinations)\n";
        echo str_repeat("-", 60) . "\n";

        // Trier par évolution décroissante
        usort($category['items'], function($a, $b) {
            return $b['evolution'] <=> $a['evolution'];
        });

        foreach ($category['items'] as $item) {
            if (isset($item['2023'])) {
                echo "📍 {$item['commune']}: " . number_format($item['2023']) . " → " . number_format($item['2024']) . " ({$item['evolution']}%)\n";
            } else {
                echo "📍 {$item['commune']}: " . number_format($item['2024']) . " visiteurs ({$item['evolution']}%)\n";
            }
        }
        echo "\n";
    }
}

// Analyse par taille de destination
echo "📏 ANALYSE PAR TAILLE DE DESTINATION\n";
echo "====================================\n\n";

$sizeCategories = [
    'petites' => ['label' => 'Petites (< 10k visiteurs)', 'min' => 0, 'max' => 10000, 'items' => []],
    'moyennes' => ['label' => 'Moyennes (10k-50k)', 'min' => 10000, 'max' => 50000, 'items' => []],
    'grandes' => ['label' => 'Grandes (50k-100k)', 'min' => 50000, 'max' => 100000, 'items' => []],
    'tres_grandes' => ['label' => 'Très grandes (>100k)', 'min' => 100000, 'max' => PHP_INT_MAX, 'items' => []]
];

foreach ($allCommunes as $commune) {
    $d2024 = $map2024[$commune]['total_visiteurs'] ?? 0;
    $d2023 = $map2023[$commune]['total_visiteurs'] ?? 0;

    if ($d2024 > 0) {
        foreach ($sizeCategories as $key => &$category) {
            if ($d2024 >= $category['min'] && $d2024 < $category['max']) {
                $evolution = $d2023 > 0 ? round((($d2024 - $d2023) / $d2023) * 100, 1) : 0;
                $category['items'][] = [
                    'commune' => $commune,
                    '2024' => $d2024,
                    '2023' => $d2023,
                    'evolution' => $evolution
                ];
                break;
            }
        }
    }
}

foreach ($sizeCategories as $category) {
    if (count($category['items']) > 0) {
        $total2024 = array_sum(array_column($category['items'], '2024'));
        $total2023 = array_sum(array_column($category['items'], '2023'));
        $avgEvolution = count($category['items']) > 0 ? round(array_sum(array_column($category['items'], 'evolution')) / count($category['items']), 1) : 0;

        echo "📊 {$category['label']} (" . count($category['items']) . " destinations)\n";
        echo "   Total 2024: " . number_format($total2024) . " visiteurs\n";
        echo "   Total 2023: " . number_format($total2023) . " visiteurs\n";
        echo "   Évolution moyenne: " . ($avgEvolution >= 0 ? '+' : '') . "{$avgEvolution}%\n\n";
    }
}

// Analyse des nouveaux marchés
echo "🌟 ANALYSE DES NOUVEAUX MARCHÉS\n";
echo "===============================\n";

$nouveaux = array_filter($categories['nouveau']['items'], function($item) {
    return $item['2024'] > 1000; // Seulement les destinations avec plus de 1000 visiteurs
});

if (count($nouveaux) > 0) {
    echo "Les destinations suivantes ont émergé en 2024 :\n";
    foreach ($nouveaux as $nouveau) {
        echo "✨ {$nouveau['commune']}: " . number_format($nouveau['2024']) . " visiteurs\n";
    }
} else {
    echo "Aucune nouvelle destination majeure n'a émergé en 2024.\n";
}

echo "\n";

// Analyse des pertes importantes
echo "⚠️  ANALYSE DES PERTES IMPORTANTES\n";
echo "=================================\n";

$pertes = array_merge($categories['fort_declin']['items'], $categories['disparu']['items']);
$pertes = array_filter($pertes, function($item) {
    return (isset($item['2023']) && $item['2023'] > 5000) || (isset($item['2024']) && $item['2024'] > 5000);
});

if (count($pertes) > 0) {
    echo "Destinations ayant perdu de l'importance :\n";
    foreach ($pertes as $perte) {
        if (isset($perte['2023'])) {
            echo "📉 {$perte['commune']}: " . number_format($perte['2023']) . " → " . number_format($perte['2024']) . " ({$perte['evolution']}%)\n";
        } else {
            echo "💔 {$perte['commune']}: " . number_format($perte['2023']) . " visiteurs (disparu)\n";
        }
    }
} else {
    echo "Aucune perte importante détectée.\n";
}

echo "\n";

// Recommandations
echo "🎯 RECOMMANDATIONS STRATÉGIQUES\n";
echo "===============================\n";

$forteCroissance = count($categories['forte_croissance']['items']);
$nouveauxCount = count($categories['nouveau']['items']);

if ($forteCroissance > 0) {
    echo "✅ {$forteCroissance} destinations en forte croissance - Opportunités d'investissement\n";
}

if ($nouveauxCount > 0) {
    echo "🌟 {$nouveauxCount} nouveaux marchés émergents - Marchés à conquérir\n";
}

$declinCount = count($categories['fort_declin']['items']) + count($categories['disparu']['items']);
if ($declinCount > 0) {
    echo "⚠️ {$declinCount} destinations en déclin - Nécessité d'actions correctives\n";
}

echo "\n📈 PERSPECTIVES 2025\n";
echo "===================\n";
echo "• Focus sur les destinations en croissance: Cheylade, Thiézac, Riom-ès-Montagnes\n";
echo "• Développement des nouveaux marchés émergents\n";
echo "• Stratégies de relance pour les destinations en déclin\n";
echo "• Renforcement de la promotion touristique globale (+3.6% en 2024)\n";

echo "\n✅ Analyse des tendances terminée\n";
?>
