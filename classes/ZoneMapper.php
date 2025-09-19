<?php
/**
 * ZoneMapper - Classe utilitaire pour le mapping des zones d'observation
 * Centralise la logique de conversion entre les noms d'affichage et les noms en base
 */

class ZoneMapper {
    
    /**
     * Détecte si on est en environnement de production
     */
    private static function isProductionEnvironment() {
        // Méthodes de détection multiples pour plus de robustesse
        
        // 1. Vérifier le nom d'hôte
        $hostname = gethostname();
        if (strpos($hostname, 'srv.cantal') !== false || strpos($hostname, 'observatoire') !== false) {
            return true;
        }
        
        // 2. Vérifier les variables d'environnement
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            if (strpos($host, 'srv.cantal-destination.com') !== false || 
                strpos($host, 'observatoire.cantal-destination.com') !== false) {
                return true;
            }
        }
        
        // 3. Vérifier le chemin du serveur
        if (isset($_SERVER['SERVER_NAME'])) {
            $server = $_SERVER['SERVER_NAME'];
            if (strpos($server, 'cantal-destination.com') !== false) {
                return true;
            }
        }
        
        // 4. Vérifier la présence de fichiers spécifiques à la production
        if (file_exists('/home/observatoire/public_html')) {
            return true;
        }
        
