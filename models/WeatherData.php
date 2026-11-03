<?php
// models/WeatherData.php
class WeatherData {
    private $conn;
    private $table = 'daily_observations';
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    private const DAY_NAMES = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
    private const MONTH_NAMES = [1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    private const MONTH_SHORT = [1 => 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

    /**
     * Get weather data for a specific date
     */
    public function getDataByDate($date, $station = 260) {
        $query = "
            SELECT
                station AS stn,
                date,
                ddvec AS wind_direction,
                fhvec AS wind_speed_vector,
                fg AS wind_speed_avg,
                fhx AS wind_speed_max,
                fhxh AS wind_speed_max_hour,
                fhn AS wind_speed_min,
                fhnh AS wind_speed_min_hour,
                fxx AS wind_gust_max,
                fxxh AS wind_gust_max_hour,
                tg AS temp_avg,
                tn AS temp_min,
                tnh AS temp_min_hour,
                tx AS temp_max,
                txh AS temp_max_hour,
                t10n AS temp_ground_min,
                t10nh AS temp_ground_min_period,
                sq AS sun_duration,
                sp AS sun_percentage,
                q AS radiation_global,
                dr AS rain_duration,
                rh AS rain_amount,
                rhx AS rain_amount_max,
                rhxh AS rain_amount_max_hour,
                pg AS pressure_avg,
                px AS pressure_max,
                pxh AS pressure_max_hour,
                pn AS pressure_min,
                pnh AS pressure_min_hour,
                vvn AS visibility_min,
                vvnh AS visibility_min_hour,
                vvx AS visibility_max,
                vvxh AS visibility_max_hour,
                ng AS cloud_cover,
                ug AS humidity_avg,
                ux AS humidity_max,
                uxh AS humidity_max_hour,
                un AS humidity_min,
                unh AS humidity_min_hour,
                ev24 AS evaporation
            FROM {$this->table}
            WHERE station = :station AND date = :date
            LIMIT 1
        ";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':station', (int)$station, PDO::PARAM_INT);
            $stmt->bindValue(':date', $date, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch();

            if ($result) {
                return $this->formatWeatherData($this->addDateNames($result));
            }

            return null;
        } catch(PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception("Error retrieving weather data");
        }
    }

    /**
     * Get weather data for multiple days (for charts)
     */
    public function getDataForPeriod($startDate, $endDate, $station = 260) {
        $query = "
            SELECT
                date,
                tg AS temp_avg,
                tn AS temp_min,
                tx AS temp_max,
                fg AS wind_speed_avg,
                rh AS rain_amount,
                dr AS rain_duration,
                sq AS sun_duration,
                q AS radiation_global,
                pg AS pressure_avg
            FROM {$this->table}
            WHERE station = :station
                AND date BETWEEN :start_date AND :end_date
            ORDER BY date ASC
        ";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':station', (int)$station, PDO::PARAM_INT);
            $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
            $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
            $stmt->execute();

            $results = $stmt->fetchAll();
            foreach ($results as &$row) {
                $timestamp = strtotime($row['date']);
                $row['date_short'] = (int)date('j', $timestamp) . ' ' . self::MONTH_SHORT[(int)date('n', $timestamp)];
            }
            unset($row);

            return array_map([$this, 'formatChartData'], $results);
        } catch(PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception("Error retrieving period data");
        }
    }

    /**
     * Get monthly statistics
     */
    public function getMonthlyStats($year, $month, $station = 260) {
        $query = "
            SELECT
                COUNT(*) AS total_days,
                AVG(tg) AS temp_avg_month,
                MIN(tn) AS temp_min_month,
                MAX(tx) AS temp_max_month,
                SUM(rh) AS rain_total_month,
                SUM(sq) AS sun_total_month,
                AVG(fg) AS wind_avg_month,
                AVG(pg) AS pressure_avg_month,
                COUNT(*) FILTER (WHERE rh >= 0.1) AS rain_days,
                COUNT(*) FILTER (WHERE tx >= 20) AS summer_days,
                COUNT(*) FILTER (WHERE tn < 0) AS frost_days
            FROM {$this->table}
            WHERE station = :station
                AND date >= make_date(:year, :month, 1)
                AND date < make_date(:year2, :month2, 1) + INTERVAL '1 month'
        ";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':station', (int)$station, PDO::PARAM_INT);
            $stmt->bindValue(':year', (int)$year, PDO::PARAM_INT);
            $stmt->bindValue(':month', (int)$month, PDO::PARAM_INT);
            $stmt->bindValue(':year2', (int)$year, PDO::PARAM_INT);
            $stmt->bindValue(':month2', (int)$month, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();

            return $this->formatMonthlyStats($result);
        } catch(PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception("Error retrieving monthly statistics");
        }
    }

    /**
     * Get date range available in database
     */
    public function getDateRange($station = 260) {
        $query = "
            SELECT
                MIN(date) AS first_date,
                MAX(date) AS last_date
            FROM {$this->table}
            WHERE station = :station
        ";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':station', (int)$station, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception("Error retrieving date range");
        }
    }

