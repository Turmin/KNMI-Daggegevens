<?php
declare(strict_types=1);

/**
 * PostgreSQL schema for the KNMI site.
 *
 * Measurements are stored in real units (°C, m/s, mm, hours, hPa, km) instead of
 * the tenths used in the KNMI etmgeg text files. Column names follow the KNMI
 * variable codes so they match both the etmgeg files and the KNMI Data Platform.
 *
 * Trace values: KNMI uses -1 for "less than 0.05" (precipitation and sunshine).
 * These are stored as 0.05, so they count as "more than nothing" but sum to almost nothing.
 */
class DatabaseSchema {
    public const OBSERVATIONS_TABLE = 'daily_observations';
    public const CRON_TABLE = 'cron_jobs';

    /** Column => [SQL type, unit/description]. Order matches the etmgeg files after station and date. */
    public const OBSERVATION_COLUMNS = [
        'ddvec' => ['smallint', 'Vector mean wind direction in degrees (360=north, 0=calm/variable)'],
        'fhvec' => ['numeric(4,1)', 'Vector mean wind speed (m/s)'],
        'fg' => ['numeric(4,1)', 'Daily mean wind speed (m/s)'],
        'fhx' => ['numeric(4,1)', 'Maximum hourly mean wind speed (m/s)'],
        'fhxh' => ['smallint', 'Hour in which fhx was measured'],
        'fhn' => ['numeric(4,1)', 'Minimum hourly mean wind speed (m/s)'],
        'fhnh' => ['smallint', 'Hour in which fhn was measured'],
        'fxx' => ['numeric(4,1)', 'Maximum wind gust (m/s)'],
        'fxxh' => ['smallint', 'Hour in which fxx was measured'],
        'tg' => ['numeric(4,1)', 'Daily mean temperature (°C)'],
        'tn' => ['numeric(4,1)', 'Minimum temperature (°C)'],
        'tnh' => ['smallint', 'Hour in which tn was measured'],
        'tx' => ['numeric(4,1)', 'Maximum temperature (°C)'],
        'txh' => ['smallint', 'Hour in which tx was measured'],
        't10n' => ['numeric(4,1)', 'Minimum temperature at 10 cm above ground (°C)'],
        't10nh' => ['smallint', '6-hour period in which t10n was measured'],
        'sq' => ['numeric(4,2)', 'Sunshine duration (hours, 0.05 = less than 0.05 h)'],
        'sp' => ['smallint', 'Percentage of maximum possible sunshine duration'],
        'q' => ['integer', 'Global radiation (J/cm²)'],
        'dr' => ['numeric(4,1)', 'Precipitation duration (hours)'],
        'rh' => ['numeric(5,2)', 'Daily precipitation amount (mm, 0.05 = less than 0.05 mm)'],
        'rhx' => ['numeric(5,2)', 'Maximum hourly precipitation amount (mm, 0.05 = less than 0.05 mm)'],
        'rhxh' => ['smallint', 'Hour in which rhx was measured'],
        'pg' => ['numeric(5,1)', 'Daily mean sea level pressure (hPa)'],
        'px' => ['numeric(5,1)', 'Maximum hourly sea level pressure (hPa)'],
        'pxh' => ['smallint', 'Hour in which px was measured'],
        'pn' => ['numeric(5,1)', 'Minimum hourly sea level pressure (hPa)'],
        'pnh' => ['smallint', 'Hour in which pn was measured'],
        'vvn' => ['numeric(4,1)', 'Minimum visibility (km, lower bound of the KNMI class)'],
        'vvnh' => ['smallint', 'Hour in which vvn was measured'],
        'vvx' => ['numeric(4,1)', 'Maximum visibility (km, lower bound of the KNMI class)'],
        'vvxh' => ['smallint', 'Hour in which vvx was measured'],
        'ng' => ['smallint', 'Mean daily cloud cover (octants, 9 = sky invisible)'],
        'ug' => ['smallint', 'Daily mean relative humidity (%)'],
        'ux' => ['smallint', 'Maximum relative humidity (%)'],
        'uxh' => ['smallint', 'Hour in which ux was measured'],
        'un' => ['smallint', 'Minimum relative humidity (%)'],
        'unh' => ['smallint', 'Hour in which un was measured'],
        'ev24' => ['numeric(4,1)', 'Potential evapotranspiration, Makkink (mm)'],
    ];

    public static function ensure(PDO $db): void {
        $isNew = !self::observationsTableExists($db);
        $columns = [];
        foreach (self::OBSERVATION_COLUMNS as $name => [$type]) {
            $columns[] = $name . ' ' . $type;
        }

        $db->exec('
            CREATE TABLE IF NOT EXISTS ' . self::OBSERVATIONS_TABLE . ' (
                station smallint NOT NULL,
                date date NOT NULL,
                ' . implode(",\n                ", $columns) . ',
                imported_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (station, date)
            )
        ');

        if ($isNew) {
            self::commentColumns($db);
        }

        // The primary key (station, date) already serves station + date range lookups;
        // this one speeds up calendar-day statistics ("every 25 July").
        $db->exec('
            CREATE INDEX IF NOT EXISTS daily_observations_station_month_day
            ON ' . self::OBSERVATIONS_TABLE . ' (station, (EXTRACT(MONTH FROM date)), (EXTRACT(DAY FROM date)))
        ');

        $db->exec("
            CREATE TABLE IF NOT EXISTS " . self::CRON_TABLE . " (
                id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                name varchar(120) NOT NULL,
                task varchar(40) NOT NULL,
                station smallint NULL,
                schedule varchar(40) NOT NULL,
                enabled boolean NOT NULL DEFAULT false,
                last_run_at timestamp NULL,
                last_status varchar(20) NULL,
                last_message text NULL,
                created_at timestamp NOT NULL DEFAULT now(),
                updated_at timestamp NOT NULL DEFAULT now()
            )
        ");
    }

    public static function observationsTableExists(PDO $db): bool {
        $stmt = $db->prepare('SELECT to_regclass(:table) IS NOT NULL');
        $stmt->execute([':table' => self::OBSERVATIONS_TABLE]);
        return (bool)$stmt->fetchColumn();
    }

    public static function commentColumns(PDO $db): void {
        foreach (self::OBSERVATION_COLUMNS as $name => [, $description]) {
            $db->exec('COMMENT ON COLUMN ' . self::OBSERVATIONS_TABLE . '.' . $name . ' IS ' . $db->quote($description));
        }
    }
}
