<?php
/**
 * API Filters - Version MySQL
 * Récupère les filtres (années, périodes, zones) depuis la base de données MySQL FluxVision
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/database.php';
require_once dirname(__DIR__, 2) . '/classes/ZoneMapper.php';
require_once dirname(__DIR__) . '/security_middleware.php';

try {
    $db = getCantalDestinationDatabase();
    
    // Récupération des années disponibles
    $anneesData = $db->query("
        SELECT DISTINCT YEAR(date) as annee 
        FROM dim_dates 
        ORDER BY annee DESC
    ");
    $annees = array_column($anneesData, 'annee');
    
    // Récupération des zones d'observation (exclusion des zones sans données)
    // ✅ Mise à jour selon le nouveau mapping : on garde les zones principales et exclut les doublons/variantes
    $zonesExclues = [
        // Doublons/variantes à exclure (on garde les zones principales)
        'CA DU BASSIN D\'AURILLAC', // Doublon de CABA → on garde CABA
        'CC DU CARLADE', // Variante de CARLADES → on garde CARLADES  
        'CC DU PAYS DE SALERS', // Variante de PAYS SALERS → on garde PAYS SALERS
        'SAINT FLOUR COMMUNAUTE', // Variante de PAYS SAINT FLOUR → on garde PAYS SAINT FLOUR
        'ST FLOUR COMMUNAUTE', // Autre variante de PAYS SAINT FLOUR
        'STATION DE SKI', // Variante de STATION → on garde STATION
        'VALLEE DE LA TRUYERE', // Variante de VAL TRUYÈRE → on garde VAL TRUYÈRE
        
        // Zones sans utilité ou de test
        'CCSA',
        'CC SUMENE ARTENSE',
        'CANTAL_TEST',
        'HAUTES TERRES COMMUNAUTE',
        'RESTE DEPARTEMENT',
        'STATION THERMALE DE CHAUDES-AIGUES'
    ];
    
    $placeholders = str_repeat('?,', count($zonesExclues) - 1) . '?';
    $zonesData = $db->query("
        SELECT nom_zone 
        FROM dim_zones_observation 
        WHERE nom_zone NOT IN ($placeholders)
        ORDER BY nom_zone ASC
    ", $zonesExclues);
    $zones = array_column($zonesData, 'nom_zone');
    
    // ✅ Utilisation de la classe ZoneMapper centralisée
    $zones = ZoneMapper::mapZonesForDisplay($zones);
    
    // Trier les zones par ordre alphabétique
    sort($zones);
    
    // Récupération des périodes depuis la base de données
    $periodesData = $db->query("
        SELECT 
            code_periode as value,
            nom_periode as label,
            CONCAT(nom_periode, ' (', DATE_FORMAT(date_debut, '%d/%m'), ' - ', DATE_FORMAT(date_fin, '%d/%m'), ')') as description,
            annee,
            date_debut,
            date_fin
        FROM dim_periodes 
        ORDER BY annee DESC, date_debut ASC
    ");
    
    $periodes = [];
    foreach ($periodesData as $periode) {
        $periodes[] = [
            'value' => $periode['value'],
            'label' => $periode['label'],
            'description' => $periode['description'],
            'annee' => (int)$periode['annee'],
            'date_debut' => $periode['date_debut'],
            'date_fin' => $periode['date_fin']
        ];
    }
    
    // Ajouter la période "Année complète" en premier (sans modifier la table)
    array_unshift($periodes, [
        'value' => 'annee_complete',
        'label' => 'Année complète',
        'description' => 'Année complète (01/01 - 31/12)',
        'annee' => null, // Valable pour toutes les années
        'date_debut' => null,
        'date_fin' => null
    ]);
    
    // Construction de la réponse
    $response = [
        'annees' => $annees,
        'periodes' => $periodes,
        'zones' => $zones,
        'metadata' => [
            'total_annees' => count($annees),
            'total_zones' => count($zones),
            'date_min' => $db->queryOne("SELECT MIN(date) as min_date FROM dim_dates")['min_date'] ?? null,
            'date_max' => $db->queryOne("SELECT MAX(date) as max_date FROM dim_dates")['max_date'] ?? null,
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    ApiSecurityMiddleware::handleApiError($e, '/api/filters_mysql');
} 
