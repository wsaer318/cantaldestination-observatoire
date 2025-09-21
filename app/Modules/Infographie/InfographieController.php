<?php
declare(strict_types=1);

namespace App\Modules\Infographie;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use PDO;

class InfographieController extends Controller
{
    private InfographieDataService $data;

    public function __construct()
    {
        $this->data = new InfographieDataService();
    }

    public function departementsTouristes(Request $request): Response
    {
        $year = $this->requireQuery($request, 'annee');
        $period = $this->requireQuery($request, 'periode');
        $zone = $this->requireQuery($request, 'zone');
        $limit = (int) ($request->getQuery('limit') ?? 15);
        $debut = $request->getQuery('debut');
        $fin = $request->getQuery('fin');

        if ($limit <= 0) {
            throw HttpException::badRequest('Le paramÃ¨tre limit doit Ãªtre positif');
        }

        $cache = $this->data->cache();
        $cacheParams = [
            'annee' => $year,
            'periode' => $period,
            'zone' => $zone,
            'limit' => $limit,
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($cached = $cache->get('infographie_departements', $cacheParams)) {
            return $this->json([
                'success' => true,
                'data' => $cached,
            ])->withHeader('X-Cache-Status', 'HIT')->withHeader('X-Cache-Category', 'infographie_departements');
        }

        $pdo = $this->data->connection();
        [$zoneIds, $zoneLabel] = $this->data->resolveZoneIds($pdo, $zone);
        [$range, $prevRange] = $this->data->resolveDateRanges($year, $period, $debut, $fin);
        $dimensions = $this->data->resolveDimensions($pdo, 'TOURISTE', ['NONLOCAL']);

        $data = $this->fetchDepartements($pdo, $zoneIds, $dimensions, $range, $prevRange, $limit);
        $total = $this->calculateTotal($pdo, $range, $dimensions, $zoneIds);

        $result = $this->hydrateDepartementResult($data, $total);
        $cache->set('infographie_departements', $cacheParams, $result);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'zone' => $zoneLabel,
                'annee' => $year,
                'periode' => $period,
                'limit' => $limit,
            ],
        ])->withHeader('X-Cache-Status', 'MISS')->withHeader('X-Cache-Category', 'infographie_departements');
    }

    public function regionsTouristes(Request $request): Response
    {
        $year = $this->requireQuery($request, 'annee');
        $period = $this->requireQuery($request, 'periode');
        $zone = $this->requireQuery($request, 'zone');
        $limit = (int) ($request->getQuery('limit') ?? 5);
        $debut = $request->getQuery('debut');
        $fin = $request->getQuery('fin');

        if ($limit <= 0) {
            throw HttpException::badRequest('Le paramÃ¨tre limit doit Ãªtre positif');
        }

        $cache = $this->data->cache();
        $cacheParams = [
            'annee' => $year,
            'periode' => $period,
            'zone' => $zone,
            'limit' => $limit,
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($cached = $cache->get('infographie_regions_touristes', $cacheParams)) {
            return $this->json([
                'success' => true,
                'data' => $cached,
            ])->withHeader('X-Cache-Status', 'HIT')->withHeader('X-Cache-Category', 'infographie_regions_touristes');
        }

        $pdo = $this->data->connection();
        [$zoneIds, $zoneLabel] = $this->data->resolveZoneIds($pdo, $zone);
        [$range, $prevRange] = $this->data->resolveDateRanges($year, $period, $debut, $fin);
        $dimensions = $this->data->resolveDimensions($pdo, 'TOURISTE', ['NONLOCAL']);

        $current = $this->fetchRegions($pdo, $zoneIds, $dimensions, $range, $limit);
        $previous = $this->fetchRegions($pdo, $zoneIds, $dimensions, $prevRange, null);

        $result = $this->hydrateRegionResult($current, $previous);
        $cache->set('infographie_regions_touristes', $cacheParams, $result);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'zone' => $zoneLabel,
                'annee' => $year,
                'periode' => $period,
                'limit' => $limit,
            ],
        ])->withHeader('X-Cache-Status', 'MISS')->withHeader('X-Cache-Category', 'infographie_regions_touristes');
    }

    public function departementsExcursionnistes(Request $request): Response
    {
        $year = $this->requireQuery($request, 'annee');
        $period = $this->requireQuery($request, 'periode');
        $zone = $this->requireQuery($request, 'zone');
        $limit = (int) ($request->getQuery('limit') ?? 15);
        $debut = $request->getQuery('debut');
        $fin = $request->getQuery('fin');

        if ($limit <= 0) {
            throw HttpException::badRequest('Le paramètre limit doit être positif');
        }

        $cache = $this->data->cache();
        $cacheParams = [
            'annee' => $year,
            'periode' => $period,
            'zone' => $zone,
            'limit' => $limit,
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($cached = $cache->get('infographie_departements_excursionnistes', $cacheParams)) {
            return $this->json([
                'success' => true,
                'data' => $cached,
            ])->withHeader('X-Cache-Status', 'HIT')->withHeader('X-Cache-Category', 'infographie_departements_excursionnistes');
        }

        $pdo = $this->data->connection();
        [$zoneIds, $zoneLabel] = $this->data->resolveZoneIds($pdo, $zone);
        [$range, $prevRange] = $this->data->resolveDateRanges($year, $period, $debut, $fin);
        $dimensions = $this->data->resolveDimensions($pdo, 'EXCURSIONNISTE', ['NONLOCAL']);

        $rows = $this->fetchDiurneDepartements($pdo, $zoneIds, $dimensions, $range, $prevRange, $limit);
        $total = $this->calculatePresenceTotal($pdo, $range, $dimensions, $zoneIds);

        $result = $this->hydrateExcursionDepartementResult($rows, $total);
        $cache->set('infographie_departements_excursionnistes', $cacheParams, $result);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'zone' => $zoneLabel,
                'annee' => $year,
                'periode' => $period,
                'limit' => $limit,
            ],
        ])->withHeader('X-Cache-Status', 'MISS')->withHeader('X-Cache-Category', 'infographie_departements_excursionnistes');
    }

    public function regionsExcursionnistes(Request $request): Response
    {
        $year = $this->requireQuery($request, 'annee');
        $period = $this->requireQuery($request, 'periode');
        $zone = $this->requireQuery($request, 'zone');
        $limit = (int) ($request->getQuery('limit') ?? 10);
        $debut = $request->getQuery('debut');
        $fin = $request->getQuery('fin');

        if ($limit <= 0) {
            throw HttpException::badRequest('Le paramètre limit doit être positif');
        }

        $cache = $this->data->cache();
        $cacheParams = [
            'annee' => $year,
            'periode' => $period,
            'zone' => $zone,
            'limit' => $limit,
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($cached = $cache->get('infographie_regions_excursionnistes', $cacheParams)) {
            return $this->json([
                'success' => true,
                'data' => $cached,
            ])->withHeader('X-Cache-Status', 'HIT')->withHeader('X-Cache-Category', 'infographie_regions_excursionnistes');
        }

        $pdo = $this->data->connection();
        [$zoneIds, $zoneLabel] = $this->data->resolveZoneIds($pdo, $zone);
        [$range, $prevRange] = $this->data->resolveDateRanges($year, $period, $debut, $fin);
        $dimensions = $this->data->resolveDimensions($pdo, 'EXCURSIONNISTE', ['NONLOCAL']);

        $current = $this->fetchDiurneRegions($pdo, $zoneIds, $dimensions, $range, $limit);
        $previous = $this->fetchDiurneRegions($pdo, $zoneIds, $dimensions, $prevRange, null);

        $result = $this->hydrateExcursionRegionResult($current, $previous);
        $cache->set('infographie_regions_excursionnistes', $cacheParams, $result);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'zone' => $zoneLabel,
                'annee' => $year,
                'periode' => $period,
                'limit' => $limit,
            ],
        ])->withHeader('X-Cache-Status', 'MISS')->withHeader('X-Cache-Category', 'infographie_regions_excursionnistes');
    }

    private function fetchDiurneDepartements(PDO $pdo, array $zoneIds, array $dimensions, array $range, array $prevRange, int $limit): array
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));
        $sql = <<<SQL
