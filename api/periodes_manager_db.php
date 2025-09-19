<?php
/**
 * Gestionnaire des périodes basé sur la base de données
 * Remplace le système JSON par une approche SQL plus efficace
 */

if (!class_exists('PeriodesManagerDB')) {
class PeriodesManagerDB {
    private static $db = null;
    
    /**
     * Initialise la connexion à la base de données
     */
    private static function getDB() {
        if (self::$db === null) {
            require_once dirname(__DIR__) . '/database.php';
            self::$db = getCantalDestinationDatabase();
        }
        return self::$db;
    }
    
    /**
     * Calcule les plages de dates selon la période et l'année
     * 
     * @param int $annee L'année
     * @param string $periode Le nom ou code de la période
     * @return array Array avec 'start' et 'end' au format YYYY-MM-DD
     */
    public static function calculateDateRanges($annee, $periode) {
        try {
            // Cas spécial : Année complète
            if (strtolower($periode) === 'annee_complete' || strtolower($periode) === 'année complète') {
                return [
                    'start' => "$annee-01-01 00:00:00",
                    'end' => "$annee-12-31 23:59:59"
                ];
            }
            
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            // Recherche directe dans la base de données par code_periode OU nom_periode
            // Pas de mapping en dur - la base de données est la source de vérité
            $sql = "SELECT date_debut, date_fin FROM dim_periodes 
                    WHERE (code_periode = ? OR nom_periode = ?) AND annee = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$periode, $periode, $annee]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Convertir les dates au bon format
                $dateDebut = new DateTime($result['date_debut']);
                $dateFin = new DateTime($result['date_fin']);
                
                return [
                    'start' => $dateDebut->format('Y-m-d H:i:s'),
                    'end' => $dateFin->format('Y-m-d H:i:s')
                ];
            } else {
                // Si pas trouvé, essayer une recherche flexible (insensible à la casse)
                $sqlFlexible = "SELECT date_debut, date_fin FROM dim_periodes 
                               WHERE (LOWER(code_periode) = LOWER(?) OR LOWER(nom_periode) = LOWER(?)) 
                               AND annee = ?";
                
                $stmtFlexible = $pdo->prepare($sqlFlexible);
                $stmtFlexible->execute([$periode, $periode, $annee]);
                $resultFlexible = $stmtFlexible->fetch();
                
                if ($resultFlexible) {
                    $dateDebut = new DateTime($resultFlexible['date_debut']);
                    $dateFin = new DateTime($resultFlexible['date_fin']);
                    
                    return [
                        'start' => $dateDebut->format('Y-m-d H:i:s'),
                        'end' => $dateFin->format('Y-m-d H:i:s')
                    ];
                }
                
                // Recherche encore plus flexible avec normalisation des underscores/espaces
                $periodeNormalized = str_replace(['_', '-'], ' ', $periode);
                
                // Cas spécial pour pont_mai -> chercher "mai" dans nom_periode
                if (strtolower($periodeNormalized) === 'pont mai') {
                    $sqlSpecial = "SELECT date_debut, date_fin FROM dim_periodes 
                                  WHERE LOWER(nom_periode) LIKE '%mai%' AND annee = ?";
                    $stmtSpecial = $pdo->prepare($sqlSpecial);
                    $stmtSpecial->execute([$annee]);
                    $resultUltraFlexible = $stmtSpecial->fetch();
                } else {
                    $sqlUltraFlexible = "SELECT date_debut, date_fin FROM dim_periodes 
                                        WHERE (LOWER(REPLACE(REPLACE(code_periode, '_', ' '), '-', ' ')) = LOWER(?) 
                                               OR LOWER(REPLACE(REPLACE(nom_periode, '_', ' '), '-', ' ')) = LOWER(?)) 
                                        AND annee = ?";
                    
                    $stmtUltraFlexible = $pdo->prepare($sqlUltraFlexible);
                    $stmtUltraFlexible->execute([$periodeNormalized, $periodeNormalized, $annee]);
                    $resultUltraFlexible = $stmtUltraFlexible->fetch();
                }
                
                if ($resultUltraFlexible) {
                    $dateDebut = new DateTime($resultUltraFlexible['date_debut']);
                    $dateFin = new DateTime($resultUltraFlexible['date_fin']);
                    
                    return [
                        'start' => $dateDebut->format('Y-m-d H:i:s'),
                        'end' => $dateFin->format('Y-m-d H:i:s')
                    ];
                }
                
                // Vraiment pas trouvé - fallback année complète
                error_log("Période '$periode' non trouvée en base pour l'année $annee");
                return [
                    'start' => "$annee-01-01 00:00:00",
                    'end' => "$annee-12-31 23:59:59"
                ];
            }
            
        } catch (Exception $e) {
            error_log("Erreur dans calculateDateRanges (DB): " . $e->getMessage());
            // Fallback sécurisé
            return [
                'start' => "$annee-01-01 00:00:00",
                'end' => "$annee-12-31 23:59:59"
            ];
        }
    }
    

    
    /**
     * Retourne toutes les périodes disponibles pour une année donnée
     */
    public static function getAvailablePeriodesForYear($annee) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $sql = "SELECT code_periode, nom_periode, date_debut, date_fin 
                    FROM dim_periodes 
                    WHERE annee = ? 
                    ORDER BY date_debut";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$annee]);
            
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[$row['code_periode']] = [
                    'nom' => $row['nom_periode'],
                    'debut' => $row['date_debut'],
                    'fin' => $row['date_fin']
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Erreur dans getAvailablePeriodesForYear (DB): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retourne toutes les périodes disponibles
     */
    public static function getAllPeriodes() {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $sql = "SELECT DISTINCT code_periode, nom_periode FROM dim_periodes ORDER BY code_periode";
            $stmt = $pdo->query($sql);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur dans getAllPeriodes (DB): " . $e->getMessage());
            // Retourner un tableau vide plutôt que des données en dur
            // La base de données est la seule source de vérité
            return [];
        }
    }
    
    /**
     * Vérifie si une période existe pour une année donnée
     */
    public static function isValidPeriodeYear($annee, $periode) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            // Recherche directe par code_periode OU nom_periode (insensible à la casse)
            $sql = "SELECT COUNT(*) FROM dim_periodes 
                    WHERE (LOWER(code_periode) = LOWER(?) OR LOWER(nom_periode) = LOWER(?)) 
                    AND annee = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$periode, $periode, $annee]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            error_log("Erreur dans isValidPeriodeYear (DB): " . $e->getMessage());
            return false;
        }
    }
}
} // Fin de if (!class_exists('PeriodesManagerDB'))

/**
 * Fonction de compatibilité pour remplacer l'ancien système
 */
if (!function_exists('calculateIntelligentDateRanges')) {
    function calculateIntelligentDateRanges($annee, $periode) {
        return PeriodesManagerDB::calculateDateRanges($annee, $periode);
    }
}
?> 