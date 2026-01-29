<?php
/**
 * AdMaster Pro - Beállítások Oldal
 * 
 * - API kulcsok kezelése
 * - Jelszó módosítás
 * - Rendszer beállítások
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';

Security::initSession();

// Belépés ellenőrzés
if (Security::requireLogin()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

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
 * API beállítások mentése
 */
function updateApiSettings(array $data): array {
    $configFile = __DIR__ . '/config.php';
    $config = file_get_contents($configFile);
    
    // Anthropic API
    if (!empty($data['anthropic_api_key'])) {
        $newKey = trim($data['anthropic_api_key']);
        if (strpos($newKey, 'sk-ant-') === 0) {
            $config = preg_replace(
                "/define\('ANTHROPIC_API_KEY',[^)]+\)/",
                "define('ANTHROPIC_API_KEY', '$newKey')",
                $config
            );
        }
    }
    
    // Google Ads
    if (isset($data['google_ads_developer_token'])) {
        $token = trim($data['google_ads_developer_token']);
        $config = preg_replace(
            "/define\('GOOGLE_ADS_DEVELOPER_TOKEN',[^)]+\)/",
            "define('GOOGLE_ADS_DEVELOPER_TOKEN', getenv('GOOGLE_ADS_DEVELOPER_TOKEN') ?: '$token')",
            $config
        );
    }
    
    if (isset($data['google_ads_client_id'])) {
        $val = trim($data['google_ads_client_id']);
        $config = preg_replace(
            "/define\('GOOGLE_ADS_CLIENT_ID',[^)]+\)/",
            "define('GOOGLE_ADS_CLIENT_ID', getenv('GOOGLE_ADS_CLIENT_ID') ?: '$val')",
            $config
        );
    }
    
    if (isset($data['google_ads_client_secret'])) {
        $val = trim($data['google_ads_client_secret']);
        $config = preg_replace(
            "/define\('GOOGLE_ADS_CLIENT_SECRET',[^)]+\)/",
            "define('GOOGLE_ADS_CLIENT_SECRET', getenv('GOOGLE_ADS_CLIENT_SECRET') ?: '$val')",
            $config
        );
    }
    
    if (isset($data['google_ads_refresh_token'])) {
        $val = trim($data['google_ads_refresh_token']);
        $config = preg_replace(
            "/define\('GOOGLE_ADS_REFRESH_TOKEN',[^)]+\)/",
            "define('GOOGLE_ADS_REFRESH_TOKEN', getenv('GOOGLE_ADS_REFRESH_TOKEN') ?: '$val')",
            $config
        );
    }
    
    // Screenshot API
    if (isset($data['screenshot_api_key'])) {
        $val = trim($data['screenshot_api_key']);
        $config = preg_replace(
            "/define\('SCREENSHOT_API_KEY',[^)]+\)/",
            "define('SCREENSHOT_API_KEY', getenv('SCREENSHOT_API_KEY') ?: '$val')",
            $config
        );
    }
    
    // SerpApi
    if (isset($data['serpapi_key'])) {
        $val = trim($data['serpapi_key']);
        $config = preg_replace(
            "/define\('SERPAPI_KEY',[^)]+\)/",
            "define('SERPAPI_KEY', getenv('SERPAPI_KEY') ?: '$val')",
            $config
        );
    }
    
    // Backup és mentés
    $backupFile = __DIR__ . '/backups/config_' . date('Y-m-d_H-i-s') . '.php';
    copy($configFile, $backupFile);
    
    if (file_put_contents($configFile, $config)) {
        Security::log('info', 'API settings updated');
        return ['type' => 'success', 'message' => '✅ API beállítások mentve! (Backup: ' . basename($backupFile) . ')'];
    }
    
    return ['type' => 'error', 'message' => '❌ Hiba a mentés során!'];
}

/**
 * Jelszó módosítás
 */
