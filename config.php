<?php
/**
 * AdMaster Pro - Configuration
 * 
 * v6.1.0: Csak az adatbázis-kapcsolat és alap rendszerbeállítások vannak itt.
 * Az API kulcsok és dinamikus beállítások az ADATBÁZISBAN tárolódnak,
 * és a Beállítások oldalon szerkeszthetők.
 * Így frissítéskor SEMMI nem vész el!
 * 
 * BIZTONSÁGI BEÁLLÍTÁSOK:
 * 1. Ez a fájl SOHA ne legyen publikusan elérhető!
 * 2. Helyezd a public_html FÖLÉ (pl. /home/user/config/)
 * 3. Vagy védd .htaccess-szel
 */

// ============================================
// KÖRNYEZET
// ============================================
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', APP_ENV === 'development');

// Alkalmazás info
define('APP_NAME', 'AdMaster Pro');
define('APP_VERSION', '6.1.0');
define('DB_REQUIRED', true);

// ============================================
// ADATBÁZIS KAPCSOLAT
// ============================================
// Ez az egyetlen dolog amit MANUÁLISAN kell beállítani!
// Minden más a Beállítások oldalon kezelhető.
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'admaster_pro');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ============================================
// ADMIN BELÉPÉS (alap - DB-ből felülírható)
// ============================================
// Első telepítés után a jelszó a DB-ben lesz tárolva.
// Ezek csak fallback értékek ha a DB még nem elérhető.
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

// ============================================
// FIX RENDSZERBEÁLLÍTÁSOK (nem dinamikus)
// ============================================
define('SESSION_NAME', 'admaster_session');
define('SESSION_LIFETIME', 3600); // Default - DB-ből felülírható
define('RATE_LIMIT_REQUESTS', 30); // Default - DB-ből felülírható  
define('RATE_LIMIT_WINDOW', 3600); // Default - DB-ből felülírható
define('CSRF_TOKEN_LIFETIME', 3600);
define('PROJECTS_DIR', __DIR__ . '/data/projects/');
define('MAX_PROJECTS_PER_USER', 50);
define('LOG_DIR', __DIR__ . '/logs/');
define('LOG_LEVEL', APP_DEBUG ? 'debug' : 'error');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_TYPES', ['text/csv', 'application/vnd.ms-excel', 'text/plain']);
define('SCREENSHOT_API_URL', 'https://api.screenshotmachine.com/?key=%s&url=%s&dimension=1280x1024&format=png');

// ============================================
// ERROR HANDLER
// ============================================
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_DIR . 'php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ============================================
// KÖNYVTÁRAK LÉTREHOZÁSA
// ============================================
$directories = [PROJECTS_DIR, LOG_DIR, __DIR__ . '/data/', __DIR__ . '/backups/'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
}
