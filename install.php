<?php
/**
 * AdMaster Pro - Installer
 * 
 * Telepítési varázsló:
 * 1. Rendszerkövetelmények ellenőrzése
 * 2. Könyvtár jogosultságok beállítása
 * 3. API kulcs konfiguráció
 * 4. Adatbázis inicializálás (ha szükséges)
 * 5. Admin felhasználó létrehozása (opcionális)
 */

// Verzió
define('INSTALLER_VERSION', '2.1');
define('REQUIRED_PHP_VERSION', '7.4.0');

// Telepítés állapot
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$warnings = [];
$success = [];

// POST feldolgozás
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Jogosultságok beállítása
            $dirs = ['data', 'data/projects', 'logs'];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    if (!@mkdir($dir, 0750, true)) {
                        $errors[] = "Nem sikerült létrehozni: $dir";
                    }
                }
                if (is_dir($dir) && !is_writable($dir)) {
                    if (!@chmod($dir, 0750)) {
                        $errors[] = "Nem sikerült írhatóvá tenni: $dir";
                    }
                }
            }
            
            // .htaccess másolás data és logs mappákba
            $htaccess = "Order Deny,Allow\nDeny from all";
            @file_put_contents('data/.htaccess', $htaccess);
            @file_put_contents('logs/.htaccess', $htaccess);
            
            if (empty($errors)) {
                header('Location: install.php?step=3');
                exit;
            }
            break;
            
        case 3:
            // API kulcs beállítása
            $api_key = trim($_POST['api_key'] ?? '');
            $app_env = $_POST['app_env'] ?? 'production';
            
            if (empty($api_key)) {
                $errors[] = 'Az API kulcs megadása kötelező!';
            } elseif (!preg_match('/^sk-ant-[a-zA-Z0-9\-_]{20,}$/', $api_key)) {
                $errors[] = 'Érvénytelen API kulcs formátum!';
            }
            
            if (empty($errors)) {
                // Config fájl frissítése
                $config = file_get_contents('config.php');
                $config = preg_replace(
                    "/define\('ANTHROPIC_API_KEY',[^;]+;/",
                    "define('ANTHROPIC_API_KEY', '$api_key');",
                    $config
                );
                $config = preg_replace(
                    "/define\('APP_ENV',[^;]+;/",
                    "define('APP_ENV', '$app_env');",
                    $config
                );
                
                if (file_put_contents('config.php', $config)) {
                    header('Location: install.php?step=4');
                    exit;
                } else {
                    $errors[] = 'Nem sikerült menteni a config.php fájlt!';
                }
            }
            break;
            
        case 4:
            // Opcionális beállítások
            $rate_limit = (int)($_POST['rate_limit'] ?? 30);
            $session_lifetime = (int)($_POST['session_lifetime'] ?? 3600);
            
            $config = file_get_contents('config.php');
            $config = preg_replace(
                "/define\('RATE_LIMIT_REQUESTS',\s*\d+\);/",
                "define('RATE_LIMIT_REQUESTS', $rate_limit);",
                $config
            );
            $config = preg_replace(
                "/define\('SESSION_LIFETIME',\s*\d+\);/",
                "define('SESSION_LIFETIME', $session_lifetime);",
                $config
            );
            file_put_contents('config.php', $config);
            
            // Telepítés befejezése - install.lock létrehozása
            file_put_contents('install.lock', json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'version' => INSTALLER_VERSION,
                'php_version' => PHP_VERSION
            ]));
            
            header('Location: install.php?step=5');
            exit;
    }
}

