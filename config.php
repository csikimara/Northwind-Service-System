<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Budapest');

// A környezeti változók betöltése (Ha a szervereden nincs automatikus, 
// használhatod a getenv() függvényt, ami közvetlenül a .env-ből olvas)
define('APP_NAME', 'Northwind Service System');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'https://sandbox.northwind.hu');
define('NAS_BASE_PATH', '/mnt/nas/Northwind');

// ... (a többi beállítás marad változatlanul)

/**
 * Feltöltési beállítások
 */
define('UPLOAD_TMP_DIR', __DIR__ . '/data/uploads_tmp');

/**
 * NAS alapútvonal.
 */
define('NAS_MODE', 'local'); 
define('NAS_BASE_PATH', '/mnt/nas/Northwind');

/**
 * Maximális feltöltési méret képenként (15 MB).
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