SELECT
    d.nom_departement,
    d.nom_region,
    d.nom_nouvelle_region,
    COALESCE(cur.n_presences, 0) AS n_presences,
    COALESCE(prev.n_presences_n1, 0) AS n_presences_n1
FROM dim_departements d
LEFT JOIN (
    SELECT id_departement, SUM(volume) AS n_presences
    FROM fact_diurnes_departements
    WHERE date BETWEEN ? AND ?
      AND id_zone IN ($zonePlaceholders)
      AND id_categorie = ?
      AND id_provenance IN ($provenancePlaceholders)
    GROUP BY id_departement
) cur ON d.id_departement = cur.id_departement
LEFT JOIN (
    SELECT id_departement, SUM(volume) AS n_presences_n1
    FROM fact_diurnes_departements
    WHERE date BETWEEN ? AND ?
      AND id_zone IN ($zonePlaceholders)
      AND id_categorie = ?
      AND id_provenance IN ($provenancePlaceholders)
    GROUP BY id_departement
) prev ON d.id_departement = prev.id_departement
WHERE d.nom_departement <> 'CUMUL'
  AND (COALESCE(cur.n_presences, 0) > 0 OR COALESCE(prev.n_presences_n1, 0) > 0)
ORDER BY COALESCE(cur.n_presences, 0) DESC
LIMIT ?
SQL;

        $stmt = $pdo->prepare($sql);
        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
            $prevRange['start'],
            $prevRange['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
            $limit,
        ];
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchDiurneRegions(PDO $pdo, array $zoneIds, array $dimensions, array $range, ?int $limit = null): array
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));
        $sql = <<<SQL