// Rendszerkövetelmények ellenőrzése
function checkRequirements(): array {
    $checks = [];
    
    // PHP verzió
    $checks['php_version'] = [
        'name' => 'PHP verzió',
        'required' => REQUIRED_PHP_VERSION . '+',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '>='),
        'critical' => true
    ];
    
    // cURL
    $checks['curl'] = [
        'name' => 'cURL extension',
        'required' => 'Enabled',
        'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('curl'),
        'critical' => true
    ];
    
    // JSON
    $checks['json'] = [
        'name' => 'JSON extension',
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('json'),
        'critical' => true
    ];
    
    // mbstring
    $checks['mbstring'] = [
        'name' => 'mbstring extension',
        'required' => 'Enabled',
        'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('mbstring'),
        'critical' => true
    ];
    
    // OpenSSL
    $checks['openssl'] = [
        'name' => 'OpenSSL extension',
        'required' => 'Enabled',
        'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('openssl'),
        'critical' => true
    ];
    
    // File info
    $checks['fileinfo'] = [
        'name' => 'Fileinfo extension',
        'required' => 'Enabled',
        'current' => extension_loaded('fileinfo') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('fileinfo'),
        'critical' => false
    ];
    
    // config.php írható
    $checks['config_writable'] = [
        'name' => 'config.php írható',
        'required' => 'Yes',
        'current' => is_writable('config.php') ? 'Yes' : 'No',
        'status' => is_writable('config.php'),
        'critical' => true
    ];
    
    // Szülő könyvtár írható
    $checks['dir_writable'] = [
        'name' => 'Alkalmazás könyvtár írható',
        'required' => 'Yes',
        'current' => is_writable('.') ? 'Yes' : 'No',
        'status' => is_writable('.'),
        'critical' => true
    ];
    
    return $checks;
}

