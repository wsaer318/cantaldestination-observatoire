<?php
$_GET = ['annee' => '2023', 'periode' => 'vacances_ete', 'zone' => 'CANTAL', 'limit' => '3', 'debug' => '1'];
error_reporting(0);
ob_start();
include 'api/infographie/infographie_communes_excursion.php';
$output = ob_get_clean();
$json = substr($output, strpos($output, '{'));
$data = json_decode($json, true);

echo "🧪 TEST APPROCHE PAR NOMS (pas d'IDs) :\n";
echo "======================================\n";

if ($data && isset($data['destinations'])) {
    echo "✅ Réponse reçue - " . count($data['destinations']) . " destinations trouvées\n";
    if (count($data['destinations']) > 0) {
        foreach (array_slice($data['destinations'], 0, 3) as $d) {
            printf("🏛️  %-20s | N:%8s | N-1:%8s | Évol:%+6.1f%%\n",
                substr($d['nom_commune'], 0, 19),
                number_format($d['total_visiteurs']),
                number_format($d['total_visiteurs_n1']),
                $d['evolution_pct'] ?? 0
            );
        }
        echo "\n🎉 ✅ APPROCHE PAR NOMS RÉUSSIE !\n";
        echo "🎯 Plus besoin de gérer les IDs différents entre environnements !\n";
    } else {
        echo "❌ Aucune destination trouvée\n";
        if (isset($data['diagnostic'])) {
            echo "🔍 Diagnostic : zone_name=" . $data['diagnostic']['zone_name'] . ", categorie_name=" . $data['diagnostic']['categorie_name'] . "\n";
            echo "📊 Données brutes disponibles : " . $data['diagnostic']['raw_data_available'] . "\n";
        }
    }
} else {
    echo "❌ Erreur dans la réponse\n";
}
?>
