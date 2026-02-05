<?php
/**
 * AdMaster Pro - Beállítások Oldal
 * 
 * v6.1.0: Minden beállítás ADATBÁZISBAN tárolódik.
 * Frissítéskor semmi nem vész el!
 * 
 * - API kulcsok kezelése (DB)
 * - Jelszó módosítás (DB)
 * - Általános beállítások (DB)
 * - Rendszer információk
 * - Migráció régi config.php-ból
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';

Security::initSession();
Database::connect();

// Belépés ellenőrzés
if (Security::requireLogin()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// === AUTO-MIGRÁCIÓ: config.php → DB ===
// Ha vannak config.php-ban API kulcsok de a DB-ben nincsenek, átemeljük
$migrated = Settings::migrateFromConfig();
if (!empty($migrated)) {
    $message = '✅ ' . count($migrated) . ' beállítás automatikusan átemelve a config.php-ból az adatbázisba: ' . implode(', ', $migrated);
    $messageType = 'success';
}

// Form feldolgozás
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Biztonsági hiba. Próbáld újra.';
        $messageType = 'error';
    } else {
        $action = $_POST['settings_action'] ?? '';
        
        switch ($action) {
            case 'update_api':
                $result = updateApiSettings($_POST);
                $message = $result['message'];
                $messageType = $result['type'];
                break;
                
            case 'change_password':
                $result = changePassword($_POST);
                $message = $result['message'];
                $messageType = $result['type'];
                break;
                
            case 'update_general':
                $result = updateGeneralSettings($_POST);
                $message = $result['message'];
                $messageType = $result['type'];
                break;
        }
    }
}

/**
 * API beállítások mentése → ADATBÁZISBA
 */
function updateApiSettings(array $data): array {
    $saved = 0;
    
    // Anthropic API
    if (isset($data['anthropic_api_key'])) {
        $key = trim($data['anthropic_api_key']);
        // Csak ha nem maszkolt érték (••••••) és nem üres
        if ($key !== '' && strpos($key, '••') === false) {
            Settings::set('anthropic_api_key', $key);
            $saved++;
        }
    }
    
    // Google Ads
    $gadsFields = [
        'google_ads_developer_token',
        'google_ads_client_id',
        'google_ads_client_secret',
        'google_ads_refresh_token',
        'google_ads_login_customer_id',
    ];
    foreach ($gadsFields as $field) {
        if (isset($data[$field])) {
            $val = trim($data[$field]);
            if ($val !== '' && strpos($val, '••') === false) {
                Settings::set($field, $val);
                $saved++;
            } elseif ($val === '' && Settings::isConfigured($field)) {
                // Ha üresen küldi = törlés
                Settings::set($field, '');
                $saved++;
            }
        }
    }
    
    // Screenshot API
    if (isset($data['screenshot_api_key'])) {
        $val = trim($data['screenshot_api_key']);
        if (strpos($val, '••') === false) {
            Settings::set('screenshot_api_key', $val);
            $saved++;
        }
    }
    
    // SerpApi
    if (isset($data['serpapi_key'])) {
        $val = trim($data['serpapi_key']);
        if (strpos($val, '••') === false) {
            Settings::set('serpapi_key', $val);
            $saved++;
        }
    }
    
    // Extension API kulcs
    if (isset($data['extension_api_key'])) {
        $val = trim($data['extension_api_key']);
        if (strpos($val, '••') === false) {
            Settings::set('extension_api_key', $val);
            $saved++;
        }
    }
    
    Security::log('info', "API settings updated ($saved changes)");
    Settings::resetCache();
    
    return ['type' => 'success', 'message' => "✅ API beállítások mentve az adatbázisba! ($saved módosítás)"];
}

/**
 * Jelszó módosítás → ADATBÁZISBA
 */
