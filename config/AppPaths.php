<?php
// config/AppPaths.php
//
// Where the app keeps files that are not part of the code:
// - config dir: credentials (database, admin login, cron token), outside the webroot
// - data dir:   downloaded KNMI station files (etmgeg_*.txt)
// In Docker both are volumes (KNMI_CONFIG_DIR, KNMI_DATA_DIR). Without the
// environment variables the old locations are used, so a plain PHP host still works.
class AppPaths {
    public static function rootDir(): string {
        return dirname(__DIR__);
    }

    public static function configDir(): string {
        $dir = getenv('KNMI_CONFIG_DIR');
        return ($dir !== false && $dir !== '') ? rtrim($dir, '/') : dirname(__DIR__, 2);
    }

    public static function dataDir(): string {
        $dir = getenv('KNMI_DATA_DIR');
        return ($dir !== false && $dir !== '') ? rtrim($dir, '/') : self::rootDir();
    }

    /**
     * 'production' or 'development'. Docker sets APP_ENV; the FTP deploy writes
     * config/environment.php. Without either we assume production.
     */
    public static function environment(): string {
        $env = getenv('APP_ENV');
        if ($env !== false && $env !== '') {
            return $env;
        }

        $environmentFile = self::rootDir() . '/config/environment.php';
        return is_file($environmentFile) ? (string)(require $environmentFile) : 'production';
    }
}
