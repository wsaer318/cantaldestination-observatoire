<?php
// API pour récupérer les dates des périodes depuis la base de données
require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/database.php';
require_once dirname(__DIR__) . '/security_middleware.php';

try {
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    // Récupérer toutes les périodes depuis la base de données
    $sql = "SELECT code_periode, nom_periode, annee, date_debut, date_fin 
            FROM dim_periodes 
            ORDER BY annee DESC, date_debut ASC";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
    
    // Reformater pour correspondre au format attendu par l'ancien système
    $periodes_dates = ['details_periodes' => []];
    
    // Récupérer toutes les années disponibles pour la période "Année complète"
    $anneesData = $db->query("
        SELECT DISTINCT YEAR(date) as annee 
        FROM dim_dates 
        ORDER BY annee DESC
    ");
    $anneesDisponibles = array_column($anneesData, 'annee');
    
    // Ajouter la période "Année complète" pour toutes les années disponibles
    $periodes_dates['details_periodes']['annee_complete'] = [];
    foreach ($anneesDisponibles as $annee) {
        $periodes_dates['details_periodes']['annee_complete'][$annee] = [
            'debut' => '01/01/' . $annee,
            'fin' => '31/12/' . $annee
        ];
    }
    
    foreach ($results as $row) {
        $codePeriode = $row['code_periode'];
        $year = $row['annee'];
        $dateDebut = date('d/m/Y', strtotime($row['date_debut']));
        $dateFin = date('d/m/Y', strtotime($row['date_fin']));
        
        // Mapping des codes vers les anciens noms de clés
        $keyMapping = [
            'annee' => 'annee',
            'hiver' => 'vacances_d_hiver',
            'paques' => 'week-end_de_paques',
            'mai' => 'pont_de_mai',
            'printemps' => 'periode_printemps'
        ];
        
        $key = $keyMapping[$codePeriode] ?? $codePeriode;
        
        if (!isset($periodes_dates['details_periodes'][$key])) {
            $periodes_dates['details_periodes'][$key] = [];
        }
        
        $periodes_dates['details_periodes'][$key][$year] = [
            'debut' => $dateDebut,
            'fin' => $dateFin
        ];
    }
    
    jsonResponse($periodes_dates);
    
} catch (Exception $e) {
    ApiSecurityMiddleware::handleApiError($e, '/api/periodes_dates');
}
?>
