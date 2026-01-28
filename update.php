<?php
/**
 * AdMaster Pro - Frissítő
 * 
 * Verziófrissítéseket kezel, adatbázis migrációkat futtat.
 * Az adatok MEGMARADNAK!
 */

require_once __DIR__ . '/config.php';

session_start();

$error = '';
$success = '';
$updates = [];

// Jelenlegi app verzió
$appVersion = APP_VERSION;

// Adatbázis kapcsolat
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Adatbázis kapcsolat sikertelen. Futtasd az install.php-t!');
}

// DB verzió lekérése
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version'");
$dbVersion = $stmt->fetchColumn() ?: '1.0.0';

// Verzió összehasonlítás
function versionCompare($v1, $v2) {
    return version_compare($v1, $v2);
}

// Elérhető migrációk
$migrations = [
    '4.0.0' => [
        'description' => 'Google Ads API és Vision támogatás',
        'sql' => []
    ],
    '4.1.0' => [
        'description' => 'AI Asszisztens chat history',
        'sql' => [
            "CREATE TABLE IF NOT EXISTS chat_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(100),
                role ENUM('user', 'assistant') NOT NULL,
                content TEXT NOT NULL,
                suggestions JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ]
    ],
    '4.2.0' => [
        'description' => 'Eredmény mentés és DKI támogatás',
        'sql' => [
            "ALTER TABLE headlines ADD COLUMN IF NOT EXISTS is_dki BOOLEAN DEFAULT FALSE"
        ]
    ],
    '5.0.0' => [
        'description' => 'Teljes adatbázis alapú működés',
        'sql' => [
            "CREATE TABLE IF NOT EXISTS keyword_bank (
                id VARCHAR(36) PRIMARY KEY,
                keyword VARCHAR(255) NOT NULL,
                industry VARCHAR(100),
                type ENUM('positive', 'negative') DEFAULT 'positive',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS headline_bank (
                id VARCHAR(36) PRIMARY KEY,
                text VARCHAR(30) NOT NULL,
                industry VARCHAR(100),
                rating INT DEFAULT 3,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE INDEX IF NOT EXISTS idx_keyword_bank_industry ON keyword_bank(industry)",
            "CREATE INDEX IF NOT EXISTS idx_headline_bank_industry ON headline_bank(industry)"
        ]
    ]
];

// Szükséges frissítések meghatározása
$pendingMigrations = [];
foreach ($migrations as $version => $migration) {
    if (versionCompare($dbVersion, $version) < 0) {
        $pendingMigrations[$version] = $migration;
    }
}

// Frissítés futtatása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_update'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($pendingMigrations as $version => $migration) {
            foreach ($migration['sql'] as $sql) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    // Ha már létezik a tábla/oszlop, nem baj
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }
            $updates[] = "v$version: " . $migration['description'];
        }
        
        // Verzió frissítése
        $pdo->exec("UPDATE settings SET setting_value = '$appVersion' WHERE setting_key = 'db_version'");
        
        // .installed fájl frissítése
        file_put_contents(__DIR__ . '/data/.installed', date('Y-m-d H:i:s') . "\nVersion: $appVersion\nUpdated from: $dbVersion");
        
        $pdo->commit();
        $success = 'Frissítés sikeres!';
        $dbVersion = $appVersion;
        $pendingMigrations = [];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Frissítés sikertelen: ' . $e->getMessage();
    }
}