function changePassword(array $data): array {
    $currentPass = $data['current_password'] ?? '';
    $newPass = $data['new_password'] ?? '';
    $confirmPass = $data['confirm_password'] ?? '';
    
    // Validálás
    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        return ['type' => 'error', 'message' => '❌ Minden mező kitöltése kötelező!'];
    }
    
    if ($newPass !== $confirmPass) {
        return ['type' => 'error', 'message' => '❌ Az új jelszavak nem egyeznek!'];
    }
    
    if (strlen($newPass) < 8) {
        return ['type' => 'error', 'message' => '❌ Az új jelszó legalább 8 karakter legyen!'];
    }
    
    // Jelenlegi jelszó ellenőrzése
    $currentValid = false;
    if (defined('ADMIN_PASSWORD_HASH') && !empty(ADMIN_PASSWORD_HASH)) {
        $currentValid = password_verify($currentPass, ADMIN_PASSWORD_HASH);
    } elseif (defined('ADMIN_PASSWORD')) {
        $currentValid = ($currentPass === ADMIN_PASSWORD);
    }
    
    if (!$currentValid) {
        return ['type' => 'error', 'message' => '❌ A jelenlegi jelszó hibás!'];
    }
    
    // Új hash generálás
    $newHash = password_hash($newPass, PASSWORD_ARGON2ID);
    
    // Config frissítése
    $configFile = __DIR__ . '/config.php';
    $config = file_get_contents($configFile);
    
    // Ha van ADMIN_PASSWORD_HASH, azt frissítjük
    if (preg_match("/define\('ADMIN_PASSWORD_HASH'/", $config)) {
        $config = preg_replace(
            "/define\('ADMIN_PASSWORD_HASH',[^)]+\)/",
            "define('ADMIN_PASSWORD_HASH', '$newHash')",
            $config
        );
    } else {
        // Ha nincs, hozzáadjuk az ADMIN_PASSWORD után
        $config = preg_replace(
            "/(define\('ADMIN_PASSWORD',[^)]+\);)/",
            "$1\n\n// Jelszó HASH (biztonságos)\ndefine('ADMIN_PASSWORD_HASH', '$newHash');",
            $config
        );
    }
    
    // Backup és mentés
    $backupFile = __DIR__ . '/backups/config_' . date('Y-m-d_H-i-s') . '.php';
    copy($configFile, $backupFile);
    
    if (file_put_contents($configFile, $config)) {
        Security::log('info', 'Admin password changed');
        return ['type' => 'success', 'message' => '✅ Jelszó sikeresen módosítva!'];
    }
    
    return ['type' => 'error', 'message' => '❌ Hiba a mentés során!'];
}

/**
 * Általános beállítások
 */
function updateGeneralSettings(array $data): array {
    $configFile = __DIR__ . '/config.php';
    $config = file_get_contents($configFile);
    
    // Demo mód
    $demoMode = isset($data['demo_mode']) ? 'true' : 'false';
    $config = preg_replace(
        "/define\('DEMO_MODE',[^)]+\)/",
        "define('DEMO_MODE', $demoMode)",
        $config
    );
    
    // Login requirement
    $requireLogin = isset($data['require_login']) ? 'true' : 'false';
    $config = preg_replace(
        "/define\('REQUIRE_LOGIN',[^)]+\)/",
        "define('REQUIRE_LOGIN', $requireLogin)",
        $config
    );
    
    // Session lifetime
    if (!empty($data['session_lifetime'])) {
        $lifetime = (int)$data['session_lifetime'];
        $config = preg_replace(
            "/define\('SESSION_LIFETIME',[^)]+\)/",
            "define('SESSION_LIFETIME', $lifetime)",
            $config
        );
    }
    
    // Rate limit
    if (!empty($data['rate_limit'])) {
        $limit = (int)$data['rate_limit'];
        $config = preg_replace(
            "/define\('RATE_LIMIT_REQUESTS',[^)]+\)/",
            "define('RATE_LIMIT_REQUESTS', $limit)",
            $config
        );
    }
    
    // Backup és mentés
    $backupFile = __DIR__ . '/backups/config_' . date('Y-m-d_H-i-s') . '.php';
    copy($configFile, $backupFile);
    
    if (file_put_contents($configFile, $config)) {
        Security::log('info', 'General settings updated');
        return ['type' => 'success', 'message' => '✅ Beállítások mentve!'];
    }
    
    return ['type' => 'error', 'message' => '❌ Hiba a mentés során!'];
}

// API kulcs maszkolás
function maskApiKey(string $key): string {
    if (empty($key)) return '';
    if (strlen($key) < 20) return '***';
    return substr($key, 0, 10) . '...' . substr($key, -4);
}

// Rendszer információk
$systemInfo = [
    'php_version' => PHP_VERSION,
    'app_version' => APP_VERSION,
    'db_connected' => false,
    'disk_free' => disk_free_space(__DIR__),
    'log_size' => 0
];

