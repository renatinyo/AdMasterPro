<?php
/**
 * AdMaster Pro - Security Class
 * 
 * Biztonsági funkciók:
 * - Admin authentikáció
 * - CSRF védelem
 * - Rate limiting
 * - Input validáció
 * - Session kezelés
 * - Brute force védelem
 */

class Security {
    
    // Brute force védelem
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 900; // 15 perc
    
    /**
     * Biztonságos session indítás
     */
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Session beállítások
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start();

        // Session regenerálás időnként
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            // 30 percenként új session ID
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }

        // User fingerprint ellenőrzés (session hijacking ellen)
        $fingerprint = self::getUserFingerprint();
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
            // Gyanús - session törlés
            session_destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }
    }

    /**
     * User fingerprint generálás
     */
    private static function getUserFingerprint(): string {
        $data = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''
        ];
        return hash('sha256', implode('|', $data));
    }
    
    // ========================================
    // ADMIN AUTHENTIKÁCIÓ
    // ========================================
    
    /**
     * Admin belépés ellenőrzése
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['admin_logged_in']) 
            && $_SESSION['admin_logged_in'] === true
            && isset($_SESSION['admin_login_time'])
            && (time() - $_SESSION['admin_login_time']) < SESSION_LIFETIME;
    }
    
    /**
     * Admin beléptetés
     */
    public static function login(string $username, string $password): array {
        // Brute force ellenőrzés
        if (self::isLockedOut()) {
            $remaining = self::getLockoutRemaining();
            return [
                'success' => false, 
                'error' => "Túl sok sikertelen próbálkozás. Próbáld újra {$remaining} perc múlva."
            ];
        }
        
        // Credentials ellenőrzés
        $adminUser = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
        $adminPassHash = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : '';
        
        // Ha nincs hash beállítva, használjuk a legacy ADMIN_PASSWORD-ot
        if (empty($adminPassHash) && defined('ADMIN_PASSWORD')) {
            $adminPassHash = password_hash(ADMIN_PASSWORD, PASSWORD_ARGON2ID);
        }
        
        if (empty($adminPassHash)) {
            return ['success' => false, 'error' => 'Admin jelszó nincs beállítva a config.php-ban!'];
        }
        
        // Username ellenőrzés (timing-safe)
        $usernameValid = hash_equals($adminUser, $username);
        
        // Jelszó ellenőrzés
        $passwordValid = password_verify($password, $adminPassHash);
        
        if ($usernameValid && $passwordValid) {
            // Sikeres belépés
            self::clearLoginAttempts();
            session_regenerate_id(true);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            
            self::log('info', 'Admin login successful', ['username' => $username, 'ip' => $_SESSION['admin_ip']]);
            
            return ['success' => true];
        }
        
        // Sikertelen belépés
        self::recordFailedLogin();
        self::log('warning', 'Admin login failed', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        
        return ['success' => false, 'error' => 'Hibás felhasználónév vagy jelszó.'];
    }
    
    /**
     * Admin kiléptetés
     */
    public static function logout(): void {
        self::log('info', 'Admin logout', ['username' => $_SESSION['admin_username'] ?? 'unknown']);
        
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
    }
    
    /**
     * Login oldal megjelenítése szükséges?
     */
    public static function requireLogin(): bool {
        // Ha REQUIRE_LOGIN nincs bekapcsolva, mindenki használhatja
        if (!defined('REQUIRE_LOGIN') || !REQUIRE_LOGIN) {
            return false;
        }
        
        return !self::isLoggedIn();
    }
    
    /**
     * Brute force - lockout ellenőrzés
     */
    private static function isLockedOut(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            return false;
        }
        
        $data = $_SESSION[$key];
        
        // Lockout lejárt?
        if (isset($data['lockout_until']) && time() > $data['lockout_until']) {
            unset($_SESSION[$key]);
            return false;
        }
        
        return isset($data['lockout_until']);
    }
    
    /**
     * Lockout hátralévő idő (percben)
     */
    private static function getLockoutRemaining(): int {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . md5($ip);
        
        if (!isset($_SESSION[$key]['lockout_until'])) {
            return 0;
        }
        
        return max(0, ceil(($_SESSION[$key]['lockout_until'] - time()) / 60));
    }
    
    /**
     * Sikertelen belépési kísérlet rögzítése
     */
    private static function recordFailedLogin(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
        }
        
        $_SESSION[$key]['attempts']++;
        $_SESSION[$key]['last_attempt'] = time();
        
        // Ha elérte a limitet, lockout
        if ($_SESSION[$key]['attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key]['lockout_until'] = time() + self::LOCKOUT_TIME;
            self::log('warning', 'IP locked out due to too many login attempts', ['ip' => $ip]);
        }
    }
    
    /**
     * Login kísérletek törlése (sikeres belépés után)
     */
    private static function clearLoginAttempts(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . md5($ip);
        unset($_SESSION[$key]);
    }
    
    /**
     * Jelszó hash generálás (setup-hoz)
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * CSRF token generálás - csak ha nincs vagy lejárt
     */
    public static function generateCsrfToken(): string {
        // Ha van érvényes token, azt adjuk vissza
        if (!empty($_SESSION['csrf_token']) && 
            isset($_SESSION['csrf_token_time']) &&
            (time() - $_SESSION['csrf_token_time']) < CSRF_TOKEN_LIFETIME) {
            return $_SESSION['csrf_token'];
        }
        
        // Új token generálás
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * CSRF token ellenőrzés
     */
    public static function verifyCsrfToken(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Lejárat ellenőrzés
        if (time() - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        // Timing-safe összehasonlítás
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * CSRF token mező HTML
     */
    public static function csrfField(): string {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Rate limiting ellenőrzés
     */
    public static function checkRateLimit(string $action = 'default'): bool {
        $key = 'rate_limit_' . $action;
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
        }

        $data = &$_SESSION[$key];

        // Időablak resetelés
        if ($now - $data['window_start'] > RATE_LIMIT_WINDOW) {
            $data = ['count' => 0, 'window_start' => $now];
        }

        // Limit ellenőrzés
        if ($data['count'] >= RATE_LIMIT_REQUESTS) {
            return false;
        }

        $data['count']++;
        return true;
    }

    /**
     * Maradék kérések száma
     */
    public static function getRateLimitRemaining(string $action = 'default'): int {
        $key = 'rate_limit_' . $action;
        if (!isset($_SESSION[$key])) {
            return RATE_LIMIT_REQUESTS;
        }
        return max(0, RATE_LIMIT_REQUESTS - $_SESSION[$key]['count']);
    }

    /**
     * Input sanitizálás
     */
    public static function sanitizeInput(string $input, string $type = 'string'): string {
        // Alapvető tisztítás
        $input = trim($input);
        
        // NULL byte eltávolítás
        $input = str_replace(chr(0), '', $input);

        switch ($type) {
            case 'string':
                // HTML entitások escape
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            case 'text':
                // Több soros szöveg - megtartjuk a sortöréseket
                $input = strip_tags($input);
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            case 'phone':
                // Csak számok, szóköz, +, -
                return preg_replace('/[^0-9\s\+\-]/', '', $input);

            case 'number':
                // Csak számok
                return preg_replace('/[^0-9]/', '', $input);

            case 'alpha':
                // Csak betűk
                return preg_replace('/[^a-zA-ZáéíóöőúüűÁÉÍÓÖŐÚÜŰ]/', '', $input);

            case 'alphanumeric':
                // Betűk és számok
                return preg_replace('/[^a-zA-Z0-9áéíóöőúüűÁÉÍÓÖŐÚÜŰ_\-]/', '', $input);

            case 'filename':
                // Biztonságos fájlnév
                $input = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $input);
                $input = preg_replace('/\.{2,}/', '.', $input); // Dupla pont eltávolítás
                return substr($input, 0, 100);

            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);

            default:
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    /**
     * Tömb sanitizálás
     */
    public static function sanitizeArray(array $input, string $type = 'string'): array {
        return array_map(fn($item) => self::sanitizeInput((string)$item, $type), $input);
    }

    /**
     * Fájl feltöltés validálás
     */
    public static function validateUpload(array $file): array {
        $errors = [];

        // Alapvető hibák
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return $errors;
        }

        // Méret ellenőrzés
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'A fájl túl nagy. Maximum: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . ' MB';
        }

        // MIME type ellenőrzés
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES)) {
            $errors[] = 'Nem engedélyezett fájltípus. Csak CSV fájlok tölthetők fel.';
        }

        // Fájlnév ellenőrzés
        $filename = $file['name'];
        if (preg_match('/\.(php|phtml|php3|php4|php5|phps|phar|exe|sh|bat|cmd)$/i', $filename)) {
            $errors[] = 'Veszélyes fájlkiterjesztés.';
        }

        return $errors;
    }

    /**
     * Upload hiba üzenet
     */
    private static function getUploadErrorMessage(int $errorCode): string {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A fájl túl nagy.',
            UPLOAD_ERR_PARTIAL => 'A fájl csak részben töltődött fel.',
            UPLOAD_ERR_NO_FILE => 'Nem lett fájl kiválasztva.',
            UPLOAD_ERR_NO_TMP_DIR => 'Szerver hiba: nincs temp könyvtár.',
            UPLOAD_ERR_CANT_WRITE => 'Szerver hiba: nem sikerült írni.',
            default => 'Ismeretlen feltöltési hiba.'
        };
    }

    /**
     * Biztonságos redirect
     */
    public static function redirect(string $url, int $code = 302): never {
        // Csak relatív URL-eket engedünk
        if (preg_match('/^https?:\/\//i', $url)) {
            $url = '/';
        }
        
        header("Location: $url", true, $code);
        exit;
    }

    /**
     * JSON válasz küldése
     */
    public static function jsonResponse(array $data, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Logging
     */
    public static function log(string $level, string $message, array $context = []): void {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentLevel = $levels[LOG_LEVEL] ?? 0;
        $messageLevel = $levels[$level] ?? 0;

        if ($messageLevel < $currentLevel) {
            return;
        }

        $logFile = LOG_DIR . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[$timestamp] [$level] $message$contextStr\n";

        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * API kulcs validálás
     */
    public static function validateApiKey(): bool {
        if (empty(ANTHROPIC_API_KEY)) {
            return false;
        }
        
        // Alapvető formátum ellenőrzés
        if (!preg_match('/^sk-ant-[a-zA-Z0-9\-_]+$/', ANTHROPIC_API_KEY)) {
            return false;
        }

        return true;
    }
    
    /**
     * XSS védett output
     */
    public static function e(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * SQL injection védelem - prepared statement wrapper
     */
    public static function prepareValue($value, string $type = 'string'): string {
        if ($value === null) {
            return 'NULL';
        }
        
        switch ($type) {
            case 'int':
                return (string)(int)$value;
            case 'float':
                return (string)(float)$value;
            case 'bool':
                return $value ? '1' : '0';
            default:
                return "'" . addslashes($value) . "'";
        }
    }
}
