<?php
/**
 * Gestionnaire de cache unifié pour les infographies et tableaux de bord FluxVision.
 */

class CantalDestinationCacheManager {
    private $cacheBaseDir;
    private $categoryDirs;
    private $defaultTtl;
    private static $memoryCache = [];
    private static $lastLookupMeta = null;

    public function __construct($baseDir = null) {
        $this->cacheBaseDir = $baseDir ?: dirname(dirname(__DIR__)) . '/cache';

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

            // Comparaisons et filtres
            'tdb_comparison' => 'tableau_bord/comparison',
            'tdb_filters' => 'tableau_bord/filters'
        ];

        $this->defaultTtl = [
            'infographie_departements' => 14400,
            'infographie_regions' => 14400,
            'infographie_pays' => 21600,
            'infographie_communes_excursion' => 14400,
            'infographie_indicateurs_cles' => 7200,
            'infographie_periodes' => 86400,

            'tdb_departements_touristes' => 3600,
            'tdb_regions_touristes' => 3600,
            'tdb_pays_touristes' => 3600,
            'tdb_age_touristes' => 7200,
            'tdb_csp_touristes' => 7200,
            'tdb_departements_excursionnistes' => 3600,
            'tdb_regions_excursionnistes' => 3600,
            'tdb_pays_excursionnistes' => 3600,
            'tdb_age_excursionnistes' => 7200,
            'tdb_csp_excursionnistes' => 7200,

            'tdb_comparison' => 1800,
            'tdb_filters' => 3600
        ];
    }

    private function buildCacheKey(string $category, array $params): string {
        ksort($params);
        return $category . ':' . md5(json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    public function getCategoryTtl(string $category): int {
        return $this->defaultTtl[$category] ?? 3600;
    }

    public static function getLastLookupMeta(): ?array {
        return self::$lastLookupMeta;
    }

    private function generateReadableFilename($category, $params) {
        $zone = $params['zone'] ?? 'cantal';
        $annee = $params['annee'] ?? date('Y');
        $periode = $params['periode'] ?? 'annee';
        $limit = isset($params['limit']) ? 'limit' . $params['limit'] : '';

        $zone = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $zone));
        $periode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $periode));

        $type = '';
        if (strpos($category, 'excursionnistes') !== false) {
            $type = 'excursionnistes';
        } elseif (strpos($category, 'touristes') !== false) {
            $type = 'touristes';
        } elseif (strpos($category, 'infographie') !== false) {
            $type = strpos($category, 'excursionnistes') !== false ? 'excursionnistes' : 'touristes';
        }

        $hashSource = serialize($params);
        $shortHash = substr(md5($hashSource), 0, 8);

        $parts = array_filter([$type, $zone, $annee, $periode, $limit]);
        return implode('_', $parts) . '_' . $shortHash . '.json';
    }

    public function get($category, $params = []) {
        if (!isset($this->categoryDirs[$category])) {
            self::$lastLookupMeta = null;
            return null;
        }

        $cacheKey = $this->buildCacheKey($category, $params);
        $ttl = $this->getCategoryTtl($category);
        $now = time();

        if (isset(self::$memoryCache[$cacheKey])) {
            $entry = self::$memoryCache[$cacheKey];
            if (($entry['stored_at'] + $ttl) > $now) {
                self::$lastLookupMeta = [
                    'category' => $category,
                    'hit' => true,
                    'source' => 'memory',
                    'ttl' => $ttl,
                    'expires_at' => $entry['stored_at'] + $ttl
                ];
                return $entry['data'];
            }
            unset(self::$memoryCache[$cacheKey]);
        }

        $cacheDir = $this->cacheBaseDir . '/' . $this->categoryDirs[$category];
        $filename = $this->generateReadableFilename($category, $params);
        $filepath = $cacheDir . '/' . $filename;

        if (!file_exists($filepath)) {
            self::$lastLookupMeta = [
                'category' => $category,
                'hit' => false,
                'source' => 'miss',
                'ttl' => $ttl
            ];
            return null;
        }

        $mtime = filemtime($filepath) ?: $now;
        if (($now - $mtime) > $ttl) {
            @unlink($filepath);
            self::$lastLookupMeta = [
                'category' => $category,
                'hit' => false,
                'source' => 'expired',
                'ttl' => $ttl
            ];
            return null;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            self::$lastLookupMeta = [
                'category' => $category,
                'hit' => false,
                'source' => 'read_error',
                'ttl' => $ttl
            ];
            return null;
        }

        $decoded = json_decode($content, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            self::$lastLookupMeta = [
                'category' => $category,
                'hit' => false,
                'source' => 'decode_error',
                'ttl' => $ttl
            ];
            return null;
        }

        self::$memoryCache[$cacheKey] = [
            'data' => $decoded,
            'stored_at' => $mtime
        ];
        self::$lastLookupMeta = [
            'category' => $category,
            'hit' => true,
            'source' => 'disk',
            'ttl' => $ttl,
            'expires_at' => $mtime + $ttl
        ];

        return $decoded;
    }

    public function set($category, $params = [], $data = null) {
        if (!isset($this->categoryDirs[$category]) || $data === null) {
            return false;
        }

        $cacheDir = $this->cacheBaseDir . '/' . $this->categoryDirs[$category];
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $filename = $this->generateReadableFilename($category, $params);
        $filepath = $cacheDir . '/' . $filename;

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            return false;
        }

        if (file_put_contents($filepath, $encoded, LOCK_EX) === false) {
            return false;
        }

        $now = time();
        $ttl = $this->getCategoryTtl($category);
        $cacheKey = $this->buildCacheKey($category, $params);
        self::$memoryCache[$cacheKey] = [
            'data' => $data,
            'stored_at' => $now
        ];
        self::$lastLookupMeta = [
            'category' => $category,
            'hit' => false,
            'source' => 'write',
            'ttl' => $ttl,
            'expires_at' => $now + $ttl
        ];

        return true;
    }

    public function invalidate($category = null, $pattern = null) {
        if ($category && isset($this->categoryDirs[$category])) {
            $cacheDir = $this->cacheBaseDir . '/' . $this->categoryDirs[$category];
            $deleted = 0;

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/' . ($pattern ?: '*') . '.json');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }

            $prefix = $category . ':';
            foreach (array_keys(self::$memoryCache) as $key) {
                if (strpos($key, $prefix) === 0) {
                    unset(self::$memoryCache[$key]);
                }
            }

            return $deleted;
        }

        if ($category === null) {
            self::$memoryCache = [];
            $totalDeleted = 0;
            foreach ($this->categoryDirs as $cat => $dir) {
                $totalDeleted += $this->invalidate($cat, $pattern);
            }
            return $totalDeleted;
        }

        return 0;
    }

    public function cleanup() {
        $totalCleaned = 0;
        $now = time();

        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            if (!is_dir($cacheDir)) {
                continue;
            }

            $ttl = $this->getCategoryTtl($category);
            $files = glob($cacheDir . '/*.json');

            foreach ($files as $file) {
                $mtime = filemtime($file) ?: $now;
                if (($now - $mtime) > $ttl) {
                    if (@unlink($file)) {
                        $totalCleaned++;
                    }
                }
            }

            $prefix = $category . ':';
            foreach (array_keys(self::$memoryCache) as $key) {
                if (strpos($key, $prefix) === 0) {
                    $entry = self::$memoryCache[$key];
                    if (($entry['stored_at'] + $ttl) <= $now) {
                        unset(self::$memoryCache[$key]);
                    }
                }
            }
        }

        return $totalCleaned;
    }

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
                'ttl' => $this->getCategoryTtl($category)
            ];

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*.json');
                $ttl = $this->getCategoryTtl($category);

                foreach ($files as $file) {
                    $categoryStats['files']++;
                    $categoryStats['size'] += filesize($file);

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

    public function dailyPurge($year = null) {
        $year = $year ?? date('Y');
        $totalPurged = 0;

        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            if (!is_dir($cacheDir)) {
                continue;
            }

            $files = glob($cacheDir . '/*.json');

            foreach ($files as $file) {
                $filename = basename($file);

                if (strpos($filename, "_{$year}_") !== false ||
                    strpos($filename, "_{$year}.json") !== false ||
                    preg_match("/_{$year}[^0-9]/", $filename)) {
                    if (@unlink($file)) {
                        $totalPurged++;
                    }
                }
            }
        }

        self::$memoryCache = [];
        return $totalPurged;
    }

    public function purgeByYearAndZone($year = null, $zone = null) {
        $year = $year ?? date('Y');
        $zone = $zone ? strtolower($zone) : null;
        $totalPurged = 0;

        foreach ($this->categoryDirs as $category => $dir) {
            $cacheDir = $this->cacheBaseDir . '/' . $dir;
            if (!is_dir($cacheDir)) {
                continue;
            }

            $files = glob($cacheDir . '/*.json');

            foreach ($files as $file) {
                $filename = basename($file);

                $yearMatch = strpos($filename, "_{$year}_") !== false ||
                           strpos($filename, "_{$year}.json") !== false ||
                           preg_match("/_{$year}[^0-9]/", $filename);

                $zoneMatch = !$zone || strpos($filename, "_{$zone}_") !== false;

                if ($yearMatch && $zoneMatch) {
                    if (@unlink($file)) {
                        $totalPurged++;
                    }
                }
            }
        }

        self::$memoryCache = [];
        return $totalPurged;
    }

    public static function getInstance($baseDir = null) {
        return new self($baseDir);
    }
}

class InfographieCacheManager extends CantalDestinationCacheManager {
}
?>
