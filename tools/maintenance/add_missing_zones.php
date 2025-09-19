<?php
/**
 * Script pour ajouter les zones manquantes dans dim_zones_observation
 */

// Configuration de base de données
require_once __DIR__ . '/config/database.php';

try {
    $dbConfig = DatabaseConfig::getConfig();
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Zones à ajouter
    $zones_to_add = [
        'HAUT CANTAL',
        'HAUTES TERRES',
        'LIORAN',
        'PAYS D\'AURILLAC'
    ];

    echo "🔍 Vérification des zones existantes...\n";

    // Vérifier quelles zones existent déjà
    $existing_zones = [];
    $stmt = $pdo->query("SELECT nom_zone FROM dim_zones_observation");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_zones[] = strtoupper($row['nom_zone']);
    }

    echo "📋 Zones existantes: " . implode(', ', $existing_zones) . "\n";

    // Identifier les zones à ajouter
    $zones_to_insert = [];
    foreach ($zones_to_add as $zone) {
        if (!in_array($zone, $existing_zones)) {
            $zones_to_insert[] = $zone;
        }
    }

    if (empty($zones_to_insert)) {
        echo "✅ Toutes les zones existent déjà !\n";
        exit(0);
    }

    echo "📝 Zones à ajouter: " . implode(', ', $zones_to_insert) . "\n";

    // Trouver le prochain ID disponible
    $stmt = $pdo->query("SELECT MAX(id_zone) as max_id FROM dim_zones_observation");
    $max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'];
    $next_id = $max_id + 1;

    // Insérer les nouvelles zones
    $stmt = $pdo->prepare("INSERT INTO dim_zones_observation (id_zone, nom_zone) VALUES (?, ?)");

    foreach ($zones_to_insert as $zone) {
        $stmt->execute([$next_id, $zone]);
        echo "✅ Zone ajoutée: $zone (ID: $next_id)\n";
        $next_id++;
    }

    echo "\n🎉 Toutes les zones ont été ajoutées avec succès !\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>