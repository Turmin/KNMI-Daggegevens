<?php

require_once __DIR__ . '/KnmiStationCatalog.php';
require_once __DIR__ . '/DatabaseSchema.php';

class YearlyStatsService {
    public const DEFAULT_YEARLY_START_DATE = '1901-01-01';
    public const DEFAULT_PRECIPITATION_START_DATE = '1906-01-01';

    private $db;
    private $cacheDir;

    public function __construct(PDO $db, ?string $cacheDir = null) {
        $this->db = $db;
        // In Docker the cron and web containers share the data volume, so the cache
        // the cron job warms up after an import is the one the website reads.
        $sharedDataDir = getenv('KNMI_DATA_DIR');
        $defaultDir = ($sharedDataDir !== false && $sharedDataDir !== '')
            ? rtrim($sharedDataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'yearly-stats'
            : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'knmi-yearly-stats';
        $this->cacheDir = $cacheDir ?: $defaultDir;
    }

    public function getStats(
        int $station = KnmiStationCatalog::DEFAULT_STATION,
        bool $forceRefresh = false,
        string $yearlyStartDate = self::DEFAULT_YEARLY_START_DATE,
        string $precipitationStartDate = self::DEFAULT_PRECIPITATION_START_DATE
    ): array {
        $latestDate = $this->latestDataDate($station);
        $cachePath = $this->cachePath($station, $yearlyStartDate, $precipitationStartDate, $latestDate);

        if (!$forceRefresh) {
            $cached = $this->readCache($cachePath, $latestDate);
            if ($cached !== null) {
                $cached['cache_hit'] = true;
                $cached['cache_path'] = $cachePath;
                return $cached;
            }
        }

        $stats = $this->computeStats($station, $yearlyStartDate, $precipitationStartDate);
        $payload = [
            'latest_date' => $latestDate,
            'created_at' => date('c'),
            'rows' => $stats['rows'],
            'daily_records' => $stats['daily_records'],
            'cache_hit' => false,
            'cache_path' => $cachePath
        ];

        $this->writeCache($cachePath, $payload);

        return $payload;
    }

    public function warmCache(
        int $station = KnmiStationCatalog::DEFAULT_STATION,
        string $yearlyStartDate = self::DEFAULT_YEARLY_START_DATE,
        string $precipitationStartDate = self::DEFAULT_PRECIPITATION_START_DATE
    ): array {
        $stats = $this->getStats($station, true, $yearlyStartDate, $precipitationStartDate);
        $yearCount = count($stats['rows'] ?? []);
        $latestDate = $stats['latest_date'] ?: 'unknown latest date';

        return [
            'success' => true,
            'latest_date' => $stats['latest_date'],
            'cache_path' => $stats['cache_path'],
            'messages' => [
                'Yearly statistics cache rebuilt for station ' . $station . ' through ' . $latestDate . ' (' . $yearCount . ' years).'
            ]
        ];
    }

    private function latestDataDate(int $station): string {
        $stmt = $this->db->prepare('SELECT MAX(date) FROM ' . DatabaseSchema::OBSERVATIONS_TABLE . ' WHERE station = :station');
        $stmt->bindValue(':station', $station, PDO::PARAM_INT);
        $stmt->execute();

        return (string)($stmt->fetchColumn() ?: '');
    }

    private function cachePath(int $station, string $yearlyStartDate, string $precipitationStartDate, string $latestDate): string {
        $cacheKey = implode('-', [
            'v3',
            $station,
            preg_replace('/[^0-9]/', '', $yearlyStartDate),
            preg_replace('/[^0-9]/', '', $precipitationStartDate),
            preg_replace('/[^0-9]/', '', $latestDate)
        ]);

        return $this->cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    }

    private function readCache(string $path, string $latestDate): ?array {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $payload = json_decode($contents, true);
        if (!is_array($payload) || ($payload['latest_date'] ?? null) !== $latestDate) {
            return null;
        }

        if (!isset($payload['rows']) || !is_array($payload['rows'])) {
            return null;
        }

        if (isset($payload['daily_records']) && !is_array($payload['daily_records'])) {
            return null;
        }

        return $payload;
    }

    private function writeCache(string $path, array $payload): void {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        file_put_contents($path, json_encode($payload), LOCK_EX);
    }

    private function computeStats(int $station, string $yearlyStartDate, string $precipitationStartDate): array {
        $rows = $this->fetchYearRows($station, $yearlyStartDate);
        $rainMonthsByYear = $this->fetchRainMonthsByYear($station, $precipitationStartDate);

        foreach ($rows as &$row) {
            $row['precipitation_mm'] = null;
            $row['precipitation_min_month'] = null;
            $row['precipitation_min_month_mm'] = null;
            $row['precipitation_avg_month'] = null;
            $row['precipitation_avg_month_mm'] = null;
            $row['precipitation_max_month'] = null;
            $row['precipitation_max_month_mm'] = null;
            $row['precipitation_days'] = null;

            $rainMonths = $rainMonthsByYear[(int)$row['year']] ?? [];
            if (!$rainMonths) {
                continue;
            }

            $totalMm = 0.0;
            $precipitationDays = 0;
            $minMonth = null;
            $maxMonth = null;

            foreach ($rainMonths as $rainMonth) {
                $totalMm += $rainMonth['precipitation_mm'];
                $precipitationDays += $rainMonth['precipitation_days'];

                if ($minMonth === null || $rainMonth['precipitation_mm'] < $minMonth['precipitation_mm']) {
                    $minMonth = $rainMonth;
                }

                if ($maxMonth === null || $rainMonth['precipitation_mm'] > $maxMonth['precipitation_mm']) {
                    $maxMonth = $rainMonth;
                }
            }

            $averageMm = $totalMm / count($rainMonths);

            $row['precipitation_mm'] = round($totalMm, 1);
            $row['precipitation_min_month'] = $minMonth['month'];
            $row['precipitation_min_month_mm'] = round($minMonth['precipitation_mm'], 1);
            $row['precipitation_avg_month'] = null;
            $row['precipitation_avg_month_mm'] = round($averageMm, 1);
            $row['precipitation_max_month'] = $maxMonth['month'];
            $row['precipitation_max_month_mm'] = round($maxMonth['precipitation_mm'], 1);
            $row['precipitation_days'] = $precipitationDays;
        }
        unset($row);

        return [
            'rows' => $rows,
            'daily_records' => [
                'warmest_day' => $this->fetchTemperatureRecord($station, $yearlyStartDate, 'tx', 'DESC'),
                'coldest_day' => $this->fetchTemperatureRecord($station, $yearlyStartDate, 'tn', 'ASC'),
                'wettest_day' => $this->fetchWettestDay($station, $precipitationStartDate)
            ]
        ];
    }

    private function fetchYearRows(int $station, string $yearlyStartDate): array {
        $stmt = $this->db->prepare("
            SELECT
                EXTRACT(YEAR FROM date)::int AS year,
                COUNT(*) AS available_days,
                ROUND(MIN(tn), 1) AS temp_min_c,
                ROUND(AVG(tg), 1) AS temp_avg_c,
                ROUND(MAX(tx), 1) AS temp_max_c,
                ROUND(SUM(sq), 1) AS sunshine_hours
            FROM " . DatabaseSchema::OBSERVATIONS_TABLE . "
            WHERE station = :station
                AND date >= :start_date
            GROUP BY 1
            ORDER BY 1 ASC
        ");
        $stmt->bindValue(':station', $station, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $yearlyStartDate, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchRainMonthsByYear(int $station, string $precipitationStartDate): array {
        $stmt = $this->db->prepare("
            SELECT
                EXTRACT(YEAR FROM date)::int AS year,
                EXTRACT(MONTH FROM date)::int AS month,
                SUM(rh) AS precipitation_mm,
                COUNT(*) FILTER (WHERE rh > 0) AS precipitation_days
            FROM " . DatabaseSchema::OBSERVATIONS_TABLE . "
            WHERE station = :station
                AND date >= :precipitation_start_date
            GROUP BY 1, 2
            ORDER BY 1 ASC, 2 ASC
        ");
        $stmt->bindValue(':station', $station, PDO::PARAM_INT);
        $stmt->bindValue(':precipitation_start_date', $precipitationStartDate, PDO::PARAM_STR);
        $stmt->execute();

        $rainMonthsByYear = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            if ($row['precipitation_mm'] === null) {
                continue;
            }
            $rainMonthsByYear[(int)$row['year']][] = [
                'month' => (int)$row['month'],
                'precipitation_mm' => (float)$row['precipitation_mm'],
                'precipitation_days' => (int)$row['precipitation_days']
            ];
        }

        return $rainMonthsByYear;
    }

    private function fetchTemperatureRecord(int $station, string $startDate, string $column, string $direction): ?array {
        if (!in_array($column, ['tn', 'tx'], true) || !in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Invalid temperature record query.');
        }

        $stmt = $this->db->prepare("
            SELECT date, ROUND({$column}, 1) AS value
            FROM " . DatabaseSchema::OBSERVATIONS_TABLE . "
            WHERE station = :station
                AND date >= :start_date
                AND {$column} IS NOT NULL
            ORDER BY {$column} {$direction}, date ASC
            LIMIT 1
        ");
        $stmt->bindValue(':station', $station, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function fetchWettestDay(int $station, string $precipitationStartDate): ?array {
        $stmt = $this->db->prepare("
            SELECT date, ROUND(rh, 1) AS value
            FROM " . DatabaseSchema::OBSERVATIONS_TABLE . "
            WHERE station = :station
                AND date >= :precipitation_start_date
                AND rh IS NOT NULL
            ORDER BY rh DESC, date ASC
            LIMIT 1
        ");
        $stmt->bindValue(':station', $station, PDO::PARAM_INT);
        $stmt->bindValue(':precipitation_start_date', $precipitationStartDate, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