SELECT
    d.nom_nouvelle_region AS region,
    SUM(fd.volume) AS n_presences
FROM fact_diurnes_departements fd
JOIN dim_departements d ON fd.id_departement = d.id_departement
WHERE fd.date BETWEEN ? AND ?
  AND fd.id_zone IN ($zonePlaceholders)
  AND fd.id_categorie = ?
  AND fd.id_provenance IN ($provenancePlaceholders)
  AND d.nom_nouvelle_region IS NOT NULL
  AND d.nom_nouvelle_region NOT IN ('CUMUL', 'Cumul', '')
GROUP BY d.nom_nouvelle_region
ORDER BY n_presences DESC
SQL;
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
        }

        $stmt = $pdo->prepare($sql);
        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
        ];
        if ($limit !== null) {
            $params[] = $limit;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculatePresenceTotal(PDO $pdo, array $range, array $dimensions, array $zoneIds): int
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));
        $sql = <<<SQL
SELECT SUM(volume) AS total
FROM fact_diurnes_departements
WHERE date BETWEEN ? AND ?
  AND id_zone IN ($zonePlaceholders)
  AND id_categorie = ?
  AND id_provenance IN ($provenancePlaceholders)
SQL;

        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?? 0);
    }

    private function hydrateExcursionDepartementResult(array $rows, int $total): array
    {
        $result = [];

        foreach ($rows as $row) {
            $presences = (int) $row['n_presences'];
            $presencesN1 = (int) $row['n_presences_n1'];
            $deltaPct = null;
            if ($presencesN1 > 0) {
                $deltaPct = round((($presences - $presencesN1) / $presencesN1) * 100, 1);
            } elseif ($presences > 0) {
                $deltaPct = 100.0;
            }

            $partPct = $total > 0 ? round(($presences / $total) * 100, 1) : 0.0;

            $result[] = [
                'nom_departement' => $row['nom_departement'],
                'nom_region' => $row['nom_region'],
                'nom_nouvelle_region' => $row['nom_nouvelle_region'],
                'n_presences' => $presences,
                'n_presences_n1' => $presencesN1,
                'delta_pct' => $deltaPct,
                'part_pct' => $partPct,
            ];
        }

        return $result;
    }

    private function hydrateExcursionRegionResult(array $currentRows, array $previousRows): array
    {
        $previousMap = [];
        foreach ($previousRows as $row) {
            $previousMap[$row['region']] = (int) ($row['n_presences'] ?? 0);
        }

        $total = 0;
        foreach ($currentRows as $row) {
            $total += (int) ($row['n_presences'] ?? 0);
        }

        $result = [];
        foreach ($currentRows as $row) {
            $region = $row['region'];
            $presences = (int) ($row['n_presences'] ?? 0);
            $presencesN1 = $previousMap[$region] ?? 0;
            $deltaPct = null;
            if ($presencesN1 > 0) {
                $deltaPct = round((($presences - $presencesN1) / $presencesN1) * 100, 1);
            } elseif ($presences > 0) {
                $deltaPct = 100.0;
            }
            $partPct = $total > 0 ? round(($presences / $total) * 100, 1) : 0.0;

            $result[] = [
                'nom_region' => $region,
                'nom_nouvelle_region' => $region,
                'n_presences' => $presences,
                'n_presences_n1' => $presencesN1,
                'delta_pct' => $deltaPct,
                'part_pct' => $partPct,
            ];
        }

        return $result;
    }

    private function requireQuery(Request $request, string $key): string
    {
        $value = $request->getQuery($key);
        if ($value === null || $value === '') {
            throw HttpException::badRequest("ParamÃ¨tre manquant : {$key}");
        }
        return $value;
    }

    private function fetchDepartements(PDO $pdo, array $zoneIds, array $dimensions, array $range, array $prevRange, int $limit): array
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));
        $sql = <<<SQL
