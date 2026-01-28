<?php
/**
 * AdMaster Pro v3.3 - Teljes Kampány Kezelő
 * 
 * Funkciók:
 * - Wizard alapú kampány generálás
 * - Ügyfél/cég kezelés
 * - Bevált headline bank
 * - Stratégiai javaslatok
 * - Extra figyelemfelkeltő szövegek
 * - Versenytárs elemzés
 * - Landing page CRO audit
 * - PMax asset generálás
 * - Dinamikus iparág létrehozás
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/ClientManager.php';

Security::initSession();

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
$tab = $_GET['tab'] ?? 'wizard';

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
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><?= APP_NAME ?></h1>
                    <span class="version">v<?= APP_VERSION ?></span>
                </div>
                <nav class="main-nav">
                    <a href="?tab=wizard" class="nav-link <?= $tab === 'wizard' ? 'active' : '' ?>">🚀 Kampány</a>
                    <a href="?tab=clients" class="nav-link <?= $tab === 'clients' ? 'active' : '' ?>">🏢 Ügyfelek</a>
                    <a href="?tab=keywords" class="nav-link <?= $tab === 'keywords' ? 'active' : '' ?>">🔤 Kulcsszavak</a>
                    <a href="?tab=competitors" class="nav-link <?= $tab === 'competitors' ? 'active' : '' ?>">🔍 Versenytárs</a>
                    <a href="?tab=landing" class="nav-link <?= $tab === 'landing' ? 'active' : '' ?>">🌐 Landing</a>
                    <a href="?tab=pmax" class="nav-link <?= $tab === 'pmax' ? 'active' : '' ?>">📦 PMax</a>
                    <a href="?tab=industries" class="nav-link <?= $tab === 'industries' ? 'active' : '' ?>">🏭 Iparágak</a>
                    <a href="?tab=strategies" class="nav-link <?= $tab === 'strategies' ? 'active' : '' ?>">🧠 Stratégiák</a>
                </nav>
                <div class="header-right">
                    <?php if (DEMO_MODE): ?><span class="badge badge-warning">Demo</span><?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">

<?php if ($tab === 'wizard'): ?>
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
                            <select id="loadClient" class="form-control" onchange="loadClientData(this.value)">
                                <option value="">-- Válassz vagy töltsd ki kézzel --</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= htmlspecialchars(json_encode($c)) ?>" <?= $selectedClient && $selectedClient['id'] === $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (<?= $c['industry'] ?? 'n/a' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
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
                        <h3 class="card-title">🔥 Extra Címsorok</h3>
                        <p class="help-text">Agresszívabb, figyelemfelkeltő szövegek</p>
                        
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
    <?php endif; ?>

<?php elseif ($tab === 'clients'): ?>
<!-- ==================== ÜGYFELEK TAB ==================== -->

    <section class="page-section">
        <div class="section-header">
            <h2>🏢 Ügyfeleim</h2>
            <button class="btn btn-primary" onclick="showModal('newClientModal')">+ Új Ügyfél</button>
        </div>
        
        <?php $clients = $clientManager->getClients(); ?>
        
        <?php if (empty($clients)): ?>
        <div class="empty-state">
            <span class="empty-icon">🏢</span>
            <h3>Még nincsenek mentett ügyfelek</h3>
            <p>Adj hozzá ügyfeleket, hogy gyorsabban készíthess kampányokat!</p>
            <button class="btn btn-primary" onclick="showModal('newClientModal')">+ Első ügyfél hozzáadása</button>
        </div>
        <?php else: ?>
        <div class="clients-grid">
            <?php foreach ($clients as $client): ?>
            <div class="client-card">
                <div class="client-header">
                    <h3><?= htmlspecialchars($client['name']) ?></h3>
                    <span class="client-industry"><?= $industries[$client['industry']]['icon'] ?? '🏢' ?> <?= $industries[$client['industry']]['name'] ?? $client['industry'] ?></span>
                </div>
                <div class="client-details">
                    <p>📞 <?= htmlspecialchars($client['phone'] ?? '-') ?></p>
                    <p>📍 <?= htmlspecialchars($client['area'] ?? '-') ?></p>
                    <?php if (!empty($client['website'])): ?>
                    <p>🌐 <?= htmlspecialchars($client['website']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="client-actions">
                    <a href="?tab=wizard&client=<?= $client['id'] ?>&industry=<?= $client['industry'] ?>" class="btn btn-sm btn-primary">🚀 Kampány</a>
                    <button class="btn btn-sm btn-secondary" onclick="editClient('<?= $client['id'] ?>')">✏️</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteClient('<?= $client['id'] ?>')">🗑️</button>
                </div>
                <?php if (!empty($client['campaigns'])): ?>
                <div class="client-campaigns">
                    <small><?= count($client['campaigns']) ?> korábbi kampány</small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

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

    <section class="page-section">
        <div class="section-header">
            <h2>🌐 Landing Page Elemző & Szövegíró</h2>
        </div>
        
        <div class="card">
            <h3 class="card-title">🔍 Technikai + CRO Audit</h3>
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
                
                <button type="submit" class="btn btn-primary btn-lg" id="landingBtn">🔍 Teljes Elemzés Indítása</button>
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
    // Modal kezelés
    function showModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    function hideModal(id) {
        document.getElementById(id).classList.add('hidden');
    }
    
    // Ügyfél betöltése
    function loadClientData(jsonData) {
        if (!jsonData) return;
        const client = JSON.parse(jsonData);
        document.getElementById('company_name').value = client.name || '';
        document.getElementById('phone').value = client.phone || '';
        document.getElementById('area').value = client.area || 'budapest';
        document.getElementById('website').value = client.website || '';
    }
    
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
                const data = await resp.json();
                if (data.success) {
                    alert(`✅ ${data.added} kulcsszó mentve (${data.total - data.added} már létezett)`);
                    location.reload();
                }
            } catch (err) {
                alert('Hiba: ' + err.message);
            }
            
            btn.disabled = false;
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
    </script>
</body>
</html>
