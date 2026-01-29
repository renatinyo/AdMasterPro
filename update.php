<?php
/**
 * AdMaster Pro - Frissítő v2
 * 
 * Egyszerűsített verzió - csak a lényeg
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

$error = '';
$success = '';
$appVersion = APP_VERSION;
$dbVersion = 'ismeretlen';
$dbConnected = false;
$pdo = null;

// Adatbázis kapcsolat
try {
    if (defined('DB_HOST') && !empty(DB_HOST)) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $dbConnected = true;
        
        // DB verzió lekérése
        try {
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version'");
            $result = $stmt->fetchColumn();
            $dbVersion = $result ?: 'nincs beállítva';
        } catch (Exception $e) {
            $dbVersion = 'settings tábla hiányzik';
        }
    }
} catch (PDOException $e) {
    $error = 'DB hiba: ' . $e->getMessage();
}

// Verzió szinkronizálás
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_version']) && $dbConnected) {
    try {
        // Settings tábla létrehozása ha nincs
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Verzió frissítése
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                               VALUES ('db_version', ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$appVersion, $appVersion]);
        
        $success = "✅ Verzió szinkronizálva: $appVersion";
        $dbVersion = $appVersion;
        
    } catch (Exception $e) {
        $error = 'Hiba: ' . $e->getMessage();
    }
}

// Hiányzó táblák létrehozása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tables']) && $dbConnected) {
    $created = [];
    $errors = [];
    
    $tables = [
        'settings' => "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'clients' => "CREATE TABLE IF NOT EXISTS clients (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            industry VARCHAR(100),
            phone VARCHAR(50),
            area VARCHAR(100),
            website VARCHAR(255),
            notes TEXT,
            generations JSON,
            last_generation DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'keyword_bank' => "CREATE TABLE IF NOT EXISTS keyword_bank (
            id VARCHAR(36) PRIMARY KEY,
            keyword VARCHAR(255) NOT NULL,
            industry VARCHAR(100),
            type ENUM('positive', 'negative') DEFAULT 'positive',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'headline_bank' => "CREATE TABLE IF NOT EXISTS headline_bank (
            id VARCHAR(36) PRIMARY KEY,
            text VARCHAR(30) NOT NULL,
            industry VARCHAR(100),
            rating INT DEFAULT 3,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'chat_history' => "CREATE TABLE IF NOT EXISTS chat_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(100),
            role ENUM('user', 'assistant') NOT NULL,
            content TEXT NOT NULL,
            suggestions JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'landing_audits' => "CREATE TABLE IF NOT EXISTS landing_audits (
            id VARCHAR(36) PRIMARY KEY,
            client_id VARCHAR(36),
            url VARCHAR(500) NOT NULL,
            score INT,
            results JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            $created[] = $name;
        } catch (Exception $e) {
            $errors[] = "$name: " . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        $success = "✅ Táblák létrehozva/ellenőrizve: " . implode(', ', $created);
    } else {
        $error = "Hibák: " . implode('; ', $errors);
    }
}

// Létező táblák lekérése
$existingTables = [];
if ($dbConnected) {
    try {
        $result = $pdo->query("SHOW TABLES");
        $existingTables = $result->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdMaster Pro - Frissítés</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            padding: 24px;
            text-align: center;
        }
        .card-header h1 { font-size: 24px; margin-bottom: 4px; }
        .card-header p { opacity: 0.9; font-size: 14px; }
        .card-body { padding: 24px; }
        
        .version-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .version-box {
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            text-align: center;
        }
        .version-box .label { font-size: 12px; color: #64748b; margin-bottom: 8px; }
        .version-box .value { font-size: 28px; font-weight: 700; }
        .version-box .value.app { color: #22c55e; }
        .version-box .value.db { color: #64748b; }
        .version-box .value.match { color: #22c55e; }
        .version-box .value.mismatch { color: #f59e0b; }
        
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .status-row:last-child { border-bottom: none; }
        .status-label { color: #64748b; }
        .status-value { font-weight: 600; }
        .status-value.ok { color: #22c55e; }
        .status-value.error { color: #ef4444; }
        .status-value.warn { color: #f59e0b; }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
            width: 100%;
            margin-bottom: 10px;
        }
        .btn-primary { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .tables-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .table-tag {
            padding: 4px 10px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            color: white;
            margin-top: 20px;
            opacity: 0.8;
        }
        .back-link:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>🔄 AdMaster Pro</h1>
                <p>Rendszer Frissítés & Karbantartás</p>
            </div>
            
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <div class="version-grid">
                    <div class="version-box">
                        <div class="label">Alkalmazás verzió</div>
                        <div class="value app">v<?= $appVersion ?></div>
                    </div>
                    <div class="version-box">
                        <div class="label">Adatbázis verzió</div>
                        <div class="value <?= $dbVersion === $appVersion ? 'match' : 'mismatch' ?>">
                            <?= is_numeric(substr($dbVersion, 0, 1)) ? 'v' . $dbVersion : $dbVersion ?>
                        </div>
                    </div>
                </div>
                
                <div class="status-row">
                    <span class="status-label">Adatbázis kapcsolat</span>
                    <span class="status-value <?= $dbConnected ? 'ok' : 'error' ?>">
                        <?= $dbConnected ? '✓ Kapcsolódva' : '✗ Nincs kapcsolat' ?>
                    </span>
                </div>
                
                <div class="status-row">
                    <span class="status-label">PHP verzió</span>
                    <span class="status-value ok"><?= PHP_VERSION ?></span>
                </div>
                
                <?php if ($dbConnected && !empty($existingTables)): ?>
                <div class="status-row">
                    <span class="status-label">Létező táblák</span>
                    <span class="status-value ok"><?= count($existingTables) ?> db</span>
                </div>
                <div class="tables-list">
                    <?php foreach ($existingTables as $table): ?>
                    <span class="table-tag"><?= htmlspecialchars($table) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($dbConnected): ?>
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 16px;">🛠️ Műveletek</h3>
                
                <?php if ($dbVersion !== $appVersion): ?>
                <form method="POST" style="margin-bottom: 10px;">
                    <button type="submit" name="sync_version" class="btn btn-primary">
                        🔄 Verzió szinkronizálása (<?= $appVersion ?>)
                    </button>
                </form>
                <?php endif; ?>
                
                <form method="POST" style="margin-bottom: 10px;">
                    <button type="submit" name="create_tables" class="btn btn-secondary">
                        🗄️ Táblák létrehozása/ellenőrzése
                    </button>
                </form>
                
                <a href="install.php" class="btn btn-secondary">
                    📦 Teljes újratelepítés (install.php)
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link">← Vissza az alkalmazáshoz</a>
    </div>
</body>
</html>