    private function addDateNames(array $row): array {
        $timestamp = strtotime($row['date']);
        $dayName = self::DAY_NAMES[(int)date('w', $timestamp)];
        $month = (int)date('n', $timestamp);
        $year = (int)date('Y', $timestamp);

        $row['day_name'] = $dayName;
        $row['month_name'] = self::MONTH_NAMES[$month];
        $row['date_formatted'] = $dayName . ', ' . (int)date('j', $timestamp) . ' ' . self::MONTH_NAMES[$month] . ' ' . $year;
        $row['month'] = $month;
        $row['year'] = $year;

        return $row;
    }

    /**
     * Format weather data for API response
     */
    private function formatWeatherData($data) {
        return [
            'station' => $this->raw($data['stn']),
            'date' => $data['date'],
            'date_formatted' => $data['date_formatted'],
            'day_name' => $data['day_name'],
            'month_name' => $data['month_name'],
            'month' => $data['month'],
            'year' => $data['year'],
            'temperature' => [
                'avg' => $this->convertTemperature($data['temp_avg']),
                'min' => $this->convertTemperature($data['temp_min']),
                'max' => $this->convertTemperature($data['temp_max']),
                'ground_min' => $this->convertTemperature($data['temp_ground_min']),
                'min_hour' => $this->raw($data['temp_min_hour']),
                'max_hour' => $this->raw($data['temp_max_hour']),
                'ground_min_period' => $this->raw($data['temp_ground_min_period'])
            ],
            'wind' => [
                'direction' => $this->raw($this->convertWindDirection($data['wind_direction'])),
                'direction_degrees' => $this->raw($data['wind_direction']),
                'direction_text' => $this->getWindDirectionText($data['wind_direction']),
                'speed_vector' => $this->convertWindSpeed($data['wind_speed_vector']),
                'speed_avg' => $this->convertWindSpeed($data['wind_speed_avg']),
                'speed_max' => $this->convertWindSpeed($data['wind_speed_max']),
                'speed_min' => $this->convertWindSpeed($data['wind_speed_min']),
                'gust_max' => $this->convertWindSpeed($data['wind_gust_max']),
                'beaufort' => $this->getBeaufortScale($this->convertWindSpeed($data['wind_speed_avg'])),
                'speed_max_hour' => $this->raw($data['wind_speed_max_hour']),
                'speed_min_hour' => $this->raw($data['wind_speed_min_hour']),
                'gust_max_hour' => $this->raw($data['wind_gust_max_hour'])
            ],
            'precipitation' => [
                'amount' => $this->convertPrecipitation($data['rain_amount']),
                'duration' => $this->convertDuration($data['rain_duration']),
                'amount_max' => $this->convertPrecipitation($data['rain_amount_max']),
                'amount_max_hour' => $this->raw($data['rain_amount_max_hour'])
            ],
            'sunshine' => [
                'duration' => $this->convertDuration($data['sun_duration']),
                'percentage' => $this->raw($data['sun_percentage']),
                'radiation' => $this->convertRadiation($data['radiation_global'])
            ],
            'pressure' => [
                'avg' => $this->convertPressure($data['pressure_avg']),
                'max' => $this->convertPressure($data['pressure_max']),
                'min' => $this->convertPressure($data['pressure_min']),
                'max_hour' => $this->raw($data['pressure_max_hour']),
                'min_hour' => $this->raw($data['pressure_min_hour'])
            ],
            'visibility' => [
                'min' => $this->convertVisibility($data['visibility_min']),
                'max' => $this->convertVisibility($data['visibility_max']),
                'min_hour' => $this->raw($data['visibility_min_hour']),
                'max_hour' => $this->raw($data['visibility_max_hour'])
            ],
            'humidity' => [
                'avg' => $this->raw($data['humidity_avg']),
                'max' => $this->raw($data['humidity_max']),
                'min' => $this->raw($data['humidity_min']),
                'max_hour' => $this->raw($data['humidity_max_hour']),
                'min_hour' => $this->raw($data['humidity_min_hour'])
            ],
            'cloud_cover' => $this->raw($data['cloud_cover']),
            'evaporation' => $this->convertEvaporation($data['evaporation'])
        ];
    }
    
