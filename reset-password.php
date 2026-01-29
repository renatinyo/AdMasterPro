<?php
/**
 * AdMaster Pro - Jelszó Visszaállítás
 * 
 * ⚠️ TÖRÖLD EZT A FÁJLT HASZNÁLAT UTÁN!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword)) {
        $message = '❌ Add meg az új jelszót!';
    } elseif (strlen($newPassword) < 6) {
        $message = '❌ A jelszó legalább 6 karakter legyen!';
    } elseif ($newPassword !== $confirmPassword) {
        $message = '❌ A két jelszó nem egyezik!';
    } else {
        // Jelszó hash generálás
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        // Config fájl beolvasása
        $configFile = __DIR__ . '/config.php';
        
        if (!file_exists($configFile)) {
            $message = '❌ config.php nem található!';
        } else {
            $config = file_get_contents($configFile);
            
            // ADMIN_PASSWORD frissítése
            if (preg_match("/define\('ADMIN_PASSWORD'/", $config)) {
                $config = preg_replace(
                    "/define\('ADMIN_PASSWORD',[^)]+\)/",
                    "define('ADMIN_PASSWORD', '$newPassword')",
                    $config
                );
            }
            
            // ADMIN_PASSWORD_HASH frissítése vagy hozzáadása
            if (preg_match("/define\('ADMIN_PASSWORD_HASH'/", $config)) {
                $config = preg_replace(
                    "/define\('ADMIN_PASSWORD_HASH',[^)]+\)/",
                    "define('ADMIN_PASSWORD_HASH', '$hash')",
                    $config
                );
            } else {
                // Ha nincs, adjuk hozzá az ADMIN_PASSWORD után
                $config = preg_replace(
                    "/(define\('ADMIN_PASSWORD',[^)]+\);)/",
                    "$1\ndefine('ADMIN_PASSWORD_HASH', '$hash');",
                    $config
                );
            }
            
            // Backup
            $backupDir = __DIR__ . '/backups/';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }
            @copy($configFile, $backupDir . 'config_backup_' . date('Y-m-d_H-i-s') . '.php');
            
            // Mentés
            if (file_put_contents($configFile, $config)) {
                $success = true;
                $message = '✅ Jelszó sikeresen módosítva!';
            } else {
                $message = '❌ Nem sikerült menteni a config.php fájlt! Ellenőrizd a jogosultságokat.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelszó Visszaállítás - AdMaster Pro</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin-bottom: 8px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .body { padding: 30px; }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #f97316;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .success-actions {
            text-align: center;
            margin-top: 20px;
        }
        .success-actions a {
            display: inline-block;
            padding: 12px 24px;
            background: #22c55e;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .delete-warning {
            background: #fef2f2;
            border: 2px solid #ef4444;
            color: #991b1b;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Jelszó Visszaállítás</h1>
            <p>AdMaster Pro Admin</p>
        </div>
        
        <div class="body">
            <?php if ($message): ?>
            <div class="message <?= $success ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="success-actions">
                <p style="margin-bottom: 16px;">Most már beléphetsz az új jelszóval!</p>
                <a href="login.php">🚀 Belépés</a>
            </div>
            
            <div class="delete-warning">
                ⚠️ FONTOS: Töröld a reset-password.php fájlt!
            </div>
            
            <?php else: ?>
            
            <div class="warning">
                ⚠️ Ez a script biztonsági kockázatot jelent! Töröld azonnal használat után!
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Új jelszó</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="Minimum 6 karakter">
                </div>
                
                <div class="form-group">
                    <label>Új jelszó újra</label>
                    <input type="password" name="confirm_password" required placeholder="Jelszó megerősítése">
                </div>
                
                <button type="submit" class="btn">🔐 Jelszó Beállítása</button>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
