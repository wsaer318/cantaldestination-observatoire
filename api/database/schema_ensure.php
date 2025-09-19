<?php
/**
 * CantalDestination - Admin API: ensure schema (whitelist only)
 * Fichier: api/database/schema_ensure.php
 * Garde-fous : API_SCHEMA_ENABLE=1 + admin + allowlist IP
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once dirname(dirname(__DIR__)) . '/classes/Auth.php';
require_once dirname(dirname(__DIR__)) . '/classes/Database.php';
require_once __DIR__ . '/_auth_helpers.php';

// Auth renforcée: admin + allowlist IP + rate-limit scope
require_api_auth_or_session([
    'require_admin' => true,
    'restrict_ips'  => true,
    'scope'         => 'schema.ensure'
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
}

if ((getenv('API_SCHEMA_ENABLE') ?: '') !== '1') {
    jsonResponse(['error' => "API Schema désactivée. Demandez à l'admin d'activer API_SCHEMA_ENABLE=1."], 403);
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        $len = strlen($needle); if ($len === 0) return true; return substr($haystack, -$len) === $needle;
    }
}

function ok_name(string $name): bool {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) return false;
    if (stripos($name, 'information_schema') !== false) return false;
    if (strpos($name, '..') !== false) return false;
    return true;
}

// === DDL builders (identiques à la version précédente, avec petites harmonisations) ===
function ddl_fact_simple(string $tbl): string { $t = "`$tbl`"; return "
CREATE TABLE IF NOT EXISTS $t (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq (date, id_zone, id_provenance, id_categorie),
  KEY idx_date_zone (date, id_zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"; }
function ddl_fact_dep(string $tbl): string { $t = "`$tbl`"; return "
CREATE TABLE IF NOT EXISTS $t (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  id_departement INT NOT NULL,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq (date, id_zone, id_provenance, id_categorie, id_departement),
  KEY idx_date_zone_dep (date, id_zone, id_departement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"; }
function ddl_fact_pays(string $tbl): string { $t = "`$tbl`"; return "
CREATE TABLE IF NOT EXISTS $t (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  id_pays INT NOT NULL,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq (date, id_zone, id_provenance, id_categorie, id_pays),
  KEY idx_date_zone_pays (date, id_zone, id_pays)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"; }
function ddl_sejours_duree(string $tbl): string { $t = "`$tbl`"; return "
CREATE TABLE IF NOT EXISTS $t (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  id_duree INT NOT NULL,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx1 (date, id_zone, id_duree),
  UNIQUE KEY uq_fact_sejours_duree (date, id_zone, id_provenance, id_categorie, id_duree)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"; }
function ddl_sejours_duree_dep(string $tbl): string { $t = "`$tbl`"; return "
CREATE TABLE IF NOT EXISTS $t (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  id_departement INT NOT NULL,
  id_duree INT NOT NULL,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx1 (date, id_zone, id_departement, id_duree),
  UNIQUE KEY uq_fact_sejours_duree_dept (date, id_zone, id_provenance, id_categorie, id_departement, id_duree)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"; }
function ddl_sejours_duree_pays(string $tbl): string { $t = "`$tbl`"; return "
CREATE TABLE IF NOT EXISTS $t (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  id_pays INT NOT NULL,
  id_duree INT NOT NULL,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx1 (date, id_zone, id_pays, id_duree),
  UNIQUE KEY uq_fact_sejours_duree_pays (date, id_zone, id_provenance, id_categorie, id_pays, id_duree)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"; }
function ddl_lieu(string $tbl, bool $withDep=false, bool $withPays=false): string {
    $t = "`$tbl`"; $extra = $withDep ? "id_departement INT NOT NULL," : ($withPays ? "id_pays INT NOT NULL," : "");
    $idx_geo = $withDep ? 'id_departement' : ($withPays ? 'id_pays' : 'id_zone');
    return "
CREATE TABLE IF NOT EXISTS $t (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  jour_semaine VARCHAR(10) NOT NULL,
  id_zone INT NOT NULL,
  id_provenance INT NOT NULL,
  id_categorie INT NOT NULL,
  $extra
  id_epci INT NOT NULL DEFAULT 0,
  id_commune INT NOT NULL DEFAULT 0,
  volume INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq (
    date,id_zone,id_provenance,id_categorie".
    ($withDep ? ",id_departement" : "").
    ($withPays ? ",id_pays" : "").
    ",id_epci,id_commune
  ),
  KEY idx_date_zone (date, id_zone),
  KEY idx_geo ($idx_geo),
  KEY idx_jour (jour_semaine),
  KEY idx_epci (id_epci),
  KEY idx_commune (id_commune)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}
function ddl_dim(string $name): ?string {
    switch ($name) {
        case 'dim_zones_observation': return "CREATE TABLE IF NOT EXISTS `dim_zones_observation` (id_zone INT AUTO_INCREMENT PRIMARY KEY, nom_zone VARCHAR(255) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_provenances':       return "CREATE TABLE IF NOT EXISTS `dim_provenances` (id_provenance INT AUTO_INCREMENT PRIMARY KEY, nom_provenance VARCHAR(255) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_categories_visiteur':return "CREATE TABLE IF NOT EXISTS `dim_categories_visiteur` (id_categorie INT AUTO_INCREMENT PRIMARY KEY, nom_categorie VARCHAR(255) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_pays':              return "CREATE TABLE IF NOT EXISTS `dim_pays` (id_pays INT AUTO_INCREMENT PRIMARY KEY, nom_pays VARCHAR(255) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_departements':      return "CREATE TABLE IF NOT EXISTS `dim_departements` (id_departement INT AUTO_INCREMENT PRIMARY KEY, nom_departement VARCHAR(255) UNIQUE, nom_region VARCHAR(255) NULL, nom_nouvelle_region VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_communes':          return "CREATE TABLE IF NOT EXISTS `dim_communes` (id_commune INT AUTO_INCREMENT PRIMARY KEY, code_insee VARCHAR(10) NOT NULL, nom_commune VARCHAR(255) NOT NULL, id_departement INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_commune_insee (code_insee), KEY idx_commune_dept (id_departement)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_epci':              return "CREATE TABLE IF NOT EXISTS `dim_epci` (id_epci INT AUTO_INCREMENT PRIMARY KEY, nom_epci VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_epci_nom (nom_epci)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_dates':             return "CREATE TABLE IF NOT EXISTS `dim_dates` (date DATE PRIMARY KEY, vacances_a TINYINT(1) DEFAULT 0, vacances_b TINYINT(1) DEFAULT 0, vacances_c TINYINT(1) DEFAULT 0, ferie TINYINT(1) DEFAULT 0, jour_semaine VARCHAR(10), mois TINYINT, annee SMALLINT, trimestre TINYINT, semaine TINYINT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        case 'dim_durees_sejour':     return "CREATE TABLE IF NOT EXISTS `dim_durees_sejour` (id_duree INT AUTO_INCREMENT PRIMARY KEY, libelle VARCHAR(50) NOT NULL, nb_nuits INT NOT NULL, ordre INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_duree_libelle (libelle), KEY idx_duree_nb (nb_nuits)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        default: return null;
    }
}
function ddl_for(string $base): ?string {
    $dims = ['dim_zones_observation','dim_provenances','dim_categories_visiteur','dim_pays','dim_departements','dim_communes','dim_epci','dim_dates','dim_durees_sejour'];
    if (in_array($base, $dims, true)) return ddl_dim($base);
    if ($base==='fact_diurnes' || $base==='fact_nuitees') return ddl_fact_simple($base);
    if ($base==='fact_diurnes_departements' || $base==='fact_nuitees_departements') return ddl_fact_dep($base);
    if ($base==='fact_diurnes_pays' || $base==='fact_nuitees_pays') return ddl_fact_pays($base);
    if ($base==='fact_sejours_duree') return ddl_sejours_duree($base);
    if ($base==='fact_sejours_duree_departements') return ddl_sejours_duree_dep($base);
    if ($base==='fact_sejours_duree_pays') return ddl_sejours_duree_pays($base);
    $LB = ['fact_lieu_activite_soir','fact_lieu_activite_veille','fact_lieu_nuitee_soir','fact_lieu_nuitee_veille'];
    foreach ($LB as $b) {
        if ($base === $b) return ddl_lieu($base, false, false);
        if ($base === $b.'_departement') return ddl_lieu($base, true, false);
        if ($base === $b.'_pays') return ddl_lieu($base, false, true);
    }
    return null;
}

try {
    $cfg = \DatabaseConfig::getConfig();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'],$cfg['port'],$cfg['database'],$cfg['charset']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $payload   = get_json_body();
    $preset    = $payload['preset'] ?? '';
    $tablesIn  = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
    $testMode  = want_test_mode($payload);

    $WH_DIMS = ['dim_zones_observation','dim_provenances','dim_categories_visiteur','dim_pays','dim_departements','dim_communes','dim_epci','dim_dates','dim_durees_sejour'];
    $WH_FACTS= ['fact_diurnes','fact_nuitees','fact_diurnes_departements','fact_nuitees_departements','fact_diurnes_pays','fact_nuitees_pays'];
    $WH_SEJ  = ['fact_sejours_duree','fact_sejours_duree_departements','fact_sejours_duree_pays'];
    $WH_LIEU = [
        'fact_lieu_activite_soir','fact_lieu_activite_soir_departement','fact_lieu_activite_soir_pays',
        'fact_lieu_activite_veille','fact_lieu_activite_veille_departement','fact_lieu_activite_veille_pays',
        'fact_lieu_nuitee_soir','fact_lieu_nuitee_soir_departement','fact_lieu_nuitee_soir_pays',
        'fact_lieu_nuitee_veille','fact_lieu_nuitee_veille_departement','fact_lieu_nuitee_veille_pays'
    ];

    $wanted = [];
    switch ($preset) {
        case 'dims':    $wanted = array_merge($wanted, $WH_DIMS); break;
        case 'facts':   $wanted = array_merge($wanted, $WH_FACTS); break;
        case 'sejours': $wanted = array_merge($wanted, $WH_SEJ); break;
        case 'lieu':    $wanted = array_merge($wanted, $WH_LIEU); break;
        case 'all':     $wanted = array_merge($WH_DIMS, $WH_FACTS, $WH_SEJ, $WH_LIEU); break;
        case '':        /* ok */ break;
        default:        jsonResponse(['error'=>'Preset invalide. Utilisez: dims, facts, lieu, sejours, all'], 400);
    }
    foreach ($tablesIn as $t) if (is_string($t)) $wanted[] = $t;
    $wanted = array_values(array_unique($wanted));

    if (empty($wanted)) jsonResponse(['error'=>'Aucune table demandée. Utilisez "preset" et/ou "tables".'], 400);

    $final = [];
    foreach ($wanted as $t) { if (!ok_name($t)) continue; $final[] = $testMode ? ($t . '_test') : $t; }
    $final = array_values(array_unique($final));

    $created = []; $existing = []; $failed = [];
    $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=:db AND table_name=:t');

    foreach ($final as $tbl) {
        if (!ok_name($tbl)) { $failed[] = ['table'=>$tbl, 'error'=>'nom invalide']; continue; }
        $base = str_ends_with($tbl, '_test') ? substr($tbl, 0, -5) : $tbl;
        $ddl = ddl_for($base);
        if ($ddl === null) { $failed[] = ['table'=>$tbl, 'error'=>'table non autorisée']; continue; }
        if ($base !== $tbl) $ddl = str_replace("`$base`", "`$tbl`", $ddl);

        $existsStmt->execute([':db' => $cfg['database'], ':t' => $tbl]);
        $already = (int)$existsStmt->fetchColumn() > 0;

        try {
            $pdo->exec($ddl);
            if ($base === 'dim_departements') {
                try { $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN nom_region VARCHAR(255) NULL"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN nom_nouvelle_region VARCHAR(255) NULL"); } catch (Throwable $e) {}
            }
            if ($already) $existing[] = $tbl; else $created[] = $tbl;
        } catch (Throwable $e) {
            $failed[] = ['table'=>$tbl, 'error'=>$e->getMessage()];
        }
    }

    $resp = [
        'success' => count($failed) === 0,
        'test_mode' => $testMode,
        'preset' => $preset ?: null,
        'tables_requested' => $final,
        'created' => $created,
        'existing' => $existing,
        'failed' => $failed,
        'count' => [
            'requested' => count($final),
            'created' => count($created),
            'existing' => count($existing),
            'failed' => count($failed),
        ]
    ];

    audit_log('schema.ensure', $resp['count']);
    jsonResponse($resp);

} catch (JsonException $e) {
    jsonResponse(['error' => 'JSON invalide', 'details' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('SCHEMA ENSURE API error: '.$e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}