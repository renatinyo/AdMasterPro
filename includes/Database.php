<?php
/**
 * AdMaster Pro - Database Handler
 * 
 * MySQL adatbázis kezelés:
 * - Projektek tárolása
 * - Session kezelés
 * - Audit log
 */

class Database {
    
    private static ?PDO $pdo = null;
    private static bool $available = false;
    
    /**
     * Kapcsolódás az adatbázishoz
     */
    public static function connect(): bool {
        if (self::$pdo !== null) {
            return self::$available;
        }
        
        // Ha nincs DB konfiguráció, visszatérünk false-szal
        if (!defined('DB_HOST') || empty(DB_HOST)) {
            self::$available = false;
            return false;
        }
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_NAME
            );
            
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            self::$available = true;
            return true;
            
        } catch (PDOException $e) {
            Security::log('error', 'Database connection failed: ' . $e->getMessage());
            self::$available = false;
            return false;
        }
    }
    
    /**
     * PDO instance lekérése
     */
    public static function getPdo(): ?PDO {
        return self::$pdo;
    }
    
    /**
     * Adatbázis elérhető?
     */
    public static function isAvailable(): bool {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$available;
    }
    
    /**
     * Táblák létrehozása
     */
    public static function createTables(): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        $queries = [
            // Projektek tábla
            "CREATE TABLE IF NOT EXISTS `projects` (
                `id` VARCHAR(50) PRIMARY KEY,
                `user_id` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `industry` VARCHAR(50),
                `data` JSON NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Audit log tábla
            "CREATE TABLE IF NOT EXISTS `audit_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` VARCHAR(64),
                `action` VARCHAR(100) NOT NULL,
                `details` JSON,
                `ip_address` VARCHAR(45),
                `user_agent` VARCHAR(500),
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_action` (`user_id`, `action`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // API usage tracking
            "CREATE TABLE IF NOT EXISTS `api_usage` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` VARCHAR(64) NOT NULL,
                `endpoint` VARCHAR(100) NOT NULL,
                `tokens_used` INT DEFAULT 0,
                `response_time` FLOAT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_date` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        
        try {
            foreach ($queries as $query) {
                self::$pdo->exec($query);
            }
            return true;
        } catch (PDOException $e) {
            Security::log('error', 'Failed to create tables: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Projekt mentése
     */
    public static function saveProject(string $id, string $userId, string $name, ?string $industry, array $data): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO projects (id, user_id, name, industry, data)
                VALUES (:id, :user_id, :name, :industry, :data)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    industry = VALUES(industry),
                    data = VALUES(data),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                'id' => $id,
                'user_id' => $userId,
                'name' => $name,
                'industry' => $industry,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (PDOException $e) {
            Security::log('error', 'Failed to save project: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Projekt betöltése
     */
    public static function loadProject(string $id, string $userId): ?array {
        if (!self::isAvailable()) {
            return null;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                SELECT * FROM projects 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            $row = $stmt->fetch();
            
            if ($row) {
                $row['data'] = json_decode($row['data'], true);
                return $row;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Projektek listázása
     */
    public static function listProjects(string $userId, int $limit = 50): array {
        if (!self::isAvailable()) {
            return [];
        }
        
        try {
            $stmt = self::$pdo->prepare("
                SELECT id, name, industry, created_at, updated_at
                FROM projects 
                WHERE user_id = :user_id
                ORDER BY updated_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue('user_id', $userId);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Projekt törlése
     */
    public static function deleteProject(string $id, string $userId): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                DELETE FROM projects 
                WHERE id = :id AND user_id = :user_id
            ");
            return $stmt->execute(['id' => $id, 'user_id' => $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Audit log bejegyzés
     */
    public static function logAction(string $action, ?string $userId = null, ?array $details = null): void {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO audit_log (user_id, action, details, ip_address, user_agent)
                VALUES (:user_id, :action, :details, :ip, :ua)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details ? json_encode($details) : null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
            ]);
        } catch (PDOException $e) {
            // Silent fail - ne akadályozza a fő műveletet
        }
    }
    
    /**
     * API használat rögzítése
     */
    public static function logApiUsage(string $userId, string $endpoint, int $tokens = 0, float $responseTime = 0): void {
        if (!self::isAvailable()) {
            return;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO api_usage (user_id, endpoint, tokens_used, response_time)
                VALUES (:user_id, :endpoint, :tokens, :time)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'tokens' => $tokens,
                'time' => $responseTime
            ]);
        } catch (PDOException $e) {
            // Silent fail
        }
    }
    
    /**
     * API használat lekérdezése
     */
    public static function getApiUsage(string $userId, int $days = 30): array {
        if (!self::isAvailable()) {
            return [];
        }
        
        try {
            $stmt = self::$pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as requests,
                    SUM(tokens_used) as total_tokens,
                    AVG(response_time) as avg_response_time
                FROM api_usage 
                WHERE user_id = :user_id 
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stmt->bindValue('user_id', $userId);
            $stmt->bindValue('days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}
