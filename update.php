<?php
/**
 * AdMaster Pro - Updater
 * 
 * Frissítési rendszer:
 * 1. Verzió ellenőrzés
 * 2. Változások letöltése
 * 3. Backup készítés
 * 4. Fájlok frissítése
 * 5. Migráció futtatása
 */

session_start();

// Jelenlegi verzió
define('CURRENT_VERSION', '2.0.0');

// Frissítési szerver (ez lenne a saját szerveretek)
define('UPDATE_SERVER', 'https://updates.rsagency.hu/admaster-pro');
define('UPDATE_CHECK_URL', UPDATE_SERVER . '/version.json');
define('UPDATE_PACKAGE_URL', UPDATE_SERVER . '/releases/');

// Biztonsági token (változtasd meg!)
define('UPDATE_SECRET', 'your-secret-key-change-this');

// Frissítési könyvtár
define('BACKUP_DIR', __DIR__ . '/backups/');
define('TEMP_DIR', __DIR__ . '/temp/');

$action = $_GET['action'] ?? 'check';
$message = null;
$error = null;

// Egyszerű auth ellenőrzés (production-ben erősebb kell!)
function checkAuth(): bool {
    // Ha van install.lock, akkor telepítve van
    if (!file_exists('install.lock')) {
        return false;
    }
    
    // Session alapú "admin" ellenőrzés
    // Production-ben valódi auth kell!
    return true;
}

// Verzió összehasonlítás
function isNewerVersion(string $remote, string $local): bool {
    return version_compare($remote, $local, '>');
}

// Távoli verzió lekérdezése
function checkRemoteVersion(): ?array {
    // Demo mód - nincs valódi szerver
    // Production-ben ez lenne:
    // $response = @file_get_contents(UPDATE_CHECK_URL);
    
    // Szimulált válasz demo célokra
    return [
        'latest_version' => '2.1.0',
        'release_date' => '2024-02-01',
        'changelog' => [
            '2.1.0' => [
                'Új: Google Ads API integráció',
                'Új: Automatikus kampány import',
                'Javítás: CSV parser hibák',
                'Javítás: Mobile responsive problémák'
            ],
            '2.0.1' => [
                'Javítás: Landing page analyzer timeout',
                'Javítás: Session kezelés'
            ]
        ],
        'min_php' => '7.4.0',
        'download_url' => UPDATE_PACKAGE_URL . 'admaster-pro-2.1.0.zip',
        'checksum' => 'sha256:abc123...',
        'size' => '2.4 MB'
    ];
}

// Backup készítés
function createBackup(): ?string {
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0750, true);
    }
    
    $backupName = 'backup_' . date('Y-m-d_H-i-s') . '_v' . CURRENT_VERSION . '.zip';
    $backupPath = BACKUP_DIR . $backupName;
    
    $zip = new ZipArchive();
    if ($zip->open($backupPath, ZipArchive::CREATE) !== true) {
        return null;
    }
    
    // Fájlok hozzáadása
    $files = [
        'index.php', 'api.php', 'config.php', '.htaccess',
        'includes/Security.php', 'includes/ProjectManager.php',
        'includes/CsvAnalyzer.php', 'includes/LandingPageAnalyzer.php',
        'assets/style.css', 'assets/app.js',
        'data/industries.php'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, $file);
        }
    }
    
    // Projektek backup
    if (is_dir('data/projects')) {
        $projects = glob('data/projects/*.json');
        foreach ($projects as $project) {
            $zip->addFile($project, $project);
        }
    }
    
    $zip->close();
    
    return $backupPath;
}

// Backup visszaállítás
function restoreBackup(string $backupPath): bool {
    if (!file_exists($backupPath)) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($backupPath) !== true) {
        return false;
    }
    
    $zip->extractTo(__DIR__);
    $zip->close();
    
    return true;
}

// Backup lista
function getBackups(): array {
    if (!is_dir(BACKUP_DIR)) {
        return [];
    }
    
    $backups = glob(BACKUP_DIR . 'backup_*.zip');
    $result = [];
    
    foreach ($backups as $backup) {
        $result[] = [
            'file' => basename($backup),
            'path' => $backup,
            'size' => filesize($backup),
            'date' => filemtime($backup)
        ];
    }
    
    // Legújabb elöl
    usort($result, fn($a, $b) => $b['date'] - $a['date']);
    
    return $result;
}

// Changelog megjelenítés
function formatChangelog(array $changelog): string {
    $html = '';
    foreach ($changelog as $version => $changes) {
        $html .= "<div class='changelog-version'><strong>v{$version}</strong></div>";
        $html .= "<ul class='changelog-list'>";
        foreach ($changes as $change) {
            $icon = str_starts_with($change, 'Új:') ? '✨' : 
                   (str_starts_with($change, 'Javítás:') ? '🔧' : '📝');
            $html .= "<li>{$icon} " . htmlspecialchars($change) . "</li>";
        }
        $html .= "</ul>";
    }
    return $html;
}

// Auth ellenőrzés
if (!checkAuth()) {
    http_response_code(403);
    die('Hozzáférés megtagadva. Telepítsd először az alkalmazást.');
}

