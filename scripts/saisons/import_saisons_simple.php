<?php
/**
 * Import automatique simple des saisons astronomiques
 */

require_once '../../config/database.php';
require_once '../../classes/Database.php';

try {
    echo "=== IMPORT AUTOMATIQUE DES SAISONS ===\n";
    
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Vérifier que le fichier de données existe
    if (!file_exists('saisons_data.php')) {
        echo "❌ Fichier saisons_data.php non trouvé\n";
        echo "   Exécutez d'abord: node scrap_date.js\n";
        exit(1);
    }
    
    // Charger les données
    $saisons = include 'saisons_data.php';
    
    if (!is_array($saisons) || empty($saisons)) {
        echo "❌ Données invalides dans saisons_data.php\n";
        exit(1);
    }
    
    echo "✓ " . count($saisons) . " saisons trouvées dans le fichier\n";
    
    // 1. Vider la table
    echo "\n1. Nettoyage de la table dim_saisons...\n";
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_saisons");
    $ancienNombre = $stmt->fetch()['nb'];
    echo "   Anciennes données: $ancienNombre saisons\n";
    
    $db->exec("DELETE FROM dim_saisons");
    $db->exec("ALTER TABLE dim_saisons AUTO_INCREMENT = 1");
    echo "   ✓ Table vidée\n";
    
    // 2. Insérer les nouvelles données
    echo "\n2. Insertion des nouvelles données...\n";
    $stmt = $db->prepare("
        INSERT INTO dim_saisons (annee, saison, date_debut, date_fin, duree_jours) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $inserted = 0;
    $annees = [];
    
    foreach ($saisons as $saison) {
        $stmt->execute([
            $saison['annee'],
            $saison['saison'],
            $saison['date_debut'],
            $saison['date_fin'],
            $saison['duree_jours']
        ]);
        
        $inserted++;
        $annees[$saison['annee']] = ($annees[$saison['annee']] ?? 0) + 1;
        
        echo "   ✓ {$saison['annee']} {$saison['saison']}: {$saison['date_debut']} → {$saison['date_fin']}\n";
    }
    
    echo "\n✅ $inserted saisons importées avec succès!\n";
    
    // 3. Résumé par année
    echo "\nRésumé par année:\n";
    foreach ($annees as $annee => $nb) {
        echo "   $annee: $nb saisons\n";
    }
    
    // 4. Liaison des périodes (règle intelligente: saison avec plus grand chevauchement)
    echo "\n=== LIAISON PERIODES → SAISONS (chevauchement maximal) ===\n";

    // Récupérer toutes les périodes (on recalcule pour corriger d'éventuelles liaisons imprécises)
    $stmt = $db->query("
        SELECT id_periode, code_periode, nom_periode, annee, DATE(date_debut) AS date_debut, DATE(date_fin) AS date_fin, id_saison
        FROM dim_periodes
        ORDER BY annee, date_debut
    ");
    $periodes = $stmt->fetchAll();

    // Préparer les requêtes nécessaires
    $stmt_saisons_candidates = $db->prepare("
        SELECT id, annee, saison, DATE(date_debut) AS date_debut, DATE(date_fin) AS date_fin
        FROM dim_saisons
        WHERE date_debut <= :periode_fin
          AND date_fin   >= :periode_debut
          AND annee BETWEEN :annee_min AND :annee_max
        ORDER BY date_debut
    ");
    $stmt_update = $db->prepare("UPDATE dim_periodes SET id_saison = ? WHERE id_periode = ?");

    $linked = 0;
    $relinked = 0;
    $unchanged = 0;

    // Helper pour calculer le chevauchement en jours inclusifs
    $calcOverlapDays = function (string $aStart, string $aEnd, string $bStart, string $bEnd): int {
        $start = max(strtotime($aStart), strtotime($bStart));
        $end   = min(strtotime($aEnd),   strtotime($bEnd));
        if ($end < $start) {
            return 0;
        }
        // +1 jour car dates inclusives
        return (int) floor(($end - $start) / 86400) + 1;
    };

    foreach ($periodes as $periode) {
        $pStart = $periode['date_debut'];
        $pEnd   = $periode['date_fin'];
        $annee  = (int) $periode['annee'];

        // Chercher des saisons candidates qui se chevauchent (sur année-1 .. année+1 pour couvrir l'hiver)
        $stmt_saisons_candidates->execute([
            ':periode_fin'   => $pEnd,
            ':periode_debut' => $pStart,
            ':annee_min'     => $annee - 1,
            ':annee_max'     => $annee + 1,
        ]);
        $candidates = $stmt_saisons_candidates->fetchAll();

        $bestSeasonId = null;
        $bestSeasonLib = null;
        $bestOverlap = 0;

        foreach ($candidates as $s) {
            $overlap = $calcOverlapDays($pStart, $pEnd, $s['date_debut'], $s['date_fin']);
            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $bestSeasonId = (int) $s['id'];
                $bestSeasonLib = $s['annee'] . ' ' . $s['saison'];
            }
        }

        if ($bestSeasonId !== null && $bestOverlap > 0) {
            // Mettre à jour seulement si différent
            if ((int) $periode['id_saison'] !== $bestSeasonId) {
                $stmt_update->execute([$bestSeasonId, $periode['id_periode']]);
                if ($periode['id_saison'] === null) {
                    $linked++;
                } else {
                    $relinked++;
                }
                echo "   ✓ {$periode['code_periode']} → {$bestSeasonLib} ({$bestOverlap} j)\n";
            } else {
                $unchanged++;
            }
        } else {
            // Aucune saison candidate trouvée
            if ($periode['id_saison'] !== null) {
                // Cas rare: déjà liée mais plus de chevauchement (incohérence), on laisse tel quel et log
                echo "   ⚠ {$periode['code_periode']}: aucune saison avec chevauchement > 0 (liaison actuelle conservée)\n";
            } else {
                echo "   ⚠ {$periode['code_periode']}: aucune saison avec chevauchement > 0\n";
            }
        }
    }

    echo "\n✅ Nouvelles liaisons: $linked | Corrections: $relinked | Inchangées: $unchanged\n";
    
    // 5. Résumé final
    echo "\n=== RÉSUMÉ FINAL ===\n";
    
    // Nombre total de saisons
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_saisons");
    $nbSaisons = $stmt->fetch()['nb'];
    echo "Total saisons: $nbSaisons\n";
    
    // Nombre de périodes liées
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_periodes WHERE id_saison IS NOT NULL");
    $nbPeriodesLiees = $stmt->fetch()['nb'];
    
    $stmt = $db->query("SELECT COUNT(*) as nb FROM dim_periodes");
    $nbPeriodesTotal = $stmt->fetch()['nb'];
    
    echo "Périodes liées: $nbPeriodesLiees / $nbPeriodesTotal\n";
    
    echo "\n🎉 Import automatique terminé avec succès!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>