// DB kapcsolat ellenőrzés
try {
    if (defined('DB_HOST') && !empty(DB_HOST)) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $systemInfo['db_connected'] = true;
    }
} catch (Exception $e) {
    $systemInfo['db_connected'] = false;
}

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
        .form-row label {
            font-weight: 500;
            padding-top: 10px;
        }
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .system-card {
            padding: 16px;
            background: var(--bg);
            border-radius: 10px;
            text-align: center;
        }
        .system-card .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--orange);
        }
        .system-card .label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 16px;
        }
        .back-link:hover { color: var(--orange); }
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
            
            <h1 style="margin-bottom: 24px;">⚙️ Beállítások</h1>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $message ?>
            </div>
            <?php endif; ?>
            
            <!-- API KULCSOK -->
            <section class="settings-section" id="section-api">
                <h2>🔑 API Kulcsok</h2>
                
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
                                       value="<?= defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY ? ANTHROPIC_API_KEY : '' ?>">
                                <?php if (defined('ANTHROPIC_API_KEY') && !empty(ANTHROPIC_API_KEY)): ?>
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
                                   value="<?= defined('GOOGLE_ADS_DEVELOPER_TOKEN') ? GOOGLE_ADS_DEVELOPER_TOKEN : '' ?>">
                            <?php if (defined('GOOGLE_ADS_DEVELOPER_TOKEN') && !empty(GOOGLE_ADS_DEVELOPER_TOKEN)): ?>
                            <span class="api-status configured">✓</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label>Client ID</label>
                        <input type="text" name="google_ads_client_id" class="form-control" 
                               value="<?= defined('GOOGLE_ADS_CLIENT_ID') ? GOOGLE_ADS_CLIENT_ID : '' ?>">
                    </div>
                    
                    <div class="form-row">
                        <label>Client Secret</label>
                        <input type="password" name="google_ads_client_secret" class="form-control" 
                               value="<?= defined('GOOGLE_ADS_CLIENT_SECRET') ? GOOGLE_ADS_CLIENT_SECRET : '' ?>">
                    </div>
                    
                    <div class="form-row">
                        <label>Refresh Token</label>
                        <input type="password" name="google_ads_refresh_token" class="form-control" 
                               value="<?= defined('GOOGLE_ADS_REFRESH_TOKEN') ? GOOGLE_ADS_REFRESH_TOKEN : '' ?>">
                    </div>
                    
                    <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border);">
                    
                    <h4 style="margin-bottom: 16px;">Egyéb API-k (opcionális)</h4>
                    
                    <div class="form-row">
                        <label>Screenshot API</label>
                        <div>
                            <input type="text" name="screenshot_api_key" class="form-control" 
                                   placeholder="screenshotmachine.com kulcs"
                                   value="<?= defined('SCREENSHOT_API_KEY') ? SCREENSHOT_API_KEY : '' ?>">
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
                               value="<?= defined('SERPAPI_KEY') ? SERPAPI_KEY : '' ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💾 API Beállítások Mentése</button>
                </form>
            </section>
            
            <!-- JELSZÓ -->
            <section class="settings-section" id="section-password" style="display: none;">
                <h2>🔐 Jelszó Módosítás</h2>
                
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
                
                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>💡 Tipp:</strong> Biztonságos jelszó generáláshoz használj jelszókezelőt!
                </div>
            </section>
            
            <!-- ÁLTALÁNOS -->
            <section class="settings-section" id="section-general" style="display: none;">
                <h2>⚙️ Általános Beállítások</h2>
                
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="settings_action" value="update_general">
                    
                    <div class="form-row">
                        <label>Belépés megkövetelése</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="require_login" value="1" <?= REQUIRE_LOGIN ? 'checked' : '' ?>>
                            <span>Kötelező bejelentkezés az alkalmazás használatához</span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label>Demo mód</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="demo_mode" value="1" <?= DEMO_MODE ? 'checked' : '' ?>>
                            <span>API nélkül működik, példa adatokkal</span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label>Session élettartam</label>
                        <select name="session_lifetime" class="form-control" style="max-width: 200px;">
                            <option value="1800" <?= SESSION_LIFETIME == 1800 ? 'selected' : '' ?>>30 perc</option>
                            <option value="3600" <?= SESSION_LIFETIME == 3600 ? 'selected' : '' ?>>1 óra</option>
                            <option value="7200" <?= SESSION_LIFETIME == 7200 ? 'selected' : '' ?>>2 óra</option>
                            <option value="28800" <?= SESSION_LIFETIME == 28800 ? 'selected' : '' ?>>8 óra</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label>Rate limit</label>
                        <div>
                            <input type="number" name="rate_limit" class="form-control" style="max-width: 150px;"
                                   value="<?= RATE_LIMIT_REQUESTS ?>" min="10" max="200">
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
        // Hide all
        document.querySelectorAll('.settings-section').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.settings-nav a').forEach(a => a.classList.remove('active'));
        
        // Show selected
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
    
    // URL hash kezelés
    if (window.location.hash) {
        const section = window.location.hash.replace('#', '');
        if (['api', 'password', 'general', 'system'].includes(section)) {
            showSection(section);
        }
    }
    </script>
</body>
</html>
<?php
// Logout kezelés
if (isset($_GET['logout'])) {
    Security::logout();
    header('Location: login.php?logout=1');
    exit;
}
?>