// Akció kezelés
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'create_backup':
            $backup = createBackup();
            if ($backup) {
                $message = "Backup sikeresen létrehozva: " . basename($backup);
            } else {
                $error = "Nem sikerült létrehozni a backup-ot!";
            }
            break;
            
        case 'restore_backup':
            $backupFile = $_POST['backup_file'] ?? '';
            $backupPath = BACKUP_DIR . basename($backupFile);
            
            if (restoreBackup($backupPath)) {
                $message = "Backup sikeresen visszaállítva!";
            } else {
                $error = "Nem sikerült visszaállítani a backup-ot!";
            }
            break;
            
        case 'delete_backup':
            $backupFile = $_POST['backup_file'] ?? '';
            $backupPath = BACKUP_DIR . basename($backupFile);
            
            if (file_exists($backupPath) && unlink($backupPath)) {
                $message = "Backup törölve.";
            } else {
                $error = "Nem sikerült törölni a backup-ot!";
            }
            break;
            
        case 'download_update':
            // Demo - valódi frissítés letöltés
            $error = "Demo mód: A frissítés letöltése nem elérhető. Töltsd le manuálisan a legújabb verziót.";
            break;
    }
}

$remoteVersion = checkRemoteVersion();
$updateAvailable = $remoteVersion && isNewerVersion($remoteVersion['latest_version'], CURRENT_VERSION);
$backups = getBackups();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdMaster Pro - Frissítések</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0f; color: #fff; min-height: 100vh; }
        
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .logo { width: 48px; height: 48px; background: linear-gradient(135deg, #E85D04, #DC2626); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; }
        .header h1 { font-size: 24px; }
        .header .version { color: rgba(255,255,255,0.5); font-size: 14px; }
        
        .back-link { color: rgba(255,255,255,0.6); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .back-link:hover { color: #fff; }
        
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .card h2 { font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .alert-info { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #3B82F6; }
        
        .update-status { display: flex; align-items: center; gap: 16px; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .update-status.available { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
        .update-status.current { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); }
        .update-icon { font-size: 40px; }
        .update-info h3 { font-size: 18px; margin-bottom: 4px; }
        .update-info p { color: rgba(255,255,255,0.6); font-size: 14px; }
        
        .version-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 500; }
        .version-badge.current { background: rgba(255,255,255,0.1); }
        .version-badge.new { background: rgba(16, 185, 129, 0.2); color: #10B981; }
        
        .changelog-version { font-size: 16px; margin: 16px 0 8px; color: #E85D04; }
        .changelog-list { list-style: none; margin-left: 0; }
        .changelog-list li { padding: 6px 0; color: rgba(255,255,255,0.7); font-size: 14px; }
        
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #E85D04, #DC2626); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(232, 93, 4, 0.3); }
        .btn-secondary { background: rgba(255,255,255,0.1); color: white; }
        .btn-secondary:hover { background: rgba(255,255,255,0.15); }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        
        .btn-group { display: flex; gap: 12px; margin-top: 16px; }
        
        .backup-list { margin-top: 16px; }
        .backup-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: rgba(255,255,255,0.03); border-radius: 8px; margin-bottom: 8px; }
        .backup-item:hover { background: rgba(255,255,255,0.05); }
        .backup-info { display: flex; align-items: center; gap: 12px; }
        .backup-icon { font-size: 20px; }
        .backup-name { font-weight: 500; }
        .backup-meta { font-size: 13px; color: rgba(255,255,255,0.5); }
        .backup-actions { display: flex; gap: 8px; }
        
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 16px; }
        .info-item { padding: 16px; background: rgba(255,255,255,0.03); border-radius: 8px; text-align: center; }
        .info-value { font-size: 24px; font-weight: 600; color: #E85D04; }
        .info-label { font-size: 13px; color: rgba(255,255,255,0.5); margin-top: 4px; }
        
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 16px; }
        .tab { padding: 10px 20px; border-radius: 8px 8px 0 0; color: rgba(255,255,255,0.6); text-decoration: none; transition: all 0.2s; }
        .tab:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .tab.active { color: #fff; background: rgba(255,255,255,0.1); border-bottom: 2px solid #E85D04; }
        
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .backup-item { flex-direction: column; gap: 12px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="logo">A</div>
                <div>
                    <h1>AdMaster Pro</h1>
                    <span class="version">Frissítések & Backup</span>
                </div>
            </div>
            <a href="index.php" class="back-link">← Vissza az alkalmazáshoz</a>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?action=check" class="tab <?= $action === 'check' ? 'active' : '' ?>">🔄 Frissítések</a>
            <a href="?action=backup" class="tab <?= $action === 'backup' ? 'active' : '' ?>">💾 Backup</a>
            <a href="?action=info" class="tab <?= $action === 'info' ? 'active' : '' ?>">ℹ️ Rendszer Info</a>
        </div>
        
        <?php if ($action === 'check'): ?>
        <!-- Frissítések tab -->
        <div class="card">
            <h2>🔄 Verzió Ellenőrzés</h2>
            
            <div class="update-status <?= $updateAvailable ? 'available' : 'current' ?>">
                <span class="update-icon"><?= $updateAvailable ? '🆕' : '✅' ?></span>
                <div class="update-info">
                    <h3><?= $updateAvailable ? 'Frissítés elérhető!' : 'Naprakész vagy!' ?></h3>
                    <p>
                        Jelenlegi verzió: <span class="version-badge current">v<?= CURRENT_VERSION ?></span>
                        <?php if ($updateAvailable): ?>
                        → Elérhető: <span class="version-badge new">v<?= htmlspecialchars($remoteVersion['latest_version']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <?php if ($updateAvailable && !empty($remoteVersion['changelog'])): ?>
            <div style="margin-top: 24px;">
                <h3 style="font-size: 16px; margin-bottom: 12px;">📝 Változások</h3>
                <?= formatChangelog($remoteVersion['changelog']) ?>
            </div>
            
            <div class="btn-group">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-secondary">💾 Backup készítése először</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="download_update">
                    <button type="submit" class="btn btn-primary">⬇️ Frissítés letöltése</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="alert alert-info">
            💡 <strong>Tipp:</strong> Frissítés előtt mindig készíts backup-ot! A backup automatikusan elmenti a beállításaidat és projektjeidet.
        </div>
        
        <?php elseif ($action === 'backup'): ?>
        <!-- Backup tab -->
        <div class="card">
            <h2>💾 Backup Kezelés</h2>
            <p style="color: rgba(255,255,255,0.6); margin-bottom: 20px;">
                A backup elmenti az összes konfigurációt, kódot és projektet.
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-primary">➕ Új Backup Készítése</button>
            </form>
            
            <?php if (!empty($backups)): ?>
            <div class="backup-list">
                <h3 style="font-size: 16px; margin: 24px 0 12px;">Mentett Backup-ok (<?= count($backups) ?>)</h3>
                
                <?php foreach ($backups as $backup): ?>
                <div class="backup-item">
                    <div class="backup-info">
                        <span class="backup-icon">📦</span>
                        <div>
                            <div class="backup-name"><?= htmlspecialchars($backup['file']) ?></div>
                            <div class="backup-meta">
                                <?= number_format($backup['size'] / 1024, 1) ?> KB • 
                                <?= date('Y-m-d H:i', $backup['date']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="backup-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="restore_backup">
                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['file']) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" 
                                    onclick="return confirm('Biztosan visszaállítod ezt a backup-ot? A jelenlegi fájlok felülíródnak!');">
                                🔄 Visszaállítás
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_backup">
                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['file']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Biztosan törlöd ezt a backup-ot?');">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-warning" style="margin-top: 20px;">
                Még nincs mentett backup. Készíts egyet a frissítés előtt!
            </div>
            <?php endif; ?>
        </div>
        
        <?php elseif ($action === 'info'): ?>
        <!-- Rendszer info tab -->
        <div class="card">
            <h2>ℹ️ Rendszer Információ</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-value">v<?= CURRENT_VERSION ?></div>
                    <div class="info-label">AdMaster Pro verzió</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?= PHP_VERSION ?></div>
                    <div class="info-label">PHP verzió</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?= count(getBackups()) ?></div>
                    <div class="info-label">Backup-ok száma</div>
                </div>
            </div>
            
            <div style="margin-top: 24px;">
                <h3 style="font-size: 16px; margin-bottom: 12px;">📋 Telepítési Adatok</h3>
                <?php 
                $lockData = file_exists('install.lock') ? json_decode(file_get_contents('install.lock'), true) : [];
                ?>
                <table style="width: 100%; font-size: 14px;">
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <td style="padding: 8px 0; color: rgba(255,255,255,0.6);">Telepítve</td>
                        <td style="padding: 8px 0;"><?= htmlspecialchars($lockData['installed_at'] ?? 'N/A') ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <td style="padding: 8px 0; color: rgba(255,255,255,0.6);">Szerver</td>
                        <td style="padding: 8px 0;"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <td style="padding: 8px 0; color: rgba(255,255,255,0.6);">Dokumentum gyökér</td>
                        <td style="padding: 8px 0; font-family: monospace; font-size: 12px;"><?= htmlspecialchars(__DIR__) ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: rgba(255,255,255,0.6);">Projektek száma</td>
                        <td style="padding: 8px 0;"><?= count(glob('data/projects/*/*.json')) ?></td>
                    </tr>
                </table>
            </div>
            
            <div style="margin-top: 24px;">
                <h3 style="font-size: 16px; margin-bottom: 12px;">🔧 PHP Extensions</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php 
                    $extensions = ['curl', 'json', 'mbstring', 'openssl', 'fileinfo', 'zip'];
                    foreach ($extensions as $ext):
                        $loaded = extension_loaded($ext);
                    ?>
                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 13px; 
                                 background: <?= $loaded ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>;
                                 color: <?= $loaded ? '#10B981' : '#EF4444' ?>;">
                        <?= $loaded ? '✓' : '✗' ?> <?= $ext ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
