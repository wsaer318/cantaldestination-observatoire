<?php
/**
 * Script pour vider la table des saisons et recommencer
 */

require_once 'config/database.php';
require_once 'classes/Database.php';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    echo "🗑️ REMISE À ZÉRO DES SAISONS ASTRONOMIQUES\n";
    echo "=" . str_repeat("=", 50) . "\n\n";
    
    // 1. Vérifier l'état actuel
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_saisons");
    $currentCount = $stmt->fetch()['nb'];
    echo "État actuel: $currentCount saisons en base\n";
    
    // 2. Supprimer les liaisons existantes
    echo "\n1️⃣ Suppression des liaisons existantes...\n";
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_periodes WHERE id_saison IS NOT NULL");
    $linkedPeriods = $stmt->fetch()['nb'];
    echo "   Périodes actuellement liées: $linkedPeriods\n";
    
    $db->exec("UPDATE dim_periodes SET id_saison = NULL");
    echo "   ✓ Toutes les liaisons supprimées\n";
    
    // 3. Vider la table des saisons
    echo "\n2️⃣ Vidage de la table dim_saisons...\n";
    $db->exec("DELETE FROM dim_saisons");
    $db->exec("ALTER TABLE dim_saisons AUTO_INCREMENT = 1");
    echo "   ✓ Table vidée et compteur remis à zéro\n";
    
    // 4. Vérification
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_saisons");
    $finalCount = $stmt->fetch()['nb'];
    
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_periodes WHERE id_saison IS NOT NULL");
    $finalLinked = $stmt->fetch()['nb'];
    
    echo "\n3️⃣ Vérification finale\n";
    echo "   Saisons restantes: $finalCount\n";
    echo "   Liaisons restantes: $finalLinked\n";
    
    if ($finalCount == 0 && $finalLinked == 0) {
        echo "   ✅ Remise à zéro réussie!\n";
    } else {
        echo "   ⚠️ Remise à zéro incomplète\n";
    }
    
    echo "\n🎯 PRÊT POUR UNE NOUVELLE IMPORTATION\n";
    echo "Exécutez maintenant: php update_saisons.php\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>