// Ellenőrzés, hogy már telepítve van-e
if (file_exists('install.lock') && $step < 5) {
    $lockData = json_decode(file_get_contents('install.lock'), true);
    ?>
    <!DOCTYPE html>
    <html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AdMaster Pro - Már telepítve</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0f; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .container { max-width: 500px; padding: 40px; background: rgba(255,255,255,0.05); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
            h1 { font-size: 24px; margin-bottom: 16px; }
            p { color: rgba(255,255,255,0.7); margin-bottom: 24px; }
            .info { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); padding: 16px; border-radius: 8px; margin-bottom: 24px; text-align: left; font-size: 14px; }
            .btn { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #E85D04, #DC2626); color: white; text-decoration: none; border-radius: 8px; margin: 8px; }
            .btn-secondary { background: rgba(255,255,255,0.1); }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>⚠️ AdMaster Pro már telepítve van!</h1>
            <p>A telepítés korábban sikeresen befejeződött.</p>
            <div class="info">
                <strong>Telepítve:</strong> <?= htmlspecialchars($lockData['installed_at'] ?? 'N/A') ?><br>
                <strong>Verzió:</strong> <?= htmlspecialchars($lockData['version'] ?? 'N/A') ?>
            </div>
            <a href="index.php" class="btn">Alkalmazás megnyitása</a>
            <a href="update.php" class="btn btn-secondary">Frissítések keresése</a>
            <p style="margin-top: 24px; font-size: 12px; color: rgba(255,255,255,0.4);">
                Újratelepítéshez töröld az <code>install.lock</code> fájlt.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$requirements = checkRequirements();
$canProceed = !in_array(false, array_column(array_filter($requirements, fn($r) => $r['critical']), 'status'));
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdMaster Pro - Telepítő</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0f; color: #fff; min-height: 100vh; }
        
        .installer { max-width: 700px; margin: 0 auto; padding: 40px 20px; }
        
        .header { text-align: center; margin-bottom: 40px; }
        .logo { width: 60px; height: 60px; background: linear-gradient(135deg, #E85D04, #DC2626); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; margin: 0 auto 16px; }
        h1 { font-size: 28px; margin-bottom: 8px; }
        .subtitle { color: rgba(255,255,255,0.5); }
        
        .steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 40px; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.2); }
        .step-dot.active { background: #E85D04; }
        .step-dot.done { background: #10B981; }
        
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 32px; margin-bottom: 24px; }
        .card h2 { font-size: 20px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .card p { color: rgba(255,255,255,0.6); margin-bottom: 24px; }
        
        .check-list { list-style: none; }
        .check-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; background: rgba(255,255,255,0.03); }
        .check-item.pass { border-left: 3px solid #10B981; }
        .check-item.fail { border-left: 3px solid #EF4444; }
        .check-item.warn { border-left: 3px solid #F59E0B; }
        .check-name { font-weight: 500; }
        .check-values { display: flex; gap: 16px; font-size: 14px; color: rgba(255,255,255,0.5); }
        .check-status { font-size: 18px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group small { display: block; margin-top: 6px; color: rgba(255,255,255,0.4); font-size: 13px; }
        .form-control { width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); color: #fff; font-size: 15px; }
        .form-control:focus { outline: none; border-color: #E85D04; }
        select.form-control { cursor: pointer; }
        
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: #F59E0B; }
        
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #E85D04, #DC2626); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(232, 93, 4, 0.3); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: white; }
        
        .btn-group { display: flex; gap: 12px; justify-content: center; margin-top: 32px; }
        
        .success-icon { font-size: 64px; margin-bottom: 24px; }
        .success-box { text-align: center; padding: 40px; }
        .success-box h2 { color: #10B981; margin-bottom: 16px; }
        
        .code-block { background: rgba(0,0,0,0.3); padding: 16px; border-radius: 8px; font-family: monospace; font-size: 14px; overflow-x: auto; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="installer">
        <div class="header">
            <div class="logo">A</div>
            <h1>AdMaster Pro Telepítő</h1>
            <p class="subtitle">v<?= INSTALLER_VERSION ?> - Google Ads AI Asszisztens</p>
        </div>
        
        <div class="steps">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="step-dot <?= $i < $step ? 'done' : ($i === $step ? 'active' : '') ?>"></div>
            <?php endfor; ?>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
            <div>❌ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
        <!-- Step 1: Rendszerkövetelmények -->
        <div class="card">
            <h2>📋 1. Rendszerkövetelmények</h2>
            <p>Az alkalmazás futtatásához szükséges követelmények ellenőrzése.</p>
            
            <ul class="check-list">
                <?php foreach ($requirements as $key => $check): ?>
                <li class="check-item <?= $check['status'] ? 'pass' : ($check['critical'] ? 'fail' : 'warn') ?>">
                    <span class="check-name"><?= htmlspecialchars($check['name']) ?></span>
                    <div class="check-values">
                        <span>Szükséges: <?= htmlspecialchars($check['required']) ?></span>
                        <span>Jelenlegi: <?= htmlspecialchars($check['current']) ?></span>
                    </div>
                    <span class="check-status"><?= $check['status'] ? '✅' : ($check['critical'] ? '❌' : '⚠️') ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if (!$canProceed): ?>
            <div class="alert alert-error" style="margin-top: 24px;">
                ⚠️ Néhány kritikus követelmény nem teljesül. Kérlek javítsd ezeket a telepítés folytatása előtt.
            </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <a href="?step=2" class="btn btn-primary <?= !$canProceed ? 'disabled' : '' ?>" 
                   <?= !$canProceed ? 'onclick="return false;"' : '' ?>>
                    Tovább →
                </a>
            </div>
        </div>
        
        <?php elseif ($step === 2): ?>
        <!-- Step 2: Könyvtár jogosultságok -->
        <div class="card">
            <h2>📁 2. Könyvtár Jogosultságok</h2>
            <p>Az alkalmazásnak írási jogosultságra van szüksége néhány könyvtárhoz.</p>
            
            <ul class="check-list">
                <?php 
                $dirs = [
                    'data' => 'Projekt adatok tárolása',
                    'data/projects' => 'Mentett projektek',
                    'logs' => 'Hibanaplók'
                ];
                foreach ($dirs as $dir => $desc): 
                    $exists = is_dir($dir);
                    $writable = $exists && is_writable($dir);
                ?>
                <li class="check-item <?= $writable ? 'pass' : ($exists ? 'warn' : 'fail') ?>">
                    <span class="check-name"><?= $dir ?>/</span>
                    <span style="color: rgba(255,255,255,0.5); font-size: 14px;"><?= $desc ?></span>
                    <span class="check-status">
                        <?= $writable ? '✅' : ($exists ? '⚠️ Nem írható' : '📁 Létrehozandó') ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <form method="POST">
                <div class="btn-group">
                    <a href="?step=1" class="btn btn-secondary">← Vissza</a>
                    <button type="submit" class="btn btn-primary">Könyvtárak létrehozása →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 3): ?>
        <!-- Step 3: API konfiguráció -->
        <div class="card">
            <h2>🔑 3. API Konfiguráció</h2>
            <p>Add meg az Anthropic API kulcsodat az AI funkciók működéséhez.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="api_key">Anthropic API Kulcs *</label>
                    <input type="text" id="api_key" name="api_key" class="form-control" 
                           placeholder="sk-ant-xxxxxxxxxxxxxxxxxxxxxxxx" required>
                    <small>
                        API kulcsot itt szerezhetsz: 
                        <a href="https://console.anthropic.com/" target="_blank" style="color: #E85D04;">console.anthropic.com</a>
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="app_env">Környezet</label>
                    <select id="app_env" name="app_env" class="form-control">
                        <option value="production">Production (ajánlott)</option>
                        <option value="development">Development (hibakereséshez)</option>
                    </select>
                    <small>Production módban a hibaüzenetek el vannak rejtve.</small>
                </div>
                
                <div class="btn-group">
                    <a href="?step=2" class="btn btn-secondary">← Vissza</a>
                    <button type="submit" class="btn btn-primary">Tovább →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 4): ?>
        <!-- Step 4: Opcionális beállítások -->
        <div class="card">
            <h2>⚙️ 4. Opcionális Beállítások</h2>
            <p>További beállítások testreszabása (alapértékek általában megfelelők).</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="rate_limit">Rate Limit (kérés/óra)</label>
                    <input type="number" id="rate_limit" name="rate_limit" class="form-control" 
                           value="30" min="10" max="1000">
                    <small>Maximum API kérések száma óránként felhasználónként.</small>
                </div>
                
                <div class="form-group">
                    <label for="session_lifetime">Session élettartam (másodperc)</label>
                    <input type="number" id="session_lifetime" name="session_lifetime" class="form-control" 
                           value="3600" min="600" max="86400">
                    <small>3600 = 1 óra, 86400 = 24 óra</small>
                </div>
                
                <div class="btn-group">
                    <a href="?step=3" class="btn btn-secondary">← Vissza</a>
                    <button type="submit" class="btn btn-primary">Telepítés befejezése →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 5): ?>
        <!-- Step 5: Kész! -->
        <div class="card">
            <div class="success-box">
                <div class="success-icon">🎉</div>
                <h2>Telepítés sikeres!</h2>
                <p>Az AdMaster Pro sikeresen telepítve és konfigurálva lett.</p>
                
                <div class="alert alert-warning" style="text-align: left; margin: 24px 0;">
                    <strong>⚠️ Biztonsági lépések:</strong>
                    <ol style="margin: 12px 0 0 20px; line-height: 1.8;">
                        <li>Töröld az <code>install.php</code> fájlt</li>
                        <li>Ellenőrizd, hogy a <code>.htaccess</code> működik</li>
                        <li>Állíts be HTTPS-t (ha még nincs)</li>
                    </ol>
                </div>
                
                <div class="code-block">
                    rm install.php
                </div>
                
                <div class="btn-group">
                    <a href="index.php" class="btn btn-primary">🚀 Alkalmazás megnyitása</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