function changePassword(array $data): array {
    $currentPass = $data['current_password'] ?? '';
    $newPass = $data['new_password'] ?? '';
    $confirmPass = $data['confirm_password'] ?? '';
    
    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        return ['type' => 'error', 'message' => '❌ Minden mező kitöltése kötelező!'];
    }
    
    if ($newPass !== $confirmPass) {
        return ['type' => 'error', 'message' => '❌ Az új jelszavak nem egyeznek!'];
    }
    
    if (strlen($newPass) < 8) {
        return ['type' => 'error', 'message' => '❌ Az új jelszó legalább 8 karakter legyen!'];
    }
    
    // Jelenlegi jelszó ellenőrzése: DB hash → config hash → config plain
    $currentValid = false;
    $dbHash = Settings::get('admin_password_hash');
    if (!empty($dbHash)) {
        $currentValid = password_verify($currentPass, $dbHash);
    } elseif (defined('ADMIN_PASSWORD_HASH') && !empty(ADMIN_PASSWORD_HASH)) {
        $currentValid = password_verify($currentPass, ADMIN_PASSWORD_HASH);
    } elseif (defined('ADMIN_PASSWORD')) {
        $currentValid = ($currentPass === ADMIN_PASSWORD);
    }
    
    if (!$currentValid) {
        return ['type' => 'error', 'message' => '❌ A jelenlegi jelszó hibás!'];
    }
    
    // Új hash generálás és mentés DB-be
    $newHash = password_hash($newPass, PASSWORD_ARGON2ID);
    Settings::set('admin_password_hash', $newHash);
    
    Security::log('info', 'Admin password changed (stored in DB)');
    return ['type' => 'success', 'message' => '✅ Jelszó módosítva! (Adatbázisban tárolva)'];
}

/**
 * Általános beállítások → ADATBÁZISBA
 */
function updateGeneralSettings(array $data): array {
    Settings::set('demo_mode', isset($data['demo_mode']) ? '1' : '0');
    Settings::set('require_login', isset($data['require_login']) ? '1' : '0');
    
    if (!empty($data['session_lifetime'])) {
        Settings::set('session_lifetime', (string)(int)$data['session_lifetime']);
    }
    
    if (!empty($data['rate_limit'])) {
        Settings::set('rate_limit_requests', (string)(int)$data['rate_limit']);
    }
    
    Security::log('info', 'General settings updated (DB)');
    Settings::resetCache();
    
    return ['type' => 'success', 'message' => '✅ Beállítások mentve az adatbázisba!'];
}

// Rendszer információk
$systemInfo = [
    'php_version' => PHP_VERSION,
    'app_version' => APP_VERSION,
    'db_connected' => Database::isAvailable(),
    'disk_free' => disk_free_space(__DIR__),
    'log_size' => 0,
    'settings_count' => count(Settings::getAll()),
];

