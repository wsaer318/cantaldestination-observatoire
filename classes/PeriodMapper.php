<?php
/**
 * PeriodMapper - Système Hybride pour la gestion des périodes
 * 
 * Permet de mapper intelligemment :
 * - Les 4 saisons utilisateur (printemps, ete, automne, hiver) 
 * - Vers les vraies périodes métier de la base de données
 * - Tout en gardant la compatibilité avec les périodes directes
 */

class PeriodMapper {
    private static $db = null;
    private static $seasonsMapping = null;
    
    /**
     * Configuration des mappings saisonniers
     * Basé sur les mois calendaires du tourisme
     */
    const SEASONS_CONFIG = [
        'printemps' => [
            'months' => [3, 4, 5], // Mars, Avril, Mai
            'priority_keywords' => ['printemps', 'mai', 'paques'],
            'fallback_months' => ['03', '04', '05']
        ],
        'ete' => [
            'months' => [6, 7, 8], // Juin, Juillet, Août
            'priority_keywords' => ['ete', 'été', 'vacances_ete', 'juillet', 'aout'],
            'fallback_months' => ['06', '07', '08']
        ],
        'automne' => [
            'months' => [9, 10, 11], // Septembre, Octobre, Novembre
            'priority_keywords' => ['automne', 'septembre', 'toussaint'],
            'fallback_months' => ['09', '10', '11']
        ],
        'hiver' => [
            'months' => [12, 1, 2], // Décembre, Janvier, Février
            'priority_keywords' => ['hiver', 'vacances_hiver', 'noel', 'fevrier'],
            'fallback_months' => ['12', '01', '02']
        ]
    ];
    
    /**
     * Initialise la connexion à la base de données
     */
    private static function getDB() {
        if (self::$db === null) {
            // Essayer plusieurs emplacements possibles pour database.php
            $possiblePaths = [
                dirname(__DIR__) . '/database.php',
                dirname(__DIR__) . '/config/database.php',
                dirname(__DIR__) . '/includes/database.php'
            ];
            
            $databaseLoaded = false;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $databaseLoaded = true;
                    break;
                }
            }
            
            if (!$databaseLoaded) {
                throw new Exception("Fichier database.php introuvable");
            }
            
