<?php
/**
 * AdMaster Pro - Settings Manager
 * 
 * Központi beállítás-kezelő:
 * - Minden beállítás az adatbázis `settings` táblából jön
 * - config.php csak DB kapcsolathoz és alap konstansokhoz kell
 * - API kulcsok, session beállítások mind DB-ben
 * - Frissítéskor NEM vesznek el a beállítások!
 * 
 * @since v6.1.0
 */

class Settings {
    
    /** @var array Memória cache - egy request alatt nem kérdezzük le újra */
    private static array $cache = [];
    
    /** @var bool Betöltöttük-e már a cache-t */
    private static bool $loaded = false;

    /**
     * Összes beállítás betöltése DB-ből (egyszer, lazy)
     */
    private static function loadAll(): void {
        if (self::$loaded) return;
        
        try {
            if (!Database::isAvailable()) {
                self::$loaded = true;
                return;
            }
            
            $pdo = Database::getPdo();
            if (!$pdo) {
                self::$loaded = true;
                return;
            }
            
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // DB nem elérhető - fallback config.php-ra
        }
        
        self::$loaded = true;
    }
    
    /**
     * Beállítás lekérdezése
     * 
     * Prioritás:
     * 1. DB settings tábla (ha van)
     * 2. Környezeti változó (getenv)
     * 3. Megadott default érték
     * 
     * @param string $key  Beállítás kulcs (pl. 'anthropic_api_key')
     * @param string $default  Alapértelmezett érték
     * @return string
     */
    public static function get(string $key, string $default = ''): string {
        self::loadAll();
        
        // 1. DB-ből
        if (isset(self::$cache[$key]) && self::$cache[$key] !== '') {
            return self::$cache[$key];
        }
        
        // 2. Környezeti változóból (nagybetűs)
        $envKey = strtoupper($key);
        $envVal = getenv($envKey);
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }
        
        // 3. Default
        return $default;
    }
    
    /**
     * Beállítás mentése DB-be
     */
    public static function set(string $key, string $value): bool {
        try {
            if (!Database::isAvailable()) return false;
            
            $pdo = Database::getPdo();
            if (!$pdo) return false;
            
            // Tábla létrehozása ha nem létezik
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (:key, :val)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $result = $stmt->execute(['key' => $key, 'val' => $value]);
            
            // Cache frissítés
            self::$cache[$key] = $value;
            
            return $result;
        } catch (Exception $e) {
            Security::log('error', "Settings::set failed for $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Beállítás törlése
     */
    public static function delete(string $key): bool {
        try {
            if (!Database::isAvailable()) return false;
            $pdo = Database::getPdo();
            if (!$pdo) return false;
            
            $stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = :key");
            $result = $stmt->execute(['key' => $key]);
            unset(self::$cache[$key]);
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Több beállítás mentése egyszerre
     */
    public static function setMany(array $settings): int {
        $saved = 0;
        foreach ($settings as $key => $value) {
            if (self::set($key, (string)$value)) {
                $saved++;
            }
        }
        return $saved;
    }
    
    /**
     * Összes beállítás lekérdezése (admin felülethez)
     */
    public static function getAll(): array {
        self::loadAll();
        return self::$cache;
    }
    
    /**
     * Bool beállítás lekérdezése
     */
    public static function getBool(string $key, bool $default = false): bool {
        $val = self::get($key, $default ? '1' : '0');
        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }
    
    /**
     * Int beállítás lekérdezése
     */
    public static function getInt(string $key, int $default = 0): int {
        $val = self::get($key, (string)$default);
        return (int)$val;
    }
    
    /**
     * Cache reset (teszteléshez)
     */
    public static function resetCache(): void {
        self::$cache = [];
        self::$loaded = false;
    }
    
    // =============================================
    // KÉNYELMI GETTEREK (gyakran használt kulcsok)
    // =============================================
    
    public static function anthropicApiKey(): string {
        return self::get('anthropic_api_key');
    }
    
    public static function serpApiKey(): string {
        return self::get('serpapi_key');
    }
    
    public static function screenshotApiKey(): string {
        return self::get('screenshot_api_key');
    }
    
    public static function googleAdsDeveloperToken(): string {
        return self::get('google_ads_developer_token');
    }
    
    public static function googleAdsClientId(): string {
        return self::get('google_ads_client_id');
    }
    
    public static function googleAdsClientSecret(): string {
        return self::get('google_ads_client_secret');
    }
    
    public static function googleAdsRefreshToken(): string {
        return self::get('google_ads_refresh_token');
    }
    
    public static function googleAdsLoginCustomerId(): string {
        return self::get('google_ads_login_customer_id');
    }
    
    public static function isDemoMode(): bool {
        return self::getBool('demo_mode', false);
    }
    
    public static function requireLogin(): bool {
        return self::getBool('require_login', true);
    }
    
    public static function sessionLifetime(): int {
        return self::getInt('session_lifetime', 3600);
    }
    
    public static function rateLimitRequests(): int {
        return self::getInt('rate_limit_requests', 30);
    }
    
    public static function rateLimitWindow(): int {
        return self::getInt('rate_limit_window', 3600);
    }
    
    /**
     * API kulcs maszkolás (megjelenítéshez)
     */
    public static function mask(string $key): string {
        $val = self::get($key);
        if (empty($val)) return '';
        if (strlen($val) < 12) return '••••••';
        return substr($val, 0, 8) . '••••••' . substr($val, -4);
    }
    
    /**
     * API kulcs beállítottság ellenőrzése
     */
    public static function isConfigured(string $key): bool {
        return !empty(self::get($key));
    }
    
    /**
     * Migráció: config.php konstansok átemelése DB-be
     * Csak egyszer kell futtatni, utána a DB az igazság
     */
    public static function migrateFromConfig(): array {
        $migrated = [];
        
        $mapping = [
            'ANTHROPIC_API_KEY'            => 'anthropic_api_key',
            'SERPAPI_KEY'                   => 'serpapi_key',
            'SCREENSHOT_API_KEY'           => 'screenshot_api_key',
            'GOOGLE_ADS_DEVELOPER_TOKEN'   => 'google_ads_developer_token',
            'GOOGLE_ADS_CLIENT_ID'         => 'google_ads_client_id',
            'GOOGLE_ADS_CLIENT_SECRET'     => 'google_ads_client_secret',
            'GOOGLE_ADS_REFRESH_TOKEN'     => 'google_ads_refresh_token',
            'GOOGLE_ADS_LOGIN_CUSTOMER_ID' => 'google_ads_login_customer_id',
            'DEMO_MODE'                    => 'demo_mode',
            'REQUIRE_LOGIN'                => 'require_login',
            'SESSION_LIFETIME'             => 'session_lifetime',
            'RATE_LIMIT_REQUESTS'          => 'rate_limit_requests',
            'RATE_LIMIT_WINDOW'            => 'rate_limit_window',
        ];
        
        foreach ($mapping as $const => $dbKey) {
            // Csak ha a DB-ben még nincs, és a config-ban van
            if (empty(self::get($dbKey)) && defined($const)) {
                $val = constant($const);
                if ($val !== '' && $val !== false && $val !== null) {
                    $strVal = is_bool($val) ? ($val ? '1' : '0') : (string)$val;
                    if (self::set($dbKey, $strVal)) {
                        $migrated[] = $dbKey;
                    }
                }
            }
        }
        
        return $migrated;
    }
}
