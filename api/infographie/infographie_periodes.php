<?php
/**
 * API REST pour les périodes - Utilise la base de données dim_periodes
 * Retourne les périodes au format compatible avec l'ancien JSON
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once dirname(dirname(__DIR__)) . '/database.php';
    require_once __DIR__ . '/periodes_manager_db.php';
    
    $action = $_GET['action'] ?? 'all';
    $annee = $_GET['annee'] ?? null;
    
    switch ($action) {
        case 'all':
            // Retourner toutes les périodes groupées par année (format JSON original)
            $pdo = DatabaseConfig::getConnection();
            
            $sql = "SELECT code_periode, nom_periode, annee, date_debut, date_fin 
                    FROM dim_periodes 
                    ORDER BY annee DESC, date_debut ASC";
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll();
            
            // Reformater pour correspondre à l'ancien format JSON
            $formatted = ['periode' => []];
            
            // Récupérer toutes les années disponibles
            $anneesData = $pdo->query("
                SELECT DISTINCT YEAR(date) as annee 
                FROM dim_dates 
                ORDER BY annee DESC
            ");
            $anneesDisponibles = array_column($anneesData->fetchAll(), 'annee');
            
            // Ajouter la période "Année complète" pour toutes les années
            $formatted['periode']['Année complète'] = [];
            foreach ($anneesDisponibles as $annee) {
                $formatted['periode']['Année complète'][$annee] = [
                    'debut' => '01/01/' . $annee,
                    'fin' => '31/12/' . $annee
                ];
            }
            
            foreach ($results as $row) {
                $periodeName = $row['nom_periode'];
                $year = $row['annee'];
                $dateDebut = date('d/m/Y', strtotime($row['date_debut']));
                $dateFin = date('d/m/Y', strtotime($row['date_fin']));
                
                if (!isset($formatted['periode'][$periodeName])) {
                    $formatted['periode'][$periodeName] = [];
                }
                
                $formatted['periode'][$periodeName][$year] = [
                    'debut' => $dateDebut,
                    'fin' => $dateFin
                ];
            }
            
            echo json_encode($formatted, JSON_PRETTY_PRINT);
            break;
            
        case 'year':
            // Retourner les périodes pour une année donnée
            if (!$annee) {
                echo json_encode(['error' => 'Paramètre annee requis']);
                exit;
            }
            
            $periods = PeriodesManagerDB::getAvailablePeriodesForYear($annee);
            echo json_encode($periods, JSON_PRETTY_PRINT);
            break;
            
        case 'list':
            // Retourner la liste simple des périodes disponibles
            $periods = PeriodesManagerDB::getAllPeriodes();
            echo json_encode($periods, JSON_PRETTY_PRINT);
            break;
            
        default:
            echo json_encode(['error' => 'Action non supportée']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Erreur API périodes',
        'message' => $e->getMessage()
    ]);
}
?> 