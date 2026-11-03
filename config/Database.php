<?php
// config/Database.php
//
// PostgreSQL connection. Configuration is read from environment variables
// (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD), which is how the Docker
// setup passes it in. Without those, a credentials file outside the webroot is
// used: <config dir>/knmi.database.credentials.php returning
// ['host' => ..., 'port' => 5432, 'db_name' => ..., 'username' => ..., 'password' => ...].
require_once __DIR__ . '/AppPaths.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;

    public function __construct($configPath = null) {
        $config = $this->configFromEnvironment();

        if ($config === null) {
            $configPath = $configPath ?: AppPaths::configDir() . '/knmi.database.credentials.php';
            if (!file_exists($configPath)) {
                throw new Exception("Database configuration file not found");
            }
            $config = require $configPath;
        }

        foreach (['host', 'db_name', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new Exception("Database configuration is missing: " . $key);
            }
        }

        $this->host = $config['host'];
        $this->port = (int)($config['port'] ?? 5432);
        $this->db_name = $config['db_name'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    private function configFromEnvironment(): ?array {
        $host = getenv('DB_HOST');
        if ($host === false || $host === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => getenv('DB_PORT') ?: 5432,
            'db_name' => getenv('DB_NAME') ?: '',
            'username' => getenv('DB_USER') ?: '',
            'password' => getenv('DB_PASSWORD') ?: ''
        ];
    }

    public function connect() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch(PDOException $e) {
                error_log("Connection error: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->conn;
    }
}