// Log méret
$logDir = __DIR__ . '/logs/';
if (is_dir($logDir)) {
    $size = 0;
    foreach (glob($logDir . '*.log') as $file) {
        $size += filesize($file);
    }
    $systemInfo['log_size'] = $size;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beállítások - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .settings-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        @media (max-width: 800px) {
            .settings-layout { grid-template-columns: 1fr; }
        }
        .settings-nav {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid var(--border);
            height: fit-content;
            position: sticky;
            top: 24px;
        }
        .settings-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.2s;
        }
        .settings-nav a:hover { background: var(--bg); }
        .settings-nav a.active { background: var(--orange-light); color: var(--orange); font-weight: 600; }
        .settings-main { min-width: 0; }
        .settings-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .settings-section h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 20px 0;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .api-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .api-status.configured { background: #dcfce7; color: #166534; }
        .api-status.not-configured { background: #fee2e2; color: #991b1b; }
        .form-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 16px;
            align-items: start;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .form-row label { font-weight: 500; padding-top: 10px; }
        .form-row .help-text {
            grid-column: 2;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: -12px;
        }
        .input-with-status {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .input-with-status input { flex: 1; }
        .system-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        .system-card {
            padding: 16px;
            background: var(--bg);
            border-radius: 10px;
            text-align: center;
        }
        .system-card .value { font-size: 24px; font-weight: 700; color: var(--orange); }
        .system-card .label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 16px;
        }
        .back-link:hover { color: var(--orange); }
        .db-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="settings-layout">
        <nav class="settings-nav">
            <a href="index.php" class="back-link">← Vissza</a>
            <a href="#api" class="active" onclick="showSection('api')">🔑 API Kulcsok</a>
            <a href="#password" onclick="showSection('password')">🔐 Jelszó</a>
            <a href="#general" onclick="showSection('general')">⚙️ Általános</a>
            <a href="#system" onclick="showSection('system')">📊 Rendszer</a>
            <hr style="margin: 16px 0; border: none; border-top: 1px solid var(--border);">
            <a href="?logout=1" style="color: #ef4444;">🚪 Kijelentkezés</a>
        </nav>
        
        <main class="settings-main">
            <a href="index.php" class="back-link">← Vissza a főoldalra</a>
            
            <h1 style="margin-bottom: 8px;">⚙️ Beállítások</h1>
            <p style="margin-bottom: 24px; color: var(--text-muted); font-size: 13px;">
                <span class="db-badge">🗄️ Adatbázisban tárolva</span>
                Minden beállítás automatikusan megőrződik frissítéskor.
            </p>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $message ?>
            </div>
            <?php endif; ?>
            
            <!-- API KULCSOK -->
            <section class="settings-section" id="section-api">
                <h2>🔑 API Kulcsok <span class="db-badge">🗄️ DB</span></h2>
                
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="settings_action" value="update_api">
                    
                    <!-- Anthropic -->
                    <div class="form-row">
                        <label>Anthropic API</label>
                        <div>
                            <div class="input-with-status">
                                <input type="password" name="anthropic_api_key" class="form-control" 
                                       placeholder="sk-ant-api03-..." 
                                       value="<?= Settings::isConfigured('anthropic_api_key') ? Settings::mask('anthropic_api_key') : '' ?>">
                                <?php if (Settings::isConfigured('anthropic_api_key')): ?>
                                <span class="api-status configured">✓ Beállítva</span>
                                <?php else: ?>
                                <span class="api-status not-configured">✗ Hiányzik</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label></label>
                        <p class="help-text">Szükséges a hirdetés generáláshoz. <a href="https://console.anthropic.com/" target="_blank">Kulcs beszerzése →</a></p>
                    </div>
                    
                    <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border);">
                    
                    <h4 style="margin-bottom: 16px;">Google Ads API (opcionális)</h4>
                    
                    <div class="form-row">
                        <label>Developer Token</label>
                        <div class="input-with-status">
                            <input type="text" name="google_ads_developer_token" class="form-control" 
                                   value="<?= Settings::isConfigured('google_ads_developer_token') ? Settings::mask('google_ads_developer_token') : '' ?>">
                            <?php if (Settings::isConfigured('google_ads_developer_token')): ?>
                            <span class="api-status configured">✓</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label>Client ID</label>
                        <input type="text" name="google_ads_client_id" class="form-control" 
                               value="<?= Settings::isConfigured('google_ads_client_id') ? Settings::mask('google_ads_client_id') : '' ?>">
                    </div>
                    
                    <div class="form-row">
                        <label>Client Secret</label>
                        <input type="password" name="google_ads_client_secret" class="form-control" 
                               value="<?= Settings::isConfigured('google_ads_client_secret') ? Settings::mask('google_ads_client_secret') : '' ?>">
                    </div>
                    
                    <div class="form-row">
                        <label>Refresh Token</label>
                        <input type="password" name="google_ads_refresh_token" class="form-control" 
                               value="<?= Settings::isConfigured('google_ads_refresh_token') ? Settings::mask('google_ads_refresh_token') : '' ?>">
                    </div>
                    
                    <div class="form-row">
                        <label>Login Customer ID</label>
                        <input type="text" name="google_ads_login_customer_id" class="form-control" 
                               placeholder="MCC fiók ID (opcionális)"
                               value="<?= Settings::isConfigured('google_ads_login_customer_id') ? Settings::mask('google_ads_login_customer_id') : '' ?>">
                    </div>
                    
                    <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border);">
                    
                    <h4 style="margin-bottom: 16px;">Egyéb API-k (opcionális)</h4>
                    
                    <div class="form-row">
                        <label>Screenshot API</label>
                        <div>
                            <input type="text" name="screenshot_api_key" class="form-control" 
                                   placeholder="screenshotmachine.com kulcs"
                                   value="<?= Settings::isConfigured('screenshot_api_key') ? Settings::mask('screenshot_api_key') : '' ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <label></label>
                        <p class="help-text">Vision CRO elemzéshez. <a href="https://screenshotmachine.com" target="_blank">screenshotmachine.com →</a></p>
                    </div>
                    
                    <div class="form-row">
                        <label>SerpApi</label>
                        <input type="text" name="serpapi_key" class="form-control" 
                               placeholder="Versenytárs elemzéshez"
                               value="<?= Settings::isConfigured('serpapi_key') ? Settings::mask('serpapi_key') : '' ?>">
                    </div>
                    
                    <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border);">
                    
                    <h4 style="margin-bottom: 16px;">🧩 Chrome Bővítmény</h4>
                    
                    <div class="form-row">
                        <label>Extension API Kulcs</label>
                        <div>
                            <div class="input-with-status">
                                <input type="text" name="extension_api_key" class="form-control" 
                                       placeholder="Automatikusan generálható"
                                       value="<?= Settings::isConfigured('extension_api_key') ? Settings::mask('extension_api_key') : '' ?>">
                                <?php if (Settings::isConfigured('extension_api_key')): ?>
                                <span class="api-status configured">✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label></label>
                        <div>
                            <p class="help-text" style="margin-bottom: 8px;">A Chrome bővítmény ezzel hitelesíti magát. Hagyd üresen ha nem kell auth.</p>
                            <button type="button" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;" onclick="generateExtensionKey()">🔑 Kulcs generálása</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💾 API Beállítások Mentése</button>
                </form>
            </section>
            
            <!-- JELSZÓ -->
            <section class="settings-section" id="section-password" style="display: none;">
                <h2>🔐 Jelszó Módosítás <span class="db-badge">🗄️ DB</span></h2>
                
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="settings_action" value="change_password">
                    
                    <div class="form-row">
                        <label>Jelenlegi jelszó</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <label>Új jelszó</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="form-row">
                        <label></label>
                        <p class="help-text">Minimum 8 karakter</p>
                    </div>
                    
                    <div class="form-row">
                        <label>Új jelszó újra</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🔐 Jelszó Módosítása</button>
                </form>
            </section>
            
            <!-- ÁLTALÁNOS -->
            <section class="settings-section" id="section-general" style="display: none;">
                <h2>⚙️ Általános Beállítások <span class="db-badge">🗄️ DB</span></h2>
                
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="settings_action" value="update_general">
                    
                    <div class="form-row">
                        <label>Belépés megkövetelése</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="require_login" value="1" <?= Settings::requireLogin() ? 'checked' : '' ?>>
                            <span>Kötelező bejelentkezés az alkalmazás használatához</span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label>Demo mód</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="demo_mode" value="1" <?= Settings::isDemoMode() ? 'checked' : '' ?>>
                            <span>API nélkül működik, példa adatokkal</span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label>Session élettartam</label>
                        <select name="session_lifetime" class="form-control" style="max-width: 200px;">
                            <?php $sl = Settings::sessionLifetime(); ?>
                            <option value="1800" <?= $sl == 1800 ? 'selected' : '' ?>>30 perc</option>
                            <option value="3600" <?= $sl == 3600 ? 'selected' : '' ?>>1 óra</option>
                            <option value="7200" <?= $sl == 7200 ? 'selected' : '' ?>>2 óra</option>
                            <option value="28800" <?= $sl == 28800 ? 'selected' : '' ?>>8 óra</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label>Rate limit</label>
                        <div>
                            <input type="number" name="rate_limit" class="form-control" style="max-width: 150px;"
                                   value="<?= Settings::rateLimitRequests() ?>" min="10" max="200">
                        </div>
                    </div>
                    <div class="form-row">
                        <label></label>
                        <p class="help-text">Max API kérések száma óránként</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💾 Beállítások Mentése</button>
                </form>
            </section>
            
            <!-- RENDSZER -->
            <section class="settings-section" id="section-system" style="display: none;">
                <h2>📊 Rendszer Információk</h2>
                
                <div class="system-grid">
                    <div class="system-card">
                        <div class="value"><?= APP_VERSION ?></div>
                        <div class="label">AdMaster Pro verzió</div>
                    </div>
                    <div class="system-card">
                        <div class="value"><?= PHP_VERSION ?></div>
                        <div class="label">PHP verzió</div>
                    </div>
                    <div class="system-card">
                        <div class="value" style="color: <?= $systemInfo['db_connected'] ? 'var(--green)' : '#ef4444' ?>">
                            <?= $systemInfo['db_connected'] ? '✓' : '✗' ?>
                        </div>
                        <div class="label">Adatbázis kapcsolat</div>
                    </div>
                    <div class="system-card">
                        <div class="value"><?= $systemInfo['settings_count'] ?></div>
                        <div class="label">DB beállítások</div>
                    </div>
                    <div class="system-card">
                        <div class="value"><?= round($systemInfo['disk_free'] / 1024 / 1024 / 1024, 1) ?> GB</div>
                        <div class="label">Szabad lemezterület</div>
                    </div>
                    <div class="system-card">
                        <div class="value"><?= round($systemInfo['log_size'] / 1024, 1) ?> KB</div>
                        <div class="label">Log fájlok mérete</div>
                    </div>
                </div>
                
                <hr style="margin: 24px 0;">
                
                <h4>Gyors műveletek</h4>
                <div style="display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap;">
                    <a href="update.php" class="btn btn-secondary">🔄 Frissítés ellenőrzése</a>
                    <a href="install.php" class="btn btn-secondary">🗄️ DB újratelepítés</a>
                    <button class="btn btn-secondary" onclick="clearLogs()">🗑️ Logok törlése</button>
                </div>
                
                <hr style="margin: 24px 0;">
                
                <h4>Adatbázis beállítások</h4>
                <table class="data-table" style="margin-top: 16px; font-size: 13px;">
                    <tr><th>Kulcs</th><th>Érték</th><th>Forrás</th></tr>
                    <?php foreach (Settings::getAll() as $key => $val): ?>
                    <?php if (strpos($key, 'password') !== false || strpos($key, 'secret') !== false || strpos($key, 'api_key') !== false || strpos($key, 'token') !== false): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($key) ?></code></td>
                        <td><?= !empty($val) ? '••••••' . substr($val, -4) : '<em>üres</em>' ?></td>
                        <td><span class="db-badge">DB</span></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><code><?= htmlspecialchars($key) ?></code></td>
                        <td><?= htmlspecialchars(substr($val, 0, 60)) ?></td>
                        <td><span class="db-badge">DB</span></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                
                <hr style="margin: 24px 0;">
                
                <h4>Elérési útvonalak</h4>
                <table class="data-table" style="margin-top: 16px;">
                    <tr><td>Alkalmazás</td><td><code><?= __DIR__ ?></code></td></tr>
                    <tr><td>Data mappa</td><td><code><?= __DIR__ ?>/data/</code></td></tr>
                    <tr><td>Log mappa</td><td><code><?= __DIR__ ?>/logs/</code></td></tr>
                    <tr><td>Backup mappa</td><td><code><?= __DIR__ ?>/backups/</code></td></tr>
                </table>
            </section>
        </main>
    </div>
    
    <script>
    function showSection(section) {
        document.querySelectorAll('.settings-section').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.settings-nav a').forEach(a => a.classList.remove('active'));
        document.getElementById('section-' + section).style.display = 'block';
        document.querySelector(`[onclick="showSection('${section}')"]`).classList.add('active');
    }
    
    function clearLogs() {
        if (confirm('Biztosan törlöd az összes log fájlt?')) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=clear_logs'
            }).then(() => {
                alert('Logok törölve!');
                location.reload();
            });
        }
    }
    
    if (window.location.hash) {
        const section = window.location.hash.replace('#', '');
        if (['api', 'password', 'general', 'system'].includes(section)) {
            showSection(section);
        }
    }
    
    function generateExtensionKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let key = 'ext_';
        for (let i = 0; i < 32; i++) {
            key += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const input = document.querySelector('input[name="extension_api_key"]');
        input.value = key;
        input.type = 'text';
    }
    </script>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    Security::logout();
    header('Location: login.php?logout=1');
    exit;
}
?>
