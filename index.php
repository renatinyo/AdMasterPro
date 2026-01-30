<?php
/**
 * AdMaster Pro v5.4 - Teljes Kampány Kezelő
 * 
 * Funkciók:
 * - Admin belépés (biztonságos)
 * - Wizard alapú kampány generálás
 * - Ügyfél/cég kezelés
 * - Élő RSA előnézet
 * - Ad Strength indikátor
 * - Bevált headline bank
 * - Stratégiai javaslatok
 * - Versenytárs elemzés
 * - Landing page CRO audit
 * - Google Ads API integráció
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/ClientManager.php';

Security::initSession();

// ========================================
// BELÉPÉS ELLENŐRZÉS
// ========================================
if (Security::requireLogin()) {
    // Redirect a login oldalra
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
    header("Location: login.php?redirect=$redirect");
    exit;
}

// Logout kezelés
if (isset($_GET['logout'])) {
    Security::logout();
    header('Location: login.php?logout=1');
    exit;
}

$industries = require __DIR__ . '/data/industries.php';

// Custom iparágak betöltése és összefésülése
$customFile = __DIR__ . '/data/custom_industries.json';
if (file_exists($customFile)) {
    $customIndustries = json_decode(file_get_contents($customFile), true) ?: [];
    $industries = array_merge($industries, $customIndustries);
}

require_once __DIR__ . '/data/strategies.php';

$clientManager = new ClientManager();
$api_key_valid = !empty(ANTHROPIC_API_KEY) && strlen(ANTHROPIC_API_KEY) > 20;

// Aktuális tab
$tab = $_GET['tab'] ?? 'assistant';

// Wizard session
$wizard = $_SESSION['wizard'] ?? [
    'step' => 1, 'industry' => null, 'goal' => null, 'client_id' => null
];

// URL paraméterek kezelése
if (isset($_GET['step'])) $wizard['step'] = max(1, min(4, (int)$_GET['step']));
if (isset($_GET['industry']) && isset($industries[$_GET['industry']])) {
    $wizard['industry'] = $_GET['industry'];
    if ($wizard['step'] < 2) $wizard['step'] = 2;
}
if (isset($_GET['goal'])) {
    $wizard['goal'] = $_GET['goal'];
    if ($wizard['step'] < 3) $wizard['step'] = 3;
}
if (isset($_GET['client'])) {
    $wizard['client_id'] = $_GET['client'];
}
if (isset($_GET['reset'])) {
    $wizard = ['step' => 1, 'industry' => null, 'goal' => null, 'client_id' => null];
}

$_SESSION['wizard'] = $wizard;

// Betöltött ügyfél
$selectedClient = $wizard['client_id'] ? $clientManager->getClient($wizard['client_id']) : null;

// Cél ajánlások
$goals = [
    'conversions' => ['name' => 'Konverziók (Hívások)', 'icon' => '📞', 'desc' => 'Hívások és érdeklődők maximalizálása', 'bid' => 'Konverzió Max.', 'ad' => 'Call-Only', 'recommended' => true],
    'clicks' => ['name' => 'Weboldal Forgalom', 'icon' => '👥', 'desc' => 'Látogatók a weboldalra', 'bid' => 'Kattintás Max.', 'ad' => 'RSA'],
    'awareness' => ['name' => 'Márkaismertség', 'icon' => '📢', 'desc' => 'Brand megismertetése', 'bid' => 'Impression Share', 'ad' => 'RSA'],
    'leads' => ['name' => 'Lead Generálás', 'icon' => '📋', 'desc' => 'Űrlap kitöltések', 'bid' => 'Cél CPA', 'ad' => 'RSA']
];

$currentIndustry = $wizard['industry'] ? $industries[$wizard['industry']] : null;
$currentGoal = $wizard['goal'] ? $goals[$wizard['goal']] : null;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> v<?= APP_VERSION ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="header-compact">
        <div class="container">
            <div class="header-content">
                <a href="?" class="logo">
                    <span class="logo-icon">🚀</span>
                    <span class="logo-text"><?= APP_NAME ?></span>
                    <span class="version">v<?= APP_VERSION ?></span>
                </a>
                
                <nav class="main-nav">
                    <!-- Fő menüpontok -->
                    <a href="?tab=assistant" class="nav-link <?= $tab === 'assistant' ? 'active' : '' ?>">
                        <span class="nav-icon">💬</span>
                        <span class="nav-text">AI</span>
                    </a>
                    <a href="?tab=wizard" class="nav-link <?= $tab === 'wizard' ? 'active' : '' ?>">
                        <span class="nav-icon">🚀</span>
                        <span class="nav-text">Kampány</span>
                    </a>
                    
                    <!-- Google Ads csoport -->
                    <div class="nav-dropdown">
                        <button class="nav-link nav-dropdown-toggle <?= in_array($tab, ['gads', 'publish', 'simulator', 'diagnosis']) ? 'active' : '' ?>">
                            <span class="nav-icon">📊</span>
                            <span class="nav-text">Ads</span>
                            <span class="dropdown-arrow">▾</span>
                        </button>
                        <div class="nav-dropdown-menu">
                            <a href="?tab=gads" class="<?= $tab === 'gads' ? 'active' : '' ?>">📊 Google Ads Sync</a>
                            <a href="?tab=publish" class="<?= $tab === 'publish' ? 'active' : '' ?>">📤 Közzététel</a>
                            <a href="?tab=simulator" class="<?= $tab === 'simulator' ? 'active' : '' ?>">💸 Költség Szimulátor</a>
                            <a href="?tab=diagnosis" class="<?= $tab === 'diagnosis' ? 'active' : '' ?>">🧠 Fiók Diagnózis</a>
                        </div>
                    </div>
                    
                    <!-- Adatok csoport -->
                    <div class="nav-dropdown">
                        <button class="nav-link nav-dropdown-toggle <?= in_array($tab, ['clients', 'keywords', 'industries', 'strategies']) ? 'active' : '' ?>">
                            <span class="nav-icon">📁</span>
                            <span class="nav-text">Adatok</span>
                            <span class="dropdown-arrow">▾</span>
                        </button>
                        <div class="nav-dropdown-menu">
                            <a href="?tab=clients" class="<?= $tab === 'clients' ? 'active' : '' ?>">🏢 Ügyfelek</a>
                            <a href="?tab=keywords" class="<?= $tab === 'keywords' ? 'active' : '' ?>">🔤 Kulcsszóbank</a>
                            <a href="?tab=industries" class="<?= $tab === 'industries' ? 'active' : '' ?>">🏭 Iparágak</a>
                            <a href="?tab=strategies" class="<?= $tab === 'strategies' ? 'active' : '' ?>">📋 Stratégiák</a>
                        </div>
                    </div>
                    
                    <!-- Eszközök csoport -->
                    <div class="nav-dropdown">
                        <button class="nav-link nav-dropdown-toggle <?= in_array($tab, ['competitors', 'landing', 'pmax']) ? 'active' : '' ?>">
                            <span class="nav-icon">🔧</span>
                            <span class="nav-text">Eszközök</span>
                            <span class="dropdown-arrow">▾</span>
                        </button>
                        <div class="nav-dropdown-menu">
                            <a href="?tab=landing" class="<?= $tab === 'landing' ? 'active' : '' ?>">🌐 Landing Elemző</a>
                            <a href="?tab=competitors" class="<?= $tab === 'competitors' ? 'active' : '' ?>">🔍 Versenytárs</a>
                            <a href="?tab=pmax" class="<?= $tab === 'pmax' ? 'active' : '' ?>">📦 PMax</a>
                        </div>
                    </div>
                </nav>
                
                <div class="header-right">
                    <?php if (DEMO_MODE): ?><span class="badge badge-warning">Demo</span><?php endif; ?>
                    <?php if (Security::isLoggedIn()): ?>
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <span class="user-avatar">👤</span>
                            <span class="user-name"><?= Security::e($_SESSION['admin_username'] ?? 'Admin') ?></span>
                            <span class="dropdown-arrow">▾</span>
                        </button>
                        <div class="user-dropdown-menu">
                            <a href="settings.php">⚙️ Beállítások</a>
                            <a href="update.php">🔄 Frissítés</a>
                            <hr>
                            <a href="?logout=1" class="logout">🚪 Kijelentkezés</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Mobil menü gomb -->
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
            </div>
        </div>
    </header>
    
    <!-- Mobil menü -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <span>Menü</span>
            <button onclick="toggleMobileMenu()">✕</button>
        </div>
        <nav class="mobile-nav">
            <a href="?tab=assistant" class="<?= $tab === 'assistant' ? 'active' : '' ?>">💬 AI Asszisztens</a>
            <a href="?tab=wizard" class="<?= $tab === 'wizard' ? 'active' : '' ?>">🚀 Kampány Generálás</a>
            <div class="mobile-nav-group">
                <div class="mobile-nav-title">📊 Google Ads</div>
                <a href="?tab=gads" class="<?= $tab === 'gads' ? 'active' : '' ?>">Sync</a>
                <a href="?tab=publish" class="<?= $tab === 'publish' ? 'active' : '' ?>">Közzététel</a>
                <a href="?tab=simulator" class="<?= $tab === 'simulator' ? 'active' : '' ?>">Szimulátor</a>
                <a href="?tab=diagnosis" class="<?= $tab === 'diagnosis' ? 'active' : '' ?>">Diagnózis</a>
            </div>
            <div class="mobile-nav-group">
                <div class="mobile-nav-title">📁 Adatok</div>
                <a href="?tab=clients" class="<?= $tab === 'clients' ? 'active' : '' ?>">Ügyfelek</a>
                <a href="?tab=keywords" class="<?= $tab === 'keywords' ? 'active' : '' ?>">Kulcsszavak</a>
                <a href="?tab=industries" class="<?= $tab === 'industries' ? 'active' : '' ?>">Iparágak</a>
                <a href="?tab=strategies" class="<?= $tab === 'strategies' ? 'active' : '' ?>">Stratégiák</a>
            </div>
            <div class="mobile-nav-group">
                <div class="mobile-nav-title">🔧 Eszközök</div>
                <a href="?tab=landing" class="<?= $tab === 'landing' ? 'active' : '' ?>">Landing Elemző</a>
                <a href="?tab=competitors" class="<?= $tab === 'competitors' ? 'active' : '' ?>">Versenytárs</a>
                <a href="?tab=pmax" class="<?= $tab === 'pmax' ? 'active' : '' ?>">PMax</a>
            </div>
            <hr>
            <a href="settings.php">⚙️ Beállítások</a>
            <a href="?logout=1" class="logout">🚪 Kijelentkezés</a>
        </nav>
    </div>

    <main>
        <div class="container">

<?php if ($tab === 'assistant'): ?>
<!-- ==================== AI ASSZISZTENS TAB ==================== -->

    <section class="page-section assistant-section">
        <div class="assistant-hero">
            <div class="hero-icon">🤖</div>
            <h1>AI Kampány Asszisztens</h1>
            <p>Mondd el mit szeretnél hirdetni, én megtervezem a kampányodat!</p>
        </div>
        
        <div class="assistant-container">
            <!-- Chat interface -->
            <div class="chat-container">
                <div class="chat-messages" id="chatMessages">
                    <div class="message assistant">
                        <div class="message-avatar">
                            <span class="avatar-icon">🤖</span>
                        </div>
                        <div class="message-bubble">
                            <div class="message-sender">AdMaster AI</div>
                            <p>Szia! 👋 Írd le <strong>mit szeretnél hirdetni</strong>, és segítek megtervezni a kampányodat!</p>
                            <div class="quick-prompts">
                                <p class="prompts-label">Próbáld ki:</p>
                                <button class="quick-prompt" onclick="useExample(this)">
                                    <span class="prompt-icon">🔧</span>
                                    <span>Vízszerelő cégem van Budapesten</span>
                                </button>
                                <button class="quick-prompt" onclick="useExample(this)">
                                    <span class="prompt-icon">📹</span>
                                    <span>Biztonsági kamerákat telepítünk</span>
                                </button>
                                <button class="quick-prompt" onclick="useExample(this)">
                                    <span class="prompt-icon">🚿</span>
                                    <span>Duguláselhárítás 0-24</span>
                                </button>
                                <button class="quick-prompt" onclick="useExample(this)">
                                    <span class="prompt-icon">💇</span>
                                    <span>Fodrász szalon a 13. kerületben</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input-wrapper">
                    <form id="chatForm" class="chat-input-container">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="assistant_chat">
                        <div class="input-area">
                            <textarea name="message" id="chatInput" class="chat-input" placeholder="Írd le az üzleted és céljaid..." rows="1"></textarea>
                            <button type="submit" class="chat-send-btn" id="chatSendBtn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="input-hint">Enter = küldés | Shift+Enter = új sor</div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar: Generated suggestions -->
            <div class="assistant-sidebar" id="assistantSidebar">
                <div class="sidebar-empty">
                    <div class="empty-icon">💡</div>
                    <h3>Kampány Javaslatok</h3>
                    <p>Itt jelennek meg az AI által generált kampány elemek</p>
                    <ul class="feature-list">
                        <li>📝 Headlines & Descriptions</li>
                        <li>🔤 Kulcsszó javaslatok</li>
                        <li>💰 Költségbecslés</li>
                        <li>🎯 Stratégiai tippek</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

<?php elseif ($tab === 'wizard'): ?>
<!-- ==================== WIZARD TAB ==================== -->

    <!-- Progress Bar -->
    <div class="progress-bar">
        <div class="progress-steps">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="progress-step <?= $wizard['step'] >= $i ? 'active' : '' ?> <?= $wizard['step'] > $i ? 'completed' : '' ?>">
                <div class="step-number"><?= $i ?></div>
                <div class="step-label"><?= ['Iparág', 'Cél', 'Adatok', 'Generálás'][$i-1] ?></div>
            </div>
            <?php if ($i < 4): ?><div class="progress-line <?= $wizard['step'] > $i ? 'active' : '' ?>"></div><?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <?php if ($wizard['step'] === 1): ?>
    <!-- STEP 1: Iparág -->
    <section class="wizard-step">
        <div class="step-header">
            <h2>Milyen iparágban hirdetsz?</h2>
            <p>Válassz iparágat vagy <a href="?tab=clients">válassz mentett ügyfelet →</a></p>
        </div>
        
        <div class="industry-grid">
            <?php foreach ($industries as $key => $ind): ?>
            <a href="?tab=wizard&industry=<?= $key ?>" class="industry-card">
                <span class="industry-icon"><?= $ind['icon'] ?></span>
                <div class="industry-info">
                    <h3><?= $ind['name'] ?></h3>
                    <span class="industry-meta">CPA: <?= $ind['benchmarks']['cpa_budapest'] ?></span>
                </div>
                <span class="arrow">→</span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($wizard['step'] === 2 && $currentIndustry): ?>
    <!-- STEP 2: Cél -->
    <section class="wizard-step">
        <div class="step-header">
            <span class="breadcrumb"><?= $currentIndustry['icon'] ?> <?= $currentIndustry['name'] ?> <a href="?tab=wizard&step=1">módosít</a></span>
            <h2>Mi a kampány célja?</h2>
        </div>
        
        <div class="goal-grid">
            <?php foreach ($goals as $key => $goal): ?>
            <a href="?tab=wizard&industry=<?= $wizard['industry'] ?>&goal=<?= $key ?>" class="goal-card <?= !empty($goal['recommended']) ? 'recommended' : '' ?>">
                <?php if (!empty($goal['recommended'])): ?><span class="rec-badge">⭐ Ajánlott</span><?php endif; ?>
                <span class="goal-icon"><?= $goal['icon'] ?></span>
                <h3><?= $goal['name'] ?></h3>
                <p><?= $goal['desc'] ?></p>
                <div class="goal-meta">
                    <span>📊 <?= $goal['bid'] ?></span>
                    <span>📝 <?= $goal['ad'] ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($wizard['step'] === 3 && $currentIndustry && $currentGoal): ?>
    <!-- STEP 3: Adatok -->
    <section class="wizard-step">
        <div class="step-header">
            <div class="breadcrumbs">
                <span class="breadcrumb"><?= $currentIndustry['icon'] ?> <?= $currentIndustry['name'] ?> <a href="?tab=wizard&step=1">módosít</a></span>
                <span class="breadcrumb"><?= $currentGoal['icon'] ?> <?= $currentGoal['name'] ?> <a href="?tab=wizard&industry=<?= $wizard['industry'] ?>&step=2">módosít</a></span>
            </div>
            <h2>Cég adatok megadása</h2>
        </div>

        <form method="POST" action="api.php" id="wizardForm">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="generate_all">
            <input type="hidden" name="industry" value="<?= $wizard['industry'] ?>">
            <input type="hidden" name="goal" value="<?= $wizard['goal'] ?>">
            
            <div class="form-columns">
                <!-- Bal: Cég adatok -->
                <div class="form-column">
                    <div class="card">
                        <h3 class="card-title">📋 Cég Adatok</h3>
                        
                        <?php 
                        // Mentett ügyfelek dropdown
                        $clients = $clientManager->getClients();
                        if (!empty($clients)): 
                        ?>
                        <div class="form-group">
                            <label>Mentett ügyfél betöltése</label>
                            <select id="loadClient" name="client_id" class="form-control" onchange="loadClientData(this)">
                                <option value="">-- Válassz vagy töltsd ki kézzel --</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" data-json="<?= htmlspecialchars(json_encode($c)) ?>" <?= $selectedClient && $selectedClient['id'] === $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (<?= $c['industry'] ?? 'n/a' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-text">💾 A generált tartalom automatikusan mentésre kerül az ügyfélhez</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="company_name">Cégnév *</label>
                            <input type="text" id="company_name" name="company_name" class="form-control" required
                                   value="<?= htmlspecialchars($selectedClient['name'] ?? '') ?>"
                                   placeholder="pl. Gyors Szolgáltatás Kft.">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefonszám *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" required
                                   value="<?= htmlspecialchars($selectedClient['phone'] ?? '') ?>"
                                   placeholder="+36 30 123 4567">
                        </div>
                        
                        <div class="form-group">
                            <label for="area">Terület</label>
                            <select id="area" name="area" class="form-control">
                                <option value="budapest" <?= ($selectedClient['area'] ?? '') === 'budapest' ? 'selected' : '' ?>>Budapest</option>
                                <option value="videk" <?= ($selectedClient['area'] ?? '') === 'videk' ? 'selected' : '' ?>>Vidék</option>
                                <option value="orszagos" <?= ($selectedClient['area'] ?? '') === 'orszagos' ? 'selected' : '' ?>>Országos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="website">Weboldal</label>
                            <input type="url" id="website" name="website" class="form-control"
                                   value="<?= htmlspecialchars($selectedClient['website'] ?? '') ?>"
                                   placeholder="https://...">
                        </div>
                        
                        <label class="checkbox-label">
                            <input type="checkbox" name="save_client" value="1">
                            <span>Ügyfél mentése a következő alkalomra</span>
                        </label>
                    </div>
                    
                    <div class="card">
                        <h3 class="card-title">⭐ USP-k (egyedi előnyök)</h3>
                        <div class="usp-list">
                            <?php foreach ($currentIndustry['usps'] as $usp): ?>
                            <label class="usp-item">
                                <input type="checkbox" name="usps[]" value="<?= $usp['id'] ?>" <?= $usp['impact'] === 'high' ? 'checked' : '' ?>>
                                <span><?= $usp['text'] ?></span>
                                <?php if ($usp['impact'] === 'high'): ?><span class="fire">🔥</span><?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Jobb: Beállítások + Extra -->
                <div class="form-column">
                    <div class="card card-highlight">
                        <h3 class="card-title">🎯 Kampány Beállítások</h3>
                        
                        <div class="setting-row">
                            <span class="setting-label">Hirdetés típus</span>
                            <select name="ad_type" class="form-control-sm">
                                <option value="callonly" <?= $currentGoal['ad'] === 'Call-Only' ? 'selected' : '' ?>>📞 Call-Only</option>
                                <option value="rsa" <?= $currentGoal['ad'] === 'RSA' ? 'selected' : '' ?>>📝 RSA</option>
                                <option value="both">📞+📝 Mindkettő</option>
                            </select>
                        </div>
                        
                        <div class="setting-row">
                            <span class="setting-label">Bid stratégia</span>
                            <select name="bid_strategy" class="form-control-sm">
                                <option value="maximize_conversions">Konverzió Max.</option>
                                <option value="target_cpa">Cél CPA</option>
                                <option value="maximize_clicks">Kattintás Max.</option>
                                <option value="target_impression_share">Impression Share</option>
                            </select>
                        </div>
                        
                        <div class="setting-row">
                            <span class="setting-label">Napi büdzsé</span>
                            <span class="setting-value"><?= number_format($currentIndustry['benchmarks']['daily_budget_recommended'], 0, ',', ' ') ?> Ft</span>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3 class="card-title">🎭 Pszichológiai Tónus</h3>
                        <p class="help-text">Válaszd ki a hirdetések fő érzelmi hatását</p>
                        
                        <div class="tone-selector">
                            <label class="tone-option" onclick="selectTone(this)">
                                <input type="radio" name="psychological_tone" value="urgent" checked>
                                <div class="tone-icon">🚨</div>
                                <div class="tone-name">Sürgető</div>
                                <div class="tone-desc">SOS helyzetek, azonnali cselekvés</div>
                            </label>
                            <label class="tone-option" onclick="selectTone(this)">
                                <input type="radio" name="psychological_tone" value="trust">
                                <div class="tone-icon">🏆</div>
                                <div class="tone-name">Bizalomépítő</div>
                                <div class="tone-desc">Megbízhatóság, tapasztalat</div>
                            </label>
                            <label class="tone-option" onclick="selectTone(this)">
                                <input type="radio" name="psychological_tone" value="value">
                                <div class="tone-icon">💰</div>
                                <div class="tone-name">Ár-fókuszú</div>
                                <div class="tone-desc">Érték, megtakarítás</div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3 class="card-title">🔥 Hangnem & Stílus</h3>
                        
                        <!-- Megszólítás -->
                        <div class="style-toggles">
                            <div class="toggle-group">
                                <label class="toggle-label">Megszólítás:</label>
                                <div class="toggle-buttons">
                                    <label class="toggle-btn">
                                        <input type="radio" name="formality" value="informal" checked>
                                        <span>👋 Tegezés</span>
                                    </label>
                                    <label class="toggle-btn">
                                        <input type="radio" name="formality" value="formal">
                                        <span>🎩 Magázás</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="toggle-group">
                                <label class="toggle-label">Kommunikáció:</label>
                                <div class="toggle-buttons">
                                    <label class="toggle-btn">
                                        <input type="radio" name="voice" value="team" checked>
                                        <span>👥 Csapat (Mi)</span>
                                    </label>
                                    <label class="toggle-btn">
                                        <input type="radio" name="voice" value="solo">
                                        <span>👤 Egyéni (Én)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <!-- Aggresszivitás Csúszka -->
                        <div class="aggressiveness-slider">
                            <label class="slider-label">Headline stílus:</label>
                            <div class="slider-container">
                                <input type="range" name="aggressiveness" id="aggressivenessSlider" min="1" max="3" value="2" class="slider">
                                <div class="slider-labels">
                                    <span class="slider-opt" data-val="1">😌 Soft</span>
                                    <span class="slider-opt active" data-val="2">😐 Balanced</span>
                                    <span class="slider-opt" data-val="3">😈 Aggressive</span>
                                </div>
                            </div>
                            <div class="slider-preview" id="sliderPreview">
                                <span class="preview-label">Példa:</span>
                                <span class="preview-text" id="previewText">"Professzionális Duguláselhárítás"</span>
                            </div>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <p class="help-text">Extra elemek:</p>
                        <label class="checkbox-label">
                            <input type="checkbox" name="extra_aggressive" value="1" checked>
                            <span>⚡ Sürgősségre ösztönző</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="extra_trust" value="1" checked>
                            <span>🏆 Bizalomépítő</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="extra_price" value="1">
                            <span>💰 Ár-központú</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="use_dki" value="1" checked>
                            <span>🔄 Dynamic Keyword Insertion</span>
                        </label>
                        <p class="help-text" style="margin-left:24px;font-size:11px;">Pl: {KeyWord:Duguláselhárítás} - a keresett kulcsszó behelyettesítődik</p>
                        
                        <?php 
                        // Bevált saját headline-ok
                        $savedHeadlines = $clientManager->getHeadlinesForIndustry($wizard['industry']);
                        if (!empty($savedHeadlines)):
                        ?>
                        <div class="divider"></div>
                        <label class="checkbox-label">
                            <input type="checkbox" name="use_saved_headlines" value="1" checked>
                            <span>📌 Bevált saját szövegeim (<?= count($savedHeadlines) ?> db)</span>
                        </label>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card card-tips">
                        <h3 class="card-title">💡 Stratégiai Tipp</h3>
                        <p><strong>Top of Page:</strong> Állítsd be a bid stratégiát "Target Impression Share" -re 95%-kal, hogy mindig az első helyen jelenj meg!</p>
                        <a href="?tab=strategies" class="link-more">Több stratégia →</a>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="?tab=wizard&industry=<?= $wizard['industry'] ?>&step=2" class="btn btn-secondary">← Vissza</a>
                <button type="submit" class="btn btn-primary btn-lg" id="generateBtn">
                    🚀 Kampány Generálása
                </button>
            </div>
        </form>
    </section>
    
    <div id="results"></div>
    
    <!-- Utolsó eredmény betöltése ha van -->
    <?php
    $lastResultFile = __DIR__ . '/data/last_result.json';
    $lastResult = file_exists($lastResultFile) ? json_decode(file_get_contents($lastResultFile), true) : null;
    if ($lastResult && !empty($lastResult['headlines'])):
    ?>
    <div class="card last-result-card">
        <div class="last-result-header">
            <h3 class="card-title">📋 Utolsó Generált Kampány</h3>
            <span class="last-result-meta">
                <?= htmlspecialchars($lastResult['company'] ?? '') ?> | 
                <?= htmlspecialchars($lastResult['industry'] ?? '') ?> | 
                <?= $lastResult['lastUpdate'] ?? '' ?>
            </span>
        </div>
        
        <div class="last-result-content">
            <div class="result-section">
                <h4>📝 Headlines (<?= count($lastResult['headlines']) ?>)</h4>
                <div class="copy-list compact scrollable">
                    <?php foreach ($lastResult['headlines'] as $h): ?>
                    <div class="copy-item"><span><?= htmlspecialchars($h) ?></span><small><?= mb_strlen($h) ?>/30</small></div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!empty($lastResult['descriptions'])): ?>
            <div class="result-section">
                <h4>📝 Descriptions (<?= count($lastResult['descriptions']) ?>)</h4>
                <div class="copy-list compact scrollable">
                    <?php foreach ($lastResult['descriptions'] as $d): ?>
                    <div class="copy-item"><span><?= htmlspecialchars($d) ?></span><small><?= mb_strlen($d) ?>/90</small></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($lastResult['callonly'])): ?>
            <div class="result-section">
                <h4>📞 Call-Only (<?= count($lastResult['callonly']) ?>)</h4>
                <?php foreach (array_slice($lastResult['callonly'], 0, 3) as $ad): ?>
                <div class="callonly-box compact">
                    <div class="co-name"><?= htmlspecialchars($ad['business'] ?? '') ?></div>
                    <div class="co-desc"><?= htmlspecialchars($ad['desc1'] ?? '') ?></div>
                    <div class="co-desc"><?= htmlspecialchars($ad['desc2'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($lastResult['keywords'])): ?>
            <div class="result-section">
                <h4>🔤 Kulcsszavak</h4>
                <div class="tags-list">
                    <?php foreach ($lastResult['keywords'] as $k): ?>
                    <span class="tag"><?= htmlspecialchars(is_array($k) ? $k['keyword'] : $k) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="last-result-actions">
            <button class="btn btn-secondary" onclick="copyAllHeadlines()">📋 Headlines</button>
            <button class="btn btn-secondary" onclick="copyAllDescriptions()">📋 Descriptions</button>
            <button class="btn btn-success" onclick="showSaveToClientModal()">💾 Mentés Ügyfélhez</button>
            <a href="?tab=publish" class="btn btn-secondary">📤 Közzététel</a>
            <button class="btn btn-primary" onclick="document.getElementById('generateBtn').scrollIntoView({behavior:'smooth'}); document.getElementById('generateBtn').classList.add('pulse');">🔄 Új Generálás</button>
        </div>
    </div>
    
    <script>
    // Last result data - frissíti a globális változót
    lastResultData = <?= json_encode($lastResult) ?>;
    
    function copyAllHeadlines() {
        const headlines = <?= json_encode($lastResult['headlines']) ?>;
        navigator.clipboard.writeText(headlines.join('\n')).then(() => alert('✅ Headlines másolva!'));
    }
    function copyAllDescriptions() {
        const descriptions = <?= json_encode($lastResult['descriptions'] ?? []) ?>;
        navigator.clipboard.writeText(descriptions.join('\n')).then(() => alert('✅ Descriptions másolva!'));
    }
    </script>
    <?php endif; ?>
    
    <!-- ELŐZMÉNYEK SZEKCIÓ -->
    <?php
    $historyFile = __DIR__ . '/data/generation_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    if (is_array($history) && count($history) > 1):
    ?>
    <div class="card history-card">
        <div class="history-header">
            <h3 class="card-title">📚 Korábbi Generálások</h3>
            <span class="history-count"><?= count($history) ?> db mentve</span>
        </div>
        <div class="history-list">
            <?php foreach (array_slice($history, 1, 10) as $idx => $item): ?>
            <div class="history-item" onclick="loadHistory(<?= $idx + 1 ?>)">
                <div class="history-info">
                    <strong><?= htmlspecialchars($item['company'] ?? 'Névtelen') ?></strong>
                    <span class="history-meta"><?= htmlspecialchars($item['industry'] ?? '') ?> • <?= $item['lastUpdate'] ?? '' ?></span>
                </div>
                <div class="history-stats">
                    <span class="stat"><?= count($item['headlines'] ?? []) ?> headline</span>
                    <span class="stat"><?= count($item['descriptions'] ?? []) ?> desc</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    function loadHistory(idx) {
        const history = <?= json_encode($history) ?>;
        const item = history[idx];
        if (!item) return;
        
        // Tároljuk el a kiválasztott history item-et globálisan a mentéshez
        window.selectedHistoryItem = item;
        window.selectedHistoryIndex = idx;
        
        // Betöltjük a modal-ba vagy egyből megjelenítjük
        let html = '<div class="modal-backdrop" onclick="closeHistoryModal()"></div>';
        html += '<div class="history-modal">';
        html += '<div class="modal-header"><h3>📋 ' + (item.company || 'Generálás') + '</h3><button onclick="closeHistoryModal()">✕</button></div>';
        html += '<div class="modal-body">';
        
        // Headlines
        html += '<h4>📝 Headlines (' + item.headlines.length + ')</h4><div class="copy-list compact">';
        item.headlines.forEach(h => {
            html += '<div class="copy-item"><span>' + h + '</span><small>' + h.length + '/30</small></div>';
        });
        html += '</div>';
        
        // Descriptions
        if (item.descriptions && item.descriptions.length) {
            html += '<h4 style="margin-top:16px">📝 Descriptions (' + item.descriptions.length + ')</h4><div class="copy-list compact">';
            item.descriptions.forEach(d => {
                html += '<div class="copy-item"><span>' + d + '</span><small>' + d.length + '/90</small></div>';
            });
            html += '</div>';
        }
        
        html += '</div>';
        html += '<div class="modal-footer">';
        html += '<button class="btn btn-secondary" onclick="copyHistoryHeadlines(' + idx + ')">📋 Headlines Másolása</button>';
        html += '<button class="btn btn-success" onclick="saveHistoryToClient()">💾 Mentés Ügyfélhez</button>';
        html += '<button class="btn btn-secondary" onclick="closeHistoryModal()">Bezárás</button>';
        html += '</div>';
        html += '</div>';
        
        document.body.insertAdjacentHTML('beforeend', html);
    }
    
    function closeHistoryModal() {
        document.querySelector('.modal-backdrop')?.remove();
        document.querySelector('.history-modal')?.remove();
    }
    
    function copyHistoryHeadlines(idx) {
        const history = <?= json_encode($history) ?>;
        const item = history[idx];
        if (item && item.headlines) {
            navigator.clipboard.writeText(item.headlines.join('\n')).then(() => alert('✅ Headlines másolva!'));
        }
    }
    
    // Korábbi generálás mentése ügyfélhez - a fő scriptben lesz definiálva
    // saveHistoryToClient() a fő scriptben van
    </script>
    <?php endif; ?>
    <?php endif; ?>

<?php elseif ($tab === 'publish'): ?>
<!-- ==================== GOOGLE ADS KÖZZÉTÉTEL TAB ==================== -->

    <?php 
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gadsManager = new GoogleAdsManager();
    $gadsConfigured = $gadsManager->isConfigured();
    
    // Utolsó generált adatok betöltése
    $extensionDataFile = __DIR__ . '/data/extension_data.json';
    $lastGenerated = file_exists($extensionDataFile) ? json_decode(file_get_contents($extensionDataFile), true) : null;
    ?>

    <section class="page-section">
        <div class="section-header">
            <h2>📤 Google Ads Közzététel</h2>
        </div>
        
        <?php if (!$gadsConfigured): ?>
        <!-- Nincs konfigurálva -->
        <div class="card card-warning">
            <h3 class="card-title">⚠️ Google Ads API Nincs Beállítva</h3>
            <p>A közvetlen közzétételhez állítsd be a Google Ads API hitelesítést.</p>
            
            <div class="setup-steps">
                <h4>Beállítás lépései:</h4>
                <ol>
                    <li>Hozz létre egy projektet a <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a>-ban</li>
                    <li>Engedélyezd a Google Ads API-t</li>
                    <li>Hozz létre OAuth2 credentials-t (Web application)</li>
                    <li>Igényelj Developer Token-t a <a href="https://ads.google.com/aw/apicenter" target="_blank">Google Ads API Center</a>-ben</li>
                    <li>Add meg a következő értékeket a <code>config.php</code>-ban:</li>
                </ol>
                
                <pre class="code-block">
define('GOOGLE_ADS_DEVELOPER_TOKEN', 'xxx');
define('GOOGLE_ADS_CLIENT_ID', 'xxx.apps.googleusercontent.com');
define('GOOGLE_ADS_CLIENT_SECRET', 'xxx');
define('GOOGLE_ADS_REFRESH_TOKEN', 'xxx');</pre>
                
                <p class="help-text">A Refresh Token megszerzéséhez használd az OAuth2 flow-t.</p>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Konfigurálva van -->
        
        <?php if (empty($lastGenerated) || empty($lastGenerated['headlines'])): ?>
        <div class="alert alert-warning">
            ⚠️ Nincs közzétételre váró tartalom. Először <a href="?tab=wizard">generálj egy kampányt</a>!
        </div>
        <?php else: ?>
        
        <div class="publish-layout">
            <!-- Bal oldal: Közzétételre váró tartalom -->
            <div class="publish-content">
                <div class="card">
                    <h3 class="card-title">📋 Közzétételre Váró Tartalom</h3>
                    <p class="help-text">Utolsó generálás: <?= $lastGenerated['lastUpdate'] ?? 'n/a' ?> 
                        <?php if (!empty($lastGenerated['company'])): ?>
                        | <?= htmlspecialchars($lastGenerated['company']) ?>
                        <?php endif; ?>
                    </p>
                    
                    <div class="content-preview">
                        <h4>📝 Headlines (<?= count($lastGenerated['headlines']) ?>)</h4>
                        <div class="preview-list">
                            <?php foreach (array_slice($lastGenerated['headlines'], 0, 5) as $h): ?>
                            <div class="preview-item"><?= htmlspecialchars($h) ?></div>
                            <?php endforeach; ?>
                            <?php if (count($lastGenerated['headlines']) > 5): ?>
                            <div class="preview-more">+<?= count($lastGenerated['headlines']) - 5 ?> további...</div>
                            <?php endif; ?>
                        </div>
                        
                        <h4>📝 Descriptions (<?= count($lastGenerated['descriptions']) ?>)</h4>
                        <div class="preview-list">
                            <?php foreach ($lastGenerated['descriptions'] as $d): ?>
                            <div class="preview-item"><?= htmlspecialchars($d) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Jobb oldal: Cél kiválasztása -->
            <div class="publish-target">
                <div class="card">
                    <h3 class="card-title">🎯 Publikálás Helye</h3>
                    
                    <form method="POST" action="api.php" id="publishForm">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="publish_to_google_ads">
                        
                        <div class="form-group">
                            <label>Google Ads Fiók</label>
                            <select name="customer_id" id="gadsAccount" class="form-control" required>
                                <option value="">-- Betöltés... --</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Kampány</label>
                            <select name="campaign_id" id="gadsCampaign" class="form-control" required disabled>
                                <option value="">-- Válassz fiókot először --</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Ad Group</label>
                            <select name="ad_group_id" id="gadsAdGroup" class="form-control" required disabled>
                                <option value="">-- Válassz kampányt először --</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Landing Page URL *</label>
                            <input type="url" name="final_url" class="form-control" placeholder="https://..." required>
                        </div>
                        
                        <div class="form-group">
                            <label>Display Path (opcionális)</label>
                            <div class="path-inputs">
                                <span>domain.hu/</span>
                                <input type="text" name="path1" class="form-control-sm" placeholder="path1" maxlength="15">
                                <span>/</span>
                                <input type="text" name="path2" class="form-control-sm" placeholder="path2" maxlength="15">
                            </div>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="form-group">
                            <label>Mit publikáljunk?</label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="publish_rsa" value="1" checked>
                                <span>📝 RSA Hirdetés (Headlines + Descriptions)</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="publish_keywords" value="1">
                                <span>🔤 Kulcsszavak</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="publish_negatives" value="1">
                                <span>🚫 Negatív kulcsszavak</span>
                            </label>
                        </div>
                        
                        <div class="alert alert-info">
                            ℹ️ A hirdetés PAUSED állapotban kerül feltöltésre biztonsági okokból.
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" id="publishBtn">
                            📤 Közzététel a Google Ads-ban
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        <?php endif; ?>
        
        <div id="publishResults"></div>
    </section>

<?php elseif ($tab === 'simulator'): ?>
<!-- ==================== KAMPÁNY SZIMULÁTOR TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>💸 Kampány Szimulátor</h2>
        </div>
        
        <div class="simulator-container">
            <div class="card">
                <h3 class="card-title">📊 Budget & Becslések</h3>
                <p class="help-text">Add meg a paramétereket és számoljuk ki a várható eredményeket</p>
                
                <form id="simulatorForm">
                    <div class="form-columns">
                        <div class="form-column">
                            <div class="form-group">
                                <label>Iparág</label>
                                <select name="industry" id="simIndustry" class="form-control" required>
                                    <option value="">-- Válassz --</option>
                                    <?php foreach ($industries as $key => $ind): ?>
                                    <option value="<?= $key ?>" 
                                        data-cpc-min="<?= $ind['benchmarks']['cpc_min'] ?? 150 ?>"
                                        data-cpc-max="<?= $ind['benchmarks']['cpc_max'] ?? 400 ?>"
                                        data-cvr="<?= $ind['benchmarks']['conversion_rate'] ?? 5 ?>"
                                        data-cpa="<?= preg_replace('/[^0-9]/', '', $ind['benchmarks']['cpa_budapest'] ?? '8000') ?>">
                                        <?= $ind['icon'] ?> <?= $ind['name'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Napi költségkeret (Ft)</label>
                                <input type="number" name="daily_budget" id="simDailyBudget" class="form-control" value="15000" min="1000" step="1000">
                                <div class="budget-presets">
                                    <button type="button" class="preset-btn" data-val="5000">5k</button>
                                    <button type="button" class="preset-btn" data-val="10000">10k</button>
                                    <button type="button" class="preset-btn active" data-val="15000">15k</button>
                                    <button type="button" class="preset-btn" data-val="25000">25k</button>
                                    <button type="button" class="preset-btn" data-val="50000">50k</button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Kampány hossza (nap)</label>
                                <input type="number" name="days" id="simDays" class="form-control" value="30" min="1" max="365">
                            </div>
                        </div>
                        
                        <div class="form-column">
                            <div class="form-group">
                                <label>Átlagos CPC (Ft) <span class="auto-filled">← iparágból</span></label>
                                <input type="number" name="cpc" id="simCpc" class="form-control" value="250" min="50">
                            </div>
                            
                            <div class="form-group">
                                <label>Konverziós ráta (%) <span class="auto-filled">← iparágból</span></label>
                                <input type="number" name="cvr" id="simCvr" class="form-control" value="5" min="0.1" max="100" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label>Terület</label>
                                <select name="area" class="form-control">
                                    <option value="budapest">Budapest (magasabb CPC)</option>
                                    <option value="videk">Vidék (alacsonyabb CPC)</option>
                                    <option value="mixed">Vegyes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-lg" id="runSimulation">📊 Szimuláció Futtatása</button>
                </form>
            </div>
            
            <!-- Eredmények -->
            <div id="simulatorResults" class="simulator-results hidden">
                <div class="sim-results-grid">
                    <div class="sim-result-card">
                        <div class="sim-icon">💰</div>
                        <div class="sim-value" id="simTotalBudget">0</div>
                        <div class="sim-label">Teljes költség</div>
                    </div>
                    <div class="sim-result-card">
                        <div class="sim-icon">👆</div>
                        <div class="sim-value" id="simClicks">0</div>
                        <div class="sim-label">Várható kattintás</div>
                    </div>
                    <div class="sim-result-card highlight">
                        <div class="sim-icon">📞</div>
                        <div class="sim-value" id="simLeads">0</div>
                        <div class="sim-label">Várható lead</div>
                    </div>
                    <div class="sim-result-card">
                        <div class="sim-icon">🎯</div>
                        <div class="sim-value" id="simCpa">0</div>
                        <div class="sim-label">Lead ára (CPA)</div>
                    </div>
                </div>
                
                <div id="simVerdict" class="sim-verdict"></div>
                
                <div class="sim-breakdown">
                    <h4>📈 Részletes bontás</h4>
                    <table class="sim-table">
                        <tr><td>Napi költés</td><td id="simDailySpend">-</td></tr>
                        <tr><td>Napi kattintás</td><td id="simDailyClicks">-</td></tr>
                        <tr><td>Napi lead</td><td id="simDailyLeads">-</td></tr>
                        <tr><td>Havi költés</td><td id="simMonthlySpend">-</td></tr>
                        <tr><td>Havi lead</td><td id="simMonthlyLeads">-</td></tr>
                    </table>
                </div>
                
                <div class="sim-recommendations" id="simRecommendations"></div>
            </div>
        </div>
    </section>

<?php elseif ($tab === 'diagnosis'): ?>
<!-- ==================== DIAGNÓZIS TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>🧠 "Miért Nem Hoz Leadet?" - AI Diagnózis</h2>
        </div>
        
        <div class="diagnosis-intro">
            <p>Az AI átfogó elemzést készít a kampányodról és megmondja, <strong>miért nem teljesít</strong> és <strong>mit javíts</strong>.</p>
        </div>
        
        <div class="card">
            <h3 class="card-title">🔍 Kampány Adatok</h3>
            
            <form method="POST" action="api.php" id="diagnosisForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="run_diagnosis">
                
                <div class="form-columns">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Iparág *</label>
                            <select name="industry" class="form-control" required>
                                <option value="">-- Válassz --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Landing Page URL</label>
                            <input type="url" name="landing_url" class="form-control" placeholder="https://...">
                        </div>
                        
                        <div class="form-group">
                            <label>Jelenlegi Headlines (soronként)</label>
                            <textarea name="headlines" class="form-control" rows="4" placeholder="Headline 1&#10;Headline 2&#10;..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-column">
                        <div class="form-group">
                            <label>Kulcsszavak (soronként)</label>
                            <textarea name="keywords" class="form-control" rows="4" placeholder="kulcsszó 1&#10;kulcsszó 2&#10;..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Jelenlegi teljesítmény</label>
                            <div class="inline-inputs">
                                <div>
                                    <label class="small-label">Kattintás/hó</label>
                                    <input type="number" name="clicks" class="form-control" placeholder="pl. 500">
                                </div>
                                <div>
                                    <label class="small-label">Lead/hó</label>
                                    <input type="number" name="leads" class="form-control" placeholder="pl. 10">
                                </div>
                                <div>
                                    <label class="small-label">Költés/hó</label>
                                    <input type="number" name="spend" class="form-control" placeholder="pl. 150000">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Bid stratégia</label>
                            <select name="bid_strategy" class="form-control">
                                <option value="">-- Nem tudom --</option>
                                <option value="maximize_conversions">Konverzió maximalizálás</option>
                                <option value="target_cpa">Cél CPA</option>
                                <option value="maximize_clicks">Kattintás maximalizálás</option>
                                <option value="manual_cpc">Manuális CPC</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" id="runDiagnosisBtn">
                    🔍 Diagnózis Futtatása
                </button>
            </form>
        </div>
        
        <div id="diagnosisResults"></div>
    </section>

<?php elseif ($tab === 'clients'): ?>
<!-- ==================== KLIENS PORTFÓLIÓ TAB ==================== -->
<?php 
$clients = $clientManager->getClients();
$selectedClientId = $_GET['client'] ?? null;
$selectedClientData = $selectedClientId ? ($clients[$selectedClientId] ?? null) : null;
?>

    <section class="page-section">
        <div class="section-header">
            <h2>🏢 Kliens Portfólió</h2>
            <button class="btn btn-primary" onclick="showModal('newClientModal')">+ Új Ügyfél</button>
        </div>
        
        <?php if (empty($clients)): ?>
        <div class="empty-state">
            <span class="empty-icon">🏢</span>
            <h3>Még nincsenek mentett ügyfelek</h3>
            <p>Adj hozzá ügyfeleket, hogy nyomon követhesd a kampányaikat!</p>
            <button class="btn btn-primary" onclick="showModal('newClientModal')">+ Első ügyfél hozzáadása</button>
        </div>
        <?php else: ?>
        
        <div class="portfolio-layout">
            <!-- Bal: Ügyfél lista -->
            <div class="portfolio-sidebar">
                <div class="sidebar-header">
                    <input type="text" id="clientSearch" class="form-control" placeholder="🔍 Ügyfél keresése..." onkeyup="filterClients()">
                </div>
                <div class="clients-list" id="clientsList">
                    <?php foreach ($clients as $client): 
                        $genCount = count($client['generations'] ?? []);
                        $lastGen = $client['last_generation'] ?? $client['updated_at'] ?? null;
                    ?>
                    <a href="?tab=clients&client=<?= $client['id'] ?>" 
                       class="client-list-item <?= $selectedClientId === $client['id'] ? 'active' : '' ?>"
                       data-name="<?= strtolower($client['name']) ?>">
                        <div class="client-list-icon"><?= $industries[$client['industry']]['icon'] ?? '🏢' ?></div>
                        <div class="client-list-info">
                            <strong><?= htmlspecialchars($client['name']) ?></strong>
                            <small><?= $industries[$client['industry']]['name'] ?? $client['industry'] ?></small>
                        </div>
                        <div class="client-list-meta">
                            <?php if ($genCount > 0): ?>
                            <span class="gen-count"><?= $genCount ?> gen</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Jobb: Részletek -->
            <div class="portfolio-main">
                <?php if ($selectedClientData): ?>
                <!-- Ügyfél fejléc -->
                <div class="client-profile-header">
                    <div class="profile-icon"><?= $industries[$selectedClientData['industry']]['icon'] ?? '🏢' ?></div>
                    <div class="profile-info">
                        <h2><?= htmlspecialchars($selectedClientData['name']) ?></h2>
                        <p class="profile-industry"><?= $industries[$selectedClientData['industry']]['name'] ?? $selectedClientData['industry'] ?></p>
                    </div>
                    <div class="profile-actions">
                        <a href="?tab=wizard&client=<?= $selectedClientData['id'] ?>&industry=<?= $selectedClientData['industry'] ?>" class="btn btn-primary">🚀 Új Kampány</a>
                        <button class="btn btn-secondary" onclick="editClient('<?= $selectedClientData['id'] ?>')">✏️ Szerkesztés</button>
                        <button class="btn btn-danger" onclick="deleteClient('<?= $selectedClientData['id'] ?>')">🗑️</button>
                    </div>
                </div>
                
                <!-- Ügyfél adatok kártyák -->
                <div class="client-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📞</div>
                        <div class="stat-info">
                            <span class="stat-label">Telefon</span>
                            <span class="stat-value"><?= htmlspecialchars($selectedClientData['phone'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📍</div>
                        <div class="stat-info">
                            <span class="stat-label">Terület</span>
                            <span class="stat-value"><?= ucfirst($selectedClientData['area'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🌐</div>
                        <div class="stat-info">
                            <span class="stat-label">Weboldal</span>
                            <span class="stat-value"><?= htmlspecialchars($selectedClientData['website'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <span class="stat-label">Generálások</span>
                            <span class="stat-value"><?= count($selectedClientData['generations'] ?? []) ?> db</span>
                        </div>
                    </div>
                </div>
                
                <!-- Tab navigation -->
                <div class="client-tabs">
                    <button class="client-tab active" onclick="showClientSection('generations')">📝 Generálások</button>
                    <button class="client-tab" onclick="showClientSection('audits')">🔍 Landing Auditok</button>
                    <button class="client-tab" onclick="showClientSection('history')">📜 Változáskövetés</button>
                </div>
                
                <!-- Generálások -->
                <div class="client-section active" id="section-generations">
                    <?php 
                    $generations = $selectedClientData['generations'] ?? [];
                    if (empty($generations)): 
                    ?>
                    <div class="empty-state small">
                        <p>Még nincs generált tartalom ehhez az ügyfélhez.</p>
                        <a href="?tab=wizard&client=<?= $selectedClientData['id'] ?>&industry=<?= $selectedClientData['industry'] ?>" class="btn btn-primary">🚀 Első kampány generálása</a>
                    </div>
                    <?php else: ?>
                    <div class="generations-timeline">
                        <?php foreach ($generations as $index => $gen): ?>
                        <div class="generation-card" data-gen-id="<?= $gen['id'] ?>">
                            <div class="gen-header">
                                <div class="gen-date">
                                    <strong><?= date('Y.m.d H:i', strtotime($gen['created_at'])) ?></strong>
                                    <?php if ($index === 0): ?><span class="badge badge-green">Legutóbbi</span><?php endif; ?>
                                </div>
                                <div class="gen-meta">
                                    <span class="badge"><?= count($gen['headlines'] ?? []) ?> headline</span>
                                    <span class="badge"><?= count($gen['descriptions'] ?? []) ?> desc</span>
                                    <?php if (isset($gen['qs_score'])): ?>
                                    <span class="badge badge-<?= $gen['qs_score'] >= 7 ? 'green' : ($gen['qs_score'] >= 4 ? 'orange' : 'red') ?>">QS: <?= $gen['qs_score'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="gen-settings">
                                <?php $settings = $gen['settings'] ?? []; ?>
                                <span class="setting-tag"><?= ($settings['formality'] ?? 'informal') === 'formal' ? '🎩 Magázás' : '👋 Tegezés' ?></span>
                                <span class="setting-tag"><?= ($settings['voice'] ?? 'team') === 'solo' ? '👤 Egyéni' : '👥 Csapat' ?></span>
                                <span class="setting-tag"><?= match($settings['tone'] ?? 'urgent') { 'urgent' => '🚨 Sürgető', 'trust' => '🏆 Bizalom', 'value' => '💰 Ár-fókusz', default => '🚨 Sürgető' } ?></span>
                            </div>
                            
                            <div class="gen-preview">
                                <div class="preview-section">
                                    <h5>Headlines</h5>
                                    <div class="preview-tags">
                                        <?php foreach (array_slice($gen['headlines'] ?? [], 0, 5) as $h): ?>
                                        <span class="tag"><?= htmlspecialchars(is_array($h) ? $h['text'] : $h) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($gen['headlines'] ?? []) > 5): ?>
                                        <span class="tag tag-more">+<?= count($gen['headlines']) - 5 ?> több</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="gen-actions">
                                <button class="btn btn-sm btn-secondary" onclick="viewGeneration('<?= $gen['id'] ?>')">👁️ Részletek</button>
                                <button class="btn btn-sm btn-secondary" onclick="copyGeneration('<?= $gen['id'] ?>')">📋 Másolás</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteGeneration('<?= $selectedClientId ?>', '<?= $gen['id'] ?>')">🗑️ Törlés</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Landing Auditok -->
                <div class="client-section" id="section-audits">
                    <?php 
                    $audits = $selectedClientData['audits'] ?? [];
                    if (empty($audits)): 
                    ?>
                    <div class="empty-state small">
                        <p>Még nincs landing page audit ehhez az ügyfélhez.</p>
                        <a href="?tab=landing" class="btn btn-secondary">🔍 Landing Audit indítása</a>
                    </div>
                    <?php else: ?>
                    <div class="audits-list">
                        <?php foreach ($audits as $audit): ?>
                        <div class="audit-card">
                            <div class="audit-url"><?= htmlspecialchars($audit['url'] ?? '') ?></div>
                            <div class="audit-date"><?= date('Y.m.d', strtotime($audit['created_at'] ?? 'now')) ?></div>
                            <div class="audit-score">
                                <span class="score-value"><?= $audit['score'] ?? '-' ?></span>/100
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Változáskövetés -->
                <div class="client-section" id="section-history">
                    <?php if (count($generations) < 2): ?>
                    <div class="empty-state small">
                        <p>Legalább 2 generálás szükséges a változások összehasonlításához.</p>
                    </div>
                    <?php else: ?>
                    <div class="change-tracker">
                        <h4>📜 Verzió Történet</h4>
                        <p class="help-text">Kövesd nyomon a hirdetések változásait és azok hatását</p>
                        
                        <div class="version-timeline">
                            <?php 
                            $prevGen = null;
                            foreach ($generations as $index => $gen): 
                                $changes = [];
                                if ($prevGen) {
                                    // Headline változások
                                    $oldHeadlines = array_map(fn($h) => is_array($h) ? $h['text'] : $h, $prevGen['headlines'] ?? []);
                                    $newHeadlines = array_map(fn($h) => is_array($h) ? $h['text'] : $h, $gen['headlines'] ?? []);
                                    $addedH = array_diff($newHeadlines, $oldHeadlines);
                                    $removedH = array_diff($oldHeadlines, $newHeadlines);
                                    if (!empty($addedH)) $changes[] = '+' . count($addedH) . ' új headline';
                                    if (!empty($removedH)) $changes[] = '-' . count($removedH) . ' headline törölve';
                                    
                                    // QS változás
                                    $oldQs = $prevGen['qs_score'] ?? 0;
                                    $newQs = $gen['qs_score'] ?? 0;
                                    if ($newQs != $oldQs) {
                                        $diff = $newQs - $oldQs;
                                        $changes[] = 'QS: ' . ($diff > 0 ? '+' : '') . $diff;
                                    }
                                }
                            ?>
                            <div class="version-item <?= $index === 0 ? 'current' : '' ?>">
                                <div class="version-marker">
                                    <span class="version-dot"></span>
                                    <?php if ($index < count($generations) - 1): ?><span class="version-line"></span><?php endif; ?>
                                </div>
                                <div class="version-content">
                                    <div class="version-header">
                                        <strong>v<?= count($generations) - $index ?></strong>
                                        <span class="version-date"><?= date('Y.m.d H:i', strtotime($gen['created_at'])) ?></span>
                                        <?php if ($index === 0): ?><span class="badge badge-green">Aktuális</span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($changes)): ?>
                                    <div class="version-changes">
                                        <?php foreach ($changes as $change): ?>
                                        <span class="change-tag <?= strpos($change, '+') === 0 ? 'added' : (strpos($change, '-') === 0 ? 'removed' : 'modified') ?>"><?= $change ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php elseif ($index === count($generations) - 1): ?>
                                    <div class="version-changes">
                                        <span class="change-tag">Első verzió</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="version-summary">
                                        <?= count($gen['headlines'] ?? []) ?> headline, 
                                        <?= count($gen['descriptions'] ?? []) ?> description
                                        <?php if (isset($gen['qs_score'])): ?>, QS: <?= $gen['qs_score'] ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                $prevGen = $gen;
                            endforeach; 
                            ?>
                        </div>
                        
                        <!-- Összehasonlító -->
                        <div class="version-compare-tool">
                            <h5>🔄 Verziók Összehasonlítása</h5>
                            <div class="compare-selectors">
                                <select id="compareFrom" class="form-control">
                                    <?php foreach ($generations as $i => $g): ?>
                                    <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>>v<?= count($generations) - $i ?> - <?= date('m.d H:i', strtotime($g['created_at'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span>→</span>
                                <select id="compareTo" class="form-control">
                                    <?php foreach ($generations as $i => $g): ?>
                                    <option value="<?= $i ?>" <?= $i === 0 ? 'selected' : '' ?>>v<?= count($generations) - $i ?> - <?= date('m.d H:i', strtotime($g['created_at'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary" onclick="compareVersions()">Összehasonlítás</button>
                            </div>
                            <div id="compareResults"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php else: ?>
                <!-- Nincs kiválasztott ügyfél -->
                <div class="no-client-selected">
                    <div class="empty-state">
                        <span class="empty-icon">👈</span>
                        <h3>Válassz egy ügyfelet</h3>
                        <p>A bal oldali listából válaszd ki az ügyfelet a részletek megtekintéséhez.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
    
    <!-- Generálás részletek modal -->
    <div class="modal" id="generationModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>📝 Generálás Részletei</h3>
                <button class="modal-close" onclick="hideModal('generationModal')">&times;</button>
            </div>
            <div class="modal-body" id="generationModalContent">
                <!-- JS-sel töltjük -->
            </div>
        </div>
    </div>
    
    <script>
    // Ügyfél keresés
    function filterClients() {
        const search = document.getElementById('clientSearch').value.toLowerCase();
        document.querySelectorAll('.client-list-item').forEach(item => {
            const name = item.dataset.name;
            item.style.display = name.includes(search) ? 'flex' : 'none';
        });
    }
    
    // Section váltás
    function showClientSection(section) {
        document.querySelectorAll('.client-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.client-section').forEach(s => s.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById('section-' + section).classList.add('active');
    }
    
    // Generálás megtekintése
    function viewGeneration(genId) {
        const clientId = '<?= $selectedClientId ?>';
        
        // Generálás keresése a lokális adatokban
        const gen = generations.find(g => g.id === genId);
        
        if (!gen) {
            // AJAX fallback
            fetch(`api.php?action=get_client_generation&client_id=${clientId}&generation_id=${genId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.generation) {
                        renderGenerationModal(data.generation);
                    } else {
                        alert('❌ Generálás nem található');
                    }
                });
            return;
        }
        
        renderGenerationModal(gen);
    }
    
    function renderGenerationModal(gen) {
        showModal('generationModal');
        
        const headlines = (gen.headlines || []).map(h => typeof h === 'object' ? h.text : h);
        const descriptions = (gen.descriptions || []).map(d => typeof d === 'object' ? d.text : d);
        
        let html = `
            <div class="gen-modal-meta">
                <span>📅 ${gen.created_at || 'N/A'}</span>
                <span>🏭 ${gen.industry || 'N/A'}</span>
                ${gen.qs_score ? `<span>⭐ QS: ${gen.qs_score}</span>` : ''}
            </div>
            
            <div class="gen-modal-section">
                <h4>📝 Headlines (${headlines.length})</h4>
                <div class="copy-list compact">
                    ${headlines.map(h => `<div class="copy-item"><span>${h}</span><small>${h.length}/30</small></div>`).join('')}
                </div>
                <button class="btn btn-sm btn-secondary" onclick="copyToClipboard('${headlines.join('\\n')}')">📋 Összes Másolása</button>
            </div>
        `;
        
        if (descriptions.length > 0) {
            html += `
                <div class="gen-modal-section">
                    <h4>📝 Descriptions (${descriptions.length})</h4>
                    <div class="copy-list compact">
                        ${descriptions.map(d => `<div class="copy-item"><span>${d}</span><small>${d.length}/90</small></div>`).join('')}
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="copyToClipboard('${descriptions.join('\\n')}')">📋 Összes Másolása</button>
                </div>
            `;
        }
        
        if (gen.callonly && gen.callonly.length > 0) {
            html += `
                <div class="gen-modal-section">
                    <h4>📞 Call-Only (${gen.callonly.length})</h4>
                    ${gen.callonly.map(c => `
                        <div class="callonly-box compact">
                            <div class="co-name">${c.business || ''}</div>
                            <div class="co-desc">${c.desc1 || ''}</div>
                            <div class="co-desc">${c.desc2 || ''}</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        if (gen.keywords && gen.keywords.length > 0) {
            const keywords = gen.keywords.map(k => typeof k === 'object' ? k.keyword : k);
            html += `
                <div class="gen-modal-section">
                    <h4>🔤 Kulcsszavak (${keywords.length})</h4>
                    <div class="tags-list">
                        ${keywords.map(k => `<span class="tag">${k}</span>`).join('')}
                    </div>
                </div>
            `;
        }
        
        if (gen.settings) {
            html += `
                <div class="gen-modal-section">
                    <h4>⚙️ Beállítások</h4>
                    <div class="settings-tags">
                        ${gen.settings.formality === 'formal' ? '<span class="tag">🎩 Magázó</span>' : '<span class="tag">👋 Tegező</span>'}
                        ${gen.settings.voice === 'team' ? '<span class="tag">👥 Csapat</span>' : '<span class="tag">👤 Egyéni</span>'}
                        ${gen.settings.tone ? `<span class="tag">${gen.settings.tone === 'urgent' ? '🚨 Sürgető' : gen.settings.tone === 'trust' ? '🏆 Bizalom' : '💰 Ár-fókusz'}</span>` : ''}
                    </div>
                </div>
            `;
        }
        
        document.getElementById('generationModalContent').innerHTML = html;
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text.replace(/\\n/g, '\\n')).then(() => alert('✅ Másolva!'));
    }
    
    // Generálás másolása
    function copyGeneration(genId) {
        const gen = generations.find(g => g.id === genId);
        if (!gen) {
            alert('❌ Generálás nem található!');
            return;
        }
        const headlines = (gen.headlines || []).map(h => typeof h === 'object' ? h.text : h);
        const descriptions = (gen.descriptions || []).map(d => typeof d === 'object' ? d.text : d);
        const text = 'HEADLINES:\n' + headlines.join('\n') + '\n\nDESCRIPTIONS:\n' + descriptions.join('\n');
        navigator.clipboard.writeText(text).then(() => alert('✅ Generálás másolva a vágólapra!'));
    }
    
    // Generálás törlése
    async function deleteGeneration(clientId, genId) {
        if (!confirm('Biztosan törlöd ezt a generálást?')) return;
        
        try {
            const form = new FormData();
            form.append('action', 'delete_generation');
            form.append('client_id', clientId);
            form.append('generation_id', genId);
            form.append('csrf_token', window.csrfToken || '');
            
            const resp = await fetch('api.php', { method: 'POST', body: form });
            const data = await resp.json();
            
            if (data.success) {
                // Töröljük a DOM-ból is
                document.querySelector(`[data-gen-id="${genId}"]`)?.remove();
                alert('✅ Generálás törölve!');
            } else {
                alert('❌ Hiba: ' + (data.error || 'Ismeretlen hiba'));
            }
        } catch (e) {
            alert('❌ Hálózati hiba: ' + e.message);
        }
    }
    
    // Verziók összehasonlítása
    const generations = <?= json_encode($selectedClientData['generations'] ?? []) ?>;
    
    function compareVersions() {
        const fromIdx = parseInt(document.getElementById('compareFrom').value);
        const toIdx = parseInt(document.getElementById('compareTo').value);
        
        if (fromIdx === toIdx) {
            alert('Válassz különböző verziókat!');
            return;
        }
        
        const fromGen = generations[fromIdx];
        const toGen = generations[toIdx];
        
        const fromHeadlines = (fromGen.headlines || []).map(h => typeof h === 'object' ? h.text : h);
        const toHeadlines = (toGen.headlines || []).map(h => typeof h === 'object' ? h.text : h);
        
        const added = toHeadlines.filter(h => !fromHeadlines.includes(h));
        const removed = fromHeadlines.filter(h => !toHeadlines.includes(h));
        const unchanged = toHeadlines.filter(h => fromHeadlines.includes(h));
        
        let html = '<div class="compare-results">';
        html += `<div class="compare-summary">
            <span class="badge badge-green">+${added.length} új</span>
            <span class="badge badge-red">-${removed.length} törölve</span>
            <span class="badge">${unchanged.length} változatlan</span>
        </div>`;
        
        if (added.length > 0) {
            html += '<div class="compare-section added"><h5>✅ Új Headlines</h5>';
            added.forEach(h => html += `<div class="compare-item">${h}</div>`);
            html += '</div>';
        }
        
        if (removed.length > 0) {
            html += '<div class="compare-section removed"><h5>❌ Törölt Headlines</h5>';
            removed.forEach(h => html += `<div class="compare-item">${h}</div>`);
            html += '</div>';
        }
        
        html += '</div>';
        document.getElementById('compareResults').innerHTML = html;
    }
    </script>

    <!-- Új ügyfél modal -->
    <div id="newClientModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Új Ügyfél</h3>
                <button class="modal-close" onclick="hideModal('newClientModal')">×</button>
            </div>
            <form method="POST" action="api.php" id="newClientForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="save_client">
                
                <div class="form-group">
                    <label>Cégnév *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Iparág *</label>
                    <select name="industry" class="form-control" required>
                        <?php foreach ($industries as $key => $ind): ?>
                        <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="tel" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Terület</label>
                    <select name="area" class="form-control">
                        <option value="budapest">Budapest</option>
                        <option value="videk">Vidék</option>
                        <option value="orszagos">Országos</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Weboldal</label>
                    <input type="url" name="website" class="form-control">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('newClientModal')">Mégse</button>
                    <button type="submit" class="btn btn-primary">Mentés</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'headlines'): ?>
<!-- ==================== SZÖVEGBANK TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>⭐ Bevált Szövegeim</h2>
            <button class="btn btn-primary" onclick="showModal('newHeadlineModal')">+ Új Szöveg</button>
        </div>
        
        <?php $bank = $clientManager->getHeadlinesBank(); ?>
        
        <div class="tabs-mini">
            <button class="tab-btn active" onclick="showBankTab('headlines')">Headlines (<?= count($bank['headlines']) ?>)</button>
            <button class="tab-btn" onclick="showBankTab('descriptions')">Descriptions (<?= count($bank['descriptions']) ?>)</button>
        </div>
        
        <div id="headlines-tab" class="bank-tab">
            <?php if (empty($bank['headlines'])): ?>
            <div class="empty-state small">
                <p>Még nincsenek mentett headline-ok</p>
                <button class="btn btn-sm btn-primary" onclick="showModal('newHeadlineModal')">+ Első hozzáadása</button>
            </div>
            <?php else: ?>
            <div class="headlines-list">
                <?php foreach ($bank['headlines'] as $h): ?>
                <div class="headline-item">
                    <div class="headline-text"><?= htmlspecialchars($h['text']) ?></div>
                    <div class="headline-meta">
                        <span class="headline-rating"><?= str_repeat('⭐', $h['rating'] ?? 3) ?></span>
                        <span class="headline-chars"><?= mb_strlen($h['text']) ?>/30</span>
                        <button class="btn-icon" onclick="deleteHeadline('<?= $h['id'] ?>')">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div id="descriptions-tab" class="bank-tab hidden">
            <?php if (empty($bank['descriptions'])): ?>
            <div class="empty-state small">
                <p>Még nincsenek mentett description-ök</p>
            </div>
            <?php else: ?>
            <div class="headlines-list">
                <?php foreach ($bank['descriptions'] as $d): ?>
                <div class="headline-item">
                    <div class="headline-text"><?= htmlspecialchars($d['text']) ?></div>
                    <div class="headline-meta">
                        <span class="headline-rating"><?= str_repeat('⭐', $d['rating'] ?? 3) ?></span>
                        <span class="headline-chars"><?= mb_strlen($d['text']) ?>/90</span>
                        <button class="btn-icon" onclick="deleteDescription('<?= $d['id'] ?>')">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Extra Headlines Preview -->
        <div class="card" style="margin-top: 24px;">
            <h3 class="card-title">🔥 Extra Figyelemfelkeltő Sablonok</h3>
            <p class="help-text">Ezek automatikusan bekerülnek a generált szövegekbe</p>
            
            <?php 
            $extraHeadlines = $GLOBALS['extra_headlines'] ?? [];
            foreach ($industries as $indKey => $ind):
                if (!isset($extraHeadlines[$indKey])) continue;
            ?>
            <div class="extra-section">
                <h4><?= $ind['icon'] ?> <?= $ind['name'] ?></h4>
                <div class="extra-tags">
                    <?php foreach (array_slice($extraHeadlines[$indKey]['aggressive'] ?? [], 0, 5) as $h): ?>
                    <span class="tag tag-orange"><?= htmlspecialchars($h) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Új headline modal -->
    <div id="newHeadlineModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Új Bevált Szöveg</h3>
                <button class="modal-close" onclick="hideModal('newHeadlineModal')">×</button>
            </div>
            <form method="POST" action="api.php" id="newHeadlineForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="save_headline">
                
                <div class="form-group">
                    <label>Típus</label>
                    <select name="type" class="form-control">
                        <option value="headline">Headline (max 30 kar)</option>
                        <option value="description">Description (max 90 kar)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Szöveg *</label>
                    <input type="text" name="text" class="form-control" required maxlength="90">
                </div>
                <div class="form-group">
                    <label>Értékelés</label>
                    <select name="rating" class="form-control">
                        <option value="5">⭐⭐⭐⭐⭐ Kiváló</option>
                        <option value="4">⭐⭐⭐⭐ Jó</option>
                        <option value="3" selected>⭐⭐⭐ Átlagos</option>
                        <option value="2">⭐⭐ Gyenge</option>
                        <option value="1">⭐ Tesztelendő</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Iparág (opcionális)</label>
                    <select name="industry" class="form-control">
                        <option value="">Bármelyik</option>
                        <?php foreach ($industries as $key => $ind): ?>
                        <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('newHeadlineModal')">Mégse</button>
                    <button type="submit" class="btn btn-primary">Mentés</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'keywords'): ?>
<!-- ==================== KULCSSZAVAK TAB ==================== -->

    <?php 
    $kwManager = new ClientManager();
    $kwBank = $kwManager->getKeywordsBank();
    ?>

    <section class="page-section">
        <div class="section-header">
            <h2>🔤 Kulcsszó Kezelés</h2>
        </div>
        
        <div class="keywords-layout">
            <!-- Bal oldal: Pozitív kulcsszavak -->
            <div class="keyword-column">
                <div class="card">
                    <h3 class="card-title">✅ Működő Kulcsszavak</h3>
                    <p class="help-text">Add hozzá a jól teljesítő kulcsszavaidat. Ezeket használjuk a generálásnál.</p>
                    
                    <form method="POST" action="api.php" class="keyword-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="save_keywords">
                        <input type="hidden" name="type" value="positive">
                        
                        <div class="form-group">
                            <label>Kulcsszavak beillesztése (soronként egy)</label>
                            <textarea name="keywords" class="form-control" rows="8" placeholder="duguláselhárítás budapest&#10;wc dugulás&#10;lefolyó tisztítás&#10;csatorna dugulás"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Iparág</label>
                            <select name="industry" class="form-control">
                                <option value="">-- Általános --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 Mentés</button>
                    </form>
                </div>
                
                <!-- Mentett pozitív kulcsszavak -->
                <?php if (!empty($kwBank['positive'])): ?>
                <div class="card">
                    <h3 class="card-title">📋 Mentett Kulcsszavak (<?= count($kwBank['positive']) ?>)</h3>
                    <div class="keywords-saved">
                        <?php 
                        $grouped = [];
                        foreach ($kwBank['positive'] as $kw) {
                            $ind = $kw['industry'] ?: 'general';
                            $grouped[$ind][] = $kw;
                        }
                        foreach ($grouped as $indKey => $kws): 
                            $indName = $indKey === 'general' ? 'Általános' : ($industries[$indKey]['name'] ?? $indKey);
                        ?>
                        <div class="keyword-group">
                            <h4><?= $indKey !== 'general' ? ($industries[$indKey]['icon'] ?? '') : '📁' ?> <?= $indName ?></h4>
                            <div class="keyword-tags">
                                <?php foreach ($kws as $kw): ?>
                                <span class="tag tag-green" title="<?= htmlspecialchars($kw['keyword']) ?>">
                                    <?= htmlspecialchars($kw['keyword']) ?>
                                    <button type="button" class="tag-remove" onclick="deleteKeyword('<?= $kw['id'] ?>', 'positive')">×</button>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="copyAllKeywords('positive')" style="margin-top:12px">📋 Mind másolása</button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Jobb oldal: Negatív kulcsszavak -->
            <div class="keyword-column">
                <div class="card card-danger">
                    <h3 class="card-title">🚫 Negatív Kulcsszavak</h3>
                    <p class="help-text">Ezeket mindig kizárjuk a kampányokból. AI elemzi és figyelmeztet a hibákra.</p>
                    
                    <form method="POST" action="api.php" class="keyword-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="save_keywords">
                        <input type="hidden" name="type" value="negative">
                        
                        <div class="form-group">
                            <label>Negatív kulcsszavak beillesztése</label>
                            <textarea name="keywords" class="form-control" rows="8" placeholder="ingyen&#10;állás&#10;munka&#10;házilag&#10;youtube"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Iparág</label>
                            <select name="industry" class="form-control">
                                <option value="">-- Általános (minden kampány) --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">🚫 Mentés</button>
                    </form>
                </div>
                
                <!-- Mentett negatív kulcsszavak -->
                <?php if (!empty($kwBank['negative'])): ?>
                <div class="card">
                    <h3 class="card-title">🚫 Mentett Negatívok (<?= count($kwBank['negative']) ?>)</h3>
                    <div class="keywords-saved">
                        <?php 
                        $grouped = [];
                        foreach ($kwBank['negative'] as $kw) {
                            $ind = $kw['industry'] ?: 'general';
                            $grouped[$ind][] = $kw;
                        }
                        foreach ($grouped as $indKey => $kws): 
                            $indName = $indKey === 'general' ? 'Általános' : ($industries[$indKey]['name'] ?? $indKey);
                        ?>
                        <div class="keyword-group">
                            <h4><?= $indKey !== 'general' ? ($industries[$indKey]['icon'] ?? '') : '🌐' ?> <?= $indName ?></h4>
                            <div class="keyword-tags">
                                <?php foreach ($kws as $kw): ?>
                                <span class="tag tag-red">
                                    <?= htmlspecialchars($kw['keyword']) ?>
                                    <button type="button" class="tag-remove" onclick="deleteKeyword('<?= $kw['id'] ?>', 'negative')">×</button>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="copyAllKeywords('negative')" style="margin-top:12px">📋 Mind másolása</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Kulcsszó Elemző -->
        <div class="card" style="margin-top: 24px;">
            <h3 class="card-title">🔍 Kulcsszó Elemző (AI)</h3>
            <p class="help-text">Illeszd be a kulcsszavaidat és az AI elemzi, javaslatokat ad, figyelmeztet a problémákra.</p>
            
            <form method="POST" action="api.php" id="analyzeKeywordsForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="analyze_keywords">
                
                <div class="form-columns">
                    <div class="form-group">
                        <label>Kulcsszavak elemzésre</label>
                        <textarea name="keywords" class="form-control" rows="6" placeholder="Illeszd be a kulcsszavaidat..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Iparág (pontosabb elemzéshez)</label>
                        <select name="industry" class="form-control">
                            <option value="">-- Válassz --</option>
                            <?php foreach ($industries as $key => $ind): ?>
                            <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="margin-top: 16px;">
                            <label class="checkbox-label">
                                <input type="checkbox" name="check_negatives" value="1" checked>
                                <span>Ellenőrizze a negatívokkal való ütközést</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="suggest_variations" value="1" checked>
                                <span>Javasoljon variációkat</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="find_problems" value="1" checked>
                                <span>Keressen problémás kulcsszavakat</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="analyzeKwBtn">🔍 Elemzés Indítása</button>
            </form>
            
            <div id="keywordAnalysisResults"></div>
        </div>
        
        <!-- Kulcsszó Klaszterezés -->
        <div class="card" style="margin-top: 24px;">
            <h3 class="card-title">📊 Kulcsszó Klaszterezés (Ad Group Javaslat)</h3>
            <p class="help-text">Illeszd be a kulcsszavaidat és az AI logikai csoportokba (Ad Group-okba) rendezi őket</p>
            
            <form method="POST" action="api.php" id="clusterKeywordsForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="cluster_keywords">
                
                <div class="form-columns">
                    <div class="form-group">
                        <label>Kulcsszavak klaszterezéshez (soronként egy)</label>
                        <textarea name="keywords" class="form-control" rows="10" placeholder="duguláselhárítás budapest&#10;wc dugulás ár&#10;sürgős duguláselhárító&#10;olcsó vízszerelő&#10;éjszakai vízszerelés&#10;csőtörés javítás&#10;..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Beállítások</label>
                        <div class="form-group">
                            <label>Iparág</label>
                            <select name="industry" class="form-control">
                                <option value="">-- Válassz (pontosabb csoportok) --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Csoportok száma (kb.)</label>
                            <select name="num_groups" class="form-control">
                                <option value="auto">Automatikus (AI dönt)</option>
                                <option value="3">3-5 csoport</option>
                                <option value="5">5-8 csoport</option>
                                <option value="10">8-12 csoport</option>
                            </select>
                        </div>
                        <label class="checkbox-label">
                            <input type="checkbox" name="suggest_names" value="1" checked>
                            <span>Javasoljon Ad Group neveket</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="suggest_headlines" value="1" checked>
                            <span>Javasoljon headline-t csoportonként</span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="clusterKwBtn">📊 Klaszterezés</button>
            </form>
            
            <div id="clusterResults"></div>
        </div>
        
        <!-- Univerzális Negatív Listák -->
        <div class="card" style="margin-top: 24px;">
            <h3 class="card-title">📦 Univerzális Negatív Listák</h3>
            <p class="help-text">Előre összeállított listák - egy kattintással hozzáadhatod</p>
            
            <?php 
            $universalNegatives = [
                'diy' => ['name' => '🔧 DIY / Csináld magad', 'keywords' => ['házilag', 'otthon', 'magam', 'hogyan', 'videó', 'youtube', 'tutorial', 'útmutató', 'tippek', 'trükkök', 'lépésről lépésre']],
                'free' => ['name' => '🆓 Ingyen / Olcsó', 'keywords' => ['ingyen', 'ingyenes', 'olcsó', 'legolcsóbb', 'akció', 'kedvezmény', 'használt', 'bontott', 'vatera', 'jófogás', 'marketplace']],
                'job' => ['name' => '💼 Állás / Karrier', 'keywords' => ['állás', 'munka', 'fizetés', 'tanfolyam', 'képzés', 'oktatás', 'gyakornok', 'diákmunka', 'cv', 'önéletrajz']],
                'info' => ['name' => '📚 Információ kereső', 'keywords' => ['wiki', 'wikipédia', 'mi az', 'jelentése', 'fórum', 'vélemény', 'tapasztalat', 'blog', 'cikk', 'teszt']]
            ];
            ?>
            
            <div class="universal-lists">
                <?php foreach ($universalNegatives as $key => $list): ?>
                <div class="universal-list-item">
                    <div class="list-header">
                        <span class="list-name"><?= $list['name'] ?></span>
                        <span class="list-count"><?= count($list['keywords']) ?> szó</span>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addUniversalList('<?= $key ?>')">+ Hozzáadás</button>
                    </div>
                    <div class="list-preview">
                        <?php foreach (array_slice($list['keywords'], 0, 6) as $kw): ?>
                        <span class="tag tag-red small"><?= $kw ?></span>
                        <?php endforeach; ?>
                        <?php if (count($list['keywords']) > 6): ?>
                        <span class="tag small">+<?= count($list['keywords']) - 6 ?> más</span>
                        <?php endif; ?>
                    </div>
                    <textarea class="hidden" id="universal-<?= $key ?>"><?= implode("\n", $list['keywords']) ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<?php elseif ($tab === 'competitors'): ?>
<!-- ==================== VERSENYTÁRS FIGYELŐ TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>🔍 Versenytárs Figyelő</h2>
        </div>
        
        <div class="card">
            <h3 class="card-title">🎯 Hirdetés Elemzés</h3>
            <p class="help-text">Add meg a kulcsszót és elemezzük a versenytársak hirdetéseit</p>
            
            <form method="POST" action="api.php" id="competitorForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="analyze_competitors">
                
                <div class="form-columns">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Keresési kulcsszó *</label>
                            <input type="text" name="keyword" class="form-control" placeholder="pl. duguláselhárítás budapest" required>
                        </div>
                        <div class="form-group">
                            <label>Iparág (pontosabb elemzéshez)</label>
                            <select name="industry" class="form-control">
                                <option value="">-- Válassz --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Saját hirdetésed (összehasonlításhoz)</label>
                            <textarea name="own_ad" class="form-control" rows="4" placeholder="Headline 1&#10;Headline 2&#10;Description..."></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="competitorBtn">🔍 Versenytársak Elemzése</button>
                
                <?php if (empty(SERPAPI_KEY)): ?>
                <p class="help-text" style="margin-top:12px">⚠️ SerpApi kulcs nincs beállítva - kézi bevitel módban működik</p>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Kézi bevitel ha nincs API -->
        <div class="card" style="margin-top:20px">
            <h3 class="card-title">📋 Kézi Hirdetés Elemzés</h3>
            <p class="help-text">Másold be a versenytársak hirdetéseit a Google keresőből</p>
            
            <form method="POST" action="api.php" id="manualCompetitorForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="analyze_competitor_manual">
                
                <div class="form-group">
                    <label>Versenytárs hirdetések (illeszd be)</label>
                    <textarea name="competitor_ads" class="form-control" rows="8" placeholder="Hirdetés 1:
Headline | Headline 2
Description szöveg...

Hirdetés 2:
..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Saját hirdetésed</label>
                    <textarea name="own_ad" class="form-control" rows="4" placeholder="A te hirdetésed szövege..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Iparág</label>
                    <select name="industry" class="form-control">
                        <option value="">-- Válassz --</option>
                        <?php foreach ($industries as $key => $ind): ?>
                        <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">🧠 AI Elemzés</button>
            </form>
        </div>
        
        <div id="competitorResults"></div>
    </section>

<?php elseif ($tab === 'landing'): ?>
<!-- ==================== LANDING PAGE TAB ==================== -->

    <?php 
    require_once __DIR__ . '/includes/VisionAnalyzer.php';
    $visionAnalyzer = new VisionAnalyzer();
    $visionConfigured = $visionAnalyzer->isConfigured();
    ?>

    <section class="page-section">
        <div class="section-header">
            <h2>🌐 Landing Page Elemző & Szövegíró</h2>
        </div>
        
        <!-- AI Vision elemzés - ÚJ! -->
        <div class="card card-premium">
            <div class="premium-badge">🤖 AI Vision</div>
            <h3 class="card-title">👁️ Vizuális UX Elemzés</h3>
            <p class="help-text">Az AI <strong>látja</strong> az oldaladat és UX/UI szempontból elemzi - észreveszi amit a kód nem tud!</p>
            
            <?php if (!$visionConfigured): ?>
            <div class="alert alert-info">
                ℹ️ Screenshot API nincs konfigurálva - az elemzés az oldal tartalmából dolgozik.
                <br><small>Opcionális: <a href="https://screenshotmachine.com" target="_blank">screenshotmachine.com</a> - vizuális screenshot elemzéshez</small>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="api.php" id="visionForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="analyze_landing_vision">
                
                <div class="form-columns">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Landing Page URL *</label>
                            <input type="url" name="url" class="form-control" placeholder="https://..." required>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Iparág (pontosabb elemzéshez)</label>
                            <select name="industry" class="form-control">
                                <option value="">-- Válassz --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="vision-features">
                    <span class="feature-tag">🔍 Tartalom elemzés</span>
                    <span class="feature-tag">🎯 CTA audit</span>
                    <span class="feature-tag">📱 Mobil becslés</span>
                    <span class="feature-tag">📞 Kontakt check</span>
                    <span class="feature-tag">🏆 Trust jelek</span>
                </div>
                
                <button type="submit" class="btn btn-premium btn-lg" id="visionBtn">
                    🔍 AI UX Elemzés
                </button>
            </form>
            
            <div id="visionResults"></div>
        </div>
        
        <div class="divider-text"><span>vagy</span></div>
        
        <div class="card">
            <h3 class="card-title">🔍 Technikai + CRO Audit (kód alapú)</h3>
            <p class="help-text">Elemezzük a landing page-et technikailag ÉS tartalmilag, konkrét átírási javaslatokkal</p>
            
            <form method="POST" action="api.php" id="landingForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="analyze_landing_full">
                
                <div class="form-columns">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Landing Page URL *</label>
                            <input type="url" name="url" class="form-control" placeholder="https://..." required>
                        </div>
                        <div class="form-group">
                            <label>Fő kulcsszó</label>
                            <input type="text" name="keyword" class="form-control" placeholder="pl. duguláselhárítás budapest">
                        </div>
                        <div class="form-group">
                            <label>Iparág</label>
                            <select name="industry" class="form-control">
                                <option value="">-- Válassz --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Mit elemezzünk?</label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="check_technical" value="1" checked>
                                <span>⚙️ Technikai (H1, meta, sebesség, mobil)</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="check_cro" value="1" checked>
                                <span>📈 CRO (szövegek, CTA-k, trust elemek)</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="check_seo" value="1" checked>
                                <span>🔍 SEO (kulcsszó sűrűség, struktúra)</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="rewrite_content" value="1" checked>
                                <span>✍️ Szöveg átírási javaslatok</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" id="landingBtn">🔍 Technikai Elemzés</button>
            </form>
        </div>
        
        <div id="landingResults"></div>
    </section>

<?php elseif ($tab === 'pmax'): ?>
<!-- ==================== PMAX TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>📦 Performance Max Asset Generátor</h2>
        </div>
        
        <div class="alert alert-info">
            ℹ️ A PMax kampányokhoz szöveg ÉS kép assetek kellenek. Mi generáljuk a szövegeket, a képekhez javaslatokat adunk.
        </div>
        
        <div class="card">
            <h3 class="card-title">🎯 Asset Group Generálás</h3>
            
            <form method="POST" action="api.php" id="pmaxForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="generate_pmax">
                
                <div class="form-columns">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Cégnév *</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Iparág *</label>
                            <select name="industry" class="form-control" required>
                                <option value="">-- Válassz --</option>
                                <?php foreach ($industries as $key => $ind): ?>
                                <option value="<?= $key ?>"><?= $ind['icon'] ?> <?= $ind['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Weboldal URL</label>
                            <input type="url" name="website" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Fő szolgáltatások/termékek</label>
                            <textarea name="services" class="form-control" rows="3" placeholder="pl. duguláselhárítás, csőtörés javítás..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>USP-k (egyedi előnyök)</label>
                            <textarea name="usps" class="form-control" rows="3" placeholder="pl. 24 órás, 30 perc kiszállás..."></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" id="pmaxBtn">📦 PMax Assetek Generálása</button>
            </form>
        </div>
        
        <div class="card" style="margin-top:20px">
            <h3 class="card-title">📋 PMax Asset Követelmények</h3>
            <div class="pmax-requirements">
                <div class="req-section">
                    <h4>📝 Szöveg Assetek (mi generáljuk)</h4>
                    <ul>
                        <li><strong>Headlines:</strong> 3-15 db, max 30 karakter</li>
                        <li><strong>Long Headlines:</strong> 1-5 db, max 90 karakter</li>
                        <li><strong>Descriptions:</strong> 2-5 db, max 90 karakter</li>
                        <li><strong>Business Name:</strong> max 25 karakter</li>
                    </ul>
                </div>
                <div class="req-section">
                    <h4>🖼️ Kép Assetek (javaslatokat adunk)</h4>
                    <ul>
                        <li><strong>Landscape:</strong> 1200x628 (1.91:1) - min 3 db</li>
                        <li><strong>Square:</strong> 1200x1200 (1:1) - min 3 db</li>
                        <li><strong>Portrait:</strong> 960x1200 (4:5) - opcionális</li>
                        <li><strong>Logo:</strong> 1200x1200 (négyzet) + 1200x300 (landscape)</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div id="pmaxResults"></div>
    </section>

<?php elseif ($tab === 'industries'): ?>
<!-- ==================== IPARÁGAK TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>🏭 Iparág Kezelés</h2>
            <button class="btn btn-primary" onclick="showModal('newIndustryModal')">+ Új Iparág</button>
        </div>
        
        <div class="industries-grid">
            <?php foreach ($industries as $key => $ind): ?>
            <div class="industry-manage-card">
                <div class="ind-header">
                    <span class="ind-icon"><?= $ind['icon'] ?></span>
                    <div class="ind-info">
                        <h3><?= htmlspecialchars($ind['name']) ?></h3>
                        <span class="ind-key"><?= $key ?></span>
                    </div>
                </div>
                <div class="ind-stats">
                    <span>USP-k: <?= count($ind['usps'] ?? []) ?></span>
                    <span>CPA: <?= $ind['benchmarks']['cpa_budapest'] ?? 'n/a' ?></span>
                </div>
                <div class="ind-actions">
                    <button class="btn btn-sm btn-secondary" onclick="viewIndustry('<?= $key ?>')">👁️ Részletek</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Egyéni iparágak -->
        <?php 
        $customIndustries = [];
        $customFile = __DIR__ . '/data/custom_industries.json';
        if (file_exists($customFile)) {
            $customIndustries = json_decode(file_get_contents($customFile), true) ?: [];
        }
        ?>
        
        <?php if (!empty($customIndustries)): ?>
        <h3 style="margin-top:30px">📁 Egyéni Iparágak</h3>
        <div class="industries-grid">
            <?php foreach ($customIndustries as $key => $ind): ?>
            <div class="industry-manage-card custom">
                <div class="ind-header">
                    <span class="ind-icon"><?= $ind['icon'] ?? '🏢' ?></span>
                    <div class="ind-info">
                        <h3><?= htmlspecialchars($ind['name']) ?></h3>
                        <span class="ind-key custom-badge">egyéni</span>
                    </div>
                </div>
                <div class="ind-stats">
                    <span>USP-k: <?= count($ind['usps'] ?? []) ?></span>
                </div>
                <div class="ind-actions">
                    <button class="btn btn-sm btn-secondary" onclick="viewIndustry('<?= $key ?>')">👁️</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteIndustry('<?= $key ?>')">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Új iparág modal -->
    <div id="newIndustryModal" class="modal hidden">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>🏭 Új Iparág Létrehozása (AI)</h3>
                <button class="modal-close" onclick="hideModal('newIndustryModal')">×</button>
            </div>
            <form method="POST" action="api.php" id="newIndustryForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="generate_industry">
                
                <div class="form-group">
                    <label>Iparág neve *</label>
                    <input type="text" name="name" class="form-control" placeholder="pl. Autószerelő, Fodrász, Ügyvéd..." required>
                </div>
                
                <div class="form-group">
                    <label>Rövid leírás (opcionális)</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Milyen szolgáltatásokat tartalmaz..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Emoji ikon</label>
                    <input type="text" name="icon" class="form-control" placeholder="🔧" maxlength="4" style="width:80px">
                </div>
                
                <p class="help-text">Az AI automatikusan generálja: USP-ket, Benchmarkokat, Negatív kulcsszavakat, Headline sablonokat</p>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('newIndustryModal')">Mégse</button>
                    <button type="submit" class="btn btn-primary" id="generateIndustryBtn">🧠 Generálás AI-val</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'gads'): ?>
<!-- ==================== GOOGLE ADS FIÓKOK TAB ==================== -->
<?php
require_once __DIR__ . '/includes/GoogleAdsManager.php';
$gadsManager = new GoogleAdsManager();
$gadsConfigured = $gadsManager->isConfigured();

// Cached fiókok betöltése
$cachedAccounts = [];
$cachedSync = null;
$cacheFile = __DIR__ . '/data/gads_accounts_cache.json';
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    $cachedAccounts = $cache['accounts'] ?? [];
    $cachedSync = $cache['synced_at'] ?? null;
}
?>

    <section class="page-section">
        <div class="section-header">
            <h2>📊 Google Ads Fiókok</h2>
            <p class="section-subtitle">Húzd be a kampányaidat, hirdetéseidet és kulcsszavaidat a Google Ads-ból</p>
        </div>
        
        <?php if (!$gadsConfigured): ?>
        <div class="card card-warning">
            <h3 class="card-title">⚠️ Google Ads API Konfiguráció Szükséges</h3>
            <p>A Google Ads integráció használatához állítsd be az alábbi értékeket a <code>config.php</code> fájlban:</p>
            <div class="code-block">
define('GOOGLE_ADS_DEVELOPER_TOKEN', 'xxx');
define('GOOGLE_ADS_CLIENT_ID', 'xxx.apps.googleusercontent.com');
define('GOOGLE_ADS_CLIENT_SECRET', 'xxx');
define('GOOGLE_ADS_REFRESH_TOKEN', 'xxx');
define('GOOGLE_ADS_LOGIN_CUSTOMER_ID', 'xxx'); // MCC fiók ID (opcionális)
            </div>
            <p style="margin-top:16px;">
                <a href="https://developers.google.com/google-ads/api/docs/first-call/overview" target="_blank" class="btn btn-secondary">📖 Google Ads API Dokumentáció</a>
            </p>
        </div>
        <?php else: ?>
        
        <div class="gads-dashboard">
            <!-- Fiókok Panel -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏢 Elérhető Fiókok</h3>
                    <button class="btn btn-primary" id="syncAccountsBtn" onclick="syncGadsAccounts()">
                        🔄 Fiókok Szinkronizálása
                    </button>
                </div>
                
                <?php if ($cachedSync): ?>
                <p class="sync-info">Utolsó szinkronizálás: <?= $cachedSync ?></p>
                <?php endif; ?>
                
                <div id="accountsList">
                    <?php if (empty($cachedAccounts)): ?>
                    <p class="help-text">Kattints a "Fiókok Szinkronizálása" gombra a fiókok betöltéséhez.</p>
                    <?php else: ?>
                    <div class="accounts-grid">
                        <?php foreach ($cachedAccounts as $acc): ?>
                        <div class="account-card" data-customer-id="<?= $acc['id'] ?>">
                            <div class="account-info">
                                <h4><?= htmlspecialchars($acc['name']) ?></h4>
                                <span class="account-id"><?= $acc['id'] ?></span>
                                <span class="account-currency"><?= $acc['currency'] ?? 'HUF' ?></span>
                            </div>
                            <div class="account-actions">
                                <button class="btn btn-sm btn-secondary" onclick="syncFullAccount('<?= $acc['id'] ?>')">
                                    📥 Teljes Sync
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="viewAccountDetails('<?= $acc['id'] ?>')">
                                    👁️ Részletek
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sync Eredmények -->
            <div class="card" id="syncResultsCard" style="display:none;">
                <div class="card-header">
                    <h3 class="card-title">📋 Szinkronizált Adatok</h3>
                    <div class="sync-stats" id="syncStats"></div>
                </div>
                <div id="syncResults"></div>
            </div>
            
            <!-- Kampányok -->
            <div class="card" id="campaignsCard" style="display:none;">
                <h3 class="card-title">📁 Kampányok</h3>
                <div id="campaignsList"></div>
            </div>
            
            <!-- Hirdetések -->
            <div class="card" id="adsCard" style="display:none;">
                <h3 class="card-title">📝 Hirdetések</h3>
                <div id="adsList"></div>
            </div>
            
            <!-- Kulcsszavak -->
            <div class="card" id="keywordsCard" style="display:none;">
                <h3 class="card-title">🔤 Kulcsszavak</h3>
                <div id="keywordsList"></div>
            </div>
        </div>
        
        <?php endif; ?>
    </section>
    
    <script>
    async function syncGadsAccounts() {
        const btn = document.getElementById('syncAccountsBtn');
        btn.disabled = true;
        btn.innerHTML = '⏳ Szinkronizálás...';
        
        try {
            const resp = await fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=gads_sync_accounts&csrf_token=<?= Security::generateCsrfToken() ?>'
            });
            const data = await resp.json();
            
            if (data.success) {
                location.reload();
            } else {
                alert('Hiba: ' + (data.error || 'Ismeretlen hiba'));
            }
        } catch (err) {
            alert('Hálózati hiba: ' + err.message);
        }
        
        btn.disabled = false;
        btn.innerHTML = '🔄 Fiókok Szinkronizálása';
    }
    
    async function syncFullAccount(customerId) {
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '⏳...';
        
        try {
            const resp = await fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=gads_sync_full&customer_id=${customerId}&with_metrics=1&csrf_token=<?= Security::generateCsrfToken() ?>`
            });
            const data = await resp.json();
            
            if (data.success) {
                showSyncResults(data);
            } else {
                alert('Hiba: ' + (data.error || 'Ismeretlen hiba'));
            }
        } catch (err) {
            alert('Hálózati hiba: ' + err.message);
        }
        
        btn.disabled = false;
        btn.innerHTML = '📥 Teljes Sync';
    }
    
    function showSyncResults(data) {
        document.getElementById('syncResultsCard').style.display = 'block';
        document.getElementById('campaignsCard').style.display = 'block';
        document.getElementById('adsCard').style.display = 'block';
        document.getElementById('keywordsCard').style.display = 'block';
        
        // Stats
        document.getElementById('syncStats').innerHTML = `
            <span class="stat-badge">📁 ${data.summary.campaigns} kampány</span>
            <span class="stat-badge">📂 ${data.summary.ad_groups} ad group</span>
            <span class="stat-badge">📝 ${data.summary.ads} hirdetés</span>
            <span class="stat-badge">🔤 ${data.summary.keywords} kulcsszó</span>
        `;
        
        // Kampányok
        let campaignsHtml = '<div class="data-table"><table><thead><tr><th>Kampány</th><th>Típus</th><th>Státusz</th><th>Napi Büdzsé</th><th>Kattintás</th><th>Költés</th></tr></thead><tbody>';
        (data.data.campaigns || []).forEach(c => {
            const m = c.metrics || {};
            campaignsHtml += `<tr>
                <td><strong>${c.name}</strong></td>
                <td><span class="badge">${c.type}</span></td>
                <td><span class="status-${c.status.toLowerCase()}">${c.status}</span></td>
                <td>${c.daily_budget.toLocaleString()} Ft</td>
                <td>${(m.clicks || 0).toLocaleString()}</td>
                <td>${(m.cost || 0).toLocaleString()} Ft</td>
            </tr>`;
        });
        campaignsHtml += '</tbody></table></div>';
        document.getElementById('campaignsList').innerHTML = campaignsHtml;
        
        // Hirdetések
        let adsHtml = '<div class="ads-list">';
        (data.data.ads || []).slice(0, 20).forEach(ad => {
            const headlines = (ad.headlines || []).slice(0, 5).join(' | ');
            adsHtml += `<div class="ad-preview">
                <div class="ad-meta">${ad.campaign_name} > ${ad.ad_group_name}</div>
                <div class="ad-type"><span class="badge badge-${ad.type === 'RESPONSIVE_SEARCH_AD' ? 'blue' : 'gray'}">${ad.type}</span></div>
                <div class="ad-headlines">${headlines}</div>
                <div class="ad-url">${(ad.final_urls || [])[0] || ''}</div>
            </div>`;
        });
        adsHtml += '</div>';
        document.getElementById('adsList').innerHTML = adsHtml;
        
        // Kulcsszavak
        let kwHtml = '<div class="data-table"><table><thead><tr><th>Kulcsszó</th><th>Match</th><th>QS</th><th>Katt.</th><th>CTR</th><th>CPC</th></tr></thead><tbody>';
        (data.data.keywords || []).slice(0, 50).forEach(kw => {
            const m = kw.metrics || {};
            kwHtml += `<tr>
                <td>${kw.text}</td>
                <td><span class="badge badge-sm">${kw.match_type}</span></td>
                <td>${kw.quality_score || '-'}</td>
                <td>${(m.clicks || 0).toLocaleString()}</td>
                <td>${m.ctr || 0}%</td>
                <td>${(m.avg_cpc || 0).toLocaleString()} Ft</td>
            </tr>`;
        });
        kwHtml += '</tbody></table></div>';
        document.getElementById('keywordsList').innerHTML = kwHtml;
        
        // Scroll to results
        document.getElementById('syncResultsCard').scrollIntoView({behavior: 'smooth'});
    }
    
    function viewAccountDetails(customerId) {
        syncFullAccount(customerId);
    }
    </script>

<?php elseif ($tab === 'strategies'): ?>
<!-- ==================== STRATÉGIÁK TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>🧠 Stratégiai Javaslatok</h2>
        </div>
        
        <div class="strategies-grid">
            <?php foreach ($GLOBALS['strategies'] as $key => $strategy): ?>
            <div class="strategy-card">
                <div class="strategy-header">
                    <h3><?= $strategy['name'] ?></h3>
                    <div class="strategy-badges">
                        <span class="badge badge-<?= $strategy['difficulty'] ?>"><?= ucfirst($strategy['difficulty']) ?></span>
                        <span class="badge badge-budget-<?= $strategy['budget_impact'] ?>">💰 <?= ucfirst($strategy['budget_impact']) ?></span>
                    </div>
                </div>
                <p class="strategy-desc"><?= $strategy['description'] ?></p>
                
                <div class="strategy-settings">
                    <h4>Beállítások:</h4>
                    <ul>
                        <?php foreach ($strategy['settings'] as $label => $value): ?>
                        <li><strong><?= $label ?>:</strong> <?= $value ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="strategy-tips">
                    <h4>💡 Tippek:</h4>
                    <ul>
                        <?php foreach ($strategy['tips'] as $tip): ?>
                        <li><?= $tip ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="strategy-when">
                    <strong>Mikor használd:</strong> <?= $strategy['when_to_use'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

<?php endif; ?>

        </div>
    </main>

    <!-- Mentés Ügyfélhez Modal - GLOBÁLISAN ELÉRHETŐ -->
    <div class="modal-overlay" id="saveToClientModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:9999;">
        <div class="modal" style="background:white; border-radius:12px; max-width:450px; width:90%; padding:0; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                <h3 style="margin:0; font-size:18px;">💾 Mentés Ügyfélhez</h3>
                <button class="modal-close" onclick="closeSaveToClientModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div class="form-group">
                    <label>Válassz ügyfelet:</label>
                    <select id="saveToClientSelect" class="form-control">
                        <option value="">-- Válassz meglévő ügyfelet --</option>
                        <?php
                        require_once __DIR__ . '/includes/ClientManager.php';
                        $globalClientManager = new ClientManager();
                        $allClientsForModal = $globalClientManager->getClients();
                        foreach ($allClientsForModal as $client):
                        ?>
                        <option value="<?= htmlspecialchars($client['id']) ?>"><?= htmlspecialchars($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="divider-text"><span>vagy</span></div>
                <div class="form-group">
                    <label>Új ügyfél létrehozása:</label>
                    <input type="text" id="newClientName" class="form-control" placeholder="Cégnév...">
                </div>
            </div>
            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end; padding:16px 20px; border-top:1px solid #e5e7eb;">
                <button class="btn btn-secondary" onclick="closeSaveToClientModal()" style="padding:10px 20px; border-radius:8px; cursor:pointer;">Mégse</button>
                <button class="btn btn-primary" onclick="saveGenerationToClient()" style="padding:10px 20px; border-radius:8px; cursor:pointer; background:#3b82f6; color:white; border:none;">💾 Mentés</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <span><?= APP_NAME ?> v<?= APP_VERSION ?></span>
            <span>
                <?php if (DEMO_MODE): ?>Demo Mód<?php else: ?>
                <span class="status-dot <?= $api_key_valid ? 'green' : 'red' ?>"></span>
                API: <?= $api_key_valid ? 'OK' : 'Nincs' ?>
                <?php endif; ?>
            </span>
        </div>
    </footer>

    <script>
    // Globális CSRF token
    window.csrfToken = '<?= Security::generateCsrfToken() ?>';
    
    // Globális lastResultData - MINDIG betöltjük ha van
    <?php
    $lastResultFile = __DIR__ . '/data/last_result.json';
    $globalLastResult = file_exists($lastResultFile) ? json_decode(file_get_contents($lastResultFile), true) : null;
    ?>
    var lastResultData = <?= $globalLastResult ? json_encode($globalLastResult) : 'null' ?>;
    
    // Modal kezelés
    function showModal(id) {
        document.getElementById(id).classList.add('show');
    }
    function hideModal(id) {
        document.getElementById(id).classList.remove('show');
    }
    
    // Mentés ügyfélhez modal
    function showSaveToClientModal() {
        console.log('showSaveToClientModal() called');
        // Ellenőrizzük mindkét helyen a lastResultData-t
        const data = window.lastResultData || lastResultData;
        console.log('data:', data);
        if (!data) {
            alert('❌ Nincs elérhető generálás! Először generálj egy kampányt a Kampány fülön.');
            return;
        }
        // Beállítjuk a globális változót is
        lastResultData = data;
        const modal = document.getElementById('saveToClientModal');
        console.log('modal element:', modal);
        if (modal) {
            modal.style.display = 'flex';
            console.log('Modal opened, display:', modal.style.display);
        } else {
            console.error('Modal NOT FOUND!');
            alert('❌ Modal nem található!');
        }
    }
    function closeSaveToClientModal() {
        document.getElementById('saveToClientModal').style.display = 'none';
    }
    
    // Korábbi generálás mentése ügyfélhez (history modal-ból hívva)
    function saveHistoryToClient() {
        if (!window.selectedHistoryItem) {
            alert('❌ Nincs kiválasztott generálás!');
            return;
        }
        // Beállítjuk a lastResultData-t a kiválasztott history item-re
        window.lastResultData = window.selectedHistoryItem;
        lastResultData = window.selectedHistoryItem;
        // Bezárjuk a history modalt
        if (typeof closeHistoryModal === 'function') {
            closeHistoryModal();
        }
        // Megnyitjuk a mentés modalt
        const modal = document.getElementById('saveToClientModal');
        if (modal) {
            modal.style.display = 'flex';
        } else {
            alert('❌ A mentés modal nem található! Frissítsd az oldalt.');
        }
    }
    
    // Mentés ügyfélhez - az adatokat a PHP-ból vesszük
    async function saveGenerationToClient() {
        console.log('saveGenerationToClient called');
        
        const selectEl = document.getElementById('saveToClientSelect');
        const newNameEl = document.getElementById('newClientName');
        
        const clientId = selectEl ? selectEl.value : '';
        const newClientName = newNameEl ? newNameEl.value.trim() : '';
        
        console.log('clientId:', clientId, 'newClientName:', newClientName);
        
        if (!clientId && !newClientName) {
            alert('❌ Válassz ügyfelet vagy adj meg új nevet!');
            return;
        }
        
        // lastResultData-t a PHP-ból kell kapni - ellenőrizzük
        console.log('lastResultData:', typeof lastResultData, lastResultData);
        
        if (typeof lastResultData === 'undefined' || !lastResultData) {
            alert('❌ Nincs elérhető generálás adat! Először generálj egy kampányt.');
            return;
        }
        
        let targetClientId = clientId;
        
        // Ha új ügyfelet kell létrehozni
        if (!clientId && newClientName) {
            console.log('Creating new client...');
            const createForm = new FormData();
            createForm.append('action', 'save_client');
            createForm.append('name', newClientName);
            createForm.append('industry', lastResultData.industry || '');
            createForm.append('csrf_token', window.csrfToken || '');
            
            try {
                const resp = await fetch('api.php', { method: 'POST', body: createForm });
                const text = await resp.text();
                console.log('Create client response:', text);
                const data = JSON.parse(text);
                if (data.success && data.client_id) {
                    targetClientId = data.client_id;
                } else {
                    alert('❌ Hiba az ügyfél létrehozásakor: ' + (data.error || JSON.stringify(data)));
                    return;
                }
            } catch (e) {
                console.error('Error creating client:', e);
                alert('❌ Hálózati hiba: ' + e.message);
                return;
            }
        }
        
        // Generálás mentése az ügyfélhez
        console.log('Saving generation to client:', targetClientId);
        const saveForm = new FormData();
        saveForm.append('action', 'save_generation_to_client');
        saveForm.append('client_id', targetClientId);
        saveForm.append('generation', JSON.stringify(lastResultData));
        saveForm.append('csrf_token', window.csrfToken || '');
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: saveForm });
            const text = await resp.text();
            console.log('Save generation response:', text);
            const data = JSON.parse(text);
            if (data.success) {
                alert('✅ Kampány mentve az ügyfélhez!');
                closeSaveToClientModal();
            } else {
                alert('❌ Hiba: ' + (data.error || JSON.stringify(data)));
            }
        } catch (e) {
            console.error('Error saving generation:', e);
            alert('❌ Hálózati hiba: ' + e.message);
        }
    }
    
    // Ügyfél betöltése
    function loadClientData(selectEl) {
        const option = selectEl.options[selectEl.selectedIndex];
        if (!option || !option.dataset.json) {
            // Ürítsd ki a mezőket ha nincs kiválasztva
            document.getElementById('company_name').value = '';
            document.getElementById('phone').value = '';
            document.getElementById('area').value = 'budapest';
            document.getElementById('website').value = '';
            return;
        }
        const client = JSON.parse(option.dataset.json);
        document.getElementById('company_name').value = client.name || '';
        document.getElementById('phone').value = client.phone || '';
        document.getElementById('area').value = client.area || 'budapest';
        document.getElementById('website').value = client.website || '';
    }
    
    // Tone selector
    function selectTone(el) {
        document.querySelectorAll('.tone-option').forEach(t => t.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input').checked = true;
    }
    // Initial selection
    document.querySelector('.tone-option input:checked')?.closest('.tone-option')?.classList.add('selected');
    
    // Ügyfél törlése
    function deleteClient(id) {
        if (confirm('Biztosan törlöd ezt az ügyfelet?')) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_client&id=' + id
            }).then(() => location.reload());
        }
    }
    
    // Headline törlése
    function deleteHeadline(id) {
        if (confirm('Törlöd ezt a headline-t?')) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_headline&id=' + id
            }).then(() => location.reload());
        }
    }
    
    // Bank tab váltás
    function showBankTab(tab) {
        document.querySelectorAll('.bank-tab').forEach(t => t.classList.add('hidden'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tab + '-tab').classList.remove('hidden');
        event.target.classList.add('active');
    }
    
    // Form submit
    document.getElementById('wizardForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('generateBtn');
        const results = document.getElementById('results');
        btn.innerHTML = '⏳ Generálás...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            results.innerHTML = await resp.text();
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '🚀 Kampány Generálása';
        btn.disabled = false;
    });
    
    // Új ügyfél/headline form
    document.querySelectorAll('.modal form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            await fetch('api.php', { method: 'POST', body: new FormData(this) });
            location.reload();
        });
    });
    
    // Kulcsszó form-ok
    document.querySelectorAll('.keyword-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '⏳...';
            
            try {
                const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
                const text = await resp.text();
                
                // Próbáljuk JSON-ként parse-olni
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert(`✅ ${data.added} kulcsszó mentve (${data.total - data.added} már létezett)`);
                        location.reload();
                    } else {
                        alert('❌ Hiba: ' + (data.error || 'Ismeretlen hiba'));
                    }
                } catch (parseErr) {
                    // Ha nem JSON, akkor HTML hiba
                    console.error('Response:', text);
                    alert('Hiba: ' + text.substring(0, 200));
                }
            } catch (err) {
                alert('Hálózati hiba: ' + err.message);
            }
            
            btn.disabled = false;
            btn.innerHTML = btn.classList.contains('btn-danger') ? '🚫 Mentés' : '💾 Mentés';
        });
    });
    
    // Kulcsszó törlése
    function deleteKeyword(id, type) {
        if (confirm('Törlöd ezt a kulcsszót?')) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_keyword&id=${id}&type=${type}`
            }).then(() => location.reload());
        }
    }
    
    // Összes kulcsszó másolása
    function copyAllKeywords(type) {
        const tags = document.querySelectorAll(`.keyword-column:${type === 'positive' ? 'first-child' : 'last-child'} .tag`);
        const keywords = Array.from(tags).map(t => t.textContent.trim().replace('×', '')).join('\n');
        navigator.clipboard.writeText(keywords).then(() => alert('✅ ' + tags.length + ' kulcsszó másolva!'));
    }
    
    // Univerzális lista hozzáadása
    function addUniversalList(key) {
        const textarea = document.getElementById('universal-' + key);
        if (textarea) {
            const form = document.querySelector('.keyword-column:last-child .keyword-form');
            if (form) {
                form.querySelector('textarea').value = textarea.value;
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }
    
    // Kulcsszó elemzés form
    document.getElementById('analyzeKeywordsForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('analyzeKwBtn');
        const results = document.getElementById('keywordAnalysisResults');
        
        btn.innerHTML = '⏳ Elemzés...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            results.innerHTML = await resp.text();
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '🔍 Elemzés Indítása';
        btn.disabled = false;
    });
    
    // Versenytárs elemzés
    ['competitorForm', 'manualCompetitorForm'].forEach(formId => {
        document.getElementById(formId)?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const results = document.getElementById('competitorResults');
            
            btn.innerHTML = '⏳ Elemzés...';
            btn.disabled = true;
            
            try {
                const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
                results.innerHTML = await resp.text();
                results.scrollIntoView({ behavior: 'smooth' });
            } catch (err) {
                results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
            }
            
            btn.innerHTML = btn.innerHTML.includes('AI') ? '🧠 AI Elemzés' : '🔍 Versenytársak Elemzése';
            btn.disabled = false;
        });
    });
    
    // Landing Page elemzés
    document.getElementById('landingForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('landingBtn');
        const results = document.getElementById('landingResults');
        
        btn.innerHTML = '⏳ Elemzés folyamatban...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            results.innerHTML = await resp.text();
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '🔍 Teljes Elemzés Indítása';
        btn.disabled = false;
    });
    
    // PMax generálás
    document.getElementById('pmaxForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('pmaxBtn');
        const results = document.getElementById('pmaxResults');
        
        btn.innerHTML = '⏳ Generálás...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            results.innerHTML = await resp.text();
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '📦 PMax Assetek Generálása';
        btn.disabled = false;
    });
    
    // Új iparág generálás
    document.getElementById('newIndustryForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('generateIndustryBtn');
        
        btn.innerHTML = '⏳ Generálás AI-val...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            const data = await resp.json();
            
            if (data.success) {
                alert('✅ Iparág létrehozva: ' + data.industry.name);
                location.reload();
            } else {
                alert('❌ Hiba: ' + (data.error || 'Ismeretlen'));
            }
        } catch (err) {
            alert('Hiba: ' + err.message);
        }
        
        btn.innerHTML = '🧠 Generálás AI-val';
        btn.disabled = false;
    });
    
    // Iparág törlése
    function deleteIndustry(key) {
        if (confirm('Biztosan törlöd ezt az iparágat?')) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_industry&key=' + key
            }).then(() => location.reload());
        }
    }
    
    // Iparág megtekintése
    function viewIndustry(key) {
        alert('Részletek: ' + key + '\n\nEz a funkció hamarosan elérhető lesz!');
    }
    
    // Klaszterezés form
    document.getElementById('clusterKeywordsForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('clusterKwBtn');
        const results = document.getElementById('clusterResults');
        
        btn.innerHTML = '⏳ Klaszterezés...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            results.innerHTML = await resp.text();
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '📊 Klaszterezés';
        btn.disabled = false;
    });
    
    // Cluster kulcsszavak másolása
    function copyClusterKeywords(index) {
        const textarea = document.getElementById('cluster-kw-' + index);
        if (textarea) {
            navigator.clipboard.writeText(textarea.value).then(() => {
                alert('✅ Kulcsszavak másolva!');
            });
        }
    }
    
    // Összes cluster másolása
    function copyAllClusters() {
        const textarea = document.getElementById('all-clusters-export');
        if (textarea) {
            navigator.clipboard.writeText(textarea.value).then(() => {
                alert('✅ Összes Ad Group másolva Google Ads formátumban!');
            });
        }
    }
    
    // Aggresszivitás csúszka
    const aggrSlider = document.getElementById('aggressivenessSlider');
    if (aggrSlider) {
        const examples = {
            1: '"Professzionális Duguláselhárítás Budapesten"',
            2: '"Gyors Duguláselhárítás - 30 Percen Belül!"',
            3: '"DUGULÁS?! 28 Perc és OTT VAGYUNK! 🚨"'
        };
        
        aggrSlider.addEventListener('input', function() {
            const val = this.value;
            document.querySelectorAll('.slider-opt').forEach(opt => {
                opt.classList.toggle('active', opt.dataset.val === val);
            });
            document.getElementById('previewText').textContent = examples[val];
        });
        
        document.querySelectorAll('.slider-opt').forEach(opt => {
            opt.addEventListener('click', function() {
                aggrSlider.value = this.dataset.val;
                aggrSlider.dispatchEvent(new Event('input'));
            });
        });
    }
    
    // Szimulátor
    const simIndustry = document.getElementById('simIndustry');
    if (simIndustry) {
        simIndustry.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const cpcMin = opt.dataset.cpcMin || 150;
            const cpcMax = opt.dataset.cpcMax || 400;
            const cvr = opt.dataset.cvr || 5;
            
            document.getElementById('simCpc').value = Math.round((parseInt(cpcMin) + parseInt(cpcMax)) / 2);
            document.getElementById('simCvr').value = cvr;
        });
    }
    
    // Budget presets
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('simDailyBudget').value = this.dataset.val;
        });
    });
    
    // Run simulation
    document.getElementById('runSimulation')?.addEventListener('click', function() {
        const dailyBudget = parseInt(document.getElementById('simDailyBudget').value) || 15000;
        const days = parseInt(document.getElementById('simDays').value) || 30;
        const cpc = parseInt(document.getElementById('simCpc').value) || 250;
        const cvr = parseFloat(document.getElementById('simCvr').value) || 5;
        
        // Calculations
        const totalBudget = dailyBudget * days;
        const totalClicks = Math.round(totalBudget / cpc);
        const totalLeads = Math.round(totalClicks * (cvr / 100));
        const cpa = totalLeads > 0 ? Math.round(totalBudget / totalLeads) : 0;
        
        const dailyClicks = Math.round(dailyBudget / cpc);
        const dailyLeads = (dailyClicks * (cvr / 100)).toFixed(1);
        const monthlySpend = dailyBudget * 30;
        const monthlyLeads = Math.round((dailyBudget * 30 / cpc) * (cvr / 100));
        
        // Update UI
        document.getElementById('simTotalBudget').textContent = totalBudget.toLocaleString('hu-HU') + ' Ft';
        document.getElementById('simClicks').textContent = totalClicks.toLocaleString('hu-HU');
        document.getElementById('simLeads').textContent = totalLeads.toLocaleString('hu-HU');
        document.getElementById('simCpa').textContent = cpa.toLocaleString('hu-HU') + ' Ft';
        
        document.getElementById('simDailySpend').textContent = dailyBudget.toLocaleString('hu-HU') + ' Ft';
        document.getElementById('simDailyClicks').textContent = dailyClicks + ' kattintás';
        document.getElementById('simDailyLeads').textContent = dailyLeads + ' lead';
        document.getElementById('simMonthlySpend').textContent = monthlySpend.toLocaleString('hu-HU') + ' Ft';
        document.getElementById('simMonthlyLeads').textContent = monthlyLeads + ' lead';
        
        // Verdict
        const verdictEl = document.getElementById('simVerdict');
        let verdict = '', verdictClass = '';
        
        if (monthlyLeads < 5) {
            verdict = '⚠️ Kevés lead várható. A büdzsé növelése vagy a konverziós ráta javítása szükséges!';
            verdictClass = 'verdict-bad';
        } else if (monthlyLeads < 15) {
            verdict = '🟡 Elfogadható eredmény, de van tér a fejlődésre. Optimalizálással több lead érhető el.';
            verdictClass = 'verdict-warning';
        } else {
            verdict = '✅ Jó kiindulópont! Ez a költségkeret már skálázható eredményeket hozhat.';
            verdictClass = 'verdict-good';
        }
        
        verdictEl.textContent = verdict;
        verdictEl.className = 'sim-verdict ' + verdictClass;
        
        // Recommendations
        const recsEl = document.getElementById('simRecommendations');
        let recs = [];
        
        if (cpa > 10000) recs.push({ icon: '💰', text: 'A lead ár magas. Próbálj jobb minőségű kulcsszavakat!' });
        if (dailyBudget < 10000) recs.push({ icon: '📈', text: 'Az alacsony napi büdzsé lassítja a tanulást. Minimum 10.000 Ft ajánlott.' });
        if (cvr < 3) recs.push({ icon: '🌐', text: 'Alacsony konverziós ráta. Javítsd a landing page-et!' });
        if (monthlyLeads >= 20) recs.push({ icon: '🚀', text: 'Jó alap! Fontold meg a büdzsé skálázását.' });
        
        recsEl.innerHTML = recs.map(r => `<div class="sim-rec-item"><span class="sim-rec-icon">${r.icon}</span>${r.text}</div>`).join('');
        
        document.getElementById('simulatorResults').classList.remove('hidden');
        document.getElementById('simulatorResults').scrollIntoView({ behavior: 'smooth' });
    });
    
    // Diagnosis form
    document.getElementById('diagnosisForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('runDiagnosisBtn');
        const results = document.getElementById('diagnosisResults');
        
        btn.innerHTML = '⏳ Diagnózis futtatása...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            results.innerHTML = await resp.text();
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '🔍 Diagnózis Futtatása';
        btn.disabled = false;
    });
    
    // Vision form
    document.getElementById('visionForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('visionBtn');
        const results = document.getElementById('visionResults');
        
        // URL validáció
        const urlInput = this.querySelector('input[name="url"]');
        if (!urlInput.value.trim()) {
            results.innerHTML = '<div class="alert alert-error">❌ Add meg a landing page URL-t!</div>';
            return;
        }
        
        btn.innerHTML = '⏳ Screenshot készítése...';
        btn.disabled = true;
        results.innerHTML = '<div class="alert alert-info">🔄 Folyamatban... Ez akár 30 másodpercig is eltarthat.</div>';
        
        try {
            const formData = new FormData(this);
            
            // Debug: ellenőrizzük a CSRF tokent
            console.log('Vision form data:', {
                action: formData.get('action'),
                url: formData.get('url'),
                csrf: formData.get('csrf_token') ? 'present' : 'MISSING'
            });
            
            const resp = await fetch('api.php', { 
                method: 'POST', 
                body: formData 
            });
            
            const text = await resp.text();
            
            // Ha JSON hiba jött vissza
            if (text.startsWith('{') && text.includes('"error"')) {
                try {
                    const err = JSON.parse(text);
                    results.innerHTML = '<div class="alert alert-error">❌ ' + (err.error || 'Ismeretlen hiba') + '</div>';
                } catch (e) {
                    results.innerHTML = text;
                }
            } else {
                results.innerHTML = text;
            }
            
            results.scrollIntoView({ behavior: 'smooth' });
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">❌ Hálózati hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '👁️ AI Vision Elemzés';
        btn.disabled = false;
    });
    
    // Google Ads Publish - Account loader
    const gadsAccountSelect = document.getElementById('gadsAccount');
    if (gadsAccountSelect) {
        // Load accounts on page load
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_gads_accounts'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.accounts.length > 0) {
                gadsAccountSelect.innerHTML = '<option value="">-- Válassz fiókot --</option>';
                data.accounts.forEach(acc => {
                    gadsAccountSelect.innerHTML += `<option value="${acc.id}">${acc.name}</option>`;
                });
            } else {
                gadsAccountSelect.innerHTML = '<option value="">Nincs elérhető fiók</option>';
            }
        })
        .catch(() => {
            gadsAccountSelect.innerHTML = '<option value="">Hiba a betöltéskor</option>';
        });
        
        // Load campaigns when account changes
        gadsAccountSelect.addEventListener('change', function() {
            const campaignSelect = document.getElementById('gadsCampaign');
            const adGroupSelect = document.getElementById('gadsAdGroup');
            
            campaignSelect.disabled = true;
            adGroupSelect.disabled = true;
            campaignSelect.innerHTML = '<option value="">Betöltés...</option>';
            
            if (!this.value) return;
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_gads_campaigns&customer_id=${this.value}`
            })
            .then(r => r.json())
            .then(data => {
                campaignSelect.innerHTML = '<option value="">-- Válassz kampányt --</option>';
                if (data.campaigns) {
                    data.campaigns.forEach(c => {
                        campaignSelect.innerHTML += `<option value="${c.id}">${c.name} (${c.status})</option>`;
                    });
                }
                campaignSelect.disabled = false;
            });
        });
        
        // Load ad groups when campaign changes
        document.getElementById('gadsCampaign')?.addEventListener('change', function() {
            const adGroupSelect = document.getElementById('gadsAdGroup');
            const customerId = gadsAccountSelect.value;
            
            adGroupSelect.disabled = true;
            adGroupSelect.innerHTML = '<option value="">Betöltés...</option>';
            
            if (!this.value || !customerId) return;
            
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_gads_adgroups&customer_id=${customerId}&campaign_id=${this.value}`
            })
            .then(r => r.json())
            .then(data => {
                adGroupSelect.innerHTML = '<option value="">-- Válassz Ad Group-ot --</option>';
                if (data.adGroups) {
                    data.adGroups.forEach(ag => {
                        adGroupSelect.innerHTML += `<option value="${ag.id}">${ag.name}</option>`;
                    });
                }
                adGroupSelect.disabled = false;
            });
        });
    }
    
    // Publish form
    document.getElementById('publishForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('publishBtn');
        const results = document.getElementById('publishResults');
        
        if (!confirm('Biztosan közzéteszed a Google Ads fiókban? A hirdetés PAUSED állapotban kerül fel.')) {
            return;
        }
        
        btn.innerHTML = '⏳ Közzététel...';
        btn.disabled = true;
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: new FormData(this) });
            const data = await resp.json();
            
            if (data.success) {
                results.innerHTML = '<div class="alert alert-success">✅ Sikeresen közzétéve! Ellenőrizd a Google Ads fiókodban.</div>';
            } else {
                results.innerHTML = '<div class="alert alert-error">❌ Hiba: ' + (data.error || JSON.stringify(data.results)) + '</div>';
            }
        } catch (err) {
            results.innerHTML = '<div class="alert alert-error">Hiba: ' + err.message + '</div>';
        }
        
        btn.innerHTML = '📤 Közzététel a Google Ads-ban';
        btn.disabled = false;
    });
    
    // AI Assistant Chat
    let conversationHistory = [];
    
    function useExample(btn) {
        const text = btn.querySelector('span:last-child')?.textContent || btn.textContent.replace(/^"|"$/g, '');
        document.getElementById('chatInput').value = text;
        document.getElementById('chatInput').focus();
    }
    
    function addMessage(role, content) {
        const messagesDiv = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ' + role;
        
        if (role === 'user') {
            messageDiv.innerHTML = `
                <div class="message-avatar"><span class="avatar-icon">👤</span></div>
                <div class="message-bubble">${content}</div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="message-avatar"><span class="avatar-icon">🤖</span></div>
                <div class="message-bubble">
                    <div class="message-sender">AdMaster AI</div>
                    ${content}
                </div>
            `;
        }
        
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    function showTyping() {
        const messagesDiv = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typingIndicator';
        typingDiv.className = 'message assistant';
        typingDiv.innerHTML = `
            <div class="message-avatar"><span class="avatar-icon">🤖</span></div>
            <div class="message-bubble">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    function hideTyping() {
        document.getElementById('typingIndicator')?.remove();
    }
    
    function renderSuggestions(suggestions) {
        const sidebar = document.getElementById('assistantSidebar');
        
        if (!suggestions || !suggestions.understood) {
            return;
        }
        
        let html = '<div class="suggestions-panel">';
        html += '<h3>✨ Kampány Javaslatok</h3>';
        
        // Business summary
        if (suggestions.business_summary) {
            html += '<div class="suggestion-section">';
            html += '<h4>📋 Összefoglaló</h4>';
            html += '<p style="font-size:13px;color:var(--text);line-height:1.5;">' + suggestions.business_summary + '</p>';
            html += '</div>';
        }
        
        // USPs
        if (suggestions.unique_selling_points?.length) {
            html += '<div class="suggestion-section">';
            html += '<h4>⭐ Egyedi Előnyök</h4>';
            html += '<div class="suggestion-tags">';
            suggestions.unique_selling_points.forEach(usp => {
                html += '<span class="suggestion-tag usp">' + usp + '</span>';
            });
            html += '</div></div>';
        }
        
        // Headlines
        if (suggestions.suggested_headlines?.length) {
            html += '<div class="suggestion-section">';
            html += '<h4>📝 Headlines</h4>';
            html += '<div class="suggestion-items">';
            suggestions.suggested_headlines.forEach(h => {
                const len = h.length;
                const cls = len > 30 ? 'color:#dc2626' : '';
                html += '<div class="suggestion-item"><span>' + h + '</span><span class="char-count" style="' + cls + '">' + len + '/30</span></div>';
            });
            html += '</div></div>';
        }
        
        // Descriptions
        if (suggestions.suggested_descriptions?.length) {
            html += '<div class="suggestion-section">';
            html += '<h4>📝 Descriptions</h4>';
            html += '<div class="suggestion-items">';
            suggestions.suggested_descriptions.forEach(d => {
                const len = d.length;
                const cls = len > 90 ? 'color:#dc2626' : '';
                html += '<div class="suggestion-item"><span>' + d + '</span><span class="char-count" style="' + cls + '">' + len + '/90</span></div>';
            });
            html += '</div></div>';
        }
        
        // Keywords
        if (suggestions.suggested_keywords?.length) {
            html += '<div class="suggestion-section">';
            html += '<h4>🔤 Kulcsszavak</h4>';
            html += '<div class="suggestion-tags">';
            suggestions.suggested_keywords.forEach(kw => {
                html += '<span class="suggestion-tag keyword">' + kw + '</span>';
            });
            html += '</div></div>';
        }
        
        // Budget
        if (suggestions.budget_estimate) {
            html += '<div class="suggestion-section">';
            html += '<h4>💰 Költségbecslés</h4>';
            html += '<div class="budget-estimate">';
            html += '<div class="budget-row"><span>Napi minimum:</span><span>' + (suggestions.budget_estimate.daily_min?.toLocaleString() || '?') + ' Ft</span></div>';
            html += '<div class="budget-row"><span>Ajánlott napi:</span><span>' + (suggestions.budget_estimate.daily_recommended?.toLocaleString() || '?') + ' Ft</span></div>';
            html += '<div class="budget-row"><span>Várható CPA:</span><span>' + (suggestions.budget_estimate.cpa_estimate || '?') + '</span></div>';
            html += '</div></div>';
        }
        
        // Strategy tips
        if (suggestions.strategy_tips?.length) {
            html += '<div class="suggestion-section">';
            html += '<h4>💡 Stratégiai Tippek</h4>';
            html += '<ul style="font-size:13px;padding-left:18px;margin:0;line-height:1.6;">';
            suggestions.strategy_tips.forEach(tip => {
                html += '<li style="margin-bottom:6px;">' + tip + '</li>';
            });
            html += '</ul></div>';
        }
        
        // Actions
        html += '<div class="suggestion-actions">';
        html += '<button class="btn btn-primary" onclick="useSuggestionsInWizard()">🚀 Használom</button>';
        html += '</div>';
        
        html += '</div>';
        
        sidebar.innerHTML = html;
        
        // Mentés localStorage-ba a wizard számára
        localStorage.setItem('assistantSuggestions', JSON.stringify(suggestions));
    }
    
    function useSuggestionsInWizard() {
        const suggestions = JSON.parse(localStorage.getItem('assistantSuggestions') || '{}');
        if (suggestions.suggested_industry) {
            window.location.href = '?tab=wizard&industry=' + suggestions.suggested_industry + '&from_assistant=1';
        } else {
            window.location.href = '?tab=wizard';
        }
    }
    
    document.getElementById('chatForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Add user message
        addMessage('user', '<p>' + message.replace(/\n/g, '<br>') + '</p>');
        conversationHistory.push({ role: 'user', content: message });
        
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        
        // Show typing indicator
        showTyping();
        
        try {
            const formData = new FormData();
            formData.append('action', 'assistant_chat');
            formData.append('message', message);
            formData.append('history', JSON.stringify(conversationHistory.slice(-10)));
            formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
            
            const resp = await fetch('api.php', { method: 'POST', body: formData });
            const data = await resp.json();
            
            hideTyping();
            
            if (data.success) {
                let formattedMessage = data.message
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\n/g, '<br>')
                    .replace(/^- /gm, '• ');
                
                addMessage('assistant', '<p>' + formattedMessage + '</p>');
                conversationHistory.push({ role: 'assistant', content: data.message });
                
                if (data.suggestions) {
                    renderSuggestions(data.suggestions);
                }
            } else {
                addMessage('assistant', '<p>❌ Hiba történt: ' + (data.error || 'Ismeretlen hiba') + '</p>');
            }
        } catch (err) {
            hideTyping();
            addMessage('assistant', '<p>❌ Hálózati hiba: ' + err.message + '</p>');
        }
        
        sendBtn.disabled = false;
        input.focus();
    });
    
    // Auto-resize textarea & Enter to send
    const chatInput = document.getElementById('chatInput');
    chatInput?.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    chatInput?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }
    });
    
    // ========================================
    // RSA PREVIEW - ÉLŐ ELŐNÉZET
    // ========================================
    class RSAPreview {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            this.headlines = [];
            this.descriptions = [];
            this.url = '';
            this.path1 = '';
            this.path2 = '';
            this.isPlaying = false;
            this.interval = null;
        }
        
        setData(headlines, descriptions, url, path1 = '', path2 = '') {
            this.headlines = headlines || [];
            this.descriptions = descriptions || [];
            this.url = url || 'example.com';
            this.path1 = path1;
            this.path2 = path2;
            this.render();
        }
        
        getRandomHeadlines(count = 3) {
            const shuffled = [...this.headlines].sort(() => Math.random() - 0.5);
            return shuffled.slice(0, Math.min(count, this.headlines.length));
        }
        
        getRandomDescriptions(count = 2) {
            const shuffled = [...this.descriptions].sort(() => Math.random() - 0.5);
            return shuffled.slice(0, Math.min(count, this.descriptions.length));
        }
        
        render(view = 'desktop') {
            if (!this.container) return;
            
            const hs = this.getRandomHeadlines(3);
            const ds = this.getRandomDescriptions(2);
            const displayUrl = this.url.replace(/^https?:\/\//, '').split('/')[0];
            const path = [this.path1, this.path2].filter(p => p).join('/');
            
            const isMobile = view === 'mobile';
            
            this.container.innerHTML = `
                <div class="rsa-preview ${isMobile ? 'rsa-mobile' : 'rsa-desktop'}">
                    <div class="rsa-header">
                        <div class="rsa-view-toggle">
                            <button class="view-btn ${!isMobile ? 'active' : ''}" onclick="rsaPreview.render('desktop')">🖥️</button>
                            <button class="view-btn ${isMobile ? 'active' : ''}" onclick="rsaPreview.render('mobile')">📱</button>
                        </div>
                        <button class="shuffle-btn" onclick="rsaPreview.shuffle()">🔀 Pörgetés</button>
                        <button class="play-btn" onclick="rsaPreview.toggleAutoPlay()">${this.isPlaying ? '⏸️ Stop' : '▶️ Auto'}</button>
                    </div>
                    <div class="rsa-ad">
                        <div class="rsa-sponsored">Hirdetés</div>
                        <div class="rsa-url">
                            <span class="rsa-domain">${this.escapeHtml(displayUrl)}</span>
                            ${path ? `<span class="rsa-path">/${this.escapeHtml(path)}</span>` : ''}
                        </div>
                        <div class="rsa-title">${hs.map(h => this.escapeHtml(h)).join(' | ')}</div>
                        <div class="rsa-desc">${ds.map(d => this.escapeHtml(d)).join(' ')}</div>
                    </div>
                </div>
            `;
        }
        
        shuffle() {
            this.render(this.container.querySelector('.rsa-mobile') ? 'mobile' : 'desktop');
        }
        
        toggleAutoPlay() {
            this.isPlaying = !this.isPlaying;
            if (this.isPlaying) {
                this.interval = setInterval(() => this.shuffle(), 2000);
            } else {
                clearInterval(this.interval);
            }
            this.render(this.container.querySelector('.rsa-mobile') ? 'mobile' : 'desktop');
        }
        
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }
    }
    
    // ========================================
    // AD STRENGTH INDIKÁTOR
    // ========================================
    class AdStrengthMeter {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
        }
        
        calculate(headlines, descriptions, keywords = []) {
            let score = 0;
            let issues = [];
            let positives = [];
            
            const hCount = (headlines || []).length;
            const dCount = (descriptions || []).length;
            
            // Headline count (max 20 points)
            if (hCount >= 15) { score += 20; positives.push('✓ Elegendő headline (15+)'); }
            else if (hCount >= 10) { score += 15; issues.push('• Adj hozzá még ' + (15-hCount) + ' headline-t'); }
            else if (hCount >= 5) { score += 8; issues.push('⚠️ Túl kevés headline (' + hCount + '/15)'); }
            else { issues.push('❌ Kritikusan kevés headline!'); }
            
            // Description count (max 20 points)
            if (dCount >= 4) { score += 20; positives.push('✓ Elegendő description (4)'); }
            else if (dCount >= 2) { score += 10; issues.push('• Adj hozzá még ' + (4-dCount) + ' description-t'); }
            else { issues.push('❌ Több description szükséges!'); }
            
            // Headline diversity (max 20 points)
            const uniqueStarts = new Set((headlines || []).map(h => (h || '').substring(0, 10).toLowerCase()));
            const diversityRatio = uniqueStarts.size / Math.max(hCount, 1);
            if (diversityRatio > 0.8) { score += 20; positives.push('✓ Változatos headline-ok'); }
            else if (diversityRatio > 0.5) { score += 10; issues.push('• Variáld a headline kezdeteket'); }
            else { issues.push('⚠️ Túl hasonló headline-ok'); }
            
            // Character utilization (max 20 points)
            const avgHLen = headlines?.length ? headlines.reduce((a,h) => a + (h||'').length, 0) / hCount : 0;
            const avgDLen = descriptions?.length ? descriptions.reduce((a,d) => a + (d||'').length, 0) / dCount : 0;
            
            if (avgHLen >= 25 && avgDLen >= 75) { score += 20; positives.push('✓ Jó karakterkihasználás'); }
            else if (avgHLen >= 20 && avgDLen >= 60) { score += 12; issues.push('• Használd ki jobban a karakterlimitet'); }
            else { issues.push('⚠️ Túl rövid szövegek'); }
            
            // Keyword presence (max 20 points)
            if (keywords.length > 0) {
                const allText = [...(headlines||[]), ...(descriptions||[])].join(' ').toLowerCase();
                const keywordMatches = keywords.filter(kw => allText.includes(kw.toLowerCase())).length;
                const kwRatio = keywordMatches / keywords.length;
                if (kwRatio >= 0.6) { score += 20; positives.push('✓ Jó kulcsszó lefedettség'); }
                else if (kwRatio >= 0.3) { score += 10; issues.push('• Használj több kulcsszót'); }
                else { issues.push('⚠️ Kulcsszavak hiányoznak'); }
            } else {
                score += 10; // Nincs kulcsszó megadva, semleges
            }
            
            return { score, issues, positives };
        }
        
        render(headlines, descriptions, keywords = []) {
            if (!this.container) return;
            
            const { score, issues, positives } = this.calculate(headlines, descriptions, keywords);
            
            let label, colorClass;
            if (score >= 80) { label = 'Kiváló'; colorClass = 'strength-excellent'; }
            else if (score >= 60) { label = 'Jó'; colorClass = 'strength-good'; }
            else if (score >= 40) { label = 'Közepes'; colorClass = 'strength-average'; }
            else { label = 'Gyenge'; colorClass = 'strength-poor'; }
            
            this.container.innerHTML = `
                <div class="ad-strength-meter ${colorClass}">
                    <div class="strength-header">
                        <span class="strength-label">Hirdetés Ereje:</span>
                        <span class="strength-value">${label}</span>
                    </div>
                    <div class="strength-bar">
                        <div class="strength-fill" style="width: ${score}%"></div>
                    </div>
                    <div class="strength-score">${score}/100</div>
                    <div class="strength-details">
                        ${positives.map(p => `<div class="strength-positive">${p}</div>`).join('')}
                        ${issues.map(i => `<div class="strength-issue">${i}</div>`).join('')}
                    </div>
                </div>
            `;
        }
    }
    
    // Global instances
    window.rsaPreview = new RSAPreview('rsaPreviewContainer');
    window.adStrength = new AdStrengthMeter('adStrengthContainer');
    
    // ========================================
    // LANDING PAGE FUNKCIÓK
    // ========================================
    
    // Landing section váltás
    function showLandingSection(section) {
        document.querySelectorAll('.landing-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.landing-section').forEach(s => s.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById('landing-' + section)?.classList.add('active');
    }
    
    // Relevancia Ellenőrzés
    document.getElementById('relevanceForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('checkRelevanceBtn');
        const resultsDiv = document.getElementById('relevanceResults');
        
        btn.disabled = true;
        btn.innerHTML = '⏳ Ellenőrzés...';
        resultsDiv.innerHTML = '<div class="loading">Weboldal elemzése folyamatban...</div>';
        
        const url = document.getElementById('relevanceUrl').value;
        const headlines = document.getElementById('relevanceHeadlines').value.split('\n').filter(h => h.trim());
        const keywords = document.getElementById('relevanceKeywords').value.split('\n').filter(k => k.trim());
        
        const formData = new FormData();
        formData.append('action', 'check_relevance');
        formData.append('url', url);
        headlines.forEach(h => formData.append('headlines[]', h));
        keywords.forEach(k => formData.append('keywords[]', k));
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: formData });
            const data = await resp.json();
            
            if (data.success) {
                resultsDiv.innerHTML = renderRelevanceResults(data);
            } else {
                resultsDiv.innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`;
            }
        } catch (err) {
            resultsDiv.innerHTML = `<div class="alert alert-error">❌ Hiba: ${err.message}</div>`;
        }
        
        btn.disabled = false;
        btn.innerHTML = '🔍 Relevancia Ellenőrzés';
    });
    
    // Relevancia eredmények megjelenítése
    function renderRelevanceResults(data) {
        const kw = data.keyword_relevance || {};
        const pm = data.promise_match || {};
        const qs = data.qs_prediction || {};
        const recs = data.recommendations || [];
        
        let html = '<div class="relevance-results">';
        
        // QS Prediction
        if (qs.score) {
            html += `
                <div class="qs-prediction">
                    <div class="qs-pred-score ${qs.color}">${qs.score}</div>
                    <div class="qs-pred-info">
                        <h4>🎯 Becsült Quality Score</h4>
                        <div class="qs-pred-factors">
                            ${(qs.factors || []).map(f => `<span class="qs-factor ${f.status}">${f.factor}: ${f.impact}</span>`).join('')}
                        </div>
                        <div class="qs-pred-cpc">💰 ${qs.cpc_impact}</div>
                    </div>
                </div>
            `;
        }
        
        // Kulcsszó relevancia
        html += `<div class="keyword-match-grid">`;
        
        if (kw.found?.length) {
            html += `<div class="kw-match-card found">
                <div class="kw-match-header">✅ Megtalálva (${kw.found.length})</div>
                <div class="kw-list">${kw.found.map(k => `<span class="kw-tag">${k}</span>`).join('')}</div>
            </div>`;
        }
        
        if (kw.partial?.length) {
            html += `<div class="kw-match-card partial">
                <div class="kw-match-header">🟡 Részleges (${kw.partial.length})</div>
                <div class="kw-list">${kw.partial.map(k => `<span class="kw-tag">${k}</span>`).join('')}</div>
            </div>`;
        }
        
        if (kw.missing?.length) {
            html += `<div class="kw-match-card missing">
                <div class="kw-match-header">❌ Hiányzik (${kw.missing.length})</div>
                <div class="kw-list">${kw.missing.map(k => `<span class="kw-tag">${k}</span>`).join('')}</div>
            </div>`;
        }
        
        html += `</div>`;
        
        // Ígéret problémák
        if (pm.issues?.length) {
            html += `<div class="promise-issues"><h4>⚠️ Hirdetési Ígéretek Problémái</h4>`;
            pm.issues.forEach(issue => {
                html += `<div class="promise-issue">${issue.message}</div>`;
            });
            html += `</div>`;
        }
        
        // Javaslatok
        if (recs.length) {
            html += `<div class="cro-tips-list"><h4>💡 Javaslatok</h4>`;
            recs.forEach(rec => {
                html += `
                    <div class="cro-tip ${rec.type}">
                        <div class="cro-tip-icon">${rec.icon || '💡'}</div>
                        <div class="cro-tip-content">
                            <div class="cro-tip-title">${rec.title}</div>
                            <div class="cro-tip-action">${rec.action}</div>
                            ${rec.impact ? `<div class="cro-tip-impact">📈 ${rec.impact}</div>` : ''}
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        }
        
        html += '</div>';
        return html;
    }
    
    // USP Kivonatolás
    document.getElementById('uspForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('extractUspBtn');
        const resultsDiv = document.getElementById('uspResults');
        
        btn.disabled = true;
        btn.innerHTML = '⏳ Elemzés...';
        resultsDiv.innerHTML = '<div class="loading">USP-k keresése a weboldalon...</div>';
        
        const formData = new FormData();
        formData.append('action', 'extract_usps');
        formData.append('url', document.getElementById('uspUrl').value);
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: formData });
            const data = await resp.json();
            
            if (data.success) {
                resultsDiv.innerHTML = renderUSPResults(data);
            } else {
                resultsDiv.innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`;
            }
        } catch (err) {
            resultsDiv.innerHTML = `<div class="alert alert-error">❌ Hiba: ${err.message}</div>`;
        }
        
        btn.disabled = false;
        btn.innerHTML = '⭐ USP-k Kivonatolása';
    });
    
    // USP eredmények megjelenítése
    function renderUSPResults(data) {
        if (!data.usps?.length) {
            return '<div class="alert alert-info">ℹ️ Nem találtunk egyértelmű USP-ket a weboldalon.</div>';
        }
        
        let html = `
            <div class="usp-results">
                <div class="alert alert-success">✅ ${data.count} USP-t találtunk a weboldalon!</div>
                <div class="usp-grid">
        `;
        
        data.usps.forEach(usp => {
            html += `
                <div class="usp-card">
                    <div class="usp-card-header">
                        <div class="usp-icon">${usp.icon}</div>
                        <div>
                            <div class="usp-type">${usp.type}</div>
                            <div class="usp-text">${usp.text}</div>
                        </div>
                    </div>
                    ${usp.context ? `<div class="usp-context">"${usp.context}"</div>` : ''}
                    ${usp.headline_suggestion ? `
                        <div class="usp-suggestion">
                            <span>💡 ${usp.headline_suggestion}</span>
                            <button class="btn btn-sm copy-btn" onclick="navigator.clipboard.writeText('${usp.headline_suggestion}')">📋</button>
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        html += '</div>';
        
        // Headline javaslatok összesítve
        if (data.headline_suggestions?.length) {
            html += `
                <div class="card" style="margin-top: 20px;">
                    <h4>📝 Javasolt Headlines</h4>
                    <div class="copy-list">
                        ${data.headline_suggestions.map(h => `
                            <div class="copy-item">
                                <span>${h}</span>
                                <button class="btn btn-sm" onclick="navigator.clipboard.writeText('${h}')">📋</button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        html += '</div>';
        return html;
    }
    
    // CRO Audit form
    document.getElementById('visionForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('visionBtn');
        const resultsDiv = document.getElementById('visionResults');
        
        btn.disabled = true;
        btn.innerHTML = '⏳ Elemzés...';
        resultsDiv.innerHTML = '<div class="loading">CRO audit folyamatban... (ez akár 30 másodpercig is tarthat)</div>';
        
        const formData = new FormData(this);
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: formData });
            const data = await resp.json();
            
            if (data.success || data.overall_score) {
                resultsDiv.innerHTML = renderCROResults(data);
            } else {
                resultsDiv.innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`;
            }
        } catch (err) {
            resultsDiv.innerHTML = `<div class="alert alert-error">❌ Hiba: ${err.message}</div>`;
        }
        
        btn.disabled = false;
        btn.innerHTML = '👁️ CRO Audit Futtatása';
    });
    
    // CRO eredmények
    function renderCROResults(data) {
        let html = '<div class="cro-results" style="margin-top: 24px;">';
        
        // Overall score
        const score = data.overall_score || 0;
        const scoreClass = score >= 70 ? 'good' : (score >= 40 ? 'warning' : 'critical');
        
        html += `
            <div class="relevance-score-card">
                <div class="relevance-score ${scoreClass}">${score}</div>
                <div class="relevance-info">
                    <h4>CRO Összpontszám</h4>
                    <p>${score >= 70 ? 'Jó alap, de mindig van mit javítani' : (score >= 40 ? 'Közepes - fejlesztések szükségesek' : 'Gyenge - sürgős javítások kellenek!')}</p>
                </div>
            </div>
        `;
        
        // CRO Tips
        if (data.cro_tips?.length) {
            html += `<div class="cro-tips-list"><h4>💡 CRO Javaslatok (${data.cro_tips.length})</h4>`;
            
            data.cro_tips.forEach(tip => {
                html += `
                    <div class="cro-tip ${tip.severity}">
                        <div class="cro-tip-icon">${tip.icon}</div>
                        <div class="cro-tip-content">
                            <div class="cro-tip-title">${tip.tip}</div>
                            <div class="cro-tip-action">${tip.action}</div>
                            ${tip.impact ? `<div class="cro-tip-impact">📈 ${tip.impact}</div>` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        // Top 3 fixes
        if (data.top_3_fixes?.length) {
            html += `<div class="card" style="margin-top: 20px;"><h4>🎯 Top 3 Prioritás</h4><ol>`;
            data.top_3_fixes.forEach(fix => {
                html += `<li><strong>${fix.fix}</strong> <span class="badge">${fix.impact}</span></li>`;
            });
            html += '</ol></div>';
        }
        
        // Positive aspects
        if (data.positive_aspects?.length) {
            html += `<div class="card" style="margin-top: 20px; background: #f0fdf4;"><h4>✅ Ami Jól Működik</h4><ul>`;
            data.positive_aspects.forEach(p => {
                html += `<li>${p}</li>`;
            });
            html += '</ul></div>';
        }
        
        html += '</div>';
        return html;
    }
    
    // ========================================
    // MOBILE MENU
    // ========================================
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('open');
        document.body.style.overflow = menu.classList.contains('open') ? 'hidden' : '';
    }
    
    // Close mobile menu on link click
    document.querySelectorAll('.mobile-nav a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('mobileMenu').classList.remove('open');
            document.body.style.overflow = '';
        });
    });
    
    // Close on escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('mobileMenu')?.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
    </script>
</body>
</html>