SELECT
    d.nom_departement,
    d.nom_region,
    d.nom_nouvelle_region,
    COALESCE(cur.n_nuitees, 0) AS n_nuitees,
    COALESCE(prev.n_nuitees_n1, 0) AS n_nuitees_n1
FROM dim_departements d
LEFT JOIN (
    SELECT id_departement, SUM(volume) AS n_nuitees
    FROM fact_nuitees_departements
    WHERE date BETWEEN ? AND ?
      AND id_zone IN ($zonePlaceholders)
      AND id_categorie = ?
      AND id_provenance IN ($provenancePlaceholders)
    GROUP BY id_departement
) cur ON d.id_departement = cur.id_departement
LEFT JOIN (
    SELECT id_departement, SUM(volume) AS n_nuitees_n1
    FROM fact_nuitees_departements
    WHERE date BETWEEN ? AND ?
      AND id_zone IN ($zonePlaceholders)
      AND id_categorie = ?
      AND id_provenance IN ($provenancePlaceholders)
    GROUP BY id_departement
) prev ON d.id_departement = prev.id_departement
WHERE d.nom_departement NOT IN ('CUMUL')
  AND (COALESCE(cur.n_nuitees, 0) > 0 OR COALESCE(prev.n_nuitees_n1, 0) > 0)
ORDER BY COALESCE(cur.n_nuitees, 0) DESC
LIMIT ?
SQL;

        $stmt = $pdo->prepare($sql);
        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
            $prevRange['start'],
            $prevRange['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
            $limit,
        ];
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRegions(PDO $pdo, array $zoneIds, array $dimensions, array $range, ?int $limit = null): array
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));
        $sql = <<<SQL
SELECT
    d.nom_nouvelle_region AS region,
    SUM(fd.volume) AS total
FROM fact_nuitees_departements fd
JOIN dim_departements d ON fd.id_departement = d.id_departement
WHERE fd.date BETWEEN ? AND ?
  AND fd.id_zone IN ($zonePlaceholders)
  AND fd.id_categorie = ?
  AND fd.id_provenance IN ($provenancePlaceholders)
  AND d.nom_nouvelle_region IS NOT NULL
  AND d.nom_nouvelle_region NOT IN ('CUMUL', 'Cumul', '')
GROUP BY d.nom_nouvelle_region
ORDER BY total DESC
SQL;
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
        }

        $stmt = $pdo->prepare($sql);
        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
        ];
        if ($limit !== null) {
            $params[] = $limit;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateTotal(PDO $pdo, array $range, array $dimensions, array $zoneIds): int
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));
        $sql = <<<SQL
SELECT SUM(volume) AS total
FROM fact_nuitees_departements
WHERE date BETWEEN ? AND ?
  AND id_zone IN ($zonePlaceholders)
  AND id_categorie = ?
  AND id_provenance IN ($provenancePlaceholders)