    /**
     * Format chart data
     */
    private function formatChartData($data) {
        return [
            'date' => $data['date'],
            'date_short' => $data['date_short'],
            'temp_avg' => $this->convertTemperature($data['temp_avg']),
            'temp_min' => $this->convertTemperature($data['temp_min']),
            'temp_max' => $this->convertTemperature($data['temp_max']),
            'wind_speed' => $this->convertWindSpeed($data['wind_speed_avg']),
            'rain_amount' => $this->convertPrecipitation($data['rain_amount']),
            'rain_duration' => $this->convertDuration($data['rain_duration']),
            'sun_duration' => $this->convertDuration($data['sun_duration']),
            'radiation' => $this->convertRadiation($data['radiation_global']),
            'pressure' => $this->convertPressure($data['pressure_avg'])
        ];
    }
    
    /**
     * Format monthly statistics
     */
    private function formatMonthlyStats($data) {
        return [
            'total_days' => $data['total_days'],
            'temperature' => [
                'avg' => $this->convertTemperature($data['temp_avg_month']),
                'min' => $this->convertTemperature($data['temp_min_month']),
                'max' => $this->convertTemperature($data['temp_max_month'])
            ],
            'precipitation' => [
                'total' => $this->convertPrecipitation($data['rain_total_month']),
                'days' => $data['rain_days']
            ],
            'sunshine' => [
                'total' => $this->convertDuration($data['sun_total_month'])
            ],
            'wind' => [
                'avg' => $this->convertWindSpeed($data['wind_avg_month'])
            ],
            'pressure' => [
                'avg' => $this->convertPressure($data['pressure_avg_month'])
            ],
            'special_days' => [
                'summer_days' => $data['summer_days'], // >20°C
                'frost_days' => $data['frost_days']    // <0°C
            ]
        ];
    }
    
    // Conversion methods. Values are stored in real units; these only round and
    // convert to the units the API has always returned.
    private function convertTemperature($value) {
        return $value !== null ? round((float)$value, 1) : null;
    }

    private function convertWindSpeed($value) {
        return $value !== null ? round((float)$value * 3.6, 1) : null; // m/s to km/h
    }

    private function convertPrecipitation($value) {
        // Trace (< 0.05 mm) is stored as 0.05 and shown as 0.1 mm, as before.
        return $value !== null ? round((float)$value, 1) : null;
    }

    private function convertDuration($value) {
        return $value !== null ? round((float)$value, 1) : null;
    }

    private function convertPressure($value) {
        return $value !== null ? round((float)$value, 1) : null;
    }

    private function convertVisibility($value) {
        return $value !== null ? round((float)$value, 1) : null;
    }

    /**
     * Pass-through values (hours, percentages, codes) were strings in the old
     * MySQL API; keep that type so the frontend and API users see no change.
     */
    private function raw($value) {
        return $value !== null ? (string)$value : null;
    }

    private function convertRadiation($value) {
        return $value !== null ? (int)$value : null;
    }

    private function convertEvaporation($value) {
        return $value !== null ? round((float)$value, 1) : null;
    }

    private function convertWindDirection($degrees) {
        return $degrees;
    }
    
    private function getWindDirectionText($degrees) {
        if ($degrees == 0) return 'Windstil';
        if ($degrees == 990) return 'Veranderlijk';
        
        $directions = [
            'N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO',
            'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'
        ];
        
        $index = round($degrees / 22.5) % 16;
        return $directions[$index];
    }
    
    private function getBeaufortScale($windSpeed) {
        if ($windSpeed === null) return null;
        
        $kmhToMS = $windSpeed / 3.6; // Convert km/h to m/s
        
        if ($kmhToMS <= 0.2) return ['scale' => 0, 'description' => 'Windstil'];
        if ($kmhToMS <= 1.5) return ['scale' => 1, 'description' => 'Zwakke wind'];
        if ($kmhToMS <= 3.3) return ['scale' => 2, 'description' => 'Zwakke wind'];
        if ($kmhToMS <= 5.4) return ['scale' => 3, 'description' => 'Matige wind'];
        if ($kmhToMS <= 7.9) return ['scale' => 4, 'description' => 'Matige wind'];
        if ($kmhToMS <= 10.7) return ['scale' => 5, 'description' => 'Vrij krachtige wind'];
        if ($kmhToMS <= 13.8) return ['scale' => 6, 'description' => 'Krachtige wind'];
        if ($kmhToMS <= 17.1) return ['scale' => 7, 'description' => 'Harde wind'];
        if ($kmhToMS <= 20.7) return ['scale' => 8, 'description' => 'Stormachtige wind'];
        if ($kmhToMS <= 24.4) return ['scale' => 9, 'description' => 'Storm'];
        if ($kmhToMS <= 28.4) return ['scale' => 10, 'description' => 'Zware storm'];
        if ($kmhToMS <= 32.6) return ['scale' => 11, 'description' => 'Zeer zware storm'];
        return ['scale' => 12, 'description' => 'Orkaan'];
    }
}