            self::$db = getCantalDestinationDatabase();
        }
        return self::$db;
    }
    
    /**
     * Point d'entrée principal : Résout intelligemment une période
     * 
     * @param string $periode La période demandée (saison ou période métier)
     * @param int $annee L'année concernée
     * @param string $context Le contexte d'utilisation ('user'|'business'|'auto')
     * @return array|null Les informations de la période résolue
     */
    public static function resolvePeriod($periode, $annee, $context = 'auto') {
        // Nettoyage des paramètres
        $periode = trim(strtolower($periode));
        $annee = (int)$annee;
        
        // 1. Vérifier si c'est une saison simple
        if (self::isSimpleSeason($periode)) {
            return self::resolveSeasonToPeriod($periode, $annee, $context);
        }
        
        // 2. Vérifier si c'est une période métier directe
        $directPeriod = self::getDirectPeriodFromDB($periode, $annee);
        if ($directPeriod) {
            return $directPeriod;
        }
        
        // 3. Tentative de reconnaissance intelligente
        $smartPeriod = self::smartPeriodRecognition($periode, $annee);
        if ($smartPeriod) {
            return $smartPeriod;
        }
        
        // 4. Fallback : année complète
        return self::getFullYearPeriod($annee);
    }
    
    /**
     * Vérifie si c'est une des 4 saisons simples (dynamique basé sur la config)
     */
    private static function isSimpleSeason($periode) {
        $periode = strtolower(trim($periode));
        
        // Vérifier contre les clés de configuration des saisons
        $seasonKeys = array_keys(self::SEASONS_CONFIG);
        
        // Ajouter les variantes communes
        $allSeasonTerms = $seasonKeys;
        $allSeasonTerms[] = 'été'; // Variante avec accent
        
        return in_array($periode, $allSeasonTerms);
    }
    
    /**
     * Résout une saison vers la meilleure période métier correspondante
     * Utilise maintenant la colonne 'saison' de la base de données
     */
    private static function resolveSeasonToPeriod($saison, $annee, $context) {
        // Normaliser la saison
        $saison = ($saison === 'été') ? 'ete' : strtolower(trim($saison));
        
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            // Stratégie 1 : Recherche directe par colonne saison
            $periodsInSeason = self::findPeriodsBySeason($saison, $annee);
        
        if (!empty($periodsInSeason)) {
            // En contexte business, prioriser les vacances
            if ($context === 'business') {
                $vacancesPeriod = self::findVacancesPeriod($periodsInSeason);
                if ($vacancesPeriod) return $vacancesPeriod;
            }
            
                // Sinon, prendre la première période trouvée (triée par priorité)
            return $periodsInSeason[0];
        }
        
            // Stratégie 2 : Fallback sur l'ancienne méthode si pas de correspondance
            if (isset(self::SEASONS_CONFIG[$saison])) {
                $config = self::SEASONS_CONFIG[$saison];
        $seasonPeriod = self::findPeriodByDateRange($config['fallback_months'], $annee);
        if ($seasonPeriod) {
            return $seasonPeriod;
        }
        
        // Stratégie 3 : Créer une période synthétique
        return self::createSyntheticSeasonPeriod($saison, $annee);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erreur resolveSeasonToPeriod: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trouve les périodes par saison en utilisant la colonne saison de la base
     */
    private static function findPeriodsBySeason($saison, $annee) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            // L'hiver de l'année N est stocké comme saison de l'année N-1 dans dim_saisons
            $seasonYear = ($saison === 'hiver') ? ($annee - 1) : $annee;

            $sql = "SELECT dp.code_periode, dp.nom_periode, dp.date_debut, dp.date_fin
                    FROM dim_periodes dp
                    INNER JOIN dim_saisons s ON dp.id_saison = s.id
                    WHERE s.saison = ? AND s.annee = ?
                    ORDER BY 
                        CASE 
                            WHEN dp.code_periode LIKE 'vacances_%' THEN 1
                            WHEN dp.code_periode LIKE 'weekend_%' THEN 2
                            WHEN dp.code_periode LIKE 'pont_%' THEN 3
                            ELSE 4
                        END,
                        dp.date_debut";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$saison, $seasonYear]);
            
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = [
                    'type' => 'business',
                    'code_periode' => $row['code_periode'],
                    'nom_periode' => $row['nom_periode'],
                    'date_debut' => $row['date_debut'],
                    'date_fin' => $row['date_fin'],
                    'annee' => $annee,
                    'saison' => $saison
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Erreur findPeriodsBySeason: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cherche les périodes contenant certains mots-clés
     */
    private static function findPeriodsByKeywords($keywords, $annee) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $keywordConditions = [];
            $params = [];
            
            foreach ($keywords as $keyword) {
                $keywordConditions[] = "(LOWER(code_periode) LIKE ? OR LOWER(nom_periode) LIKE ?)";
                $params[] = "%$keyword%";
                $params[] = "%$keyword%";
            }
            
            $sql = "SELECT code_periode, nom_periode, date_debut, date_fin 
                    FROM dim_periodes 
                    WHERE (" . implode(' OR ', $keywordConditions) . ") 
                    AND annee = ? 
                    ORDER BY date_debut";
            
            $params[] = $annee;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = [
                    'type' => 'business',
                    'code_periode' => $row['code_periode'],
                    'nom_periode' => $row['nom_periode'],
                    'date_debut' => $row['date_debut'],
                    'date_fin' => $row['date_fin'],
                    'annee' => $annee
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Erreur findPeriodsByKeywords: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Trouve la période "vacances" dans une liste (priorité business)
     */
    private static function findVacancesPeriod($periods) {
        foreach ($periods as $period) {
            if (strpos(strtolower($period['code_periode']), 'vacances') !== false ||
                strpos(strtolower($period['nom_periode']), 'vacances') !== false) {
                return $period;
            }
        }
        return null;
    }
    
    /**
     * Cherche une période directement en base
     */
    private static function getDirectPeriodFromDB($periode, $annee) {
        try {
            require_once __DIR__ . '/../api/periodes_manager_db.php';
            
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $sql = "SELECT code_periode, nom_periode, date_debut, date_fin 
                    FROM dim_periodes 
                    WHERE (LOWER(code_periode) = LOWER(?) OR LOWER(nom_periode) = LOWER(?)) 
                    AND annee = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$periode, $periode, $annee]);
            $row = $stmt->fetch();
            
            if ($row) {
                return [
                    'type' => 'business',
                    'code_periode' => $row['code_periode'],
                    'nom_periode' => $row['nom_periode'],
                    'date_debut' => $row['date_debut'],
                    'date_fin' => $row['date_fin'],
                    'annee' => $annee
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erreur getDirectPeriodFromDB: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Reconnaissance intelligente de périodes basée sur la base de données
     * Utilise maintenant la colonne saison pour une meilleure correspondance
     */
    private static function smartPeriodRecognition($periode, $annee) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            // Étape 1: Vérifier si le terme correspond directement à une saison
            $seasonMatch = self::findDirectSeasonMatch($periode, $annee);
            if ($seasonMatch) {
                return $seasonMatch;
            }
            
            // Étape 2: Recherche flexible avec termes générés
            $searchTerms = self::generateSearchTerms($periode);
            
            foreach ($searchTerms as $term) {
                // Recherche enrichie incluant la saison via jointure dim_saisons
                $sql = "SELECT dp.code_periode, dp.nom_periode
                        FROM dim_periodes dp
                        LEFT JOIN dim_saisons s ON dp.id_saison = s.id
                        WHERE dp.annee = ? AND (
                            LOWER(dp.code_periode) LIKE ? OR 
                            LOWER(dp.nom_periode) LIKE ? OR
                            LOWER(dp.nom_periode) LIKE ? OR
                            LOWER(dp.code_periode) LIKE ? OR
                            LOWER(s.saison) LIKE ?
                        )
                        ORDER BY 
                            CASE 
                                WHEN LOWER(dp.code_periode) = ? THEN 1
                                WHEN LOWER(dp.nom_periode) = ? THEN 2
                                WHEN LOWER(s.saison) = ? THEN 3
                                WHEN LOWER(dp.code_periode) LIKE ? THEN 4
                                ELSE 5 
                            END,
                            CASE 
                                WHEN dp.code_periode LIKE 'vacances_%' THEN 1
                                WHEN dp.code_periode LIKE 'weekend_%' THEN 2
                                ELSE 3
                            END
                        LIMIT 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $annee,
                    "%{$term}%",        // code_periode contient
                    "%{$term}%",        // nom_periode contient
                    "{$term}%",         // nom_periode commence par
                    "%{$term}",         // code_periode finit par
                    "%{$term}%",        // s.saison contient
                    $term,              // Priorité 1: code_periode exact
                    $term,              // Priorité 2: nom_periode exact
                    $term,              // Priorité 3: s.saison exact
                    "%{$term}%"         // Priorité 4: contient
                ]);
                
                $result = $stmt->fetch();
                if ($result) {
                    return self::getDirectPeriodFromDB($result['code_periode'], $annee);
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erreur smartPeriodRecognition: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Recherche directe de correspondance avec les saisons
     */
    private static function findDirectSeasonMatch($periode, $annee) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $normalizedPeriode = strtolower(trim($periode));
            
            // Correspondances directes avec les saisons
            $seasonMappings = [
                'hiver' => 'hiver',
                'printemps' => 'printemps', 
                'ete' => 'ete',
                'été' => 'ete',
                'automne' => 'automne'
            ];
            
            if (isset($seasonMappings[$normalizedPeriode])) {
                $targetSeason = $seasonMappings[$normalizedPeriode];
                $seasonYear = ($targetSeason === 'hiver') ? ($annee - 1) : $annee;
                
                // Trouver la meilleure période pour cette saison via la jointure saisons
                $sql = "SELECT dp.code_periode, dp.nom_periode, dp.date_debut, dp.date_fin
                        FROM dim_periodes dp
                        INNER JOIN dim_saisons s ON dp.id_saison = s.id
                        WHERE s.saison = ? AND s.annee = ?
                        ORDER BY 
                            CASE 
                                WHEN dp.code_periode LIKE 'vacances_%' THEN 1
                                WHEN dp.code_periode LIKE 'weekend_%' THEN 2
                                ELSE 3
                            END,
                            dp.date_debut
                        LIMIT 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$targetSeason, $seasonYear]);
                $result = $stmt->fetch();
                
                if ($result) {
                    return [
                        'type' => 'business',
                        'code_periode' => $result['code_periode'],
                        'nom_periode' => $result['nom_periode'],
                        'date_debut' => $result['date_debut'],
                        'date_fin' => $result['date_fin'],
                        'annee' => $annee,
                        'saison' => $targetSeason
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erreur findDirectSeasonMatch: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Génère les termes de recherche intelligents basés sur l'input utilisateur
     * Combine des expansions logiques avec l'apprentissage automatique depuis la base
     */
    private static function generateSearchTerms($periode) {
        $periode = strtolower(trim($periode));
        $terms = [$periode]; // Terme original en premier
        
        // Étape 1: Apprendre depuis la base de données
        $learnedTerms = self::learnTermsFromDatabase($periode);
        $terms = array_merge($terms, $learnedTerms);
        
        // Étape 2: Expansions logiques de fallback
        $logicalExpansions = self::getLogicalExpansions($periode);
        $terms = array_merge($terms, $logicalExpansions);
        
        // Retirer les doublons et retourner
        return array_unique($terms);
    }
    
    /**
     * Apprend les termes de recherche directement depuis la base de données
     */
    private static function learnTermsFromDatabase($periode) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $terms = [];
            
            // Rechercher tous les mots dans les noms de périodes qui contiennent notre terme
            $sql = "SELECT DISTINCT code_periode, nom_periode FROM dim_periodes 
                    WHERE LOWER(code_periode) LIKE ? OR LOWER(nom_periode) LIKE ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%{$periode}%", "%{$periode}%"]);
            
            while ($row = $stmt->fetch()) {
                // Extraire les mots-clés des codes et noms trouvés
                $keywords = self::extractKeywords($row['code_periode'], $row['nom_periode']);
                $terms = array_merge($terms, $keywords);
            }
            
            // Recherche inversée : trouver des périodes qui pourraient correspondre sémantiquement
            $semanticTerms = self::findSemanticMatches($periode);
            $terms = array_merge($terms, $semanticTerms);
            
            return array_unique($terms);
            
        } catch (Exception $e) {
            error_log("Erreur learnTermsFromDatabase: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Extrait les mots-clés significatifs d'un code_periode et nom_periode
     */
    private static function extractKeywords($code, $nom) {
        $keywords = [];
        
        // Diviser les codes par underscore et traits d'union
        $codeParts = preg_split('/[_\-\s]+/', strtolower($code));
        $nomParts = preg_split('/[_\-\s\'"]+/', strtolower($nom));
        
        // Filtrer les mots trop courts ou non significatifs
        $stopWords = ['de', 'du', 'la', 'le', 'des', 'et', 'd'];
        
        foreach (array_merge($codeParts, $nomParts) as $part) {
            $part = trim($part);
            if (strlen($part) > 2 && !in_array($part, $stopWords)) {
                $keywords[] = $part;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Trouve des correspondances sémantiques pour des termes spéciaux
     */
    private static function findSemanticMatches($periode) {
        try {
            $db = self::getDB();
            $pdo = $db->getConnection();
            
            $semanticMappings = [
                'vacances' => "SELECT DISTINCT SUBSTRING(code_periode, 1, 3) as stem FROM dim_periodes WHERE code_periode LIKE 'vacances_%'",
                'weekend' => "SELECT DISTINCT SUBSTRING(code_periode, 1, 7) as stem FROM dim_periodes WHERE code_periode LIKE 'weekend_%'",
                'pont' => "SELECT DISTINCT SUBSTRING(code_periode, 1, 4) as stem FROM dim_periodes WHERE code_periode LIKE 'pont_%'"
            ];
            
            $matches = [];
            
            foreach ($semanticMappings as $pattern => $query) {
                if (strpos($periode, $pattern) !== false) {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    while ($row = $stmt->fetch()) {
                        $matches[] = $row['stem'];
                    }
                }
            }
            
            return $matches;
            
        } catch (Exception $e) {
            error_log("Erreur findSemanticMatches: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Expansions logiques de fallback (utilisées seulement si l'apprentissage automatique échoue)
     */
    private static function getLogicalExpansions($periode) {
        $basicExpansions = [
            'annee' => ['complete'],
            'année' => ['complete'],
            'ete' => ['juillet', 'aout'],
            'été' => ['juillet', 'aout'],
            'hiver' => ['février', 'décembre'],
            'printemps' => ['avril', 'mai'],
            'automne' => ['septembre', 'octobre']
        ];
        
        foreach ($basicExpansions as $key => $values) {
            if (strpos($periode, $key) !== false) {
                return $values;
            }
        }
        
        return [];
    }
    
    /**
     * Crée une période synthétique pour une saison
     */
    private static function createSyntheticSeasonPeriod($saison, $annee) {
        $config = self::SEASONS_CONFIG[$saison];
        
        // Calculer les dates de début et fin de saison
        $months = $config['months'];
        
        if ($saison === 'hiver') {
            // Hiver spécial : Dec N-1 -> Feb N
            $debut = ($annee - 1) . "-12-01 00:00:00";
            $fin = $annee . "-02-28 23:59:59";
        } else {
            $debutMois = str_pad($months[0], 2, '0', STR_PAD_LEFT);
            $finMois = str_pad($months[2], 2, '0', STR_PAD_LEFT);
            
            $debut = $annee . "-$debutMois-01 00:00:00";
            $fin = $annee . "-$finMois-31 23:59:59";
        }
        
        return [
            'type' => 'synthetic',
            'code_periode' => $saison,
            'nom_periode' => ucfirst($saison) . " $annee",
            'date_debut' => $debut,
            'date_fin' => $fin,
            'annee' => $annee,
            'is_synthetic' => true
        ];
    }
    
    /**
     * Retourne la période année complète
     */
    private static function getFullYearPeriod($annee) {
        return [
            'type' => 'synthetic',
            'code_periode' => 'annee_complete',
            'nom_periode' => "Année complète $annee",
            'date_debut' => "$annee-01-01 00:00:00",
            'date_fin' => "$annee-12-31 23:59:59",
            'annee' => $annee,
            'is_synthetic' => true
        ];
    }
    
    /**
     * Interface de compatibilité avec PeriodesManagerDB
     * Retourne le format attendu par les APIs existantes
     */
    public static function getDateRanges($annee, $periode, $context = 'auto') {
        $resolvedPeriod = self::resolvePeriod($periode, $annee, $context);
        
        if (!$resolvedPeriod) {
            // Fallback vers l'ancien système
            require_once __DIR__ . '/../api/periodes_manager_db.php';
            return PeriodesManagerDB::calculateDateRanges($annee, $periode);
        }
        
        return [
            'start' => $resolvedPeriod['date_debut'],
            'end' => $resolvedPeriod['date_fin'],
            'meta' => $resolvedPeriod // Informations additionnelles
        ];
    }
    
    /**
     * Retourne les informations sur la période actuelle (pour le dashboard)
     */
    public static function getCurrentPeriodInfo() {
        $now = new DateTime();
        $year = (int)$now->format('Y');
        $month = (int)$now->format('n');
        
        // Déterminer la saison actuelle
        if ($month >= 3 && $month <= 5) {
            $season = 'printemps';
        } elseif ($month >= 6 && $month <= 8) {
            $season = 'ete';
        } elseif ($month >= 9 && $month <= 11) {
            $season = 'automne';
        } else {
            $season = 'hiver';
        }
        
        // Résoudre vers la vraie période
        $resolvedPeriod = self::resolvePeriod($season, $year, 'user');
        
        return [
            'current_season' => $season,
            'current_year' => $year,
            'resolved_period' => $resolvedPeriod,
            'display_name' => ucfirst($season) . " $year"
        ];
    }
    
    /**
     * Liste toutes les options disponibles (saisons + périodes métier)
     */
    public static function getAllAvailableOptions($annee, $context = 'user') {
        $options = [];
        
        // Ajouter les 4 saisons
        if ($context === 'user' || $context === 'hybrid') {
            $options['seasons'] = [
                'printemps' => ['name' => 'Printemps', 'type' => 'season'],
                'ete' => ['name' => 'Été', 'type' => 'season'],
                'automne' => ['name' => 'Automne', 'type' => 'season'],
                'hiver' => ['name' => 'Hiver', 'type' => 'season']
            ];
        }
        
        // Ajouter les périodes métier
        if ($context === 'business' || $context === 'hybrid') {
            try {
                require_once __DIR__ . '/../api/periodes_manager_db.php';
                $businessPeriods = PeriodesManagerDB::getAvailablePeriodesForYear($annee);
                
                $options['business'] = [];
                foreach ($businessPeriods as $code => $info) {
                    $options['business'][$code] = [
                        'name' => $info['nom'],
                        'type' => 'business',
                        'dates' => $info['debut'] . ' - ' . $info['fin']
                    ];
                }
            } catch (Exception $e) {
                error_log("Erreur getAllAvailableOptions: " . $e->getMessage());
            }
        }
        
        return $options;
    }
}
?>