SQL;

        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?? 0);
    }

    private function hydrateDepartementResult(array $rows, int $total): array
    {
        $result = [];

        foreach ($rows as $row) {
            $nuitees = (int) $row['n_nuitees'];
            $nuiteesN1 = (int) $row['n_nuitees_n1'];
            $deltaPct = 0.0;
            if ($nuiteesN1 > 0) {
                $deltaPct = round((($nuitees - $nuiteesN1) / $nuiteesN1) * 100, 1);
            } elseif ($nuitees > 0) {
                $deltaPct = 100.0;
            }

            $partPct = $total > 0 ? round(($nuitees / $total) * 100, 2) : 0.0;

            $result[] = [
                'nom_departement' => $row['nom_departement'],
                'nom_region' => $row['nom_region'],
                'nom_nouvelle_region' => $row['nom_nouvelle_region'],
                'n_nuitees' => $nuitees,
                'n_nuitees_n1' => $nuiteesN1,
                'delta_pct' => $deltaPct,
                'part_pct' => $partPct,
            ];
        }

        return $result;
    }

    private function hydrateRegionResult(array $currentRows, array $previousRows): array
    {
        $previousMap = [];
        foreach ($previousRows as $row) {
            $previousMap[$row['region']] = (int) ($row['total'] ?? 0);
        }

        $total = 0;
        foreach ($currentRows as $row) {
            $total += (int) ($row['total'] ?? 0);
        }

        $result = [];
        foreach ($currentRows as $row) {
            $region = $row['region'];
            $nuitees = (int) ($row['total'] ?? 0);
            $nuiteesN1 = $previousMap[$region] ?? 0;
            $deltaPct = null;
            if ($nuiteesN1 > 0) {
                $deltaPct = round((($nuitees - $nuiteesN1) / $nuiteesN1) * 100, 1);
            } elseif ($nuitees > 0) {
                $deltaPct = 100.0;
            }
            $partPct = $total > 0 ? round(($nuitees / $total) * 100, 1) : 0.0;

            $result[] = [
                'nom_region' => $region,
                'n_nuitees' => $nuitees,
                'n_nuitees_n1' => $nuiteesN1,
                'delta_pct' => $deltaPct,
                'part_pct' => $partPct,
            ];
        }

        return $result;
    }

    public function paysTouristes(Request $request): Response
    {
        $year = $this->requireQuery($request, 'annee');
        $period = $this->requireQuery($request, 'periode');
        $zone = $this->requireQuery($request, 'zone');
        $limit = (int) ($request->getQuery('limit') ?? 5);
        $debut = $request->getQuery('debut');
        $fin = $request->getQuery('fin');

        if ($limit <= 0) {
            throw HttpException::badRequest('Le paramètre limit doit être positif');
        }

        $cache = $this->data->cache();
        $cacheParams = [
            'annee' => $year,
            'periode' => $period,
            'zone' => $zone,
            'limit' => $limit,
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($cached = $cache->get('infographie_pays_touristes', $cacheParams)) {
            return $this->json([
                'success' => true,
                'data' => $cached,
            ])->withHeader('X-Cache-Status', 'HIT')->withHeader('X-Cache-Category', 'infographie_pays_touristes');
        }

        $pdo = $this->data->connection();
        [$zoneIds, $zoneLabel] = $this->data->resolveZoneIds($pdo, $zone);
        [$range, $prevRange] = $this->data->resolveDateRanges($year, $period, $debut, $fin);
        $dimensions = $this->data->resolveDimensions($pdo, 'TOURISTE', ['ETRANGER']);

        $current = $this->fetchPays($pdo, $zoneIds, $dimensions, $range, $limit);
        $previous = $this->fetchPays($pdo, $zoneIds, $dimensions, $prevRange, null);

        $result = $this->hydratePaysResult($current, $previous);
        $cache->set('infographie_pays_touristes', $cacheParams, $result);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'zone' => $zoneLabel,
                'annee' => $year,
                'periode' => $period,
                'limit' => $limit,
            ],
        ])->withHeader('X-Cache-Status', 'MISS')->withHeader('X-Cache-Category', 'infographie_pays_touristes');
    }

    public function paysExcursionnistes(Request $request): Response
    {
        $year = $this->requireQuery($request, 'annee');
        $period = $this->requireQuery($request, 'periode');
        $zone = $this->requireQuery($request, 'zone');
        $limit = (int) ($request->getQuery('limit') ?? 5);
        $debut = $request->getQuery('debut');
        $fin = $request->getQuery('fin');

        if ($limit <= 0) {
            throw HttpException::badRequest('Le paramètre limit doit être positif');
        }

        $cache = $this->data->cache();
        $cacheParams = [
            'annee' => $year,
            'periode' => $period,
            'zone' => $zone,
            'limit' => $limit,
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($cached = $cache->get('infographie_pays_excursionnistes', $cacheParams)) {
            return $this->json([
                'success' => true,
                'data' => $cached,
            ])->withHeader('X-Cache-Status', 'HIT')->withHeader('X-Cache-Category', 'infographie_pays_excursionnistes');
        }

        $pdo = $this->data->connection();
        [$zoneIds, $zoneLabel] = $this->data->resolveZoneIds($pdo, $zone);
        [$range, $prevRange] = $this->data->resolveDateRanges($year, $period, $debut, $fin);
        $dimensions = $this->data->resolveDimensions($pdo, 'EXCURSIONNISTE', ['ETRANGER']);

        $current = $this->fetchDiurnePays($pdo, $zoneIds, $dimensions, $range, $limit);
        $previous = $this->fetchDiurnePays($pdo, $zoneIds, $dimensions, $prevRange, null);

        $result = $this->hydratePaysResult($current, $previous);
        $cache->set('infographie_pays_excursionnistes', $cacheParams, $result);

        return $this->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'zone' => $zoneLabel,
                'annee' => $year,
                'periode' => $period,
                'limit' => $limit,
            ],
        ])->withHeader('X-Cache-Status', 'MISS')->withHeader('X-Cache-Category', 'infographie_pays_excursionnistes');
    }

    private function fetchDiurnePays(PDO $pdo, array $zoneIds, array $dimensions, array $range, ?int $limit = null): array
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));

        $sql = <<<SQL
        SELECT
            d.nom_pays AS pays,
            SUM(fd.volume) AS total
        FROM fact_diurnes_pays fd
        JOIN dim_pays d ON fd.id_pays = d.id_pays
        WHERE fd.date BETWEEN ? AND ?
          AND fd.id_zone IN ($zonePlaceholders)
          AND fd.id_categorie = ?
          AND fd.id_provenance IN ($provenancePlaceholders)
          AND d.nom_pays IS NOT NULL
          AND d.nom_pays NOT IN ('CUMUL', 'Cumul', '')
        GROUP BY d.nom_pays
        ORDER BY total DESC
        SQL;

        if ($limit !== null) {
            $sql .= ' LIMIT ?';
        }

        $stmt = $pdo->prepare($sql);
        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
        ];

        if ($limit !== null) {
            $params[] = $limit;
        }

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchPays(PDO $pdo, array $zoneIds, array $dimensions, array $range, ?int $limit = null): array
    {
        $zonePlaceholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $provenancePlaceholders = implode(',', array_fill(0, count($dimensions['id_provenances']), '?'));

        $sql = <<<SQL
        SELECT
            d.nom_pays AS pays,
            SUM(fd.volume) AS total
        FROM fact_nuitees_pays fd
        JOIN dim_pays d ON fd.id_pays = d.id_pays
        WHERE fd.date BETWEEN ? AND ?
          AND fd.id_zone IN ($zonePlaceholders)
          AND fd.id_categorie = ?
          AND fd.id_provenance IN ($provenancePlaceholders)
          AND d.nom_pays IS NOT NULL
          AND d.nom_pays NOT IN ('CUMUL', 'Cumul', '')
        GROUP BY d.nom_pays
        ORDER BY total DESC
        SQL;

        if ($limit !== null) {
            $sql .= ' LIMIT ?';
        }

        $stmt = $pdo->prepare($sql);
        $params = [
            $range['start'],
            $range['end'],
            ...$zoneIds,
            $dimensions['id_categorie'],
            ...$dimensions['id_provenances'],
        ];

        if ($limit !== null) {
            $params[] = $limit;
        }

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hydratePaysResult(array $currentRows, array $previousRows): array
    {
        $previousMap = [];
        foreach ($previousRows as $row) {
            $previousMap[$row['pays']] = (int) ($row['total'] ?? 0);
        }

        $total = 0;
        foreach ($currentRows as $row) {
            $total += (int) ($row['total'] ?? 0);
        }

        $result = [];
        foreach ($currentRows as $row) {
            $pays = $row['pays'];
            $nuitees = (int) ($row['total'] ?? 0);
            $nuiteesN1 = $previousMap[$pays] ?? 0;
            $deltaPct = null;

            if ($nuiteesN1 > 0) {
                $deltaPct = round((($nuitees - $nuiteesN1) / $nuiteesN1) * 100, 1);
            } elseif ($nuitees > 0) {
                $deltaPct = 100.0;
            }

            $partPct = $total > 0 ? round(($nuitees / $total) * 100, 1) : 0.0;

            $result[] = [
                'nom_pays' => $pays,
                'n_nuitees' => $nuitees,
                'n_nuitees_n1' => $nuiteesN1,
                'delta_pct' => $deltaPct,
                'part_pct' => $partPct,
            ];
        }

        return $result;
    }
}