// JSON adatok migrálása DB-be
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate_json'])) {
    $migrated = [];
    
    try {
        // Clients migráció
        $clientsFile = __DIR__ . '/data/clients.json';
        if (file_exists($clientsFile)) {
            $clients = json_decode(file_get_contents($clientsFile), true) ?: [];
            foreach ($clients as $client) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO clients (id, name, industry, phone, area, website, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $client['id'],
                    $client['name'],
                    $client['industry'] ?? null,
                    $client['phone'] ?? null,
                    $client['area'] ?? null,
                    $client['website'] ?? null,
                    $client['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            $migrated[] = count($clients) . ' ügyfél';
        }
        
        // Keywords migráció
        $keywordsFile = __DIR__ . '/data/keywords.json';
        if (file_exists($keywordsFile)) {
            $keywords = json_decode(file_get_contents($keywordsFile), true) ?: [];
            foreach ($keywords['positive'] ?? [] as $kw) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO keyword_bank (id, keyword, industry, type) VALUES (?, ?, ?, 'positive')");
                $stmt->execute([$kw['id'], $kw['keyword'], $kw['industry'] ?? null]);
            }
            foreach ($keywords['negative'] ?? [] as $kw) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO keyword_bank (id, keyword, industry, type) VALUES (?, ?, ?, 'negative')");
                $stmt->execute([$kw['id'], $kw['keyword'], $kw['industry'] ?? null]);
            }
            $migrated[] = 'Kulcsszavak';
        }
        
        // Headlines migráció
        $headlinesFile = __DIR__ . '/data/headlines.json';
        if (file_exists($headlinesFile)) {
            $headlines = json_decode(file_get_contents($headlinesFile), true) ?: [];
            foreach ($headlines as $h) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO headline_bank (id, text, industry, rating, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$h['id'], $h['text'], $h['industry'] ?? null, $h['rating'] ?? 3, $h['notes'] ?? null]);
            }
            $migrated[] = count($headlines) . ' headline';
        }
        
        if (!empty($migrated)) {
            $success = 'JSON adatok migrálva: ' . implode(', ', $migrated);
        } else {
            $success = 'Nincs migrálható adat.';
        }
        
    } catch (Exception $e) {
        $error = 'Migráció sikertelen: ' . $e->getMessage();
    }
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .updater {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .updater-header {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .updater-header h1 { font-size: 28px; margin-bottom: 8px; }
        .updater-body { padding: 30px; }
        
        .version-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .version-box { text-align: center; }
        .version-box .label { font-size: 12px; color: #6b7280; }
        .version-box .value { font-size: 24px; font-weight: 700; }
        .version-box .value.current { color: #6b7280; }
        .version-box .value.new { color: #22c55e; }
        .version-arrow { font-size: 24px; color: #94a3b8; }
        
        .migration-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .migration-list h3 { font-size: 14px; margin-bottom: 12px; }
        .migration-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }
        .migration-item:last-child { border-bottom: none; }
        .migration-version { 
            background: #dbeafe; 
            color: #1d4ed8; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-weight: 600;
            font-size: 11px;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            text-align: center;
            text-decoration: none;
            margin-bottom: 10px;
        }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: linear-gradient(135deg, #6b7280, #4b5563); }
        .btn-small { padding: 10px 16px; font-size: 14px; }
        
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        
        .up-to-date {
            text-align: center;
            padding: 30px;
        }
        .up-to-date-icon {
            width: 60px;
            height: 60px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 30px;
            color: white;
        }
        
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="updater">
        <div class="updater-header">
            <h1>🔄 AdMaster Pro</h1>
            <p>Verziófrissítés</p>
        </div>
        
        <div class="updater-body">
            <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="version-info">
                <div class="version-box">
                    <div class="label">Jelenlegi</div>
                    <div class="value current">v<?= $dbVersion ?></div>
                </div>
                <div class="version-arrow">→</div>
                <div class="version-box">
                    <div class="label">Legújabb</div>
                    <div class="value new">v<?= $appVersion ?></div>
                </div>
            </div>
            
            <?php if (empty($pendingMigrations)): ?>
            <div class="up-to-date">
                <div class="up-to-date-icon">✓</div>
                <h3>Naprakész!</h3>
                <p style="color: #6b7280; margin-top: 8px;">Az adatbázis a legújabb verzión van.</p>
            </div>
            
            <a href="index.php" class="btn btn-secondary">← Vissza az alkalmazáshoz</a>
            
            <?php else: ?>
            
            <div class="migration-list">
                <h3>Elérhető frissítések:</h3>
                <?php foreach ($pendingMigrations as $version => $migration): ?>
                <div class="migration-item">
                    <span class="migration-version">v<?= $version ?></span>
                    <span><?= htmlspecialchars($migration['description']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="alert alert-info">
                ℹ️ A frissítés NEM törli az adataidat. Biztonsági mentést mégis ajánlott készíteni!
            </div>
            
            <form method="POST">
                <button type="submit" name="run_update" class="btn">🚀 Frissítés indítása</button>
            </form>
            
            <?php endif; ?>
            
            <div class="divider"></div>
            
            <h4 style="margin-bottom: 12px; font-size: 14px;">JSON → Adatbázis migráció</h4>
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 12px;">
                Ha korábban JSON fájlokban tároltál adatokat, ide migrálhatod őket.
            </p>
            <form method="POST">
                <button type="submit" name="migrate_json" class="btn btn-secondary btn-small">📦 JSON adatok migrálása</button>
            </form>
        </div>
    </div>
</body>
</html>
