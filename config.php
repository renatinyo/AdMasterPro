<?php
/**
 * AdMaster Pro - Configuration
 * 
 * BIZTONSÁGI BEÁLLÍTÁSOK:
 * 1. Ez a fájl SOHA ne legyen web-root-ban!
 * 2. Helyezd a public_html FÖLÉ (pl. /home/user/config/)
 * 3. Vagy védd .htaccess-szel
 */

// Környezet: 'development' vagy 'production'
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Debug mód (SOHA ne legyen true production-ben!)
define('APP_DEBUG', APP_ENV === 'development');

// Demo mód - működik API nélkül is
// Ha true, akkor nem hív API-t, hanem példa adatokat ad vissza
define('DEMO_MODE', false);

// API Key - KÖTELEZŐ beállítani (kivéve DEMO_MODE)!
// Legjobb: környezeti változóból olvasni
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');

// ============================================
// ADATBÁZIS BEÁLLÍTÁSOK (OPCIONÁLIS)
// ============================================
// Ha üres, fájl alapú tárolás lesz használva
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'admaster_pro');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ============================================
// SESSION BEÁLLÍTÁSOK
// ============================================
define('SESSION_LIFETIME', 3600); // 1 óra
define('SESSION_NAME', 'admaster_session');

// ============================================
// RATE LIMITING
// ============================================
define('RATE_LIMIT_REQUESTS', 30); // Max kérés
define('RATE_LIMIT_WINDOW', 3600); // Időablak (másodperc)

// ============================================
// FÁJL FELTÖLTÉS
// ============================================
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED_TYPES', ['text/csv', 'application/vnd.ms-excel', 'text/plain']);

// ============================================
// EGYÉB BEÁLLÍTÁSOK
// ============================================
define('CSRF_TOKEN_LIFETIME', 3600);
define('PROJECTS_DIR', __DIR__ . '/data/projects/');
define('MAX_PROJECTS_PER_USER', 50);
define('LOG_DIR', __DIR__ . '/logs/');
define('LOG_LEVEL', APP_DEBUG ? 'debug' : 'error');

// Alkalmazás info
// Alkalmazás info
define('APP_NAME', 'AdMaster Pro');
define('APP_VERSION', '5.0.6');
define('DB_REQUIRED', true); // Adatbázis KÖTELEZŐ v5.0-tól

// SerpApi kulcs (versenytárs figyeléshez)
define('SERPAPI_KEY', getenv('SERPAPI_KEY') ?: '');

// Screenshot API (screenshotlayer.com vagy screenshotmachine.com)
define('SCREENSHOT_API_KEY', getenv('SCREENSHOT_API_KEY') ?: '');
define('SCREENSHOT_API_URL', 'https://api.screenshotmachine.com/?key=%s&url=%s&dimension=1280x1024&format=png');

// Google Ads API
define('GOOGLE_ADS_DEVELOPER_TOKEN', getenv('GOOGLE_ADS_DEVELOPER_TOKEN') ?: '');
define('GOOGLE_ADS_CLIENT_ID', getenv('GOOGLE_ADS_CLIENT_ID') ?: '');
define('GOOGLE_ADS_CLIENT_SECRET', getenv('GOOGLE_ADS_CLIENT_SECRET') ?: '');
define('GOOGLE_ADS_REFRESH_TOKEN', getenv('GOOGLE_ADS_REFRESH_TOKEN') ?: '');

/**
 * Error handler beállítás
 */
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_DIR . 'php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

/**
 * Könyvtárak létrehozása ha nem léteznek
 */
$directories = [PROJECTS_DIR, LOG_DIR];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
}
