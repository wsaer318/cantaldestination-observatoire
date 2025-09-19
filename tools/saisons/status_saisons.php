<?php
/**
 * Script de statut rapide des saisons astronomiques
 */

require_once 'config/database.php';
require_once 'classes/Database.php';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Statut rapide
    $stmt = $db->query("SELECT COUNT(*) as total FROM dim_saisons");
    $totalSaisons = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT MAX(updated_at) as derniere_maj FROM dim_saisons");
    $derniereMaj = $stmt->fetch()['derniere_maj'];
    
    $stmt = $db->query("SELECT COUNT(*) as liees FROM dim_periodes WHERE id_saison IS NOT NULL");
    $periodesLiees = $stmt->fetch()['liees'];
    
    echo "📊 STATUT RAPIDE SAISONS ASTRONOMIQUES\n";
    echo "Saisons: $totalSaisons | Périodes liées: $periodesLiees | Dernière MAJ: $derniereMaj\n";
    
    if ($totalSaisons >= 20) {
        echo "✅ Système opérationnel\n";
    } else {
        echo "⚠️ Système nécessite une mise à jour\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>