        // Par défaut, considérer comme développement
        return false;
    }
    
    /**
     * Mapping des noms d'affichage vers les noms en base de données
     * Utilisé par les APIs pour convertir les paramètres reçus
     * 
     * Mapping selon spécifications utilisateur :
     * CABA → Pays d'Aurillac, Cantal → Cantal, Carlades → Carlades,
     * Châtaigneraie → Châtaigneraie, Gentiane → Haut Cantal, HTC → Hautes Terres,
     * Pays de Mauriac → Pays de Mauriac, Pays Saint Flour → Pays Saint Flour,
     * Pays Salers → Pays Salers, Station → Lioran, Val Truyère → Val Truyère
     */
    private static $displayToBaseMapping = [
        // ✅ Mappings principaux selon le tableau de spécifications
        'PAYS D\'AURILLAC' => 'CABA', // CABA et CA DU BASSIN D'AURILLAC → Pays d'Aurillac
        "PAYS D'AURILLAC" => 'CABA', // Variante avec apostrophe droite
        'CANTAL' => 'CANTAL', // Cantal → Cantal (inchangé)
        'CARLADES' => 'CARLADES', // Carlades → Carlades (inchangé)
        'CHÂTAIGNERAIE' => 'CHÂTAIGNERAIE', // Châtaigneraie → Châtaigneraie (inchangé)
        'CHATAIGNERAIE' => 'CHÂTAIGNERAIE', // Variante sans accent
        'HAUT CANTAL' => 'GENTIANE', // Gentiane → Haut Cantal
        'HAUT-CANTAL' => 'GENTIANE', // Variante avec tiret
        'HAUTCANTAL' => 'GENTIANE', // Variante sans espace
        'HAUTES TERRES' => 'HTC', // HTC → Hautes Terres
        'HAUTES-TERRES' => 'HTC', // Variante avec tiret
        'HAUTESTERRES' => 'HTC', // Variante sans espace
        'PAYS DE MAURIAC' => 'PAYS DE MAURIAC', // Pays de Mauriac → Pays de Mauriac (inchangé)
        'PAYS SAINT FLOUR' => 'PAYS SAINT FLOUR', // Pays Saint Flour → Pays Saint Flour (inchangé)
        'PAYS SALERS' => 'PAYS SALERS', // Pays Salers → Pays Salers (inchangé)
        'LIORAN' => 'STATION', // Station → Lioran
        'VAL TRUYÈRE' => 'VAL TRUYÈRE', // Val Truyère → Val Truyère (inchangé)
        'VAL TRUYERE' => 'VAL TRUYÈRE', // Variante sans accent
        
        // ✅ Mappings pour caractères spéciaux des CSV (�)
        'PAYS D�AURILLAC' => 'CABA', // Variante caractère spécial CSV (�)
        'PAYS DAURILLAC' => 'CABA', // Variante sans apostrophe (encodage CSV)
        'CH�TAIGNERAIE' => 'CHÂTAIGNERAIE', // Variante caractère spécial CSV (�)
        'CHATAIGNERAIE' => 'CHÂTAIGNERAIE', // Variante sans accent (encodage CSV)
        'VAL TRUY�RE' => 'VAL TRUYÈRE', // Variante caractère spécial CSV (�)
        'VAL TRUYERE' => 'VAL TRUYÈRE', // Variante sans accent (encodage CSV)

        // ✅ Mappings de compatibilité descendante (noms en base vers eux-mêmes)
        'CABA' => 'CABA',
        'GENTIANE' => 'GENTIANE',
        'HTC' => 'HTC',
        'STATION' => 'STATION',

        // ✅ Mappings pour les doublons/variantes en base
        'CA DU BASSIN D\'AURILLAC' => 'CABA', // Doublon de CABA
        "CA DU BASSIN D'AURILLAC" => 'CABA', // Variante avec apostrophe droite
        'CC DU CARLADE' => 'CARLADES', // Variante de CARLADES
        'CC DU PAYS DE SALERS' => 'PAYS SALERS', // Variante de PAYS SALERS
        'SAINT FLOUR COMMUNAUTE' => 'PAYS SAINT FLOUR', // Variante de PAYS SAINT FLOUR
        'ST FLOUR COMMUNAUTE' => 'PAYS SAINT FLOUR', // Autre variante
        'STATION DE SKI' => 'STATION', // Variante de STATION
        'VALLEE DE LA TRUYERE' => 'VAL TRUYÈRE', // Variante de VAL TRUYÈRE

        // ✅ Zones exclues mais gardées pour compatibilité
        'HAUTES TERRES COMMUNAUTE' => 'HAUTES TERRES COMMUNAUTE',
        'STATION THERMALE DE CHAUDES-AIGUES' => 'STATION THERMALE DE CHAUDES-AIGUES',
        'CC SUMENE ARTENSE' => 'CC SUMENE ARTENSE',
        'RESTE DEPARTEMENT' => 'RESTE DEPARTEMENT',
        'CCSA' => 'CCSA'
    ];
    
    /**
     * Mapping des noms en base vers les noms d'affichage
     * Utilisé par filters_mysql.php pour l'affichage dans l'interface
     * 
     * Mapping selon spécifications utilisateur (base → affichage) :
     * CABA → Pays d'Aurillac, CANTAL → Cantal, CARLADES → Carlades,
     * CHÂTAIGNERAIE → Châtaigneraie, GENTIANE → Haut Cantal, HTC → Hautes Terres,
     * PAYS DE MAURIAC → Pays de Mauriac, PAYS SAINT FLOUR → Pays Saint Flour,
     * PAYS SALERS → Pays Salers, STATION → Lioran, VAL TRUYÈRE → Val Truyère
     */
    private static $baseToDisplayMapping = [
        // ✅ Mappings principaux selon le tableau de spécifications (base → affichage)
        'CABA' => 'PAYS D\'AURILLAC', // CABA → Pays d'Aurillac
        'CANTAL' => 'CANTAL', // CANTAL → Cantal (inchangé)
        'CARLADES' => 'CARLADES', // CARLADES → Carlades (inchangé)
        'CHÂTAIGNERAIE' => 'CHÂTAIGNERAIE', // CHÂTAIGNERAIE → Châtaigneraie (inchangé)
        'GENTIANE' => 'HAUT CANTAL', // GENTIANE → Haut Cantal
        'HTC' => 'HAUTES TERRES', // HTC → Hautes Terres
        'PAYS DE MAURIAC' => 'PAYS DE MAURIAC', // PAYS DE MAURIAC → Pays de Mauriac (inchangé)
        'PAYS SAINT FLOUR' => 'PAYS SAINT FLOUR', // PAYS SAINT FLOUR → Pays Saint Flour (inchangé)
        'PAYS SALERS' => 'PAYS SALERS', // PAYS SALERS → Pays Salers (inchangé)
        'STATION' => 'LIORAN', // STATION → Lioran
        'VAL TRUYÈRE' => 'VAL TRUYÈRE', // VAL TRUYÈRE → Val Truyère (inchangé)

        // ✅ Mappings pour les doublons/variantes en base (tous vers le même affichage)
        'CA DU BASSIN D\'AURILLAC' => 'PAYS D\'AURILLAC', // Doublon de CABA → Pays d'Aurillac
        "CA DU BASSIN D'AURILLAC" => 'PAYS D\'AURILLAC', // Variante apostrophe droite
        'CC DU CARLADE' => 'CARLADES', // Variante de CARLADES
        'CC DU PAYS DE SALERS' => 'PAYS SALERS', // Variante de PAYS SALERS
        'SAINT FLOUR COMMUNAUTE' => 'PAYS SAINT FLOUR', // Variante de PAYS SAINT FLOUR
        'ST FLOUR COMMUNAUTE' => 'PAYS SAINT FLOUR', // Autre variante
        'STATION DE SKI' => 'LIORAN', // Variante de STATION → Lioran
        'VALLEE DE LA TRUYERE' => 'VAL TRUYÈRE', // Variante de VAL TRUYÈRE

        // ✅ Zones exclues mais gardées pour compatibilité (pas d'affichage souhaité)
        'HAUTES TERRES COMMUNAUTE' => 'HAUTES TERRES COMMUNAUTE',
        'STATION THERMALE DE CHAUDES-AIGUES' => 'STATION THERMALE DE CHAUDES-AIGUES',
        'CC SUMENE ARTENSE' => 'CC SUMENE ARTENSE',
        'RESTE DEPARTEMENT' => 'RESTE DEPARTEMENT',
        'CCSA' => 'CCSA'
    ];
    
    /**
     * Convertit un nom d'affichage vers le nom en base de données
     * Utilisé par les APIs pour chercher les données
     *
     * @param string $displayName Le nom tel qu'affiché dans l'interface
     * @param int|null $year L'année (paramètre conservé pour compatibilité future)
     * @return string Le nom tel qu'il existe en base de données
     */
    public static function displayToBase($displayName, $year = null) {
        $normalized = strtoupper(trim($displayName));
        
        // Détection d'environnement pour adapter les mappings
        $is_production = self::isProductionEnvironment();
        
        if ($is_production) {
            // PRODUCTION : Utiliser les nouveaux noms directement
            $production_mappings = [
                'HAUT CANTAL' => 'HAUT CANTAL',           // Direct en production
                'HAUTES TERRES' => 'HAUTES TERRES',  // Mapping corrigé vers la zone avec données 2023-2025  
                'PAYS D\'AURILLAC' => 'PAYS D\'AURILLAC', // Direct en production
                "PAYS D'AURILLAC" => 'PAYS D\'AURILLAC',  // Variante apostrophe
                'LIORAN' => 'LIORAN',                     // Direct en production
                
                // Autres zones inchangées
                'CANTAL' => 'CANTAL',
                'CARLADES' => 'CARLADES', 
                'CHÂTAIGNERAIE' => 'CHÂTAIGNERAIE',
                'CHATAIGNERAIE' => 'CHÂTAIGNERAIE',
                'PAYS DE MAURIAC' => 'PAYS DE MAURIAC',
                'PAYS SAINT FLOUR' => 'PAYS SAINT FLOUR',
                'PAYS SALERS' => 'PAYS SALERS',
                'VAL TRUYÈRE' => 'VAL TRUYÈRE',
                'VAL TRUYERE' => 'VAL TRUYÈRE'
            ];
            
            return $production_mappings[$normalized] ?? $normalized;
        } else {
            // DÉVELOPPEMENT : Utiliser les anciens mappings
            return self::$displayToBaseMapping[$normalized] ?? $normalized;
        }
    }
    
    /**
     * Convertit un nom en base vers le nom d'affichage
     * Utilisé par filters_mysql.php pour l'affichage
     * 
     * @param string $baseName Le nom tel qu'il existe en base de données
     * @return string Le nom tel qu'il doit être affiché
     */
    public static function baseToDisplay($baseName) {
        $normalized = strtoupper(trim($baseName));
        return self::$baseToDisplayMapping[$normalized] ?? $normalized;
    }
    
    /**
     * Applique le mapping d'affichage à un tableau de zones
     * Utilisé par filters_mysql.php
     * 
     * @param array $zones Tableau de noms de zones en base
     * @return array Tableau de noms de zones pour l'affichage
     */
    public static function mapZonesForDisplay($zones) {
        $mapped = array_map([self::class, 'baseToDisplay'], $zones);
        // Supprimer les doublons après mapping
        return array_unique($mapped);
    }
    
    /**
     * Récupère tous les mappings disponibles pour debug
     * 
     * @return array Tous les mappings
     */
    public static function getAllMappings() {
        return [
            'display_to_base' => self::$displayToBaseMapping,
            'base_to_display' => self::$baseToDisplayMapping
        ];
    }
    
    /**
     * Vérifie si une zone a un mapping
     * 
     * @param string $zoneName Nom de la zone
     * @param string $direction 'display_to_base' ou 'base_to_display'
     * @return bool True si la zone a un mapping
     */
    public static function hasMapping($zoneName, $direction = 'display_to_base') {
        $normalized = strtoupper(trim($zoneName));
        
        if ($direction === 'display_to_base') {
            return isset(self::$displayToBaseMapping[$normalized]);
        } else {
            return isset(self::$baseToDisplayMapping[$normalized]);
        }
    }
    
    /**
     * Cache des IDs de zones pour optimiser les performances
     */
    private static $zoneIdCache = [];

    /**
     * Récupère l'ID de zone depuis la base de données
     * Convertit automatiquement le nom d'affichage vers le nom en base
     *
     * @param string $displayName Le nom tel qu'affiché dans l'interface
     * @param PDO $pdo Connexion PDO à la base de données
     * @return int|null L'ID de la zone ou null si non trouvée
     */
    public static function getZoneId($displayName, $pdo = null) {
        // Vérifier le cache d'abord
        if (isset(self::$zoneIdCache[$displayName])) {
            return self::$zoneIdCache[$displayName];
        }

        // Convertir le nom d'affichage vers le nom en base
        $baseName = self::displayToBase($displayName);

        // Si pas de PDO fourni, essayer de se connecter
        if ($pdo === null) {
            try {
                require_once __DIR__ . '/../database.php';
                $db = getCantalDestinationDatabase();
                $pdo = $db->getConnection();
            } catch (Exception $e) {
                throw new Exception("Impossible de se connecter à la base de données: " . $e->getMessage());
            }
        }

        // Récupérer l'ID de la zone
        $stmt = $pdo->prepare("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?");
        $stmt->execute([$baseName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $zoneId = $result ? (int)$result['id_zone'] : null;

        // Mettre en cache
        self::$zoneIdCache[$displayName] = $zoneId;

        return $zoneId;
    }

    /**
     * Vide le cache des IDs de zones
     * À utiliser après des modifications de la base de données
     */
    public static function clearCache() {
        self::$zoneIdCache = [];
    }

    /**
     * Récupère toutes les zones disponibles depuis la base
     *
     * @param PDO $pdo Connexion PDO à la base de données
     * @return array Liste des zones [id => nom]
     */
    public static function getAllZones($pdo = null) {
        // Si pas de PDO fourni, essayer de se connecter
        if ($pdo === null) {
            try {
                require_once __DIR__ . '/../database.php';
                $db = getCantalDestinationDatabase();
                $pdo = $db->getConnection();
            } catch (Exception $e) {
                throw new Exception("Impossible de se connecter à la base de données: " . $e->getMessage());
            }
        }

        $stmt = $pdo->query("SELECT id_zone, nom_zone FROM dim_zones_observation ORDER BY nom_zone");
        $zones = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $zones[$row['id_zone']] = $row['nom_zone'];
        }

        return $zones;
    }

    /**
     * Valide qu'une zone existe dans la base de données
     *
     * @param string $zoneName Nom de la zone
     * @param PDO $pdo Connexion PDO à la base de données
     * @return bool True si la zone existe
     */
    public static function zoneExists($zoneName, $pdo = null) {
        $zoneId = self::getZoneId($zoneName, $pdo);
        return $zoneId !== null;
    }

    /**
     * Retourne tous les noms de zones à inclure pour les requêtes historiques
     * Gère les cas où une zone a été renommée ou fusionnée
     *
     * @param string $displayName Nom affiché de la zone
     * @return array Liste des noms de zones à inclure dans les requêtes
     */
    public static function getHistoricalZoneNames($displayName) {
        $normalized = self::normalizeZoneName($displayName);
        
        // Zones avec historique complexe
        $historical_mappings = [
            'HAUTES TERRES' => ['HAUTES TERRES', 'HAUTES TERRES COMMUNAUTE']
        ];
        
        if (isset($historical_mappings[$normalized])) {
            return $historical_mappings[$normalized];
        }
        
        // Par défaut, retourner juste le mapping standard
        $standardMapping = self::mapToDatabase($displayName);
        return [$standardMapping];
    }
}
