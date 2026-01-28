<?php
/**
 * AdMaster Pro v2.0 - API Endpoint
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/ProjectManager.php';
require_once __DIR__ . '/includes/CsvAnalyzer.php';
require_once __DIR__ . '/includes/LandingPageAnalyzer.php';

Security::initSession();

// Csak POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse(['error' => 'Method not allowed'], 405);
}

// CSRF ellenőrzés
if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    Security::log('warning', 'CSRF token failed');
    http_response_code(403);
    echo renderError('Biztonsági hiba. Frissítsd az oldalt.');
    exit;
}

// Rate limit
if (!Security::checkRateLimit('api')) {
    http_response_code(429);
    echo renderError('Túl sok kérés. Várj egy kicsit.');
    exit;
}

$industries = require __DIR__ . '/data/industries.php';
require_once __DIR__ . '/includes/ClientManager.php';
require_once __DIR__ . '/data/strategies.php';

$action = Security::sanitizeInput($_POST['action'] ?? '', 'alpha');

try {
    match($action) {
        'generate_all', 'generateall' => handleGenerateAll($industries),
        'generate_ads', 'generateads' => handleGenerateAds($industries),
        'generate_callonly', 'generatecallonly' => handleGenerateCallOnly($industries),
        'generate_settings', 'generatesettings' => handleGenerateSettings($industries),
        'generate_extensions', 'generateextensions' => handleGenerateExtensions($industries),
        'generate_keywords', 'generatekeywords' => handleGenerateKeywords($industries),
        'generate_script', 'generatescript' => handleGenerateScript(),
        'analyze_csv', 'analyzecsv' => handleAnalyzeCsv(),
        'analyze_landing', 'analyzelanding' => handleAnalyzeLanding($industries),
        'save_project', 'saveproject' => handleSaveProject(),
        'delete_project', 'deleteproject' => handleDeleteProject(),
        'save_client', 'saveclient' => handleSaveClient(),
        'delete_client', 'deleteclient' => handleDeleteClient(),
        'save_headline', 'saveheadline' => handleSaveHeadline(),
        'delete_headline', 'deleteheadline' => handleDeleteHeadline(),
        default => print renderError('Ismeretlen művelet')
    };
} catch (Exception $e) {
    Security::log('error', 'API error: ' . $e->getMessage());
    echo renderError(APP_DEBUG ? $e->getMessage() : 'Váratlan hiba történt.');
}

// === CLIENT HANDLERS ===
function handleSaveClient(): void {
    $cm = new ClientManager();
    $client = [
        'id' => $_POST['id'] ?? null,
        'name' => Security::sanitizeInput($_POST['name'] ?? '', 'string'),
        'industry' => Security::sanitizeInput($_POST['industry'] ?? '', 'alpha'),
        'phone' => Security::sanitizeInput($_POST['phone'] ?? '', 'phone'),
        'area' => Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha'),
        'website' => Security::sanitizeInput($_POST['website'] ?? '', 'url')
    ];
    
    if (empty($client['name'])) {
        echo json_encode(['success' => false, 'error' => 'Cégnév kötelező']);
        return;
    }
    
    $id = $cm->saveClient($client);
    echo json_encode(['success' => true, 'id' => $id]);
}

function handleDeleteClient(): void {
    $cm = new ClientManager();
    $id = Security::sanitizeInput($_POST['id'] ?? '', 'string');
    $result = $cm->deleteClient($id);
    echo json_encode(['success' => $result]);
}

function handleSaveHeadline(): void {
    $cm = new ClientManager();
    $type = Security::sanitizeInput($_POST['type'] ?? 'headline', 'alpha');
    $text = Security::sanitizeInput($_POST['text'] ?? '', 'string');
    $rating = (int)($_POST['rating'] ?? 3);
    $industry = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    
    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Szöveg kötelező']);
        return;
    }
    
    $result = $type === 'description' 
        ? $cm->addDescription($text, $rating, $industry)
        : $cm->addHeadline($text, $rating, $industry);
    
    echo json_encode(['success' => $result]);
}

function handleDeleteHeadline(): void {
    $cm = new ClientManager();
    $id = Security::sanitizeInput($_POST['id'] ?? '', 'string');
    $result = $cm->deleteHeadline($id);
    echo json_encode(['success' => $result]);
}

// === GENERATE ALL - WIZARD HANDLER ===
function handleGenerateAll(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    $goal = Security::sanitizeInput($_POST['goal'] ?? '', 'alpha');
    
    if (!isset($industries[$industry_key])) {
        echo renderError('Érvénytelen iparág.');
        return;
    }
    
    $industry = $industries[$industry_key];
    $company_name = Security::sanitizeInput($_POST['company_name'] ?? '', 'string');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '', 'phone');
    $area = Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha');
    $ad_type = Security::sanitizeInput($_POST['ad_type'] ?? 'callonly', 'alpha');
    $bid_strategy = Security::sanitizeInput($_POST['bid_strategy'] ?? 'maximize_conversions', 'alphanumeric');
    
    // Extra opciók
    $extra_aggressive = isset($_POST['extra_aggressive']);
    $extra_trust = isset($_POST['extra_trust']);
    $extra_price = isset($_POST['extra_price']);
    $use_saved = isset($_POST['use_saved_headlines']);
    $save_client = isset($_POST['save_client']);
    
    $usps = Security::sanitizeArray($_POST['usps'] ?? [], 'alphanumeric');
    
    if (empty($company_name)) {
        echo renderError('A cégnév megadása kötelező.');
        return;
    }
    
    // Ügyfél mentése ha kérték
    if ($save_client) {
        $cm = new ClientManager();
        $cm->saveClient([
            'name' => $company_name,
            'industry' => $industry_key,
            'phone' => $phone,
            'area' => $area,
            'website' => $_POST['website'] ?? ''
        ]);
    }
    
    $usp_texts = [];
    foreach ($industry['usps'] as $usp) {
        if (in_array($usp['id'], $usps)) {
            $usp_texts[] = $usp['text'];
        }
    }
    
    $area_text = match($area) { 'budapest' => 'Budapest', 'videk' => 'Vidék', 'orszagos' => 'Országos', default => $area };
    
    // Extra headlines összegyűjtése
    $extra_headlines = $GLOBALS['extra_headlines'][$industry_key] ?? [];
    $extra_texts = [];
    if ($extra_aggressive && isset($extra_headlines['aggressive'])) {
        $extra_texts = array_merge($extra_texts, array_slice($extra_headlines['aggressive'], 0, 5));
    }
    if ($extra_trust && isset($extra_headlines['trust'])) {
        $extra_texts = array_merge($extra_texts, array_slice($extra_headlines['trust'], 0, 3));
    }
    if ($extra_price && isset($extra_headlines['price'])) {
        $extra_texts = array_merge($extra_texts, array_slice($extra_headlines['price'], 0, 3));
    }
    
    // Változók cseréje az extra headline-okban
    $vars = $GLOBALS['headline_variables'] ?? [];
    foreach ($extra_texts as &$text) {
        foreach ($vars as $var => $values) {
            if (strpos($text, $var) !== false) {
                $text = str_replace($var, $values[array_rand($values)], $text);
            }
        }
    }
    
    // Bevált saját szövegek
    $saved_headlines = [];
    if ($use_saved) {
        $cm = new ClientManager();
        $saved = $cm->getHeadlinesForIndustry($industry_key);
        foreach ($saved as $h) {
            if ($h['rating'] >= 3) {
                $saved_headlines[] = $h['text'];
            }
        }
    }
    
    // Build prompt
    $extra_instruction = '';
    if (!empty($extra_texts)) {
        $extra_instruction = "\n\nHASZNÁLD EZEKET AZ EXTRA HEADLINE SABLONOKAT (figyelemfelkeltőbbek):\n" . implode("\n", array_slice($extra_texts, 0, 8));
    }
    if (!empty($saved_headlines)) {
        $extra_instruction .= "\n\nBEVÁLT SAJÁT HEADLINE-OK (használd ezeket is):\n" . implode("\n", array_slice($saved_headlines, 0, 5));
    }
    
    $prompt = "Te egy magyar Google Ads szakértő vagy. Generálj AGRESSZÍV, FIGYELEMFELKELTŐ kampány anyagot.

IPARÁG: {$industry['name']}
CÉG: $company_name | Telefon: " . ($phone ?: 'nincs') . " | Terület: $area_text
USP-k: " . (count($usp_texts) ? implode(', ', $usp_texts) : 'nincs') . "
TÍPUS: " . ($ad_type === 'callonly' ? 'Call-Only' : ($ad_type === 'both' ? 'Mindkettő' : 'RSA')) . "
$extra_instruction

A SZÖVEGEK LEGYENEK:
- SÜRGŐSSÉGRE ÖSZTÖNZŐK (\"Most\", \"Azonnal\", számok)
- EGYEDIEK, NE ÁTLAGOSAK
- BIZALOMÉPÍTŐK (garancia, tapasztalat, értékelés)
- ÉRZELMI TRIGGEREK (félelem a problémától, megoldás öröme)

GENERÁLJ JSON-ben:
{
    \"headlines\": [{\"text\": \"max 30 kar\"}] (15 db, legyenek EGYEDIEK és AGRESSZÍVEK),
    \"descriptions\": [{\"text\": \"max 90 kar\"}] (4 db),
    \"callonly\": [{\"business\": \"max 25\", \"desc1\": \"max 35\", \"desc2\": \"max 35\"}] (5 db),
    \"sitelinks\": [{\"title\": \"...\", \"desc\": \"...\"}] (4 db),
    \"callouts\": [\"max 25 kar\"] (6 db),
    \"keywords\": [\"kulcsszó\"] (10 db),
    \"negatives\": [\"negatív\"] (10 db)
}";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    // Extra headlines hozzáadása az eredményhez
    if (!empty($extra_texts) && isset($data['headlines'])) {
        foreach (array_slice($extra_texts, 0, 5) as $et) {
            if (mb_strlen($et) <= 30) {
                array_unshift($data['headlines'], ['text' => $et, 'extra' => true]);
            }
        }
    }
    
    // Render results
    echo '<div class="results-wrapper">';
    echo '<div class="alert alert-success">✅ <strong>Kampány anyagok elkészültek!</strong>';
    if ($save_client) echo ' <span style="opacity:0.7">(Ügyfél mentve)</span>';
    echo '</div>';
    
    // Settings summary
    $bid_names = ['maximize_conversions'=>'Konverzió Max.', 'target_cpa'=>'Cél CPA', 'maximize_clicks'=>'Kattintás Max.', 'manual_cpc'=>'Manuális CPC'];
    echo '<div class="card"><h3 class="card-title">⚙️ Beállítások</h3>';
    echo '<p><strong>Bid:</strong> '.($bid_names[$bid_strategy] ?? $bid_strategy).' | ';
    echo '<strong>Büdzsé:</strong> '.number_format($industry['benchmarks']['daily_budget_recommended'],0,',',' ').' Ft/nap</p></div>';
    
    // Headlines (RSA)
    if (!empty($data['headlines']) && $ad_type !== 'callonly') {
        echo '<div class="card"><h3 class="card-title">📝 Headlines ('.count($data['headlines']).')</h3>';
        echo '<div class="copy-list">';
        foreach ($data['headlines'] as $h) {
            $text = $h['text'] ?? $h;
            echo '<div class="copy-item"><span>'.htmlspecialchars($text).'</span><small>'.mb_strlen($text).'/30</small></div>';
        }
        echo '</div></div>';
    }
    
    // Call-Only
    if (!empty($data['callonly']) && ($ad_type === 'callonly' || $ad_type === 'both')) {
        echo '<div class="card"><h3 class="card-title">📞 Call-Only ('.count($data['callonly']).')</h3>';
        echo '<p class="help-text">Tel: '.htmlspecialchars($phone).'</p>';
        foreach ($data['callonly'] as $ad) {
            echo '<div class="callonly-box">';
            echo '<div class="co-name">'.htmlspecialchars($ad['business'] ?? $ad['business_name'] ?? '').'</div>';
            echo '<div class="co-desc">'.htmlspecialchars($ad['desc1'] ?? $ad['description1'] ?? '').'</div>';
            echo '<div class="co-desc">'.htmlspecialchars($ad['desc2'] ?? $ad['description2'] ?? '').'</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Sitelinks
    if (!empty($data['sitelinks'])) {
        echo '<div class="card"><h3 class="card-title">🔗 Sitelinks</h3><div class="tags-list">';
        foreach ($data['sitelinks'] as $sl) {
            echo '<span class="tag tag-blue">'.htmlspecialchars($sl['title'] ?? $sl).'</span>';
        }
        echo '</div></div>';
    }
    
    // Callouts
    if (!empty($data['callouts'])) {
        echo '<div class="card"><h3 class="card-title">📢 Callouts</h3><div class="tags-list">';
        foreach ($data['callouts'] as $c) {
            echo '<span class="tag tag-green">'.htmlspecialchars($c).'</span>';
        }
        echo '</div></div>';
    }
    
    // Keywords
    if (!empty($data['keywords'])) {
        echo '<div class="card"><h3 class="card-title">🔤 Kulcsszavak</h3><div class="tags-list">';
        foreach ($data['keywords'] as $k) {
            echo '<span class="tag">'.htmlspecialchars(is_array($k) ? $k['keyword'] : $k).'</span>';
        }
        echo '</div></div>';
    }
    
    // Negatives
    if (!empty($data['negatives'])) {
        echo '<div class="card"><h3 class="card-title">🚫 Negatív kulcsszavak</h3><div class="tags-list">';
        foreach ($data['negatives'] as $n) {
            echo '<span class="tag tag-red">'.htmlspecialchars($n).'</span>';
        }
        echo '</div></div>';
    }
    
    echo '</div>';
}

// === HANDLERS ===

function handleGenerateAds(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    if (!isset($industries[$industry_key])) {
        echo renderError('Érvénytelen iparág.');
        return;
    }
    
    $industry = $industries[$industry_key];
    $company_name = Security::sanitizeInput($_POST['company_name'] ?? '', 'string');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '', 'phone');
    $area = Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha');
    $price = Security::sanitizeInput($_POST['price'] ?? '', 'string');
    $usps = Security::sanitizeArray($_POST['usps'] ?? [], 'alphanumeric');
    
    if (empty($company_name)) {
        echo renderError('A cégnév megadása kötelező.');
        return;
    }
    
    // Konkurensek
    $competitors = [];
    for ($i = 1; $i <= 3; $i++) {
        $comp = trim($_POST['competitor_' . $i] ?? '');
        if ($comp) $competitors[] = Security::sanitizeInput($comp, 'text');
    }
    
    // USP szövegek
    $usp_texts = [];
    foreach ($industry['usps'] as $usp) {
        if (in_array($usp['id'], $usps)) {
            $usp_texts[] = $usp['text'];
        }
    }
    
    $area_map = ['budapest' => 'Budapest és környéke', 'videk' => 'Vidék', 'orszagos' => 'Országos'];
    $area_text = $area_map[$area] ?? $area;
    
    // Session mentés
    $_SESSION['form_data'] = $_POST;
    
    // AI Prompt
    $prompt = buildAdsPrompt($industry, $company_name, $phone, $area_text, $price, $usp_texts, $competitors);
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    renderAdResults($data, count($competitors) > 0);
}

// === CALL-ONLY HANDLER ===
function handleGenerateCallOnly(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    if (!isset($industries[$industry_key])) {
        echo renderError('Érvénytelen iparág.');
        return;
    }
    
    $industry = $industries[$industry_key];
    $company_name = Security::sanitizeInput($_POST['company_name'] ?? '', 'string');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '', 'phone');
    $area = Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha');
    $usps = Security::sanitizeArray($_POST['usps'] ?? [], 'alphanumeric');
    
    if (empty($company_name)) {
        echo renderError('A cégnév megadása kötelező.');
        return;
    }
    
    if (empty($phone)) {
        echo renderError('Call-Only hirdetéshez a telefonszám megadása kötelező!');
        return;
    }
    
    $usp_texts = [];
    foreach ($industry['usps'] as $usp) {
        if (in_array($usp['id'], $usps)) {
            $usp_texts[] = $usp['text'];
        }
    }
    
    $area_map = ['budapest' => 'Budapest', 'videk' => 'Vidék', 'orszagos' => 'Országosan'];
    $area_text = $area_map[$area] ?? $area;
    
    $prompt = buildCallOnlyPrompt($industry, $company_name, $phone, $area_text, $usp_texts);
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    renderCallOnlyResults($data, $phone);
}

function buildCallOnlyPrompt(array $industry, string $company, string $phone, string $area, array $usps): string {
    return "Generálj Google Ads CALL-ONLY hirdetéseket magyarul egy {$industry['name']} cégnek.

CÉG ADATOK:
- Cégnév: $company
- Telefon: $phone
- Terület: $area
- USP-k: " . (count($usps) ? implode(', ', $usps) : 'Nincs megadva') . "

FONTOS SZABÁLYOK CALL-ONLY HIRDETÉSEKNÉL:
1. Business Name: Max 25 karakter (cégnév, nem a teljes név!)
2. Description 1: Max 35 karakter - fő előny/USP
3. Description 2: Max 35 karakter - másodlagos előny, CTA

A SZÖVEGEK LEGYENEK:
- Sürgősségre ösztönzők ('Most', 'Azonnal', 'Hívj')
- Rövidek és velősek
- Bizalomépítők (garancia, tapasztalat)
- Cselekvésre ösztönzők

Válaszolj CSAK JSON formátumban:
{
    \"callonly_ads\": [
        {
            \"business_name\": \"...\",
            \"description1\": \"...\",
            \"description2\": \"...\",
            \"final_url_note\": \"Ellenőrző URL - a cég weboldalára mutasson\"
        }
    ],
    \"tips\": [\"...\", \"...\"]
}

Generálj 5 különböző variációt!";
}

// === SCRIPT GENERATOR HANDLER ===
function handleGenerateScript(): void {
    $script_type = Security::sanitizeInput($_POST['script_type'] ?? '', 'alphanumeric');
    $email = Security::sanitizeInput($_POST['notification_email'] ?? '', 'email');
    $custom_params = Security::sanitizeInput($_POST['custom_params'] ?? '', 'text');
    
    if (empty($script_type)) {
        echo renderError('Válassz script típust!');
        return;
    }
    
    $templates = $GLOBALS['script_templates'] ?? [];
    if (!isset($templates[$script_type])) {
        echo renderError('Ismeretlen script típus.');
        return;
    }
    
    $template = $templates[$script_type];
    
    $prompt = buildScriptPrompt($template, $email, $custom_params);
    
    $response = callAnthropicAPI($prompt);
    
    // A script kód nem JSON, hanem nyers JS
    renderScriptResults($response, $template);
}

function buildScriptPrompt(array $template, string $email, string $custom_params): string {
    $emailLine = $email ? "E-mail értesítés címe: $email" : "E-mail értesítés: nincs megadva (kommenteld ki az e-mail küldő részt)";
    
    return "Generálj egy működő Google Ads Script-et (JavaScript) az alábbi célra:

SCRIPT CÉL: {$template['name']}
LEÍRÁS: {$template['description']}
$emailLine
" . ($custom_params ? "EXTRA PARAMÉTEREK: $custom_params" : "") . "

KÖVETELMÉNYEK:
1. A kód legyen TELJESEN MŰKÖDŐKÉPES és beilleszthető a Google Ads Scripts felületre
2. Használd a Google Ads API helyes szintaxisát (AdsApp objektum)
3. Adj hozzá magyar nyelvű kommenteket a kód magyarázatához
4. A kód elején legyen CONFIG szekció a testreszabható értékekkel
5. Legyen hibakezelt (try-catch)
6. Ha van e-mail, használd a MailApp.sendEmail() funkciót

FONTOS: Válaszolj CSAK a JavaScript kóddal, semmi más szöveggel!
Ne használj markdown code block-ot, csak a nyers JS kódot add vissza.";
}

function handleGenerateSettings(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    if (!isset($industries[$industry_key])) {
        echo renderError('Érvénytelen iparág.');
        return;
    }
    
    $industry = $industries[$industry_key];
    $area = Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha');
    $bid_strategy = Security::sanitizeInput($_POST['bid_strategy'] ?? 'target_cpa', 'alphanumeric');
    $target_cpa = Security::sanitizeInput($_POST['target_cpa'] ?? '', 'number');
    $target_cpc = Security::sanitizeInput($_POST['target_cpc'] ?? '', 'number');
    
    $_SESSION['form_data'] = $_POST;
    
    $prompt = buildSettingsPrompt($industry, $area, $bid_strategy, $target_cpa, $target_cpc);
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    renderSettingsResults($data);
}

function handleAnalyzeCsv(): void {
    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo renderError('Nincs fájl feltöltve vagy hiba történt.');
        return;
    }
    
    $errors = Security::validateUpload($_FILES['csv_file']);
    if (!empty($errors)) {
        echo renderError(implode('<br>', $errors));
        return;
    }
    
    $analyzer = new CsvAnalyzer();
    $parseResult = $analyzer->parseFile($_FILES['csv_file']['tmp_name']);
    
    if (!$parseResult['success']) {
        echo renderError($parseResult['error']);
        return;
    }
    
    $analysis = $analyzer->analyze();
    renderCsvResults($analysis);
}

function handleAnalyzeLanding(array $industries): void {
    $url = trim($_POST['landing_url'] ?? '');
    
    if (empty($url)) {
        echo renderError('Az URL megadása kötelező.');
        return;
    }
    
    // URL validálás
    if (!filter_var($url, FILTER_VALIDATE_URL) && !filter_var('https://' . $url, FILTER_VALIDATE_URL)) {
        echo renderError('Érvénytelen URL formátum.');
        return;
    }
    
    // Iparág betöltése ha van
    $industry = [];
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    if (isset($industries[$industry_key])) {
        $industry = $industries[$industry_key];
    }
    
    try {
        $analyzer = new LandingPageAnalyzer($url, $industry);
        
        if (!$analyzer->fetch()) {
            echo renderError('Nem sikerült letölteni az oldalt: ' . implode(', ', $analyzer->getErrors()));
            return;
        }
        
        $result = $analyzer->analyze();
        
        if (!$result['success']) {
            echo renderError($result['error']);
            return;
        }
        
        renderLandingResults($result);
        
    } catch (Exception $e) {
        echo renderError('Elemzési hiba: ' . $e->getMessage());
    }
}

// === EXTENSIONS GENERATOR ===
function handleGenerateExtensions(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    if (!isset($industries[$industry_key])) {
        echo renderError('Érvénytelen iparág.');
        return;
    }
    
    $industry = $industries[$industry_key];
    $company_name = Security::sanitizeInput($_POST['company_name'] ?? '', 'string');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '', 'string');
    $area = Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha');
    
    $usps = $_POST['usps'] ?? [];
    $usps = array_map(fn($u) => Security::sanitizeInput($u, 'alphanumeric'), (array)$usps);
    
    $usp_texts = [];
    foreach ($industry['usps'] as $usp) {
        if (in_array($usp['id'], $usps)) {
            $usp_texts[] = $usp['text'];
        }
    }
    
    $prompt = buildExtensionsPrompt($industry, $company_name, $phone, $area, $usp_texts);
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    renderExtensionsResults($data);
}

function buildExtensionsPrompt(array $industry, string $company, string $phone, string $area, array $usps): string {
    $area_text = match($area) {
        'budapest' => 'Budapest és környéke',
        'videk' => 'Vidék',
        'orszagos' => 'Országos',
        default => $area
    };
    
    return "Generálj Google Ads bővítményeket (extensions) egy magyar {$industry['name']} cégnek.

CÉG ADATOK:
- Név: $company
- Telefon: $phone
- Terület: $area_text
- USP-k: " . implode(', ', $usps) . "

FELADAT:
Készíts professzionális Google Ads bővítményeket:

1. SITELINK EXTENSIONS (4-6 db):
- Minden sitelink: rövid címsor (max 25 karakter) + 2 soros leírás (max 35 karakter soronként)
- Releváns landing page javaslat
- Magyarul, szolgáltatás-specifikus

2. CALLOUT EXTENSIONS (6-8 db):
- Max 25 karakter / callout
- Előnyök, USP-k kiemelése
- Ne ismételd a hirdetés szövegét

3. STRUCTURED SNIPPETS (2-3 header):
- Header típus + értékek
- Pl: Szolgáltatások, Típusok, Márkák

4. CALL EXTENSION:
- Javaslat a hívási ütemezésre

Válaszolj CSAK JSON formátumban:
{
    \"sitelinks\": [
        {\"title\": \"...\", \"description1\": \"...\", \"description2\": \"...\", \"url_hint\": \"...\"}
    ],
    \"callouts\": [\"...\", \"...\"],
    \"structured_snippets\": [
        {\"header\": \"Szolgáltatások\", \"values\": [\"...\", \"...\"]}
    ],
    \"call_schedule\": \"...\"
}";
}

// === KEYWORD PERMUTATOR ===
function handleGenerateKeywords(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alpha');
    if (!isset($industries[$industry_key])) {
        echo renderError('Érvénytelen iparág.');
        return;
    }
    
    $industry = $industries[$industry_key];
    $area = Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha');
    $seed_keywords = Security::sanitizeInput($_POST['seed_keywords'] ?? '', 'string');
    
    // Ha van seed kulcsszó, használjuk azt, különben az iparág kulcsszavait
    $keywords = !empty($seed_keywords) 
        ? array_filter(array_map('trim', explode("\n", $seed_keywords)))
        : array_merge($industry['keywords']['primary'], $industry['keywords']['secondary']);
    
    $result = generateKeywordPermutations($keywords, $area, $industry);
    
    renderKeywordsResults($result, $industry);
}

function generateKeywordPermutations(array $keywords, string $area, array $industry): array {
    // Földrajzi módosítók
    $locations = match($area) {
        'budapest' => ['budapest', 'budapesten', 'bp', 'pest', 'buda', 'óbuda', 'újpest', 'kispest', 'zugló', 'angyalföld'],
        'videk' => ['debrecen', 'szeged', 'miskolc', 'pécs', 'győr', 'nyíregyháza', 'kecskemét', 'székesfehérvár'],
        'orszagos' => ['magyarország', 'országosan', 'budapest', 'vidék'],
        default => ['budapest']
    };
    
    // Módosítók
    $modifiers = [
        'intent' => ['árak', 'olcsó', 'gyors', 'sürgős', 'azonnal', 'non-stop', '24 órás', 'hétvégi'],
        'quality' => ['profi', 'szakértő', 'megbízható', 'garantált', 'minőségi', 'legjobb'],
        'action' => ['keres', 'keresek', 'hívás', 'rendelés', 'árajánlat']
    ];
    
    $permutations = [
        'exact' => [],      // [keyword]
        'phrase' => [],     // "keyword"
        'broad_mod' => [],  // +keyword
        'longtail' => []    // kombinációk
    ];
    
    foreach ($keywords as $kw) {
        $kw = trim(mb_strtolower($kw));
        if (empty($kw)) continue;
        
        // Exact match
        $permutations['exact'][] = "[$kw]";
        
        // Phrase match
        $permutations['phrase'][] = "\"$kw\"";
        
        // Broad match modifier
        $words = explode(' ', $kw);
        $permutations['broad_mod'][] = '+' . implode(' +', $words);
        
        // Long-tail kombinációk
        foreach ($locations as $loc) {
            $permutations['longtail'][] = "$kw $loc";
            $permutations['longtail'][] = "$loc $kw";
        }
        
        foreach ($modifiers['intent'] as $mod) {
            $permutations['longtail'][] = "$mod $kw";
            $permutations['longtail'][] = "$kw $mod";
        }
        
        foreach ($modifiers['quality'] as $mod) {
            $permutations['longtail'][] = "$mod $kw";
        }
    }
    
    // Duplikátumok eltávolítása
    foreach ($permutations as $type => $list) {
        $permutations[$type] = array_unique($list);
    }
    
    return [
        'permutations' => $permutations,
        'stats' => [
            'exact_count' => count($permutations['exact']),
            'phrase_count' => count($permutations['phrase']),
            'broad_mod_count' => count($permutations['broad_mod']),
            'longtail_count' => count($permutations['longtail']),
            'total' => count($permutations['exact']) + count($permutations['phrase']) + 
                      count($permutations['broad_mod']) + count($permutations['longtail'])
        ]
    ];
}

function handleSaveProject(): void {
    $name = Security::sanitizeInput($_POST['project_name'] ?? '', 'string');
    if (empty($name)) {
        Security::jsonResponse(['success' => false, 'error' => 'Projekt név kötelező']);
        return;
    }
    
    $data = [
        'industry' => $_POST['industry'] ?? '',
        'company_name' => $_POST['company_name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'area' => $_POST['area'] ?? '',
        'price' => $_POST['price'] ?? '',
        'usps' => $_POST['usps'] ?? [],
        'bid_strategy' => $_POST['bid_strategy'] ?? '',
        'target_cpa' => $_POST['target_cpa'] ?? '',
        'target_cpc' => $_POST['target_cpc'] ?? ''
    ];
    
    $pm = new ProjectManager();
    $result = $pm->save($name, $data);
    
    Security::jsonResponse($result);
}

function handleDeleteProject(): void {
    $id = Security::sanitizeInput($_POST['project_id'] ?? '', 'alphanumeric');
    $pm = new ProjectManager();
    Security::jsonResponse($pm->delete($id));
}

// === API CALL ===

function callAnthropicAPI(string $prompt): string {
    // Demo mód - nincs valódi API hívás
    if (defined('DEMO_MODE') && DEMO_MODE) {
        return getDemoResponse($prompt);
    }
    
    if (!Security::validateApiKey()) {
        throw new Exception('API kulcs nincs beállítva! Állítsd be a config.php-ban vagy kapcsold be a DEMO_MODE-ot.');
    }
    
    $data = [
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 4000,
        'messages' => [['role' => 'user', 'content' => $prompt]]
    ];
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Kapcsolódási hiba: $error. Ellenőrizd az internet kapcsolatot.");
    }
    
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $errMsg = $err['error']['message'] ?? "HTTP hiba: $httpCode";
        
        if ($httpCode === 401) {
            throw new Exception("Érvénytelen API kulcs! Ellenőrizd a config.php-ban.");
        } elseif ($httpCode === 429) {
            throw new Exception("API rate limit túllépve. Várj egy kicsit.");
        } elseif ($httpCode === 500) {
            throw new Exception("Anthropic szerver hiba. Próbáld újra később.");
        }
        
        throw new Exception($errMsg);
    }
    
    $result = json_decode($response, true);
    return implode('', array_map(fn($b) => $b['text'] ?? '', $result['content'] ?? []));
}

/**
 * Demo válaszok API nélkül
 */
