<?php
/**
 * Gestionnaire intelligent des périodes basé sur le fichier période.json
 * Centralise la logique de calcul des dates pour toutes les APIs
 */

class PeriodesManager {
    private static $periodesData = null;
    
    /**
     * Charge les données de périodes depuis le fichier JSON
     */
    private static function loadPeriodesData() {
        if (self::$periodesData === null) {
            $jsonPath = __DIR__ . '/../static/data/periode.json';
            if (file_exists($jsonPath)) {
                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);
                if ($data && isset($data['periode'])) {
                    self::$periodesData = $data['periode'];
                } else {
                    throw new Exception("Format JSON invalide dans periode.json");
                }
            } else {
                throw new Exception("Fichier periode.json non trouvé: $jsonPath");
            }
        }
        return self::$periodesData;
    }
    
    /**
     * Normalise le nom de la période pour correspondre aux clés du JSON
     */
    private static function normalizePeriodeName($periode) {
        $periode = strtolower(trim($periode));
        
        // Mapping des variantes possibles
        $mappings = [
            'année' => 'Année',
            'annee' => 'Année',
            'year' => 'Année',
            'vacances d\'hiver' => 'Vacances d\'hiver',
            'vacances d\'hiver' => 'Vacances d\'hiver',
            'hiver' => 'Vacances d\'hiver',
            'vacances_hiver' => 'Vacances d\'hiver',
            'vacances_d_hiver' => 'Vacances d\'hiver',
            'winter' => 'Vacances d\'hiver',
            'week-end de pâques' => 'week-end de Pâques',
            'week-end de paques' => 'week-end de Pâques',
            'paques' => 'week-end de Pâques',
            'pâques' => 'week-end de Pâques',
            'easter' => 'week-end de Pâques',
            'week_end_paques' => 'week-end de Pâques',
            'week-end_de_paques' => 'week-end de Pâques',
            'pont de mai' => 'Pont de mai',
            'pont_mai' => 'Pont de mai',
            'pont_de_mai' => 'Pont de mai',
            'mai' => 'Pont de mai',
            'période printemps' => 'Période printemps',
            'periode printemps' => 'Période printemps',
            'printemps' => 'Période printemps',
            'spring' => 'Période printemps',
            'periode_printemps' => 'Période printemps'
        ];
        
        return $mappings[$periode] ?? $periode;
    }
    
    /**
     * Convertit une date du format DD/MM/YYYY vers YYYY-MM-DD
     */
    private static function convertDateFormat($dateStr) {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        return $dateStr; // Retourne tel quel si déjà au bon format
    }
    
    /**
     * Calcule les plages de dates intelligemment selon la période et l'année
     * 
     * @param int $annee L'année
     * @param string $periode Le nom de la période
     * @return array Array avec 'start' et 'end' au format YYYY-MM-DD
     */
    public static function calculateDateRanges($annee, $periode) {
        try {
            $periodesData = self::loadPeriodesData();
            $normalizedPeriode = self::normalizePeriodeName($periode);
            $anneeStr = strval($annee);
            
            // Vérifier si la période existe
            if (!isset($periodesData[$normalizedPeriode])) {
                // Fallback vers les dates par défaut de l'année complète
                error_log("Période non trouvée: $normalizedPeriode. Utilisation de l'année complète.");
                return [
                    'start' => "$annee-01-01",
                    'end' => "$annee-12-31"
                ];
            }
            
            // Vérifier si l'année existe pour cette période
            if (!isset($periodesData[$normalizedPeriode][$anneeStr])) {
                // Essayer de calculer automatiquement pour les années manquantes
                error_log("Année $annee non trouvée pour période $normalizedPeriode. Tentative de calcul automatique.");
                return self::calculateFallbackDates($annee, $normalizedPeriode);
            }
            
            $periodeData = $periodesData[$normalizedPeriode][$anneeStr];
            
            return [
                'start' => self::convertDateFormat($periodeData['debut']),
                'end' => self::convertDateFormat($periodeData['fin'])
            ];
            
        } catch (Exception $e) {
            error_log("Erreur dans calculateDateRanges: " . $e->getMessage());
            // Fallback sécurisé
            return self::calculateFallbackDates($annee, $periode);
        }
    }
    
    /**
     * Calcule des dates de fallback quand les données JSON ne sont pas disponibles
     */
    private static function calculateFallbackDates($annee, $periode) {
        $periode = strtolower(trim($periode));
        
        switch ($periode) {
            case 'année':
            case 'annee':
                return [
                    'start' => "$annee-01-01",
                    'end' => "$annee-12-31"
                ];
                
            case 'vacances d\'hiver':
            case 'hiver':
                // Estimation approximative basée sur les données historiques
                // Généralement début février à début mars
                return [
                    'start' => "$annee-02-08",
                    'end' => "$annee-03-08"
                ];
                
            case 'week-end de pâques':
            case 'paques':
                // Estimation approximative
                // Généralement autour de début avril
                return [
                    'start' => "$annee-04-08",
                    'end' => "$annee-04-10"
                ];
                
            case 'pont de mai':
            case 'pont_mai':
            case 'mai':
                // Pont de mai : 01/05 -> 09/06
                return [
                    'start' => "$annee-05-01",
                    'end' => "$annee-06-09"
                ];
                
            case 'période printemps':
            case 'periode printemps':
            case 'printemps':
                // Période printemps : 05/04 -> 08/06
                return [
                    'start' => "$annee-04-05",
                    'end' => "$annee-06-08"
                ];
                
            default:
                return [
                    'start' => "$annee-01-01",
                    'end' => "$annee-12-31"
                ];
        }
    }
    
    /**
     * Retourne toutes les périodes disponibles pour une année donnée
     */
    public static function getAvailablePeriodesForYear($annee) {
        try {
            $periodesData = self::loadPeriodesData();
            $anneeStr = strval($annee);
            $available = [];
            
            foreach ($periodesData as $periodeName => $periodesYears) {
                if (isset($periodesYears[$anneeStr])) {
                    $available[$periodeName] = $periodesYears[$anneeStr];
                }
            }
            
            return $available;
        } catch (Exception $e) {
            error_log("Erreur dans getAvailablePeriodesForYear: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retourne toutes les données de périodes
     */
    public static function getAllPeriodes() {
        try {
            return self::loadPeriodesData();
        } catch (Exception $e) {
            error_log("Erreur dans getAllPeriodes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Valide si une période et une année sont supportées
     */
    public static function isValidPeriodeYear($annee, $periode) {
        try {
            $periodesData = self::loadPeriodesData();
            $normalizedPeriode = self::normalizePeriodeName($periode);
            $anneeStr = strval($annee);
            
            return isset($periodesData[$normalizedPeriode][$anneeStr]);
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Fonction de compatibilité pour remplacer calculateWorkingDateRanges
 * 
 * @deprecated Utiliser PeriodesManager::calculateDateRanges() à la place
 */
if (!function_exists('calculateIntelligentDateRanges')) {
    function calculateIntelligentDateRanges($annee, $periode) {
        return PeriodesManager::calculateDateRanges($annee, $periode);
    }
}

// Fonction utilitaire pour les APIs existantes
function getPeriodesManagerInstance() {
    return new PeriodesManager();
}
?> 