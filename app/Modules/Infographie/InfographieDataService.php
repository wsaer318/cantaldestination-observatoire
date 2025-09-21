<?php
declare(strict_types=1);

namespace App\Modules\Infographie;

use App\Core\HttpException;
use DateTime;
use Exception;
use PDO;

class InfographieDataService
{
    public function __construct()
    {
        require_once BASE_PATH . '/api/infographie/CacheManager.php';
        require_once BASE_PATH . '/api/infographie/periodes_manager_db.php';
        require_once BASE_PATH . '/classes/ZoneMapper.php';
        require_once BASE_PATH . '/database.php';
    }

    public function cache(): \CantalDestinationCacheManager
    {
        return new \CantalDestinationCacheManager();
    }

    public function connection(): PDO
    {
        return \DatabaseConfig::getConnection();
    }

    /**
     * @return array{0: array<int>, 1: string}
     */
    public function resolveZoneIds(PDO $pdo, string $zone): array
    {
        $mapped = \ZoneMapper::displayToBase($zone);
        $stmt = $pdo->prepare('SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?');
        $stmt->execute([$mapped]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw HttpException::notFound('Zone inconnue');
        }

        $zoneIds = [(int) $row['id_zone']];

        if ($mapped === 'HAUTES TERRES') {
            $legacy = $pdo->prepare("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = 'HAUTES TERRES COMMUNAUTE'");
            $legacy->execute();
            if ($legacyRow = $legacy->fetch(PDO::FETCH_ASSOC)) {
                $zoneIds[] = (int) $legacyRow['id_zone'];
            }
        }

        return [$zoneIds, $mapped];
    }

    /**
     * @return array{0: array{start: string, end: string}, 1: array{start: string, end: string}}
     */
    public function resolveDateRanges(string $year, string $period, ?string $start, ?string $end): array
    {
        if ($start && $end) {
            try {
                $startDate = new DateTime($start . ' 00:00:00');
                $endDate = new DateTime($end . ' 23:59:59');
            } catch (Exception $exception) {
                throw HttpException::badRequest('Parametres de date invalides');
            }

            $previousStart = (clone $startDate)->modify('-1 year');
            $previousEnd = (clone $endDate)->modify('-1 year');

            return [
                ['start' => $startDate->format('Y-m-d H:i:s'), 'end' => $endDate->format('Y-m-d H:i:s')],
                ['start' => $previousStart->format('Y-m-d H:i:s'), 'end' => $previousEnd->format('Y-m-d H:i:s')],
            ];
        }

        $current = \PeriodesManagerDB::calculateDateRanges($year, $period);
        $previous = \PeriodesManagerDB::calculateDateRanges((int) $year - 1, $period);

        return [$current, $previous];
    }

    /**
     * @param array<int, string> $provenances
     *
     * @return array{id_categorie: int, id_provenances: array<int, int>}
     */
    public function resolveDimensions(PDO $pdo, string $category, array $provenances): array
    {
        $categoryId = $this->resolveCategoryId($pdo, $category);

        $uniqueProvenances = array_values(array_unique($provenances));
        if (count($uniqueProvenances) === 0) {
            throw HttpException::badRequest('Au moins une provenance est requise');
        }

        $provenanceIds = [];
        $missing = [];
        foreach ($uniqueProvenances as $provenance) {
            $resolved = $this->resolveProvenanceId($pdo, $provenance);
            if ($resolved === null) {
                $missing[] = $provenance;
                continue;
            }
            $provenanceIds[] = $resolved;
        }

        if (count($missing) > 0) {
            $missingList = implode(', ', $missing);
            throw HttpException::badRequest("Provenance inconnue: {$missingList}");
        }

        return [
            'id_categorie' => $categoryId,
            'id_provenances' => $provenanceIds,
        ];
    }

    private function resolveCategoryId(PDO $pdo, string $category): int
    {
        $sql = "SELECT id_categorie FROM dim_categories_visiteur\n                WHERE LOWER(REPLACE(REPLACE(nom_categorie, ' ', ''), '-', '')) = LOWER(REPLACE(REPLACE(?, ' ', ''), '-', ''))\n                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw HttpException::badRequest("Categorie inconnue: {$category}");
        }

        return (int) $row['id_categorie'];
    }

    private function resolveProvenanceId(PDO $pdo, string $provenance): ?int
    {
        $normalize = static function (string $value): string {
            return strtolower(str_replace([' ', '-', '_'], '', $value));
        };

        $normalized = $normalize($provenance);
        $sql = "SELECT id_provenance, nom_provenance FROM dim_provenances";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if ($normalize($row['nom_provenance']) === $normalized) {
                return (int) $row['id_provenance'];
            }
        }

        return null;
    }
}
