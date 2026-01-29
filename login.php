<?php
/**
 * AdMaster Pro - Login Page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';

Security::initSession();

// Ha már be van jelentkezve, redirect
if (Security::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Login form feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // CSRF ellenőrzés
    if (!Security::verifyCsrfToken($csrfToken)) {
        $error = 'Biztonsági hiba. Frissítsd az oldalt és próbáld újra.';
    } else {
        $result = Security::login($username, $password);
        
        if ($result['success']) {
            // Sikeres belépés - redirect
            $redirect = $_GET['redirect'] ?? 'index.php';
            // Biztonsági ellenőrzés
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php/', $redirect)) {
                $redirect = 'index.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// Logout kezelés
if (isset($_GET['logout'])) {
    Security::logout();
    $success = 'Sikeres kijelentkezés.';
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belépés - <?= APP_NAME ?></title>
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
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo {
            font-size: 48px;
            margin-bottom: 12px;
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        .login-subtitle {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(249,115,22,0.4);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #94a3b8;
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle input {
            padding-right: 50px;
        }
        .toggle-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            opacity: 0.5;
        }
        .toggle-btn:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">🚀</div>
            <h1 class="login-title"><?= APP_NAME ?></h1>
            <p class="login-subtitle">Google Ads Kampány Generátor</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <?= Security::csrfField() ?>
            
            <div class="form-group">
                <label for="username">Felhasználónév</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="admin" required autocomplete="username" autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Jelszó</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="toggle-btn" onclick="togglePassword()">👁️</button>
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                🔐 Belépés
            </button>
        </form>
        
        <div class="login-footer">
            <?= APP_NAME ?> v<?= APP_VERSION ?> | &copy; <?= date('Y') ?>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const btn = event.target;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }
        
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Belépés...';
        });
    </script>
</body>
</html>
