<?php
/**
 * Gestionnaire de Cache Unifié FluxVision
 * Gère les caches pour les infographies ET le tableau de bord
 * Avec noms de fichiers lisibles et organisation par sous-dossiers
 */

class CantalDestinationCacheManager {
    private $cacheBaseDir;
    private $categoryDirs;
    private $defaultTtl;

    public function __construct($baseDir = null) {
        // Utiliser le cache principal du projet
        $this->cacheBaseDir = $baseDir ?: dirname(dirname(__DIR__)) . '/cache';
        
        // Définir les sous-dossiers par catégorie
        $this->categoryDirs = [
            // Infographies
            'infographie_departements' => 'infographie/departements',
            'infographie_regions' => 'infographie/regions',
            'infographie_pays' => 'infographie/pays',
            'infographie_communes_excursion' => 'infographie/communes_excursion',
            'infographie_indicateurs_cles' => 'infographie/indicateurs_cles',
            'infographie_periodes' => 'infographie/periodes',
            
            // Tableau de bord - Touristes
            'tdb_departements_touristes' => 'tableau_bord/departements_touristes',
            'tdb_regions_touristes' => 'tableau_bord/regions_touristes',
            'tdb_pays_touristes' => 'tableau_bord/pays_touristes',
            'tdb_age_touristes' => 'tableau_bord/age_touristes',
            'tdb_csp_touristes' => 'tableau_bord/csp_touristes',
            
            // Tableau de bord - Excursionnistes  
            'tdb_departements_excursionnistes' => 'tableau_bord/departements_excursionnistes',
            'tdb_regions_excursionnistes' => 'tableau_bord/regions_excursionnistes',
            'tdb_pays_excursionnistes' => 'tableau_bord/pays_excursionnistes',
            'tdb_age_excursionnistes' => 'tableau_bord/age_excursionnistes',
            'tdb_csp_excursionnistes' => 'tableau_bord/csp_excursionnistes',
            
            // APIs de comparaison
            'tdb_comparison' => 'tableau_bord/comparison',
            'tdb_filters' => 'tableau_bord/filters'
        ];

        // TTL par catégorie (en secondes) - OPTIMISÉS pour performance
        $this->defaultTtl = [
            // Infographies - cache très long car données peu volatiles
            'infographie_departements' => 14400,   // 4h (données stables)
            'infographie_regions' => 14400,        // 4h (données stables)
            'infographie_pays' => 21600,           // 6h (données très stables, requête lourde)
            'infographie_communes_excursion' => 14400, // 4h (données stables)
            'infographie_indicateurs_cles' => 7200, // 2h
            'infographie_periodes' => 86400,       // 24h (très stable)
            
            // Tableau de bord - cache modéré
            'tdb_departements_touristes' => 3600,      // 1h
            'tdb_regions_touristes' => 3600,           // 1h
            'tdb_pays_touristes' => 3600,              // 1h
            'tdb_age_touristes' => 7200,               // 2h (plus stable)
            'tdb_csp_touristes' => 7200,               // 2h (plus stable)
            'tdb_departements_excursionnistes' => 3600, // 1h
            'tdb_regions_excursionnistes' => 3600,     // 1h
            'tdb_pays_excursionnistes' => 3600,        // 1h
            'tdb_age_excursionnistes' => 7200,         // 2h (plus stable)
            'tdb_csp_excursionnistes' => 7200,         // 2h (plus stable)
            
            // Comparaisons - cache court car plus dynamique
            'tdb_comparison' => 1800,               // 30min
            'tdb_filters' => 3600                   // 1h
        ];
    }

