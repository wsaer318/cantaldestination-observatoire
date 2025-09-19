<?php
/**
 * Fournisseur de données pour le calendrier intelligent
 * Génère les données côté serveur pour éviter les problèmes de CSP
 */

class CalendarDataProvider {
    
    /**
     * Génère les données du calendrier pour l'année actuelle
     */
    public static function getCalendarData() {
        try {
            require_once dirname(__DIR__) . '/config/database.php';
            require_once dirname(__DIR__) . '/classes/Database.php';
            
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $currentYear = (int) date('Y');
            $currentDate = date('Y-m-d');
            $today = new DateTime($currentDate);
            
            // Chargement des saisons officielles depuis dim_saisons
            // printemps/ete/automne: annee = currentYear
            // hiver: annee = currentYear - 1 (déc -> mars)
            $seasonToYear = [
                'printemps' => $currentYear,
                'ete' => $currentYear,
                'automne' => $currentYear,
                'hiver' => $currentYear - 1,
            ];
            
            $seasons = ['printemps', 'ete', 'automne', 'hiver'];
            $calendarData = [];
            $currentPeriod = null;
            
            // Préparer requêtes
            $stmtSeason = $pdo->prepare("SELECT id, annee, saison, date_debut, date_fin FROM dim_saisons WHERE annee = ? AND saison = ? LIMIT 1");
            $stmtAlt = $pdo->prepare("SELECT code_periode, nom_periode, date_debut, date_fin FROM dim_periodes WHERE id_saison = ? ORDER BY 
                CASE 
                    WHEN code_periode LIKE 'vacances_%' THEN 1
                    WHEN code_periode LIKE 'weekend_%' THEN 2
                    WHEN code_periode LIKE 'pont_%' THEN 3
                    ELSE 9
                END, date_debut");
            $stmtAltSummary = $pdo->prepare("SELECT COUNT(*) AS nb, GROUP_CONCAT(nom_periode ORDER BY date_debut SEPARATOR ', ') AS periodes_incluses FROM dim_periodes WHERE id_saison = ?");

            foreach ($seasons as $season) {
                $seasonYear = $seasonToYear[$season];
                $stmtSeason->execute([$seasonYear, $season]);
                $s = $stmtSeason->fetch();
                if (!$s) {
                    continue;
                }
                $startDate = new DateTime($s['date_debut']);
                $endDate = new DateTime($s['date_fin']);
                $duration = $startDate->diff($endDate)->days + 1;
                $icon = self::getSeasonIcon($season);

                // Alternatives et résumé
                $stmtAlt->execute([$s['id']]);
                $alternatives = $stmtAlt->fetchAll();
                $stmtAltSummary->execute([$s['id']]);
                $altSummary = $stmtAltSummary->fetch() ?: ['nb' => 0, 'periodes_incluses' => null];

                // Période courante ?
                $isCurrent = ($today >= $startDate && $today <= $endDate);
                if ($isCurrent) {
                    $currentPeriod = [
                        'saison' => $season,
                        'nom_periode' => self::getSeasonDisplayName($season),
                        'code_periode' => $season, // code simple, l'année est fournie ailleurs
                        'date_debut' => $s['date_debut'],
                        'date_fin' => $s['date_fin'],
                    ];
                }

                $calendarData[$season] = [
                    'code_periode' => $season, // utiliser le code canonique de saison
                    'nom_periode' => self::getSeasonDisplayName($season),
                    'date_debut' => $s['date_debut'],
                    'date_fin' => $s['date_fin'],
                    'date_debut_fr' => $startDate->format('d/m/Y'),
                    'date_fin_fr' => $endDate->format('d/m/Y'),
                    'duree_jours' => $duration,
                    'nb_periodes' => (int)($altSummary['nb'] ?? 0),
                    'periodes_incluses' => $altSummary['periodes_incluses'],
                    'is_current' => $isCurrent,
                    'icon' => $icon,
                    'description' => self::getSeasonDescription($startDate, $endDate),
                    'season_display' => self::getSeasonDisplayName($season),
                ];
            }
            
            // Ajouter l'année complète
            $isLeap = (bool) date('L', strtotime($currentYear . '-01-01'));
            $calendarData['annee'] = [
                'code_periode' => 'annee_complete',
                'nom_periode' => "Année $currentYear",
                'date_debut' => $currentYear . "-01-01",
                'date_fin' => $currentYear . "-12-31",
                'date_debut_fr' => "01/01/$currentYear",
                'date_fin_fr' => "31/12/$currentYear",
                'duree_jours' => $isLeap ? 366 : 365,
                'is_current' => false,
                'priorite' => 0,
                'icon' => 'fas fa-calendar-alt',
                'description' => 'Janvier - Décembre',
                'season_display' => 'Année complète'
            ];
            
            // Alternatives déjà calculées par saison (via id_saison)
            $alternatives = [];
            foreach ($seasons as $season) {
                $seasonYear = $seasonToYear[$season];
                $stmtSeason->execute([$seasonYear, $season]);
                $s = $stmtSeason->fetch();
                if ($s) {
                    $stmtAlt->execute([$s['id']]);
                    $alternatives[$season] = $stmtAlt->fetchAll();
                } else {
                    $alternatives[$season] = [];
                }
            }
            
            return [
                'status' => 'success',
                'current_year' => $currentYear,
                'current_date' => $currentDate,
                'current_period' => $currentPeriod,
                'calendar' => $calendarData,
                'alternatives' => $alternatives,
                'message' => 'Calendrier généré côté serveur'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur lors de la génération du calendrier: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Génère la description d'une saison
     */
    private static function getSeasonDescription($startDate, $endDate) {
        $startMonth = $startDate->format('F');
        $endMonth = $endDate->format('F');
        
        // Traduire les mois en français
        $months = [
            'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
            'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
            'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
            'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
        ];
        
        $startMonthFr = $months[$startMonth] ?? $startMonth;
        $endMonthFr = $months[$endMonth] ?? $endMonth;
        
        return $startMonthFr === $endMonthFr ? $startMonthFr : "$startMonthFr - $endMonthFr";
    }
    
    /**
     * Retourne le nom d'affichage d'une saison
     */
    private static function getSeasonDisplayName($season) {
        $names = [
            'printemps' => 'Printemps',
            'ete' => 'Été', 
            'automne' => 'Automne',
            'hiver' => 'Hiver'
        ];
        
        return $names[$season] ?? ucfirst($season);
    }
    
    /**
     * Retourne l'icône appropriée pour une saison
     */
    private static function getSeasonIcon($season) {
        $icons = [
            'printemps' => 'fas fa-seedling',
            'ete' => 'fas fa-umbrella-beach',
            'automne' => 'fas fa-leaf',
            'hiver' => 'fas fa-snowflake'
        ];
        
        return $icons[$season] ?? 'fas fa-calendar';
    }
    
    /**
     * Retourne les données sous forme de JSON pour JavaScript
     */
    public static function getCalendarDataAsJson() {
        return json_encode(self::getCalendarData(), JSON_UNESCAPED_UNICODE);
    }
}
?>