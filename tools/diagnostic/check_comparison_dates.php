<?php
/**
 * Vérifier les dates utilisées pour la comparaison 2025 vs 2024
 */

echo "🔍 VÉRIFICATION DES DATES POUR 2025 vs 2024\n";
echo "===========================================\n\n";

require_once 'database.php';
require_once 'api/infographie/periodes_manager_db.php';

// Dates pour 2025
$dates2025 = PeriodesManagerDB::calculateDateRanges(2025, 'vacances_ete');
echo "📅 2025 - vacances_ete :\n";
echo "  Début: {$dates2025['start']}\n";
echo "  Fin: {$dates2025['end']}\n\n";

// Dates pour 2024 (année précédente)
$dates2024 = PeriodesManagerDB::calculateDateRanges(2024, 'vacances_ete');
echo "📅 2024 - vacances_ete :\n";
echo "  Début: {$dates2024['start']}\n";
echo "  Fin: {$dates2024['end']}\n\n";

// Vérifier les données
$db = getCantalDestinationDatabase();
$pdo = $db->getConnection();

// Données pour 2025
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM fact_lieu_activite_soir WHERE date >= ? AND date <= ? AND id_zone = 2 AND id_categorie = 3 AND id_commune > 0");
$stmt->execute([$dates2025['start'], $dates2025['end']]);
$count2025 = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📊 DONNÉES 2025 :\n";
echo "  Enregistrements: {$count2025['count']}\n\n";

// Données pour 2024
$stmt->execute([$dates2024['start'], $dates2024['end']]);
$count2024 = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📊 DONNÉES 2024 :\n";
echo "  Enregistrements: {$count2024['count']}\n\n";

// Vérifier les top communes pour 2024
if ($count2024['count'] > 0) {
    echo "🏆 TOP COMMUNES 2024 :\n";
    $stmt = $pdo->prepare("
        SELECT
            c.nom_commune,
            SUM(f.volume) as total_visiteurs
        FROM fact_lieu_activite_soir f
        INNER JOIN dim_provenances p ON f.id_provenance = p.id_provenance
        INNER JOIN dim_communes c ON f.id_commune = c.id_commune
        WHERE f.date >= ? AND f.date <= ?
          AND f.id_zone = 2
          AND f.id_commune > 0
          AND f.id_categorie = 3
          AND p.nom_provenance != 'LOCAL'
        GROUP BY f.id_commune, c.nom_commune
        ORDER BY SUM(f.volume) DESC
        LIMIT 5
    ");
    $stmt->execute([$dates2024['start'], $dates2024['end']]);
    $top2024 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($top2024 as $i => $commune) {
        echo "  " . ($i + 1) . ". {$commune['nom_commune']}: {$commune['total_visiteurs']} visiteurs\n";
    }
    echo "\n";
}

echo "🎯 CONCLUSION :\n";
if ($count2024['count'] > 0) {
    echo "✅ Il y a des données pour 2024 - La comparaison devrait fonctionner\n";
    echo "💡 Le problème pourrait être dans la correspondance des communes\n";
} else {
    echo "❌ Pas de données pour 2024 - C'est pourquoi total_visiteurs_n1 = 0\n";
    echo "🔧 Il faut vérifier si les périodes existent dans dim_periodes\n";
}

// Vérifier les périodes dans la base
echo "\n📋 PÉRIODES DISPONIBLES :\n";
$stmt = $pdo->prepare("SELECT DISTINCT annee, nom_periode, date_debut, date_fin FROM dim_periodes WHERE nom_periode LIKE '%ete%' ORDER BY annee DESC, date_debut");
$stmt->execute();
$periodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($periodes as $periode) {
    echo "  {$periode['annee']} - {$periode['nom_periode']}: {$periode['date_debut']} → {$periode['date_fin']}\n";
}
?>