    /**
     * Génère un nom de fichier lisible basé sur les paramètres
     */
    private function generateReadableFilename($category, $params) {
        // Extraire les informations importantes
        $zone = $params['zone'] ?? 'cantal';
        $annee = $params['annee'] ?? date('Y');
        $periode = $params['periode'] ?? 'annee';
        $limit = isset($params['limit']) ? "limit{$params['limit']}" : '';
        
        // Nettoyer les valeurs
        $zone = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $zone));
        $periode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $periode));
        
        // Déterminer le type depuis la catégorie
        $type = '';
        if (strpos($category, 'excursionnistes') !== false) {
            $type = 'excursionnistes';
        } elseif (strpos($category, 'touristes') !== false) {
            $type = 'touristes';
        } elseif (strpos($category, 'infographie') !== false) {
            $type = strpos($category, 'excursionnistes') !== false ? 'excursionnistes' : 'touristes';
        }
        
        // Générer un hash court pour l'unicité
        $hashSource = serialize($params);
        $shortHash = substr(md5($hashSource), 0, 8);
        
        // Construire le nom de fichier
        $parts = array_filter([$type, $zone, $annee, $periode, $limit]);
        $filename = implode('_', $parts) . '_' . $shortHash . '.json';
        
        return $filename;
    }

    /**
     * Obtient un élément du cache
     */
    public function get($category, $params = []) {
        if (!isset($this->categoryDirs[$category])) {
            return null;
        }

        $cacheDir = $this->cacheBaseDir . '/' . $this->categoryDirs[$category];
        $filename = $this->generateReadableFilename($category, $params);
        $filepath = $cacheDir . '/' . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        // Vérifier TTL
        $ttl = $this->defaultTtl[$category] ?? 3600;
        if ((time() - filemtime($filepath)) > $ttl) {
            unlink($filepath);
            return null;
        }

        $content = file_get_contents($filepath);
        return $content ? json_decode($content, true) : null;
    }

    /**
     * Stocke un élément dans le cache
     */
    public function set($category, $params = [], $data = null) {
        if (!isset($this->categoryDirs[$category]) || $data === null) {
            return false;
        }

        $cacheDir = $this->cacheBaseDir . '/' . $this->categoryDirs[$category];
        
        // Créer le dossier si nécessaire
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $filename = $this->generateReadableFilename($category, $params);
        $filepath = $cacheDir . '/' . $filename;

        return file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Invalide les caches d'une catégorie
     */
    public function invalidate($category = null, $pattern = null) {
        if ($category && isset($this->categoryDirs[$category])) {
            $cacheDir = $this->cacheBaseDir . '/' . $this->categoryDirs[$category];
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/' . ($pattern ?: '*') . '.json');
                foreach ($files as $file) {
                    unlink($file);
                }
                return count($files);
            }
        } elseif (!$category) {
            // Invalider tout le cache
            $totalDeleted = 0;
            foreach ($this->categoryDirs as $cat => $dir) {
                $totalDeleted += $this->invalidate($cat, $pattern);
            }
            return $totalDeleted;
        }
        
        return 0;
    }

    /**
     * Nettoie les caches expirés
     */
    public function cleanup() {
        $totalCleaned = 0;
        
        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            if (!is_dir($cacheDir)) continue;
            
            $ttl = $this->defaultTtl[$category] ?? 3600;
            $files = glob($cacheDir . '/*.json');
            
            foreach ($files as $file) {
                if ((time() - filemtime($file)) > $ttl) {
                    unlink($file);
                    $totalCleaned++;
                }
            }
        }
        
        return $totalCleaned;
    }

    /**
     * Statistiques du cache
     */
    public function getStats() {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'categories' => []
        ];

        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            $categoryStats = [
                'files' => 0,
                'size' => 0,
                'expired' => 0,
                'ttl' => $this->defaultTtl[$category] ?? 3600
            ];

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*.json');
                $ttl = $this->defaultTtl[$category] ?? 3600;
                
                foreach ($files as $file) {
                    $categoryStats['files']++;
                    $size = filesize($file);
                    $categoryStats['size'] += $size;
                    
                    if ((time() - filemtime($file)) > $ttl) {
                        $categoryStats['expired']++;
                    }
                }
            }

            $stats['categories'][$category] = $categoryStats;
            $stats['total_files'] += $categoryStats['files'];
            $stats['total_size'] += $categoryStats['size'];
        }

        return $stats;
    }

    /**
     * Purge quotidienne - Supprime tous les caches de l'année actuelle
     * À exécuter à minuit pour garantir des données fraîches
     */
    public function dailyPurge($year = null) {
        $year = $year ?? date('Y');
        $totalPurged = 0;
        
        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            if (!is_dir($cacheDir)) continue;
            
            $files = glob($cacheDir . '/*.json');
            
            foreach ($files as $file) {
                $filename = basename($file);
                
                // Vérifier si le fichier contient l'année actuelle
                if (strpos($filename, "_{$year}_") !== false || 
                    strpos($filename, "_{$year}.json") !== false ||
                    preg_match("/_{$year}[^0-9]/", $filename)) {
                    
                    unlink($file);
                    $totalPurged++;
                }
            }
        }
        
        return $totalPurged;
    }

    /**
     * Purge sélective par année et zone
     */
    public function purgeByYearAndZone($year = null, $zone = null) {
        $year = $year ?? date('Y');
        $zone = $zone ? strtolower($zone) : null;
        $totalPurged = 0;
        
        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            if (!is_dir($cacheDir)) continue;
            
            $files = glob($cacheDir . '/*.json');
            
            foreach ($files as $file) {
                $filename = basename($file);
                
                // Vérifier l'année
                $yearMatch = strpos($filename, "_{$year}_") !== false || 
                           strpos($filename, "_{$year}.json") !== false ||
                           preg_match("/_{$year}[^0-9]/", $filename);
                
                // Vérifier la zone si spécifiée
                $zoneMatch = !$zone || strpos($filename, "_{$zone}_") !== false;
                
                if ($yearMatch && $zoneMatch) {
                    unlink($file);
                    $totalPurged++;
                }
            }
        }
        
        return $totalPurged;
    }

    /**
     * Méthode de compatibilité pour l'ancien nom de classe
     */
    public static function getInstance($baseDir = null) {
        return new self($baseDir);
    }
}

// Alias pour compatibilité avec l'ancien code d'infographie
class InfographieCacheManager extends CantalDestinationCacheManager {
    // Héritage direct, toutes les méthodes sont disponibles
}
?> 