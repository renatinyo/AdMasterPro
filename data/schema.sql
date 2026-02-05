-- AdMaster Pro Database Schema v5.0.0
-- ======================================

-- Settings tábla
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ügyfelek
CREATE TABLE IF NOT EXISTS clients (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    phone VARCHAR(50),
    area VARCHAR(100),
    website VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kampányok
CREATE TABLE IF NOT EXISTS campaigns (
    id VARCHAR(36) PRIMARY KEY,
    client_id VARCHAR(36),
    name VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    ad_type VARCHAR(50),
    bid_strategy VARCHAR(100),
    area VARCHAR(100),
    phone VARCHAR(50),
    status VARCHAR(50) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Headlines
CREATE TABLE IF NOT EXISTS headlines (
    id VARCHAR(36) PRIMARY KEY,
    campaign_id VARCHAR(36),
    text VARCHAR(30) NOT NULL,
    is_dki BOOLEAN DEFAULT FALSE,
    is_extra BOOLEAN DEFAULT FALSE,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Descriptions
CREATE TABLE IF NOT EXISTS descriptions (
    id VARCHAR(36) PRIMARY KEY,
    campaign_id VARCHAR(36),
    text VARCHAR(90) NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Call-Only Ads
CREATE TABLE IF NOT EXISTS callonly_ads (
    id VARCHAR(36) PRIMARY KEY,
    campaign_id VARCHAR(36),
    business_name VARCHAR(25) NOT NULL,
    description1 VARCHAR(35),
    description2 VARCHAR(35),
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kulcsszavak
CREATE TABLE IF NOT EXISTS keywords (
    id VARCHAR(36) PRIMARY KEY,
    campaign_id VARCHAR(36),
    industry VARCHAR(100),
    keyword VARCHAR(255) NOT NULL,
    match_type VARCHAR(20) DEFAULT 'phrase',
    is_negative BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keyword Bank (globális)
CREATE TABLE IF NOT EXISTS keyword_bank (
    id VARCHAR(36) PRIMARY KEY,
    keyword VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    type ENUM('positive', 'negative') DEFAULT 'positive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Headlines Bank (bevált címsorok)
CREATE TABLE IF NOT EXISTS headline_bank (
    id VARCHAR(36) PRIMARY KEY,
    text VARCHAR(30) NOT NULL,
    industry VARCHAR(100),
    rating INT DEFAULT 3,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sitelinks
CREATE TABLE IF NOT EXISTS sitelinks (
    id VARCHAR(36) PRIMARY KEY,
    campaign_id VARCHAR(36),
    title VARCHAR(25) NOT NULL,
    description VARCHAR(35),
    url VARCHAR(255),
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Callouts
CREATE TABLE IF NOT EXISTS callouts (
    id VARCHAR(36) PRIMARY KEY,
    campaign_id VARCHAR(36),
    text VARCHAR(25) NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custom Industries (AI generált)
CREATE TABLE IF NOT EXISTS custom_industries (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(10),
    description TEXT,
    usps JSON,
    benchmarks JSON,
    keywords JSON,
    negative_keywords JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utolsó generált eredmény (cache)
CREATE TABLE IF NOT EXISTS last_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id VARCHAR(36),
    result_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat history (AI Asszisztens)
CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    role ENUM('user', 'assistant') NOT NULL,
    content TEXT NOT NULL,
    suggestions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index-ek létrehozása PHP-ből történik (install.php)
-- mert a CREATE INDEX nem támogatja az IF NOT EXISTS-t MySQL 5.7-ben

-- Alapértelmezett beállítások (v6.1.0)
-- API kulcsok és dinamikus beállítások mind itt tárolódnak
INSERT INTO settings (setting_key, setting_value) VALUES 
    ('db_version', '6.1.0'),
    ('app_installed', NOW()),
    ('require_login', '1'),
    ('demo_mode', '0'),
    ('session_lifetime', '3600'),
    ('rate_limit_requests', '30'),
    ('rate_limit_window', '3600')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
