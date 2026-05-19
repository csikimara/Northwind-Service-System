<?php
declare(strict_types=1);

/**
 * Northwind Service System - alap konfiguráció
 */

date_default_timezone_set('Europe/Budapest');

define('APP_NAME', 'Northwind Service System');
define('APP_VERSION', '2.0.0');

/**
 * Adatbázis beállítások
 * Ezeket a cPanel MySQL adataidra kell majd átírni.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'xxxxxxxx');     // A teljes cPanel-es adatbázis név
define('DB_USER', 'xxxxxxxx');    // A teljes cPanel-es felhasználónév
define('DB_PASS', 'xxxxxxxx); 
define('DB_CHARSET', xxxxxx);

// A weboldalad új, pontos elérési útja
define('BASE_URL', xxxxxxxx);

/**
 * Feltöltési beállítások
 * A képek végleges helye NAS lesz.
 */
define('UPLOAD_TMP_DIR', __DIR__ . '/data/uploads_tmp');

/**
 * NAS alapútvonal.
 */
define('NAS_MODE', 'local'); 
define('NAS_BASE_PATH', '/mnt/nas/Northwind');

/**
 * Maximális feltöltési méret képenként.
 */
define('MAX_UPLOAD_SIZE', 15 * 1024 * 1024);

/**
 * Engedélyezett képtípusok.
 */
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
]);

/**
 * Fejlesztői hibakijelzés.
 * Éles rendszerben false legyen.
 */
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}