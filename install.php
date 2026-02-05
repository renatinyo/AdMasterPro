<?php
/**
 * AdMaster Pro - Telepítő
 * 
 * Ez a script létrehozza az adatbázis struktúrát és az alapértelmezett adatokat.
 * Futtatás: Nyisd meg böngészőben, töltsd ki az űrlapot.
 * FONTOS: Telepítés után TÖRÖLD ezt a fájlt!
 */

session_start();

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Ha már telepítve van
if (file_exists(__DIR__ . '/data/.installed') && $step == 1 && !isset($_GET['force'])) {
    header('Location: index.php');
    exit;
}

// Verzió info
define('INSTALLER_VERSION', '5.0.0');

// Step 2: Adatbázis létrehozása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $api_key = trim($_POST['api_key'] ?? '');
    
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Minden adatbázis mező kötelező!';
        $step = 1;
    } else {
        try {
            // Kapcsolódás tesztelése
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Táblák létrehozása
            $sql = file_get_contents(__DIR__ . '/data/schema.sql');
            $pdo->exec($sql);
            
            // Indexek biztonságos létrehozása (nem dob hibát ha már létezik)
            $indexes = [
                ['clients', 'idx_clients_industry', 'industry'],
                ['campaigns', 'idx_campaigns_client', 'client_id'],
                ['keywords', 'idx_keywords_industry', 'industry'],
                ['keyword_bank', 'idx_keyword_bank_industry', 'industry'],
                ['headline_bank', 'idx_headline_bank_industry', 'industry'],
                ['chat_history', 'idx_chat_session', 'session_id'],
            ];
            foreach ($indexes as [$table, $indexName, $column]) {
                try {
                    $pdo->exec("CREATE INDEX $indexName ON $table($column)");
                } catch (PDOException $e) {
                    // 1061 = Duplicate key name → index már létezik, OK
                    if ($e->getCode() != '42000' && strpos($e->getMessage(), '1061') === false) {
                        throw $e;
                    }
                }
            }
            
            // Config fájl frissítése
            $configContent = file_get_contents(__DIR__ . '/config.php');
            
            // DB beállítások frissítése
            $configContent = preg_replace(
                "/define\('DB_HOST',.*?\);/",
                "define('DB_HOST', '$db_host');",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('DB_NAME',.*?\);/",
                "define('DB_NAME', '$db_name');",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('DB_USER',.*?\);/",
                "define('DB_USER', '$db_user');",
                $configContent
            );
            $configContent = preg_replace(
                "/define\('DB_PASS',.*?\);/",
                "define('DB_PASS', '$db_pass');",
                $configContent
            );
            
            // API key frissítése: DB-BE MENTJÜK (nem config.php-ba!)
            if (!empty($api_key)) {
                $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('anthropic_api_key', " . $pdo->quote($api_key) . ") ON DUPLICATE KEY UPDATE setting_value = " . $pdo->quote($api_key));
            }
            
            file_put_contents(__DIR__ . '/config.php', $configContent);
            
            // Verzió mentése
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('db_version', '6.1.0') ON DUPLICATE KEY UPDATE setting_value = '6.1.0'");
            
            // Default beállítások mentése DB-be
            $defaults = [
                'require_login' => '1',
                'demo_mode' => '0',
                'session_lifetime' => '3600',
                'rate_limit_requests' => '30',
                'rate_limit_window' => '3600',
            ];
            foreach ($defaults as $key => $val) {
                $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES (" . $pdo->quote($key) . ", " . $pdo->quote($val) . ") ON DUPLICATE KEY UPDATE setting_key = setting_key");
            }
            
            // Installed flag
            file_put_contents(__DIR__ . '/data/.installed', date('Y-m-d H:i:s') . "\nVersion: " . INSTALLER_VERSION);
            
            $success = 'Telepítés sikeres!';
            $step = 3;
            
        } catch (PDOException $e) {
            $error = 'Adatbázis hiba: ' . $e->getMessage();
            $step = 1;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdMaster Pro - Telepítés</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .installer-header {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .installer-header h1 { font-size: 28px; margin-bottom: 8px; }
        .installer-header p { opacity: 0.9; }
        .installer-body { padding: 30px; }
        
        .steps {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #94a3b8;
        }
        .step-item.active { color: #f97316; font-weight: 600; }
        .step-item.done { color: #22c55e; }
        .step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .step-item.active .step-num { background: #f97316; color: white; }
        .step-item.done .step-num { background: #22c55e; color: white; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 6px; color: #374151; }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #f97316; }
        .form-group small { color: #6b7280; font-size: 12px; margin-top: 4px; display: block; }
        
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            text-align: center;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(249, 115, 22, 0.4); }
        
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .requirements {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .requirements h3 { font-size: 14px; margin-bottom: 12px; }
        .req-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .req-ok { color: #22c55e; }
        .req-fail { color: #dc2626; }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }
        .success-message { text-align: center; }
        .success-message h2 { margin-bottom: 10px; color: #22c55e; }
        .success-message p { color: #6b7280; margin-bottom: 20px; }
        .warning-box {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="installer-header">
            <h1>🚀 AdMaster Pro</h1>
            <p>Telepítő - v<?= INSTALLER_VERSION ?></p>
        </div>
        
        <div class="installer-body">
            <div class="steps">
                <div class="step-item <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
                    <span class="step-num"><?= $step > 1 ? '✓' : '1' ?></span>
                    Követelmények
                </div>
                <div class="step-item <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
                    <span class="step-num"><?= $step > 2 ? '✓' : '2' ?></span>
                    Beállítások
                </div>
                <div class="step-item <?= $step >= 3 ? 'active' : '' ?>">
                    <span class="step-num">3</span>
                    Kész
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
            <!-- Step 1: Követelmények -->
            <?php
            $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
            $pdoOk = extension_loaded('pdo_mysql');
            $curlOk = extension_loaded('curl');
            $jsonOk = extension_loaded('json');
            $mbOk = extension_loaded('mbstring');
            $writableData = is_writable(__DIR__ . '/data');
            $writableLogs = is_writable(__DIR__ . '/logs');
            $allOk = $phpOk && $pdoOk && $curlOk && $jsonOk && $mbOk && $writableData && $writableLogs;
            ?>
            
            <div class="requirements">
                <h3>Rendszerkövetelmények</h3>
                <div class="req-item <?= $phpOk ? 'req-ok' : 'req-fail' ?>">
                    <?= $phpOk ? '✓' : '✗' ?> PHP 8.0+ (jelenlegi: <?= PHP_VERSION ?>)
                </div>
                <div class="req-item <?= $pdoOk ? 'req-ok' : 'req-fail' ?>">
                    <?= $pdoOk ? '✓' : '✗' ?> PDO MySQL extension
                </div>
                <div class="req-item <?= $curlOk ? 'req-ok' : 'req-fail' ?>">
                    <?= $curlOk ? '✓' : '✗' ?> cURL extension
                </div>
                <div class="req-item <?= $jsonOk ? 'req-ok' : 'req-fail' ?>">
                    <?= $jsonOk ? '✓' : '✗' ?> JSON extension
                </div>
                <div class="req-item <?= $mbOk ? 'req-ok' : 'req-fail' ?>">
                    <?= $mbOk ? '✓' : '✗' ?> Multibyte String extension
                </div>
                <div class="req-item <?= $writableData ? 'req-ok' : 'req-fail' ?>">
                    <?= $writableData ? '✓' : '✗' ?> data/ mappa írható
                </div>
                <div class="req-item <?= $writableLogs ? 'req-ok' : 'req-fail' ?>">
                    <?= $writableLogs ? '✓' : '✗' ?> logs/ mappa írható
                </div>
            </div>
            
            <?php if ($allOk): ?>
            <a href="?step=2" class="btn">Tovább a beállításokhoz →</a>
            <?php else: ?>
            <div class="alert alert-error">Kérlek javítsd a fenti hibákat a folytatás előtt!</div>
            <?php endif; ?>
            
            <?php elseif ($step == 2): ?>
            <!-- Step 2: Beállítások -->
            <form method="POST" action="?step=2">
                <h3 style="margin-bottom: 20px;">Adatbázis beállítások</h3>
                
                <div class="form-group">
                    <label>MySQL Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label>Adatbázis neve</label>
                    <input type="text" name="db_name" value="admaster_pro" required>
                    <small>Az adatbázisnak már léteznie kell!</small>
                </div>
                
                <div class="form-group">
                    <label>Felhasználónév</label>
                    <input type="text" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label>Jelszó</label>
                    <input type="password" name="db_pass">
                </div>
                
                <h3 style="margin: 30px 0 20px;">API beállítások</h3>
                
                <div class="form-group">
                    <label>Anthropic API Key</label>
                    <input type="text" name="api_key" placeholder="sk-ant-...">
                    <small>Opcionális most, később is beállítható</small>
                </div>
                
                <button type="submit" class="btn">Telepítés →</button>
            </form>
            
            <?php elseif ($step == 3): ?>
            <!-- Step 3: Kész -->
            <div class="success-message">
                <div class="success-icon">✓</div>
                <h2>Telepítés sikeres!</h2>
                <p>Az AdMaster Pro sikeresen telepítve lett.</p>
            </div>
            
            <div class="warning-box">
                ⚠️ <strong>Fontos:</strong> Biztonsági okokból töröld az <code>install.php</code> fájlt!
            </div>
            
            <a href="index.php" class="btn">Indítás →</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
