<?php
/**
 * CantalDestination - Admin API: upsert dimensions
 * Fichier: api/database/dim.php
 * Endpoint: /api/database/dim
 * Méthode: POST JSON — upsert dims basiques + mapping
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

// Scope = dim (rate-limit + audit)
require_api_auth_or_session(['scope' => 'dim.upsert']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error'=>'Méthode non autorisée. Utilisez POST.'], 405);
}

function parse_bool_flag($v): int {
    if ($v === null) return 0;
    if (is_bool($v)) return $v ? 1 : 0;
    $s = strtolower(trim((string)$v));
    return in_array($s, ['1','true','yes','on','y'], true) ? 1 : 0;
}

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

    $type = $payload['type'] ?? '';
    $items = $payload['items'] ?? null;
    if (!$type || !is_array($items)) {
        jsonResponse(['error'=>'Paramètres manquants: "type" et "items[]" requis'], 400);
    }

    $mapped = [];
    $count  = 0;

    $pdo->beginTransaction();

    switch ($type) {
        case 'zones':
        case 'provenances':
        case 'categories':
        case 'pays': {
            $map = [
                'zones'        => ['table'=>"dim_zones_observation$suf",   'id'=>'id_zone',       'name'=>'nom_zone'],
                'provenances'  => ['table'=>"dim_provenances$suf",         'id'=>'id_provenance', 'name'=>'nom_provenance'],
                'categories'   => ['table'=>"dim_categories_visiteur$suf", 'id'=>'id_categorie',  'name'=>'nom_categorie'],
                'pays'         => ['table'=>"dim_pays$suf",                'id'=>'id_pays',       'name'=>'nom_pays'],
            ][$type];

            $ins = $pdo->prepare("INSERT INTO {$map['table']} ({$map['name']}) VALUES (:v) ON DUPLICATE KEY UPDATE {$map['name']}=VALUES({$map['name']})");
            $norms = [];
            foreach ($items as $v) {
                if (!is_string($v) || $v==='') continue;
                $n = normalize_uc($v);
                $ins->execute([':v'=>$n]);
                $norms[] = $n; $count++;
            }
            if ($norms) {
                $in = implode(',', array_fill(0, count($norms), '?'));
                $sel = $pdo->prepare("SELECT {$map['id']} AS id, {$map['name']} AS name FROM {$map['table']} WHERE {$map['name']} IN ($in)");
                $sel->execute($norms);
                foreach ($sel->fetchAll() as $r) $mapped[$r['name']] = (int)$r['id'];
            }
            break;
        }

        case 'departements': {
            $tbl = "dim_departements$suf";

            // Accepte soit une simple chaîne, soit un objet {nom_departement, nom_region?, nom_nouvelle_region?}
            $ins = $pdo->prepare("
                INSERT INTO $tbl (nom_departement, nom_region, nom_nouvelle_region)
                VALUES (:nom, :reg, :newreg)
                ON DUPLICATE KEY UPDATE
                  nom_departement = VALUES(nom_departement),
                  nom_region = COALESCE(VALUES(nom_region), nom_region),
                  nom_nouvelle_region = COALESCE(VALUES(nom_nouvelle_region), nom_nouvelle_region)
            ");

            $names = [];
            foreach ($items as $it) {
                if (is_string($it)) {
                    $nom = normalize_uc($it);
                    if ($nom==='') continue;
                    $ins->execute([':nom'=>$nom, ':reg'=>null, ':newreg'=>null]);
                    $names[] = $nom; $count++;
                } elseif (is_array($it)) {
                    $nom    = isset($it['nom_departement']) ? normalize_uc((string)$it['nom_departement']) : '';
                    if ($nom==='') continue;
                    $reg    = isset($it['nom_region']) ? normalize_uc((string)$it['nom_region']) : null;
                    $newreg = isset($it['nom_nouvelle_region']) ? normalize_uc((string)$it['nom_nouvelle_region']) : null;
                    $ins->execute([':nom'=>$nom, ':reg'=>$reg, ':newreg'=>$newreg]);
                    $names[] = $nom; $count++;
                }
            }
            if ($names) {
                $in = implode(',', array_fill(0, count($names), '?'));
                $sel = $pdo->prepare("SELECT id_departement AS id, nom_departement AS name FROM $tbl WHERE nom_departement IN ($in)");
                $sel->execute($names);
                foreach ($sel->fetchAll() as $r) $mapped[$r['name']] = (int)$r['id'];
            }
            break;
        }

        case 'communes': {
            $tbl = "dim_communes$suf";
            $dep = "dim_departements$suf";
            $ins = $pdo->prepare("
                INSERT INTO $tbl (code_insee, nom_commune, id_departement)
                VALUES (:code, :nom, (SELECT id_departement FROM $dep WHERE nom_departement=:dep LIMIT 1))
                ON DUPLICATE KEY UPDATE
                  nom_commune=COALESCE(VALUES(nom_commune), nom_commune),
                  id_departement=COALESCE(VALUES(id_departement), id_departement)
            ");
            $codes=[];
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $code = isset($it['code_insee']) ? normalize_uc((string)$it['code_insee']) : null;
                if (!$code) continue;
                $nom  = isset($it['nom_commune']) ? normalize_uc((string)$it['nom_commune']) : null;
                $depn = isset($it['nom_departement']) ? normalize_uc((string)$it['nom_departement']) : null;
                $ins->execute([':code'=>$code, ':nom'=>$nom, ':dep'=>$depn]);
                $codes[]=$code; $count++;
            }
            if ($codes) {
                $in = implode(',', array_fill(0, count($codes), '?'));
                $sel=$pdo->prepare("SELECT id_commune, code_insee FROM $tbl WHERE code_insee IN ($in)");
                $sel->execute($codes);
                foreach ($sel->fetchAll() as $r) $mapped[$r['code_insee']] = (int)$r['id_commune'];
            }
            break;
        }

        case 'epci': {
            $tbl = "dim_epci$suf";
            $ins = $pdo->prepare("
                INSERT INTO $tbl (nom_epci) VALUES (:v)
                ON DUPLICATE KEY UPDATE id_epci = LAST_INSERT_ID(id_epci)
            ");
            foreach ($items as $v) {
                if (!is_string($v) || $v==='') continue;
                $n = normalize_uc($v);
                $ins->execute([':v'=>$n]);
                $id = (int)$pdo->lastInsertId();
                if ($id === 0) {
                    $q = $pdo->prepare("SELECT id_epci FROM $tbl WHERE nom_epci=:v LIMIT 1");
                    $q->execute([':v'=>$n]);
                    $id = (int)($q->fetchColumn() ?: 0);
                }
                if ($id>0) $mapped[$n] = $id;
                $count++;
            }
            break;
        }

        case 'durees': {
            $tbl = "dim_durees_sejour$suf";
            $ins = $pdo->prepare("
                INSERT INTO $tbl (libelle, nb_nuits, ordre)
                VALUES (:lib, :nb, :nb)
                ON DUPLICATE KEY UPDATE id_duree = LAST_INSERT_ID(id_duree), nb_nuits=VALUES(nb_nuits), ordre=VALUES(ordre)
            ");
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $lib = isset($it['libelle']) ? normalize_uc((string)$it['libelle']) : null;
                $nb  = isset($it['nb_nuits']) && $it['nb_nuits']!=='' ? (int)$it['nb_nuits'] : null;
                if (!$lib) continue;
                $ins->execute([':lib'=>$lib, ':nb'=>$nb]);
                $id = (int)$pdo->lastInsertId();
                if ($id===0) {
                    $q=$pdo->prepare("SELECT id_duree FROM $tbl WHERE libelle=:lib LIMIT 1");
                    $q->execute([':lib'=>$lib]);
                    $id=(int)($q->fetchColumn() ?: 0);
                }
                if ($id>0) $mapped[$lib] = $id;
                $count++;
            }
            break;
        }

        case 'dates': {
            $tbl = "dim_dates$suf";
            $ins = $pdo->prepare("
                INSERT INTO $tbl
                  (date, vacances_a, vacances_b, vacances_c, ferie, jour_semaine, mois, annee, trimestre, semaine)
                VALUES
                  (:d, :va, :vb, :vc, :fe, :js, :mo, :an, :tri, :sem)
                ON DUPLICATE KEY UPDATE
                  vacances_a = VALUES(vacances_a),
                  vacances_b = VALUES(vacances_b),
                  vacances_c = VALUES(vacances_c),
                  ferie      = VALUES(ferie),
                  jour_semaine = VALUES(jour_semaine),
                  mois       = VALUES(mois),
                  annee      = VALUES(annee),
                  trimestre  = VALUES(trimestre),
                  semaine    = VALUES(semaine)
            ");

            $dowNames = [1=>'LUNDI',2=>'MARDI',3=>'MERCREDI',4=>'JEUDI',5=>'VENDREDI',6=>'SAMEDI',7=>'DIMANCHE'];

            foreach ($items as $it) {
                // item peut être une chaîne "YYYY-MM-DD" ou un objet {date: "...", ...}
                if (is_string($it)) {
                    $dstr = trim($it);
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dstr)) continue;
                    $ts = strtotime($dstr);
                    if ($ts === false) continue;

                    $va = 0; $vb=0; $vc=0; $fe=0;
                    $mo = (int)date('n', $ts);
                    $an = (int)date('Y', $ts);
                    $tri= (int)ceil($mo/3);
                    $sem= (int)date('W', $ts);
                    $js = $dowNames[(int)date('N', $ts)] ?? null;

                } elseif (is_array($it)) {
                    $dstr = isset($it['date']) ? trim((string)$it['date']) : '';
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dstr)) continue;
                    $ts = strtotime($dstr);
                    if ($ts === false) continue;

                    $va = parse_bool_flag($it['vacances_a'] ?? 0);
                    $vb = parse_bool_flag($it['vacances_b'] ?? 0);
                    $vc = parse_bool_flag($it['vacances_c'] ?? 0);
                    $fe = parse_bool_flag($it['ferie'] ?? 0);

                    $mo = isset($it['mois']) ? (int)$it['mois'] : (int)date('n', $ts);
                    $an = isset($it['annee']) ? (int)$it['annee'] : (int)date('Y', $ts);
                    $tri= isset($it['trimestre']) ? (int)$it['trimestre'] : (int)ceil($mo/3);
                    $sem= isset($it['semaine']) ? (int)$it['semaine'] : (int)date('W', $ts);

                    $jsIn = $it['jour_semaine'] ?? null;
                    $js = $jsIn !== null && $jsIn !== '' ? normalize_uc((string)$jsIn) : ($dowNames[(int)date('N', $ts)] ?? null);
                } else {
                    continue;
                }

                $ins->execute([
                    ':d'=>$dstr, ':va'=>$va, ':vb'=>$vb, ':vc'=>$vc, ':fe'=>$fe,
                    ':js'=>$js, ':mo'=>$mo, ':an'=>$an, ':tri'=>$tri, ':sem'=>$sem
                ]);
                $mapped[$dstr] = 1;
                $count++;
            }
            break;
        }

        default:
            jsonResponse(['error'=>'type invalide'], 400);
    }

    $pdo->commit();

    audit_log('dim.upsert', ['type'=>$type, 'count'=>$count, 'test_mode'=>$test]);
    jsonResponse(['success'=>true, 'type'=>$type, 'test_mode'=>$test, 'mapped'=>$mapped, 'count'=>$count]);

} catch (JsonException $e) {
    jsonResponse(['error'=>'JSON invalide','details'=>$e->getMessage()], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('DIM API error: '.$e->getMessage());
    jsonResponse(['error'=>'Erreur serveur'], 500);
}
