<?php
/**
 * CantalDestination - Admin API: upsert de faits (fact_*)
 * Fichier: api/database/facts_upsert.php
 * Endpoint: POST /api/database/facts_upsert.php
 *
 * Sécurité: require_api_auth_or_session(scope: "facts.upsert")
 *  - Optionnellement IP allowlist et admin via variables d'env:
 *      API_FACTS_REQUIRE_ADMIN=1, API_FACTS_RESTRICT_IPS=1
 *
 * Payload JSON (exemples):
 * {
 *   "table": "fact_nuitees",              // voir la whitelist plus bas
 *   "rows": [
 *     {"date":"2024-02-12","nom_zone":"CANTAL","nom_provenance":"FRANCE","nom_categorie":"TOURISTE","volume":123},
 *     {"date":"2024-02-13","id_zone":1,"id_provenance":2,"id_categorie":1,"volume":456}
 *   ],
 *   "options": { "test_mode": true }
 * }
 *
 * Gestion des variantes:
 *  - *_departements : + (id_departement|nom_departement)
 *  - *_pays         : + (id_pays|nom_pays)
 *  - fact_sejours_duree* : + (id_duree | libelle_duree, nb_nuits)
 *  - fact_lieu_* : colonnes (date, jour_semaine, id_zone, id_provenance, id_categorie,
 *      [id_departement|id_pays], id_epci, id_commune, volume)
 *    + aides de résolution: nom_epci | code_insee (+ nom_commune/nom_departement)
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

// —————————————————————————————————————————————————————
// Auth (scope dédié, IP/admin optionnels via ENV)
// —————————————————————————————————————————————————————
require_api_auth_or_session([
    'scope'        => 'facts.upsert',
    'restrict_ips' => boolish(getenv('API_FACTS_RESTRICT_IPS') ?: '0'),
    'require_admin'=> boolish(getenv('API_FACTS_REQUIRE_ADMIN') ?: '0')
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
}

// —————————————————————————————————————————————————————
// Helpers locaux
// —————————————————————————————————————————————————————
function is_valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y,$m,$day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

function french_dow(string $d): string { // LUNDI..DIMANCHE
    static $names = [1=>'LUNDI',2=>'MARDI',3=>'MERCREDI',4=>'JEUDI',5=>'VENDREDI',6=>'SAMEDI',7=>'DIMANCHE'];
    $ts = strtotime($d);
    $n = (int)date('N', $ts);
    return $names[$n] ?? '';
}

function up_int($v): ?int { return (isset($v) && $v !== '') ? (int)$v : null; }

// —————————————————————————————————————————————————————
// Tables autorisées (base sans suffixe)
// —————————————————————————————————————————————————————
$WH = [
    'fact_diurnes','fact_nuitees',
    'fact_diurnes_departements','fact_nuitees_departements',
    'fact_diurnes_pays','fact_nuitees_pays',
    'fact_sejours_duree','fact_sejours_duree_departements','fact_sejours_duree_pays',
    'fact_lieu_activite_soir','fact_lieu_activite_soir_departement','fact_lieu_activite_soir_pays',
    'fact_lieu_activite_veille','fact_lieu_activite_veille_departement','fact_lieu_activite_veille_pays',
    'fact_lieu_nuitee_soir','fact_lieu_nuitee_soir_departement','fact_lieu_nuitee_soir_pays',
    'fact_lieu_nuitee_veille','fact_lieu_nuitee_veille_departement','fact_lieu_nuitee_veille_pays'
];

try {
    $db = \DatabaseConfig::getConfig();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'],$db['port'],$db['database'],$db['charset']);
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);

    $payload = get_json_body();
    $test = want_test_mode($payload);
    $suf  = test_suffix($test);

    $dimSuf = $suf; // utiliser aussi les dims *_test en mode test

    $tableBase = trim((string)($payload['table'] ?? ''));
    $rows      = $payload['rows'] ?? null;
    if ($tableBase === '' || !in_array($tableBase, $WH, true)) {
        jsonResponse(['error'=>'Table non autorisée', 'allowed'=>$WH], 400);
    }
    if (!is_array($rows) || !$rows) {
        jsonResponse(['error'=>'"rows" doit être un tableau non vide'], 400);
    }

    $tableName = $tableBase . $suf;

    // ————————————————————————————————————————————
    // Caches dimensions (nom_upper -> id)
    // ————————————————————————————————————————————
    $norm = fn($s) => $s === null ? null : normalize_uc((string)$s);

    $cache = [
        'zones'=>[], 'provenances'=>[], 'categories'=>[], 'departements'=>[], 'pays'=>[],
        'epci_by_name'=>[], 'commune_by_insee'=>[], 'durees'=>[]
    ];

    $fillMap = function(string $sql, int $keyIdx, int $valIdx) use($pdo, &$cache, $norm) {
        $st = $pdo->query($sql);
        foreach ($st as $r) {
            $k = $norm($r[$keyIdx] ?? null);
            if ($k !== null && $k !== '') $cache[$valIdx][$k] = (int)$r[$valIdx+1];
        }
    };

    // Simple helpers de (get|create) dims
    $getOrCreate = function(string $tbl, string $idCol, string $nameCol, string $bucket, $raw) use($pdo, &$cache, $norm) : ?int {
        $v = $norm($raw);
        if (!$v) return null;
        if (isset($cache[$bucket][$v])) return $cache[$bucket][$v];
        // Try insert
        $ins = $pdo->prepare("INSERT IGNORE INTO {$tbl} ({$nameCol}) VALUES (:v)");
        $ins->execute([':v'=>$v]);
        $id = (int)$pdo->lastInsertId();
        if ($id === 0) {
            $q = $pdo->prepare("SELECT {$idCol} FROM {$tbl} WHERE {$nameCol}=:v LIMIT 1");
            $q->execute([':v'=>$v]);
            $id = (int)($q->fetchColumn() ?: 0);
        }
        if ($id>0) { $cache[$bucket][$v] = $id; return $id; }
        return null;
    };

    $getZone = fn($v) => $getOrCreate('dim_zones_observation'.$dimSuf,'id_zone','nom_zone','zones',$v);
    $getProv = fn($v) => $getOrCreate('dim_provenances'.$dimSuf,'id_provenance','nom_provenance','provenances',$v);
    $getCat  = fn($v) => $getOrCreate('dim_categories_visiteur'.$dimSuf,'id_categorie','nom_categorie','categories',$v);
    $getDep  = fn($v) => $getOrCreate('dim_departements'.$dimSuf,'id_departement','nom_departement','departements',$v);
    $getPays = fn($v) => $getOrCreate('dim_pays'.$dimSuf,'id_pays','nom_pays','pays',$v);

    // Communes par code_insee
    $getCommune = function($code_insee, $nom_commune=null, $dep_name=null) use($pdo, &$cache, $norm, $getDep): ?int {
        $code = $norm($code_insee);
        if (!$code) return null;
        if (isset($cache['commune_by_insee'][$code])) return $cache['commune_by_insee'][$code];
        $id_departement = $getDep($dep_name);
        $nom = $norm($nom_commune) ?: '';
        $st = $pdo->prepare("INSERT INTO dim_communes{$dimSuf} (code_insee, nom_commune, id_departement)
                              VALUES (:c,:n,:d)
                              ON DUPLICATE KEY UPDATE nom_commune=VALUES(nom_commune), id_departement=COALESCE(VALUES(id_departement), id_departement)");
        $st->execute([':c'=>$code, ':n'=>$nom, ':d'=>$id_departement]);
        $id = (int)$pdo->lastInsertId();
        if ($id === 0) {
            $q = $pdo->prepare("SELECT id_commune FROM dim_communes{$dimSuf} WHERE code_insee=:c LIMIT 1");
            $q->execute([':c'=>$code]);
            $id = (int)($q->fetchColumn() ?: 0);
        }
        if ($id>0) { $cache['commune_by_insee'][$code]=$id; return $id; }
        return null;
    };

    // EPCI par nom
    $getEpci = function($nom_epci) use($pdo, &$cache, $norm): ?int {
        $v = $norm($nom_epci);
        if (!$v) return null;
        if (isset($cache['epci_by_name'][$v])) return $cache['epci_by_name'][$v];
        $st = $pdo->prepare("INSERT INTO dim_epci{$dimSuf} (nom_epci) VALUES (:n) ON DUPLICATE KEY UPDATE id_epci = LAST_INSERT_ID(id_epci)");
        $st->execute([':n'=>$v]);
        $id = (int)$pdo->lastInsertId();
        if ($id>0) { $cache['epci_by_name'][$v]=$id; return $id; }
        return null;
    };

    // Durees
    $getDuree = function($libelle=null, $nb_nuits=null) use($pdo, &$cache, $norm): ?int {
        $lib = $norm($libelle);
        if (!$lib) return null;
        if (isset($cache['durees'][$lib])) return $cache['durees'][$lib];
        $nb  = ($nb_nuits!==null && $nb_nuits!=='') ? (int)$nb_nuits : null;
        $st = $pdo->prepare("INSERT INTO dim_durees_sejour{$dimSuf} (libelle, nb_nuits, ordre)
                              VALUES (:l,:n,:n)
                              ON DUPLICATE KEY UPDATE id_duree = LAST_INSERT_ID(id_duree)");
        $st->execute([':l'=>$lib, ':n'=>$nb]);
        $id = (int)$pdo->lastInsertId();
        if ($id>0) { $cache['durees'][$lib]=$id; return $id; }
        return null;
    };

    // Insère la date dans dim_dates si absente
    $ensureDimDate = function(string $d, array $hints=[]) use($pdo) {
        if (!is_valid_date($d)) return;
        $ts = strtotime($d);
        $mo = (int)date('n', $ts);
        $an = (int)date('Y', $ts);
        $tri= (int)ceil($mo/3);
        $sem= (int)date('W', $ts);
        $dow= french_dow($d);
        $st = $pdo->prepare("INSERT IGNORE INTO dim_dates{$dimSuf}
            (date,vacances_a,vacances_b,vacances_c,ferie,jour_semaine,mois,annee,trimestre,semaine)
            VALUES (:d,:va,:vb,:vc,:fe,:js,:mo,:an,:tr,:se)");
        $st->execute([
            ':d'=>$d,
            ':va'=> up_int($hints['vacances_a'] ?? $hints['VacancesA'] ?? 0) ?? 0,
            ':vb'=> up_int($hints['vacances_b'] ?? $hints['VacancesB'] ?? 0) ?? 0,
            ':vc'=> up_int($hints['vacances_c'] ?? $hints['VacancesC'] ?? 0) ?? 0,
            ':fe'=> up_int($hints['ferie'] ?? $hints['Ferie'] ?? 0) ?? 0,
            ':js'=> ($hints['jour_semaine'] ?? $hints['JourDeLaSemaine'] ?? '') ?: $dow,
            ':mo'=> $mo, ':an'=> $an, ':tr'=> $tri, ':se'=> $sem,
        ]);
    };

    // ————————————————————————————————————————————
    // Préparation de la requête d'upsert par famille
    // ————————————————————————————————————————————
    $buildInsert = function(string $base, string $table) : array {
        // Retourne [SQL, colonnes_attendues]
        switch ($base) {
            case 'fact_sejours_duree_pays':
                return [
                    "INSERT INTO {$table} (date,id_zone,id_provenance,id_categorie,id_pays,id_duree,volume)
                     VALUES (:date,:z,:p,:c,:py,:du,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','id_zone','id_provenance','id_categorie','id_pays','id_duree','volume']
                ];
            case 'fact_sejours_duree_departements':
                return [
                    "INSERT INTO {$table} (date,id_zone,id_provenance,id_categorie,id_departement,id_duree,volume)
                     VALUES (:date,:z,:p,:c,:d,:du,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','id_zone','id_provenance','id_categorie','id_departement','id_duree','volume']
                ];
            case 'fact_sejours_duree':
                return [
                    "INSERT INTO {$table} (date,id_zone,id_provenance,id_categorie,id_duree,volume)
                     VALUES (:date,:z,:p,:c,:du,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','id_zone','id_provenance','id_categorie','id_duree','volume']
                ];
            case 'fact_diurnes_pays':
            case 'fact_nuitees_pays':
                return [
                    "INSERT INTO {$table} (date,id_zone,id_provenance,id_categorie,id_pays,volume)
                     VALUES (:date,:z,:p,:c,:py,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','id_zone','id_provenance','id_categorie','id_pays','volume']
                ];
            case 'fact_diurnes_departements':
            case 'fact_nuitees_departements':
                return [
                    "INSERT INTO {$table} (date,id_zone,id_provenance,id_categorie,id_departement,volume)
                     VALUES (:date,:z,:p,:c,:d,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','id_zone','id_provenance','id_categorie','id_departement','volume']
                ];
            case 'fact_diurnes':
            case 'fact_nuitees':
                return [
                    "INSERT INTO {$table} (date,id_zone,id_provenance,id_categorie,volume)
                     VALUES (:date,:z,:p,:c,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','id_zone','id_provenance','id_categorie','volume']
                ];
            // Lieu*
            case 'fact_lieu_activite_soir_departement':
            case 'fact_lieu_activite_veille_departement':
            case 'fact_lieu_nuitee_soir_departement':
            case 'fact_lieu_nuitee_veille_departement':
                return [
                    "INSERT INTO {$table} (date,jour_semaine,id_zone,id_provenance,id_categorie,id_departement,id_epci,id_commune,volume)
                     VALUES (:date,:js,:z,:p,:c,:d,:e,:co,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','jour_semaine','id_zone','id_provenance','id_categorie','id_departement','id_epci','id_commune','volume']
                ];
            case 'fact_lieu_activite_soir_pays':
            case 'fact_lieu_activite_veille_pays':
            case 'fact_lieu_nuitee_soir_pays':
            case 'fact_lieu_nuitee_veille_pays':
                return [
                    "INSERT INTO {$table} (date,jour_semaine,id_zone,id_provenance,id_categorie,id_pays,id_epci,id_commune,volume)
                     VALUES (:date,:js,:z,:p,:c,:py,:e,:co,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','jour_semaine','id_zone','id_provenance','id_categorie','id_pays','id_epci','id_commune','volume']
                ];
            default: // fact_lieu_* sans geo sup
                return [
                    "INSERT INTO {$table} (date,jour_semaine,id_zone,id_provenance,id_categorie,id_epci,id_commune,volume)
                     VALUES (:date,:js,:z,:p,:c,:e,:co,:v)
                     ON DUPLICATE KEY UPDATE volume=VALUES(volume), updated_at=CURRENT_TIMESTAMP",
                    ['date','jour_semaine','id_zone','id_provenance','id_categorie','id_epci','id_commune','volume']
                ];
        }
    };

    [$sql, $cols] = $buildInsert($tableBase, $tableName);
    $stmt = $pdo->prepare($sql);

    $processed=0; $skipped=0; $errors=0; $error_examples=[];

    // ————————————————————————————————————————————
    // Traitement ligne par ligne (fiable et clair)
    // ————————————————————————————————————————————
    foreach ($rows as $i => $r) {
        if (!is_array($r)) { $skipped++; continue; }

        // 1) date + dim_dates
        $date = (string)($r['date'] ?? $r['Date'] ?? '');
        if (!is_valid_date($date)) { $skipped++; continue; }
        $ensureDimDate($date, $r);

        // 2) résolution dims de base (zone/prov/cat)
        $id_zone = up_int($r['id_zone'] ?? null) ?: $getZone($r['nom_zone'] ?? $r['ZoneObservation'] ?? null);
        $id_prov = up_int($r['id_provenance'] ?? null) ?: $getProv($r['nom_provenance'] ?? $r['Provenance'] ?? null);
        $id_cat  = up_int($r['id_categorie'] ?? null) ?: $getCat($r['nom_categorie'] ?? $r['CategorieVisiteur'] ?? null);
        if (!$id_zone || !$id_prov || !$id_cat) { $skipped++; continue; }

        // 3) volume
        $volume = up_int($r['volume'] ?? $r['Volume'] ?? null) ?? 0;
        if ($volume <= 0) { $skipped++; continue; }

        // 4) spécifiques
        $params = [ ':date'=>$date, ':z'=>$id_zone, ':p'=>$id_prov, ':c'=>$id_cat, ':v'=>$volume ];

        if (str_starts_with($tableBase, 'fact_sejours_duree')) {
            $id_duree = up_int($r['id_duree'] ?? null);
            if (!$id_duree) {
                $id_duree = $getDuree($r['libelle_duree'] ?? $r['DureeSejour'] ?? null, $r['nb_nuits'] ?? $r['DureeSejourNum'] ?? null);
            }
            if (!$id_duree) { $skipped++; continue; }
            $params[':du'] = $id_duree;
        }

        if (str_ends_with($tableBase, '_departements')) {
            $id_dep = up_int($r['id_departement'] ?? null) ?: $getDep($r['nom_departement'] ?? $r['NomDepartement'] ?? null);
            if (!$id_dep) { $skipped++; continue; }
            $params[':d'] = $id_dep;
        }
        if (str_ends_with($tableBase, '_pays')) {
            $id_pays = up_int($r['id_pays'] ?? null) ?: $getPays($r['nom_pays'] ?? $r['Pays'] ?? null);
            if (!$id_pays) { $skipped++; continue; }
            $params[':py'] = $id_pays;
        }

        // Lieu*
        if (str_starts_with($tableBase, 'fact_lieu_')) {
            $js = (string)($r['jour_semaine'] ?? $r['JourDeLaSemaine'] ?? '') ?: french_dow($date);
            $params[':js'] = normalize_uc($js);
            // epci/commune
            $id_epci = up_int($r['id_epci'] ?? null) ?: $getEpci($r['nom_epci'] ?? $r['EPCI'] ?? $r['NomEPCI'] ?? null);
            $id_commune = up_int($r['id_commune'] ?? null);
            if (!$id_commune) {
                $id_commune = $getCommune(
                    $r['code_insee'] ?? $r['CodeInsee'] ?? $r['CodeINSEE'] ?? $r['CodeInseeNuiteeSoir'] ?? $r['CodeInseeDiurneSoir'] ?? $r['CodeInseeNuiteeVeille'] ?? $r['CodeInseeDiurneVeille'] ?? null,
                    $r['nom_commune'] ?? null,
                    $r['nom_departement'] ?? $r['NomDepartement'] ?? $r['Departement'] ?? null
                );
            }
            $params[':e']  = $id_epci ?: 0;
            $params[':co'] = $id_commune ?: 0;
        }

        try {
            $stmt->execute($params);
            $processed++;
        } catch (Throwable $e) {
            $errors++;
            if (count($error_examples) < 5) {
                $error_examples[] = ['row_index'=>$i,'error'=>$e->getMessage()];
            }
        }
    }

    audit_log('facts.upsert', ['table'=>$tableName, 'processed'=>$processed, 'skipped'=>$skipped, 'errors'=>$errors, 'test_mode'=>$test]);
    jsonResponse([
        'success'    => $errors === 0,
        'action'     => 'facts.upsert',
        'table'      => $tableName,
        'test_mode'  => $test,
        'counts'     => ['processed'=>$processed, 'skipped'=>$skipped, 'errors'=>$errors],
        'error_examples' => $error_examples ?: null
    ]);

} catch (JsonException $e) {
    jsonResponse(['error'=>'JSON invalide','details'=>$e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('FACTS UPSERT API error: '.$e->getMessage());
    jsonResponse(['error'=>'Erreur serveur'], 500);
}
