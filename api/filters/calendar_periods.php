<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/classes/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $currentYear = date('Y');
    $currentDate = date('Y-m-d');
    
    // Récupérer la meilleure période pour chaque saison de l'année actuelle
    $seasons = ['printemps', 'ete', 'automne', 'hiver'];
    $calendarData = [];
    $currentPeriod = null;
    
    foreach ($seasons as $season) {
        // Calculer la plage complète de la saison (toutes périodes confondues)
        $sql = "SELECT 
                    MIN(date_debut) as debut_saison,
                    MAX(date_fin) as fin_saison,
                    COUNT(*) as nb_periodes,
                    GROUP_CONCAT(nom_periode SEPARATOR ', ') as periodes_incluses
                FROM dim_periodes 
                WHERE saison = ? AND annee = ?";
        
        // Pour l'hiver : limiter aux mois 1-3 (éviter Décembre année suivante)
        if ($season === 'hiver') {
            $sql .= " AND MONTH(date_debut) BETWEEN 1 AND 3";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$season, $currentYear]);
        $result = $stmt->fetch();
        
        if ($result && $result['nb_periodes'] > 0) {
            $startDate = new DateTime($result['debut_saison']);
            $endDate = new DateTime($result['fin_saison']);
            $today = new DateTime($currentDate);
            
            // Vérifier si on est dans cette saison
            $isCurrent = ($today >= $startDate && $today <= $endDate);
            if ($isCurrent) {
                $currentPeriod = [
                    'saison' => $season,
                    'debut_saison' => $result['debut_saison'],
                    'fin_saison' => $result['fin_saison'],
                    'periodes_incluses' => $result['periodes_incluses']
                ];
            }
            
            // Calculer la durée totale de la saison
            $duration = $startDate->diff($endDate)->days + 1;
            
            // Déterminer l'icône selon la saison
            $icons = [
                'printemps' => 'fas fa-seedling',
                'ete' => 'fas fa-umbrella-beach',
                'automne' => 'fas fa-leaf',
                'hiver' => 'fas fa-snowflake'
            ];
            $icon = $icons[$season] ?? 'fas fa-calendar';
            
            // Noms des saisons
            $seasonNames = [
                'printemps' => 'Printemps',
                'ete' => 'Été', 
                'automne' => 'Automne',
                'hiver' => 'Hiver'
            ];
            
            // Générer un code période unifié pour la saison
            $seasonCode = $season . '_' . $currentYear;
            
            $calendarData[$season] = [
                'code_periode' => $seasonCode,
                'nom_periode' => $seasonNames[$season],
                'date_debut' => $result['debut_saison'],
                'date_fin' => $result['fin_saison'],
                'date_debut_fr' => $startDate->format('d/m/Y'),
                'date_fin_fr' => $endDate->format('d/m/Y'),
                'duree_jours' => $duration,
                'nb_periodes' => $result['nb_periodes'],
                'periodes_incluses' => $result['periodes_incluses'],
                'is_current' => $isCurrent,
                'icon' => $icon,
                'description' => $startDate->format('F') . ' - ' . $endDate->format('F'),
                'season_display' => $seasonNames[$season]
            ];
        }
    }
    
    // Ajouter l'année complète
    $calendarData['annee'] = [
        'code_periode' => 'annee_complete',
        'nom_periode' => "Année $currentYear",
        'date_debut' => "$currentYear-01-01 00:00:00",
        'date_fin' => "$currentYear-12-31 23:59:59", 
        'date_debut_fr' => "01/01/$currentYear",
        'date_fin_fr' => "31/12/$currentYear",
        'duree_jours' => 365,
        'is_current' => false,
        'priorite' => 0,
        'icon' => 'fas fa-calendar-alt',
        'description' => 'Janvier - Décembre',
        'season_display' => 'Année complète'
    ];
    
    // Trouver toutes les périodes alternatives par saison
    $alternatives = [];
    foreach ($seasons as $season) {
        $sql = "SELECT code_periode, nom_periode, date_debut, date_fin
                FROM dim_periodes 
                WHERE saison = ? AND annee = ?
                ORDER BY 
                    CASE 
                        WHEN code_periode LIKE 'vacances_%' THEN 1
                        WHEN code_periode LIKE 'weekend_%' THEN 2 
                        ELSE 3
                    END, date_debut";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$season, $currentYear]);
        $alternatives[$season] = $stmt->fetchAll();
    }
    
    echo json_encode([
        'status' => 'success',
        'current_year' => $currentYear,
        'current_date' => $currentDate,
        'current_period' => $currentPeriod,
        'calendar' => $calendarData,
        'alternatives' => $alternatives,
        'message' => 'Calendrier généré dynamiquement depuis la base de données'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la génération du calendrier: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
