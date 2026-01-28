<?php
/**
 * AdMaster Pro - Security Class
 * 
 * Biztonsági funkciók:
 * - CSRF védelem
 * - Rate limiting
 * - Input validáció
 * - Session kezelés
 */

class Security {
    
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

    /**
     * CSRF token generálás
     */
    public static function generateCsrfToken(): string {
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
                return preg_replace('/[^a-zA-Z0-9áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/', '', $input);

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
}
