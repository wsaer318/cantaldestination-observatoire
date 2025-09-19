<?php
require_once 'config/database.php';
require_once 'classes/Database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "=== MIGRATION : AJOUT DE LA COLONNE SAISON ===\n\n";
    
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM dim_periodes LIKE 'saison'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  La colonne 'saison' existe déjà.\n";
        echo "Voulez-vous la réinitialiser ? (o/N): ";
        $handle = fopen("php://stdin", "r");
        $response = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($response) !== 'o') {
            echo "Migration annulée.\n";
            exit;
        }
        
        // Remettre à NULL toutes les valeurs
        $pdo->exec("UPDATE dim_periodes SET saison = NULL");
        echo "✅ Valeurs de saison réinitialisées.\n\n";
    } else {
        // Ajouter la colonne
        echo "📋 Ajout de la colonne 'saison'...\n";
        $pdo->exec("ALTER TABLE dim_periodes ADD COLUMN saison VARCHAR(50) NULL AFTER nom_periode");
        echo "✅ Colonne 'saison' ajoutée avec succès.\n\n";
    }
    
    // Appliquer les mappings
    echo "📋 Application des mappings saison...\n";
    
    $mappings = [
        'hiver' => ['vacances_hiver', 'vacances_noel'],
        'printemps' => ['weekend_paques', 'vacances_paques', 'weekend_ascension', 'weekend_pentecote', 'pont_de_mai'],
        'ete' => ['vacances_ete'],
        'automne' => ['vacances_toussaint']
    ];
    
    foreach ($mappings as $saison => $codes) {
        foreach ($codes as $code) {
            $stmt = $pdo->prepare("UPDATE dim_periodes SET saison = ? WHERE code_periode = ?");
            $result = $stmt->execute([$saison, $code]);
            $affectedRows = $stmt->rowCount();
            echo "  → $code → $saison ($affectedRows enregistrements)\n";
        }
    }
    
    echo "\n=== RÉSULTATS ===\n";
    
    // Statistiques par saison
    echo "\n📊 Répartition par saison :\n";
    $stmt = $pdo->query("
        SELECT saison, COUNT(*) as nb_periodes, GROUP_CONCAT(DISTINCT code_periode) as codes_periodes 
        FROM dim_periodes 
        WHERE saison IS NOT NULL 
        GROUP BY saison 
        ORDER BY saison
    ");
    
    while ($row = $stmt->fetch()) {
        echo "  {$row['saison']}: {$row['nb_periodes']} périodes ({$row['codes_periodes']})\n";
    }
    
    // Périodes sans saison
    echo "\n⚠️  Périodes sans saison assignée :\n";
    $stmt = $pdo->query("
        SELECT code_periode, nom_periode, COUNT(*) as nb_occurrences
        FROM dim_periodes 
        WHERE saison IS NULL 
        GROUP BY code_periode, nom_periode
    ");
    
    $hasUnassigned = false;
    while ($row = $stmt->fetch()) {
        $hasUnassigned = true;
        echo "  - {$row['code_periode']} ({$row['nom_periode']}) : {$row['nb_occurrences']} occurrences\n";
    }
    
    if (!$hasUnassigned) {
        echo "  ✅ Toutes les périodes ont une saison assignée !\n";
    }
    
    echo "\n=== MIGRATION TERMINÉE ===\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>