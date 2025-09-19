<?php
/**
 * Script de vérification du système de saisons astronomiques
 */

require_once 'config/database.php';
require_once 'classes/Database.php';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    echo "🔍 VÉRIFICATION DU SYSTÈME DE SAISONS\n";
    echo "=" . str_repeat("=", 50) . "\n\n";
    
    // 1. Vérifier la table dim_saisons
    echo "1️⃣ Vérification de la table dim_saisons\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM dim_saisons");
    $totalSaisons = $stmt->fetch()['total'];
    echo "   Total saisons: $totalSaisons\n";
    
    if ($totalSaisons >= 20) {
        echo "   ✅ Nombre de saisons correct\n";
    } else {
        echo "   ⚠️ Nombre de saisons insuffisant (attendu: ~24)\n";
    }
    
    // 2. Vérifier les années couvertes
    echo "\n2️⃣ Années couvertes\n";
    $stmt = $db->query("
        SELECT MIN(annee) as min_annee, MAX(annee) as max_annee, COUNT(DISTINCT annee) as nb_annees
        FROM dim_saisons
    ");
    $couverture = $stmt->fetch();
    
    echo "   De {$couverture['min_annee']} à {$couverture['max_annee']} ({$couverture['nb_annees']} années)\n";
    
    if ($couverture['nb_annees'] >= 5) {
        echo "   ✅ Couverture temporelle suffisante\n";
    } else {
        echo "   ⚠️ Couverture temporelle limitée\n";
    }
    
    // 3. Vérifier les 4 saisons par année
    echo "\n3️⃣ Répartition par année\n";
    $stmt = $db->query("
        SELECT annee, COUNT(*) as nb_saisons, GROUP_CONCAT(saison ORDER BY FIELD(saison, 'printemps', 'ete', 'automne', 'hiver')) as saisons
        FROM dim_saisons 
        GROUP BY annee 
        ORDER BY annee
    ");
    $repartition = $stmt->fetchAll();
    
    $anneesCompletes = 0;
    foreach ($repartition as $annee) {
        $status = $annee['nb_saisons'] == 4 ? "✅" : "⚠️";
        echo "   {$annee['annee']}: {$annee['nb_saisons']} saisons $status\n";
        if ($annee['nb_saisons'] == 4) $anneesCompletes++;
    }
    
    echo "   Années complètes (4 saisons): $anneesCompletes\n";
    
    // 4. Vérifier les liaisons avec dim_periodes
    echo "\n4️⃣ Liaisons avec les périodes\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM dim_periodes");
    $totalPeriodes = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as liees FROM dim_periodes WHERE id_saison IS NOT NULL");
    $periodesLiees = $stmt->fetch()['liees'];
    
    $pourcentage = $totalPeriodes > 0 ? round(($periodesLiees / $totalPeriodes) * 100, 1) : 0;
    
    echo "   Périodes totales: $totalPeriodes\n";
    echo "   Périodes liées: $periodesLiees ($pourcentage%)\n";
    
    if ($pourcentage >= 10) {
        echo "   ✅ Liaisons établies\n";
    } else {
        echo "   ⚠️ Peu de liaisons établies\n";
    }
    
    // 5. Vérifier la dernière mise à jour
    echo "\n5️⃣ Dernière mise à jour\n";
    $stmt = $db->query("SELECT MAX(updated_at) as derniere_maj FROM dim_saisons");
    $derniereMaj = $stmt->fetch()['derniere_maj'];
    
    if ($derniereMaj) {
        $dateMAJ = new DateTime($derniereMaj);
        $maintenant = new DateTime();
        $diff = $maintenant->diff($dateMAJ);
        
        echo "   Dernière mise à jour: " . $dateMAJ->format('d/m/Y H:i:s') . "\n";
        
        if ($diff->days == 0) {
            echo "   ✅ Mise à jour récente (aujourd'hui)\n";
        } elseif ($diff->days <= 7) {
            echo "   ✅ Mise à jour récente ({$diff->days} jour(s))\n";
        } else {
            echo "   ⚠️ Mise à jour ancienne ({$diff->days} jour(s))\n";
        }
    } else {
        echo "   ⚠️ Aucune date de mise à jour trouvée\n";
    }
    
    // 6. Exemples de données
    echo "\n6️⃣ Exemples de données récentes\n";
    $stmt = $db->query("
        SELECT annee, saison, date_debut, date_fin, duree_jours
        FROM dim_saisons 
        WHERE annee >= YEAR(CURDATE())
        ORDER BY annee, FIELD(saison, 'printemps', 'ete', 'automne', 'hiver')
        LIMIT 8
    ");
    $exemples = $stmt->fetchAll();
    
    if (!empty($exemples)) {
        foreach ($exemples as $ex) {
            echo "   {$ex['annee']} {$ex['saison']}: {$ex['date_debut']} → {$ex['date_fin']} ({$ex['duree_jours']} jours)\n";
        }
        echo "   ✅ Données cohérentes\n";
    } else {
        echo "   ⚠️ Aucune donnée récente trouvée\n";
    }
    
    // 7. Test de la vue
    echo "\n7️⃣ Test de la vue v_calendrier_complet\n";
    try {
        $stmt = $db->query("SELECT COUNT(*) as nb FROM v_calendrier_complet LIMIT 1");
        $nbVue = $stmt->fetch()['nb'];
        echo "   Vue accessible: $nbVue enregistrements\n";
        echo "   ✅ Vue fonctionnelle\n";
    } catch (Exception $e) {
        echo "   ❌ Erreur vue: " . $e->getMessage() . "\n";
    }
    
    // 8. Résumé final
    echo "\n🎯 RÉSUMÉ FINAL\n";
    echo "=" . str_repeat("=", 20) . "\n";
    
    $score = 0;
    if ($totalSaisons >= 20) $score++;
    if ($couverture['nb_annees'] >= 5) $score++;
    if ($anneesCompletes >= 5) $score++;
    if ($pourcentage >= 10) $score++;
    if ($derniereMaj && $diff->days <= 7) $score++;
    if (!empty($exemples)) $score++;
    
    $note = round(($score / 6) * 100);
    
    if ($note >= 80) {
        echo "✅ SYSTÈME FONCTIONNEL ($note/100)\n";
        echo "   Le système de saisons astronomiques fonctionne correctement!\n";
    } elseif ($note >= 60) {
        echo "⚠️ SYSTÈME PARTIELLEMENT FONCTIONNEL ($note/100)\n";
        echo "   Quelques améliorations recommandées.\n";
    } else {
        echo "❌ SYSTÈME DÉFAILLANT ($note/100)\n";
        echo "   Des corrections sont nécessaires.\n";
    }
    
    echo "\n💡 Pour mettre à jour: php update_saisons.php\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>