function getDemoResponse(string $prompt): string {
    // Hirdetés generálás
    if (strpos($prompt, 'RSA hirdetésszövegeket') !== false || strpos($prompt, 'headline') !== false) {
        return json_encode([
            'headlines' => [
                ['text' => '24 Órás Duguláselhárítás', 'pin' => 'H1', 'chars' => 24],
                ['text' => 'Azonnal Megyünk!', 'pin' => 'H2', 'chars' => 16],
                ['text' => 'Garanciával Dolgozunk', 'pin' => null, 'chars' => 21],
                ['text' => 'Fix Árak - Nincs Rejtett', 'pin' => null, 'chars' => 24],
                ['text' => 'Hívjon Most: Gyors Segítség', 'pin' => null, 'chars' => 27],
                ['text' => '30 Perc Alatt Nálad', 'pin' => 'H3', 'chars' => 19],
                ['text' => 'Hétvégén Is Dolgozunk', 'pin' => null, 'chars' => 21],
                ['text' => '15+ Év Tapasztalat', 'pin' => null, 'chars' => 18],
                ['text' => 'Budapest És Környéke', 'pin' => null, 'chars' => 20],
                ['text' => 'Modern Gépi Technológia', 'pin' => null, 'chars' => 23],
                ['text' => 'Profi Duguláselhárítók', 'pin' => null, 'chars' => 22],
                ['text' => 'Azonnali Számla', 'pin' => null, 'chars' => 15],
                ['text' => 'Ingyenes Felmérés', 'pin' => null, 'chars' => 17],
                ['text' => 'Megbízható Szolgáltatás', 'pin' => null, 'chars' => 23],
                ['text' => 'Hívj Most!', 'pin' => null, 'chars' => 10]
            ],
            'descriptions' => [
                ['text' => 'Duguláselhárítás 24 órában, Budapest egész területén. Gyors kiszállás, fix árak. Hívjon!', 'chars' => 86],
                ['text' => 'Professzionális duguláselhárítás garanciával. 30 percen belül nálad vagyunk. Azonnal hívható!', 'chars' => 89],
                ['text' => 'Modern gépi technológiával dolgozunk. 15+ év tapasztalat, elégedett ügyfelek százai. Hívj!', 'chars' => 87],
                ['text' => 'WC, lefolyó, csatorna dugulás? Azonnal segítünk! Fix árak, nincs rejtett költség. Hívjon!', 'chars' => 86]
            ],
            'competitorAnalysis' => [
                'commonElements' => ['24 órás szolgáltatás', 'Gyors kiszállás', 'Budapest'],
                'unusedOpportunities' => ['Garancia kiemelése', 'Fix árak hangsúlyozása', 'Tapasztalat évek'],
                'differentiationStrategy' => 'Fókuszálj a garanciára és a fix árakra - ezek bizalmat építenek.'
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // Beállítások generálás
    if (strpos($prompt, 'beállítási tanácsot') !== false || strpos($prompt, 'STRATÉGIA') !== false) {
        return json_encode([
            'budget' => [
                'daily' => ['min' => 15000, 'recommended' => 25000, 'max' => 50000],
                'note' => 'Kezdj a minimum büdzsével, majd növeld az eredmények alapján.'
            ],
            'bidStrategy' => [
                'recommendation' => 'Kezdd Konverzió maximalizálással 2 hétig, majd válts Cél CPA-ra ha van elég adat.',
                'startingCpa' => 3500,
                'adjustAfter' => '2 hét, minimum 30 konverzió után'
            ],
            'schedule' => [
                'recommended' => '0-24 óra (sürgősségi szolgáltatás)',
                'bidModifiers' => [
                    ['period' => 'Éjszaka (22:00-06:00)', 'modifier' => '+20%', 'reason' => 'Sürgős hívások, magasabb konverzió'],
                    ['period' => 'Hétvége', 'modifier' => '+10%', 'reason' => 'Több otthoni probléma'],
                    ['period' => 'Hétfő reggel', 'modifier' => '+15%', 'reason' => 'Hétvégi problémák bejelentése']
                ]
            ],
            'targeting' => [
                'location' => 'Budapest + 30 km-es körzet',
                'locationOption' => 'PRESENCE - csak ott tartózkodók!',
                'exclusions' => ['Érdeklődők kizárása', 'Álláskeresők']
            ],
            'devices' => [
                ['device' => 'Mobil', 'modifier' => '+15%', 'reason' => 'Sürgős keresések mobilról'],
                ['device' => 'Desktop', 'modifier' => '0%', 'reason' => 'Alapértelmezett'],
                ['device' => 'Tablet', 'modifier' => '-10%', 'reason' => 'Kevesebb konverzió']
            ],
            'keywords' => [
                'strategy' => 'Kezdj Phrase match-csel, majd bővítsd Broad match modifier-rel ha jól teljesít.',
                'examples' => ['duguláselhárítás budapest', 'duguláselhárító', 'wc dugulás', 'lefolyó tisztítás'],
                'avoid' => 'Kerüld a túl általános kulcsszavakat mint "vízszerelő" - nem elég specifikus.'
            ],
            'tips' => [
                'Állíts be híváskövetést a telefonszámra - ez a fő konverziós pont!',
                'Használj call-only hirdetéseket mobilon',
                'A "sürgős" és "azonnal" szavak növelik a CTR-t ebben az iparágban',
                'Remarketing lista: 7 nap (rövid döntési ciklus)',
                'Teszteld a különböző CTA-kat: "Hívjon" vs "Kérjen árajánlatot"'
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // Extensions generálás
    if (strpos($prompt, 'extensions') !== false || strpos($prompt, 'SITELINK') !== false) {
        return json_encode([
            'sitelinks' => [
                ['title' => 'Áraink', 'description1' => 'Átlátható árazás', 'description2' => 'Nincs rejtett költség', 'url_hint' => '/araink'],
                ['title' => 'Szolgáltatások', 'description1' => 'Teljes körű szolgáltatás', 'description2' => 'Profi csapat', 'url_hint' => '/szolgaltatasok'],
                ['title' => '24 Órás Ügyelet', 'description1' => 'Éjjel-nappal hívható', 'description2' => 'Hétvégén is', 'url_hint' => '/ugyeleti-szolgalat'],
                ['title' => 'Kapcsolat', 'description1' => 'Hívjon most!', 'description2' => 'Gyors válasz garantált', 'url_hint' => '/kapcsolat'],
                ['title' => 'Garanciánk', 'description1' => '100% elégedettség', 'description2' => 'Vagy visszakapja pénzét', 'url_hint' => '/garancia'],
                ['title' => 'Rólunk', 'description1' => '15+ év tapasztalat', 'description2' => '5000+ elégedett ügyfél', 'url_hint' => '/rolunk']
            ],
            'callouts' => [
                '24 Órás Szolgáltatás',
                'Garanciával Dolgozunk',
                'Fix Árak',
                'Gyors Kiszállás',
                '15+ Év Tapasztalat',
                'Hétvégén Is',
                'Azonnali Számla',
                'Profi Csapat'
            ],
            'structured_snippets' => [
                ['header' => 'Szolgáltatások', 'values' => ['WC dugulás', 'Lefolyó tisztítás', 'Csatorna tisztítás', 'Szennyvíz elvezetés']],
                ['header' => 'Típusok', 'values' => ['Lakossági', 'Társasházi', 'Ipari', 'Üzleti']]
            ],
            'call_schedule' => 'Állítsd be 0-24 órás hívhatóságra, mivel ez sürgősségi szolgáltatás. Bid modifier +20% éjszakára (22:00-06:00).'
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // Call-Only generálás
    if (strpos($prompt, 'CALL-ONLY') !== false) {
        return json_encode([
            'callonly_ads' => [
                [
                    'business_name' => 'Gyors Dugulás',
                    'description1' => '24h Sürgősségi Szolgálat',
                    'description2' => 'Hívj Most - 30 Perc!',
                    'final_url_note' => 'Ellenőrző URL - a cég weboldalára mutasson'
                ],
                [
                    'business_name' => 'Dugulás SOS',
                    'description1' => 'Azonnal Megyünk!',
                    'description2' => 'Fix Ár - Garancia',
                    'final_url_note' => 'Ellenőrző URL - a cég weboldalára mutasson'
                ],
                [
                    'business_name' => 'Non-Stop Dugulás',
                    'description1' => 'Éjjel-Nappal Hívható',
                    'description2' => '15 Év Tapasztalat',
                    'final_url_note' => 'Ellenőrző URL - a cég weboldalára mutasson'
                ],
                [
                    'business_name' => 'Profi Dugulás',
                    'description1' => 'Garanciával Dolgozunk',
                    'description2' => 'Hétvégén Is!',
                    'final_url_note' => 'Ellenőrző URL - a cég weboldalára mutasson'
                ],
                [
                    'business_name' => 'Duguláselhárító',
                    'description1' => 'Budapest - Környék',
                    'description2' => 'Azonnali Indulás!',
                    'final_url_note' => 'Ellenőrző URL - a cég weboldalára mutasson'
                ]
            ],
            'tips' => [
                'Call-Only hirdetések CSAK mobilon jelennek meg',
                'A Description 2 végén használj felszólítást: "Hívj!", "Most!"',
                'Éjszakai órákban +20-30% bid modifier ajánlott',
                'A hívások konverzióként lesznek rögzítve - állítsd be a híváskövetést!'
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // Script generálás
    if (strpos($prompt, 'Google Ads Script') !== false) {
        return "/**
 * AdMaster Pro - Link Checker Script
 * Ellenőrzi a landing page-ek elérhetőségét
 * 
 * DEMO MÓD - Ez egy minta kód
 */

// ============ KONFIGURÁCIÓ ============
var CONFIG = {
  // E-mail cím az értesítésekhez
  EMAIL: 'pelda@email.hu',
  
  // Ellenőrzési időköz (órában)
  CHECK_INTERVAL: 1,
  
  // Hibás státuszkódok
  ERROR_CODES: [404, 500, 502, 503]
};

// ============ FŐ FUNKCIÓ ============
function main() {
  var campaigns = AdsApp.campaigns()
    .withCondition('Status = ENABLED')
    .get();
  
  var errors = [];
  
  while (campaigns.hasNext()) {
    var campaign = campaigns.next();
    var ads = campaign.ads().withCondition('Status = ENABLED').get();
    
    while (ads.hasNext()) {
      var ad = ads.next();
      var url = ad.urls().getFinalUrl();
      
      if (url) {
        var response = UrlFetchApp.fetch(url, {muteHttpExceptions: true});
        var code = response.getResponseCode();
        
        if (CONFIG.ERROR_CODES.indexOf(code) !== -1) {
          errors.push({
            campaign: campaign.getName(),
            url: url,
            code: code
          });
        }
      }
    }
  }
  
  // Értesítés küldése ha van hiba
  if (errors.length > 0 && CONFIG.EMAIL) {
    sendAlert(errors);
  }
  
  Logger.log('Ellenőrzés kész. Hibák száma: ' + errors.length);
}

function sendAlert(errors) {
  var subject = '⚠️ Landing Page Hiba - Google Ads';
  var body = 'Az alábbi landing page-ek nem elérhetők:\\n\\n';
  
  errors.forEach(function(err) {
    body += '- ' + err.campaign + ': ' + err.url + ' (HTTP ' + err.code + ')\\n';
  });
  
  MailApp.sendEmail(CONFIG.EMAIL, subject, body);
}";
    }
    
    // Alapértelmezett
    return json_encode(['error' => 'Demo mód - ismeretlen kérés típus'], JSON_UNESCAPED_UNICODE);
}

function parseJsonResponse(string $text): array {
    $text = preg_replace('/```json\s*|\s*```/', '', $text);
    $data = json_decode(trim($text), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON hiba: ' . json_last_error_msg());
    }
    return $data;
}

// === PROMPT BUILDERS ===

function buildAdsPrompt($industry, $company, $phone, $area, $price, $usps, $competitors): string {
    $prompt = "Te egy magyar Google Ads szakértő vagy. Generálj RSA hirdetésszövegeket.

IPARÁG: {$industry['name']}
CÉGNÉV: {$company}
TELEFON: " . ($phone ?: 'nincs') . "
TERÜLET: {$area}
" . ($price ? "ÁR: {$price}" : "") . "

USP-K:
" . (count($usps) ? implode("\n", array_map(fn($u) => "- $u", $usps)) : "- Nincs megadva") . "
";

    if ($competitors) {
        $prompt .= "\nKONKURENSEK (különbözz!):\n";
        foreach ($competitors as $i => $c) {
            $prompt .= ($i+1) . ": \"$c\"\n";
        }
    }

    $prompt .= "
FELADAT:
1. 15 db headline (MAX 30 karakter!)
2. 4 db description (MAX 90 karakter!)
3. PIN javaslat (H1/H2/H3/null)
" . ($competitors ? "4. Konkurencia elemzés" : "") . "

VÁLASZ (csak JSON):
{
  \"headlines\": [{\"text\": \"...\", \"pin\": \"H1\", \"chars\": 25}],
  \"descriptions\": [{\"text\": \"...\", \"chars\": 85}]" . 
  ($competitors ? ",\n  \"competitorAnalysis\": {\"commonElements\": [], \"unusedOpportunities\": [], \"differentiationStrategy\": \"...\"}" : "") . "
}

FONTOS: CSAK JSON, magyar ékezetek, pontos karakterszám!";
    
    return $prompt;
}

function buildSettingsPrompt($industry, $area, $strategy, $cpa, $cpc): string {
    $strategies = [
        'max_conv' => 'Konverzió maximalizálás',
        'target_cpa' => 'Cél CPA',
        'max_clicks' => 'Kattintás maximalizálás',
        'target_cpc' => 'Cél CPC'
    ];
    
    $area_map = ['budapest' => 'Budapest', 'videk' => 'Vidék', 'orszagos' => 'Országos'];
    
    return "Te egy magyar Google Ads szakértő vagy. Adj kampány beállítási tanácsot.

IPARÁG: {$industry['name']}
TERÜLET: " . ($area_map[$area] ?? $area) . "
STRATÉGIA: " . ($strategies[$strategy] ?? $strategy) . "
" . ($cpa ? "CÉL CPA: {$cpa} Ft" : "") . "
" . ($cpc ? "MAX CPC: {$cpc} Ft" : "") . "

BENCHMARKOK:
- CPA: {$industry['benchmarks']['cpa_budapest']}
- CPC: {$industry['benchmarks']['cpc']}
- Napi büdzsé: {$industry['benchmarks']['daily_budget_recommended']} Ft

VÁLASZ (csak JSON):
{
  \"budget\": {\"daily\": {\"min\": 15000, \"recommended\": 25000, \"max\": 50000}, \"note\": \"...\"},
  \"bidStrategy\": {\"recommendation\": \"...\", \"startingCpa\": 3500, \"adjustAfter\": \"2 hét\"},
  \"schedule\": {\"recommended\": \"...\", \"bidModifiers\": [{\"period\": \"...\", \"modifier\": \"+20%\", \"reason\": \"...\"}]},
  \"targeting\": {\"location\": \"...\", \"locationOption\": \"Presence\", \"exclusions\": []},
  \"devices\": [{\"device\": \"Mobil\", \"modifier\": \"+15%\", \"reason\": \"...\"}],
  \"keywords\": {\"strategy\": \"...\", \"examples\": [], \"avoid\": \"...\"},
  \"tips\": [\"...\"]
}";
}

// === RENDERERS ===

function renderError(string $message): string {
    return '<div class="alert alert-error"><strong>Hiba!</strong> ' . $message . '</div>';
}

function renderAdResults(array $data, bool $hasCompetitors): void {
    if ($hasCompetitors && isset($data['competitorAnalysis'])): ?>
    <section class="mb-6">
        <div class="card gradient-card">
            <h3 class="card-title">🔍 Konkurencia Elemzés</h3>
            <div class="analysis-grid">
                <div class="analysis-card warning">
                    <h4>⚠️ Amit mindenki használ</h4>
                    <ul><?php foreach ($data['competitorAnalysis']['commonElements'] ?? [] as $item): ?>
                        <li>• <?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?></ul>
                </div>
                <div class="analysis-card success">
                    <h4>✅ Kihasználatlan</h4>
                    <ul><?php foreach ($data['competitorAnalysis']['unusedOpportunities'] ?? [] as $item): ?>
                        <li>• <?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?></ul>
                </div>
                <div class="analysis-card">
                    <h4>💡 Stratégia</h4>
                    <p><?= htmlspecialchars($data['competitorAnalysis']['differentiationStrategy'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section>
        <div class="results-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Headlines (<?= count($data['headlines'] ?? []) ?>)</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyAllHeadlines()">📋 Másolás</button>
                </div>
                <div id="headlinesList">
                <?php foreach ($data['headlines'] ?? [] as $i => $h): ?>
                    <div class="result-item">
                        <span class="result-num"><?= $i+1 ?>.</span>
                        <span class="result-text"><?= htmlspecialchars($h['text']) ?></span>
                        <span class="result-chars <?= ($h['chars'] ?? mb_strlen($h['text'])) <= 30 ? 'ok' : 'over' ?>">
                            <?= $h['chars'] ?? mb_strlen($h['text']) ?>/30
                        </span>
                        <?php if (!empty($h['pin'])): ?>
                        <span class="result-pin"><?= htmlspecialchars($h['pin']) ?></span>
                        <?php endif; ?>
                        <button class="copy-btn" onclick="copyText('<?= addslashes($h['text']) ?>')">📋</button>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Descriptions (<?= count($data['descriptions'] ?? []) ?>)</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyAllDescriptions()">📋 Másolás</button>
                </div>
                <div id="descriptionsList">
                <?php foreach ($data['descriptions'] ?? [] as $i => $d): ?>
                    <div class="result-item">
                        <span class="result-num"><?= $i+1 ?>.</span>
                        <span class="result-text small"><?= htmlspecialchars($d['text']) ?></span>
                        <span class="result-chars <?= ($d['chars'] ?? mb_strlen($d['text'])) <= 90 ? 'ok' : 'over' ?>">
                            <?= $d['chars'] ?? mb_strlen($d['text']) ?>/90
                        </span>
                        <button class="copy-btn" onclick="copyText('<?= addslashes($d['text']) ?>')">📋</button>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <div class="alert alert-success">✅ Hirdetésszövegek generálva!</div>
    <?php
}

function renderSettingsResults(array $data): void { ?>
    <section class="settings-results">
        <div class="card">
            <h3 class="card-title">💰 Büdzsé</h3>
            <div class="benchmark-grid cols-3">
                <div class="benchmark-card">
                    <div class="benchmark-label">Minimum</div>
                    <div class="benchmark-value"><?= number_format($data['budget']['daily']['min'] ?? 0, 0, ',', '.') ?> Ft/nap</div>
                </div>
                <div class="benchmark-card highlight">
                    <div class="benchmark-label">Ajánlott</div>
                    <div class="benchmark-value green"><?= number_format($data['budget']['daily']['recommended'] ?? 0, 0, ',', '.') ?> Ft/nap</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-label">Maximum</div>
                    <div class="benchmark-value"><?= number_format($data['budget']['daily']['max'] ?? 0, 0, ',', '.') ?> Ft/nap</div>
                </div>
            </div>
            <?php if (!empty($data['budget']['note'])): ?>
            <p class="help-text">💡 <?= htmlspecialchars($data['budget']['note']) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="card-title">🎯 Bid Stratégia</h3>
            <p><?= htmlspecialchars($data['bidStrategy']['recommendation'] ?? '') ?></p>
            <?php if (!empty($data['bidStrategy']['startingCpa'])): ?>
            <p class="text-green">Kezdő CPA: <?= number_format($data['bidStrategy']['startingCpa'], 0, ',', '.') ?> Ft</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="card-title">⏰ Ütemezés</h3>
            <p><strong><?= htmlspecialchars($data['schedule']['recommended'] ?? '') ?></strong></p>
            <?php foreach ($data['schedule']['bidModifiers'] ?? [] as $mod): ?>
            <div class="settings-row">
                <span><?= htmlspecialchars($mod['period']) ?></span>
                <span class="<?= str_starts_with($mod['modifier'], '+') ? 'text-green' : 'text-red' ?>"><?= htmlspecialchars($mod['modifier']) ?></span>
                <span class="text-muted"><?= htmlspecialchars($mod['reason']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3 class="card-title">📍 Célzás</h3>
            <p>📍 <?= htmlspecialchars($data['targeting']['location'] ?? '') ?></p>
            <p class="text-orange">⚠️ <?= htmlspecialchars($data['targeting']['locationOption'] ?? '') ?></p>
        </div>

        <div class="card">
            <h3 class="card-title">📱 Eszközök</h3>
            <?php foreach ($data['devices'] ?? [] as $dev): ?>
            <div class="settings-row">
                <span><?= htmlspecialchars($dev['device']) ?></span>
                <span class="<?= str_starts_with($dev['modifier'], '+') ? 'text-green' : (str_starts_with($dev['modifier'], '-') ? 'text-red' : '') ?>">
                    <?= htmlspecialchars($dev['modifier']) ?>
                </span>
                <span class="text-muted"><?= htmlspecialchars($dev['reason']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3 class="card-title">🔤 Kulcsszavak</h3>
            <p><?= htmlspecialchars($data['keywords']['strategy'] ?? '') ?></p>
            <div class="keyword-tags blue">
                <?php foreach ($data['keywords']['examples'] ?? [] as $kw): ?>
                <span class="keyword-tag">"<?= htmlspecialchars($kw) ?>"</span>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($data['keywords']['avoid'])): ?>
            <p class="text-orange">⚠️ <?= htmlspecialchars($data['keywords']['avoid']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($data['tips'])): ?>
        <div class="card tips-card">
            <h3 class="card-title">💡 Tippek</h3>
            <ul>
                <?php foreach ($data['tips'] as $tip): ?>
                <li><span class="text-yellow">•</span> <?= htmlspecialchars($tip) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </section>
    <div class="alert alert-success">✅ Beállítási javaslatok generálva!</div>
    <?php
}

function renderCsvResults(array $analysis): void { ?>
    <section class="csv-results">
        <div class="card">
            <h3 class="card-title">📊 Összesítés - <?= ucfirst($analysis['report_type'] ?? 'Ismeretlen') ?> Report</h3>
            <div class="benchmark-grid">
                <div class="benchmark-card">
                    <div class="benchmark-value"><?= number_format($analysis['summary']['total_cost'] ?? 0, 0, ',', '.') ?> Ft</div>
                    <div class="benchmark-label">Összes költés</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-value"><?= number_format($analysis['summary']['total_clicks'] ?? 0, 0, ',', '.') ?></div>
                    <div class="benchmark-label">Kattintások</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-value"><?= $analysis['summary']['total_conversions'] ?? 0 ?></div>
                    <div class="benchmark-label">Konverziók</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-value"><?= number_format($analysis['summary']['avg_cpa'] ?? 0, 0, ',', '.') ?> Ft</div>
                    <div class="benchmark-label">Átlag CPA</div>
                </div>
            </div>
        </div>

        <?php foreach ($analysis['recommendations'] ?? [] as $rec): ?>
        <div class="card <?= $rec['type'] === 'critical' ? 'alert-card-error' : ($rec['type'] === 'warning' ? 'alert-card-warning' : '') ?>">
            <h4><?= $rec['priority'] ?>. <?= htmlspecialchars($rec['title']) ?></h4>
            <p><?= htmlspecialchars($rec['description']) ?></p>
            <p class="text-green"><strong>👉 <?= htmlspecialchars($rec['action']) ?></strong></p>
            <?php if (!empty($rec['items'])): ?>
            <ul class="small">
                <?php foreach (array_slice($rec['items'], 0, 5) as $item): ?>
                <li><?= is_array($item) ? htmlspecialchars($item['keyword'] ?? $item['name'] ?? $item['search_term'] ?? json_encode($item)) : htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <div class="alert alert-success">✅ CSV elemzés kész!</div>
    <?php
}

function renderLandingResults(array $result): void {
    $score = $result['score'] ?? ['percentage' => 0, 'grade' => 'N/A', 'color' => 'red'];
    $pct = $score['percentage'] ?? 0;
    $scoreClass = $score['color'] ?? 'red';
    
    // Quality Score becslés a pontszám alapján
    $lpExperience = $pct >= 75 ? 'Above Average' : ($pct >= 50 ? 'Average' : 'Below Average');
    $lpExperienceHu = $pct >= 75 ? 'Átlag feletti' : ($pct >= 50 ? 'Átlagos' : 'Átlag alatti');
    $potentialQs = $pct >= 75 ? '+1-2 pont' : ($pct >= 50 ? '+2-3 pont' : '+3-5 pont');
    $cpcImpact = $pct >= 75 ? 'Minimális megtakarítás' : ($pct >= 50 ? '10-20% CPC csökkenés lehetséges' : '20-40% CPC csökkenés lehetséges');
    ?>
    
    <section class="landing-results">
        <!-- Score Overview -->
        <div class="card">
            <div class="score-header">
                <div class="score-circle <?= $scoreClass ?>">
                    <span class="score-value"><?= $pct ?></span>
                    <span class="score-max">/100</span>
                </div>
                <div class="score-info">
                    <h3>Landing Page Pontszám: <span class="text-<?= $scoreClass ?>"><?= htmlspecialchars($score['grade'] ?? 'N/A') ?></span></h3>
                    <p class="score-url"><?= htmlspecialchars($result['url'] ?? '') ?></p>
                    <p class="score-time">⏱️ Betöltési idő: <?= $result['load_time'] ?? '?' ?>s</p>
                    <?php if (!empty($result['industry'])): ?>
                    <p class="score-industry">🏭 Iparág: <?= htmlspecialchars($result['industry']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quality Score Impact - kalkulált értékek -->
        <div class="card gradient-card-blue">
            <h3 class="card-title">🎯 Google Ads Quality Score Becslés</h3>
            <div class="qs-grid">
                <div class="qs-item">
                    <span class="qs-label">Landing Page Experience:</span>
                    <span class="qs-value <?= $pct >= 75 ? 'text-green' : ($pct >= 50 ? 'text-orange' : 'text-red') ?>">
                        <?= $lpExperienceHu ?>
                    </span>
                </div>
                <div class="qs-item">
                    <span class="qs-label">Javítások után:</span>
                    <span class="qs-value text-green">Átlag feletti</span>
                </div>
                <div class="qs-item">
                    <span class="qs-label">Becsült QS javulás:</span>
                    <span class="qs-value text-green"><?= $potentialQs ?></span>
                </div>
                <div class="qs-item">
                    <span class="qs-label">CPC hatás:</span>
                    <span class="qs-value"><?= $cpcImpact ?></span>
                </div>
            </div>
        </div>

        <!-- Technical Analysis -->
        <div class="card">
            <h3 class="card-title">⚙️ Technikai Elemzés</h3>
            <div class="analysis-items">
                <?php foreach ($result['technical'] ?? [] as $key => $item): ?>
                <div class="analysis-item <?= ($item['status'] ?? false) ? 'success' : 'error' ?>">
                    <span class="analysis-icon"><?= ($item['status'] ?? false) ? '✅' : '❌' ?></span>
                    <span class="analysis-label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    <span class="analysis-message"><?= htmlspecialchars($item['message'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content Analysis -->
        <div class="card">
            <h3 class="card-title">📝 Tartalom Elemzés</h3>
            <div class="analysis-items">
                <?php foreach ($result['content'] ?? [] as $key => $item): ?>
                <div class="analysis-item <?= ($item['status'] ?? false) ? 'success' : 'error' ?>">
                    <span class="analysis-icon"><?= ($item['status'] ?? false) ? '✅' : '❌' ?></span>
                    <span class="analysis-label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    <span class="analysis-message"><?= htmlspecialchars($item['message'] ?? '') ?></span>
                </div>
                <?php if (!empty($item['found']) && is_array($item['found'])): ?>
                <div class="analysis-found">
                    <small>Megtalálva:</small>
                    <?php foreach (array_slice($item['found'], 0, 5) as $found): ?>
                    <span class="tag tag-green"><?= htmlspecialchars($found) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['missing']) && is_array($item['missing'])): ?>
                <div class="analysis-missing">
                    <small>Hiányzik:</small>
                    <?php foreach (array_slice($item['missing'], 0, 3) as $missing): ?>
                    <span class="tag tag-red"><?= htmlspecialchars($missing) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Trust Elements -->
        <div class="card">
            <h3 class="card-title">🛡️ Bizalmi Elemek</h3>
            <div class="analysis-items">
                <?php foreach ($result['trust'] ?? [] as $key => $item): ?>
                <div class="analysis-item <?= ($item['status'] ?? false) ? 'success' : 'warning' ?>">
                    <span class="analysis-icon"><?= ($item['status'] ?? false) ? '✅' : '⚠️' ?></span>
                    <span class="analysis-label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    <span class="analysis-message"><?= htmlspecialchars($item['message'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CTA Analysis -->
        <div class="card">
            <h3 class="card-title">🎯 CTA / Konverzió Elemek</h3>
            <div class="analysis-items">
                <?php foreach ($result['cta'] ?? [] as $key => $item): ?>
                <div class="analysis-item <?= ($item['status'] ?? false) ? 'success' : 'error' ?>">
                    <span class="analysis-icon"><?= ($item['status'] ?? false) ? '✅' : '❌' ?></span>
                    <span class="analysis-label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    <span class="analysis-message"><?= htmlspecialchars($item['message'] ?? '') ?></span>
                </div>
                <?php if (!empty($item['value']) && is_array($item['value'])): ?>
                <div class="analysis-found">
                    <?php foreach (array_slice($item['value'], 0, 5) as $btn): ?>
                    <span class="tag tag-blue"><?= htmlspecialchars($btn) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SEO Analysis -->
        <div class="card">
            <h3 class="card-title">🔍 SEO Elemzés</h3>
            <div class="analysis-items">
                <?php foreach ($result['seo'] ?? [] as $key => $item): ?>
                <div class="analysis-item <?= ($item['status'] ?? false) ? 'success' : 'warning' ?>">
                    <span class="analysis-icon"><?= ($item['status'] ?? false) ? '✅' : '⚠️' ?></span>
                    <span class="analysis-label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    <span class="analysis-message"><?= htmlspecialchars($item['message'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tracking / Analytics -->
        <?php if (!empty($result['tracking'])): ?>
        <div class="card">
            <h3 class="card-title">📊 Követőkódok (GTM/Analytics)</h3>
            <div class="analysis-items">
                <?php foreach ($result['tracking'] ?? [] as $key => $item): 
                    $isCritical = $item['critical'] ?? false;
                    $isDeprecated = $item['deprecated'] ?? false;
                ?>
                <div class="analysis-item <?= ($item['status'] ?? false) ? ($isDeprecated ? 'warning' : 'success') : ($isCritical ? 'error' : 'warning') ?>">
                    <span class="analysis-icon">
                        <?php if ($item['status'] ?? false): ?>
                            <?= $isDeprecated ? '⚠️' : '✅' ?>
                        <?php else: ?>
                            <?= $isCritical ? '❌' : '⚪' ?>
                        <?php endif; ?>
                    </span>
                    <span class="analysis-label"><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    <span class="analysis-message"><?= htmlspecialchars($item['message'] ?? '') ?></span>
                    <?php if (!empty($item['id'])): ?>
                    <span class="tag tag-blue"><?= htmlspecialchars($item['id']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="tracking-summary" style="margin-top: 16px; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px;">
                <?php 
                $hasGtm = $result['tracking']['gtm']['status'] ?? false;
                $hasGa4 = $result['tracking']['ga4']['status'] ?? false;
                $hasGads = $result['tracking']['gads']['status'] ?? false;
                ?>
                <?php if ($hasGtm && $hasGa4 && $hasGads): ?>
                <p class="text-green">✅ <strong>Teljes tracking beállítás!</strong> GTM + GA4 + Google Ads konverzió telepítve.</p>
                <?php elseif (!$hasGads): ?>
                <p class="text-red">⚠️ <strong>Google Ads konverziókövetés hiányzik!</strong> Konverziók nem lesznek mérve.</p>
                <?php elseif (!$hasGa4): ?>
                <p class="text-orange">⚠️ <strong>Google Analytics 4 hiányzik!</strong> Telepítsd a GA4-et az elemzésekhez.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if (!empty($result['recommendations'])): ?>
        <div class="card">
            <h3 class="card-title">📋 Prioritásos Javaslatok (<?= count($result['recommendations']) ?>)</h3>
            <div class="recommendations-list">
                <?php foreach ($result['recommendations'] as $rec): ?>
                <div class="recommendation-item <?= $rec['type'] ?? 'info' ?>">
                    <div class="rec-header">
                        <span class="rec-priority"><?= $rec['priority'] ?? '' ?>.</span>
                        <span class="rec-badge <?= $rec['type'] ?? 'info' ?>">
                            <?= ($rec['type'] ?? '') === 'critical' ? '🔴 Kritikus' : (($rec['type'] ?? '') === 'warning' ? '🟡 Fontos' : '🔵 Javaslat') ?>
                        </span>
                    </div>
                    <h4 class="rec-title"><?= htmlspecialchars($rec['title'] ?? '') ?></h4>
                    <p class="rec-description"><?= htmlspecialchars($rec['description'] ?? '') ?></p>
                    <p class="rec-action">👉 <strong><?= htmlspecialchars($rec['action'] ?? '') ?></strong></p>
                    <?php if (!empty($rec['impact'])): ?>
                    <p class="rec-impact">📊 Hatás: <?= htmlspecialchars($rec['impact']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
    
    <div class="alert alert-success">✅ Landing Page elemzés kész!</div>
    <?php
}

// === EXTENSIONS RESULTS RENDERER ===
function renderExtensionsResults(array $data): void { ?>
    <section class="extensions-results">
        <!-- Sitelinks -->
        <div class="card">
            <h3 class="card-title">🔗 Sitelink Extensions (<?= count($data['sitelinks'] ?? []) ?>)</h3>
            <div class="sitelinks-grid">
                <?php foreach ($data['sitelinks'] ?? [] as $i => $sitelink): ?>
                <div class="sitelink-item">
                    <div class="sitelink-header">
                        <span class="sitelink-num">#<?= $i + 1 ?></span>
                        <button class="copy-btn" onclick="copySitelink(<?= $i ?>)">📋</button>
                    </div>
                    <div class="sitelink-title"><?= htmlspecialchars($sitelink['title'] ?? '') ?></div>
                    <div class="sitelink-desc"><?= htmlspecialchars($sitelink['description1'] ?? '') ?></div>
                    <div class="sitelink-desc"><?= htmlspecialchars($sitelink['description2'] ?? '') ?></div>
                    <div class="sitelink-url">💡 <?= htmlspecialchars($sitelink['url_hint'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Callouts -->
        <div class="card">
            <h3 class="card-title">📢 Callout Extensions (<?= count($data['callouts'] ?? []) ?>)</h3>
            <div class="callouts-grid">
                <?php foreach ($data['callouts'] ?? [] as $callout): ?>
                <div class="callout-item">
                    <span class="callout-text"><?= htmlspecialchars($callout) ?></span>
                    <span class="callout-chars"><?= mb_strlen($callout) ?>/25</span>
                    <button class="copy-btn-sm" onclick="copyText('<?= addslashes($callout) ?>')">📋</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Structured Snippets -->
        <div class="card">
            <h3 class="card-title">📑 Structured Snippets</h3>
            <?php foreach ($data['structured_snippets'] ?? [] as $snippet): ?>
            <div class="snippet-item">
                <div class="snippet-header"><?= htmlspecialchars($snippet['header'] ?? '') ?></div>
                <div class="snippet-values">
                    <?php foreach ($snippet['values'] ?? [] as $val): ?>
                    <span class="snippet-value"><?= htmlspecialchars($val) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Call Schedule -->
        <?php if (!empty($data['call_schedule'])): ?>
        <div class="card">
            <h3 class="card-title">📞 Call Extension Javaslat</h3>
            <p><?= htmlspecialchars($data['call_schedule']) ?></p>
        </div>
        <?php endif; ?>
    </section>
    
    <div class="alert alert-success">✅ Bővítmények generálva!</div>
    <?php
}

// === KEYWORDS RESULTS RENDERER ===
function renderKeywordsResults(array $result, array $industry): void { 
    $perms = $result['permutations'];
    $stats = $result['stats'];
    ?>
    <section class="keywords-results">
        <!-- Stats -->
        <div class="card gradient-card-blue">
            <h3 class="card-title">📊 Kulcsszó Statisztika</h3>
            <div class="benchmark-grid">
                <div class="benchmark-card">
                    <div class="benchmark-value green"><?= $stats['exact_count'] ?></div>
                    <div class="benchmark-label">Exact Match</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-value blue"><?= $stats['phrase_count'] ?></div>
                    <div class="benchmark-label">Phrase Match</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-value orange"><?= $stats['broad_mod_count'] ?></div>
                    <div class="benchmark-label">Broad Mod.</div>
                </div>
                <div class="benchmark-card">
                    <div class="benchmark-value purple"><?= $stats['longtail_count'] ?></div>
                    <div class="benchmark-label">Long-tail</div>
                </div>
            </div>
            <p class="text-center" style="margin-top: 16px;">
                <strong>Összesen: <?= $stats['total'] ?> kulcsszó generálva</strong>
            </p>
        </div>
        
        <!-- Exact Match -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">[Exact Match] - <?= $stats['exact_count'] ?> db</h3>
                <button class="btn btn-secondary" onclick="copyKeywordList('exact')">📋 Lista másolása</button>
            </div>
            <div class="keyword-list" id="keywords-exact">
                <?php foreach (array_slice($perms['exact'], 0, 20) as $kw): ?>
                <span class="keyword-tag blue"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
                <?php if (count($perms['exact']) > 20): ?>
                <span class="keyword-more">+<?= count($perms['exact']) - 20 ?> további</span>
                <?php endif; ?>
            </div>
            <textarea class="hidden" id="exact-list"><?= htmlspecialchars(implode("\n", $perms['exact'])) ?></textarea>
        </div>
        
        <!-- Phrase Match -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">"Phrase Match" - <?= $stats['phrase_count'] ?> db</h3>
                <button class="btn btn-secondary" onclick="copyKeywordList('phrase')">📋 Lista másolása</button>
            </div>
            <div class="keyword-list" id="keywords-phrase">
                <?php foreach (array_slice($perms['phrase'], 0, 20) as $kw): ?>
                <span class="keyword-tag green"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
                <?php if (count($perms['phrase']) > 20): ?>
                <span class="keyword-more">+<?= count($perms['phrase']) - 20 ?> további</span>
                <?php endif; ?>
            </div>
            <textarea class="hidden" id="phrase-list"><?= htmlspecialchars(implode("\n", $perms['phrase'])) ?></textarea>
        </div>
        
        <!-- Broad Match Modifier -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">+Broad +Match +Modifier - <?= $stats['broad_mod_count'] ?> db</h3>
                <button class="btn btn-secondary" onclick="copyKeywordList('broad')">📋 Lista másolása</button>
            </div>
            <div class="keyword-list" id="keywords-broad">
                <?php foreach (array_slice($perms['broad_mod'], 0, 20) as $kw): ?>
                <span class="keyword-tag orange"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
                <?php if (count($perms['broad_mod']) > 20): ?>
                <span class="keyword-more">+<?= count($perms['broad_mod']) - 20 ?> további</span>
                <?php endif; ?>
            </div>
            <textarea class="hidden" id="broad-list"><?= htmlspecialchars(implode("\n", $perms['broad_mod'])) ?></textarea>
        </div>
        
        <!-- Long-tail -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🎯 Long-tail Kulcsszavak - <?= $stats['longtail_count'] ?> db</h3>
                <button class="btn btn-secondary" onclick="copyKeywordList('longtail')">📋 Lista másolása</button>
            </div>
            <div class="keyword-list" id="keywords-longtail">
                <?php foreach (array_slice($perms['longtail'], 0, 30) as $kw): ?>
                <span class="keyword-tag"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
                <?php if (count($perms['longtail']) > 30): ?>
                <span class="keyword-more">+<?= count($perms['longtail']) - 30 ?> további</span>
                <?php endif; ?>
            </div>
            <textarea class="hidden" id="longtail-list"><?= htmlspecialchars(implode("\n", $perms['longtail'])) ?></textarea>
        </div>
        
        <!-- Negatív kulcsszavak -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🚫 Ajánlott Negatív Kulcsszavak</h3>
                <button class="btn btn-secondary" onclick="copyKeywordList('negative')">📋 Lista másolása</button>
            </div>
            <div class="keyword-list">
                <?php foreach ($industry['negative_keywords'] ?? [] as $kw): ?>
                <span class="keyword-tag red"><?= htmlspecialchars($kw) ?></span>
                <?php endforeach; ?>
            </div>
            <textarea class="hidden" id="negative-list"><?= htmlspecialchars(implode("\n", $industry['negative_keywords'] ?? [])) ?></textarea>
        </div>
    </section>
    
    <div class="alert alert-success">✅ <?= $stats['total'] ?> kulcsszó generálva!</div>
    <?php
}

// === CALL-ONLY RESULTS RENDERER ===
function renderCallOnlyResults(array $data, string $phone): void { ?>
    <section class="callonly-results">
        <div class="card gradient-card-orange">
            <h3 class="card-title">📞 Call-Only Hirdetések</h3>
            <p style="opacity: 0.8;">Telefonszám: <strong><?= htmlspecialchars($phone) ?></strong></p>
        </div>
        
        <div class="callonly-grid">
            <?php foreach ($data['callonly_ads'] ?? [] as $i => $ad): ?>
            <div class="card callonly-card">
                <div class="callonly-header">
                    <span class="callonly-num">#<?= $i + 1 ?></span>
                    <button class="btn btn-secondary btn-sm" onclick="copyCallOnlyAd(<?= $i ?>)">📋 Másolás</button>
                </div>
                
                <div class="callonly-preview">
                    <div class="callonly-business"><?= htmlspecialchars($ad['business_name'] ?? '') ?></div>
                    <div class="callonly-phone"><?= htmlspecialchars($phone) ?></div>
                    <div class="callonly-desc"><?= htmlspecialchars($ad['description1'] ?? '') ?></div>
                    <div class="callonly-desc"><?= htmlspecialchars($ad['description2'] ?? '') ?></div>
                </div>
                
                <div class="callonly-chars">
                    <span>Business: <?= mb_strlen($ad['business_name'] ?? '') ?>/25</span>
                    <span>Desc1: <?= mb_strlen($ad['description1'] ?? '') ?>/35</span>
                    <span>Desc2: <?= mb_strlen($ad['description2'] ?? '') ?>/35</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($data['tips'])): ?>
        <div class="card">
            <h3 class="card-title">💡 Call-Only Tippek</h3>
            <ul class="tips-list">
                <?php foreach ($data['tips'] as $tip): ?>
                <li><?= htmlspecialchars($tip) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </section>
    
    <div class="alert alert-success">✅ <?= count($data['callonly_ads'] ?? []) ?> Call-Only hirdetés generálva!</div>
    <?php
}

// === SCRIPT RESULTS RENDERER ===
function renderScriptResults(string $code, array $template): void { ?>
    <section class="script-results">
        <div class="card gradient-card-purple">
            <h3 class="card-title">🤖 <?= htmlspecialchars($template['name']) ?></h3>
            <p style="opacity: 0.8;"><?= htmlspecialchars($template['description']) ?></p>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📝 Google Ads Script Kód</h3>
                <button class="btn btn-primary" onclick="copyScriptCode()">📋 Kód Másolása</button>
            </div>
            <div class="script-code-wrapper">
                <pre class="script-code" id="scriptCode"><?= htmlspecialchars($code) ?></pre>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">📖 Használati Útmutató</h3>
            <ol class="script-steps">
                <li>Másold ki a fenti kódot a <strong>"Kód Másolása"</strong> gombbal</li>
                <li>Nyisd meg a <a href="https://ads.google.com/aw/bulkactions/scripts" target="_blank">Google Ads Scripts</a> felületet</li>
                <li>Kattints a <strong>"+"</strong> gombra új script létrehozásához</li>
                <li>Töröld ki az alapértelmezett kódot és illeszd be a másolt kódot</li>
                <li>A <strong>CONFIG</strong> részben állítsd be az e-mail címet és egyéb paramétereket</li>
                <li>Kattints az <strong>"Előnézet"</strong> gombra a teszteléshez</li>
                <li>Ha minden rendben, állítsd be az ütemezést és mentsd el</li>
            </ol>
        </div>
        
        <div class="alert alert-warning">
            ⚠️ <strong>Fontos:</strong> A script előnézeti futtatása előtt győződj meg róla, hogy a CONFIG részben minden beállítás helyes!
        </div>
    </section>
    
    <div class="alert alert-success">✅ Google Ads Script generálva!</div>
    <?php
}
