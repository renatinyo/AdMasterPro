<?php
/**
 * AdMaster Pro v2.0 - API Endpoint
 */

// Globális hibakezelés - JSON válasz minden esetben
set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Szerverhiba: ' . $e->getMessage()
    ]);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Ne kezeljünk notice, warning és deprecated hibákat
    if ($errno === E_NOTICE || $errno === E_DEPRECATED || $errno === E_USER_DEPRECATED || $errno === E_WARNING) {
        return false;
    }
    // DOM/HTML parse hibák kihagyása
    if (strpos($errstr, 'DOMDocument') !== false || strpos($errstr, 'Tag ') !== false) {
        return true; // Hiba kezelve (elnyomva)
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/ProjectManager.php';
require_once __DIR__ . '/includes/CsvAnalyzer.php';
require_once __DIR__ . '/includes/LandingPageAnalyzer.php';

// DB kapcsolat - Settings-hez szükséges
Database::connect();

Security::initSession();

// Csak POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = strtolower($_POST['action'] ?? '');

// Extension API-k nem használnak CSRF-et (saját API key auth)
$extensionActions = ['extension_ping', 'extensionping', 'get_extension_data', 'getextensiondata', 'save_extension_data', 'saveextensiondata', 'save_competitor_scan', 'savecompetitorscan', 'get_competitor_scans', 'getcompetitorscans', 'save_gads_import', 'savegadsimport'];
$isExtensionCall = in_array($action, $extensionActions);

// CSRF ellenőrzés (kivéve extension hívások)
if (!$isExtensionCall) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!Security::verifyCsrfToken($csrfToken)) {
        $debugInfo = [
            'provided_token' => $csrfToken ? substr($csrfToken, 0, 10) . '...' : 'null',
            'session_token' => isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 10) . '...' : 'null',
            'token_age' => isset($_SESSION['csrf_token_time']) ? (time() - $_SESSION['csrf_token_time']) . 's' : 'no time',
            'session_id' => session_id() ? substr(session_id(), 0, 10) . '...' : 'no session'
        ];
        Security::log('warning', 'CSRF token failed: ' . json_encode($debugInfo));
        
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'Biztonsági hiba (CSRF). Frissítsd az oldalt!',
            'debug' => APP_DEBUG ? $debugInfo : null
        ]);
        exit;
    }
}

// Rate limit
if (!Security::checkRateLimit('api')) {
    header('Content-Type: application/json');
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Túl sok kérés. Várj egy kicsit.']);
    exit;
}

$industries = require __DIR__ . '/data/industries.php';

// Custom iparágak betöltése és összefésülése
$customFile = __DIR__ . '/data/custom_industries.json';
if (file_exists($customFile)) {
    $customIndustries = json_decode(file_get_contents($customFile), true) ?: [];
    $industries = array_merge($industries, $customIndustries);
}

require_once __DIR__ . '/includes/ClientManager.php';
require_once __DIR__ . '/data/strategies.php';

$action = Security::sanitizeInput($_POST['action'] ?? '', 'alpha');

// Extension API kulcs hitelesítés helper
function verifyExtensionAuth(): bool {
    $extKey = Settings::get('extension_api_key');
    if (!empty($extKey)) {
        $providedKey = $_POST['api_key'] ?? '';
        if ($providedKey !== $extKey) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Érvénytelen API kulcs. Állítsd be a bővítmény beállításaiban (Options → API Kulcs).']);
            return false;
        }
    }
    return true;
}

try {
    match($action) {
        'generate_all', 'generateall' => handleGenerateAll($industries),
        'generate_ads', 'generateads' => handleGenerateAds($industries),
        'generate_callonly', 'generatecallonly' => handleGenerateCallOnly($industries),
        'generate_settings', 'generatesettings' => handleGenerateSettings($industries),
        'generate_extensions', 'generateextensions' => handleGenerateExtensions($industries),
        'generate_keywords', 'generatekeywords' => handleGenerateKeywords($industries),
        'generate_script', 'generatescript' => handleGenerateScript(),
        'generate_snippets', 'generatesnippets' => handleGenerateSnippets($industries),
        'analyze_csv', 'analyzecsv' => handleAnalyzeCsv(),
        'analyze_landing', 'analyzelanding' => handleAnalyzeLanding($industries),
        'save_project', 'saveproject' => handleSaveProject(),
        'delete_project', 'deleteproject' => handleDeleteProject(),
        'save_client', 'saveclient' => handleSaveClient(),
        'delete_client', 'deleteclient' => handleDeleteClient(),
        'save_headline', 'saveheadline' => handleSaveHeadline(),
        'delete_headline', 'deleteheadline' => handleDeleteHeadline(),
        'save_keywords', 'savekeywords' => handleSaveKeywords(),
        'delete_keyword', 'deletekeyword' => handleDeleteKeyword(),
        'analyze_keywords', 'analyzekeywords' => handleAnalyzeKeywords($industries),
        'cluster_keywords', 'clusterkeywords' => handleClusterKeywords($industries),
        'analyze_competitors', 'analyzecompetitors' => handleAnalyzeCompetitors($industries),
        'analyze_competitor_manual', 'analyzecompetitormanual' => handleAnalyzeCompetitorManual($industries),
        'analyze_landing_full', 'analyzelandingfull' => handleAnalyzeLandingFull($industries),
        'generate_pmax', 'generatepmax' => handleGeneratePmax($industries),
        'generate_sitelinks', 'generatesitelinks' => handleGenerateSitelinks($industries),
        'generate_schema', 'generateschema' => handleGenerateSchema($industries),
        'generate_industry', 'generateindustry' => handleGenerateIndustry(),
        'delete_industry', 'deleteindustry' => handleDeleteIndustry(),
        'extension_ping', 'extensionping' => handleExtensionPing(),
        'get_extension_data', 'getextensiondata' => handleGetExtensionData(),
        'save_extension_data', 'saveextensiondata' => handleSaveExtensionData(),
        'save_competitor_scan', 'savecompetitorscan' => handleSaveCompetitorScan(),
        'save_gads_import', 'savegadsimport' => handleSaveGadsImport(),
        'get_competitor_scans', 'getcompetitorscans' => handleGetCompetitorScans(),
        'run_diagnosis', 'rundiagnosis' => handleRunDiagnosis($industries),
        'analyze_landing_vision', 'analyzelandingvision' => handleAnalyzeLandingVision($industries),
        'publish_to_google_ads', 'publishtogoogleads' => handlePublishToGoogleAds(),
        'get_gads_accounts', 'getgadsaccounts' => handleGetGadsAccounts(),
        'get_gads_campaigns', 'getgadscampaigns' => handleGetGadsCampaigns(),
        'get_gads_adgroups', 'getgadsadgroups' => handleGetGadsAdGroups(),
        'gads_sync_accounts', 'gadssyncaccounts' => handleGadsSyncAccounts(),
        'gads_sync_full', 'gadssyncfull' => handleGadsSyncFull(),
        'gads_get_ads', 'gadsgetads' => handleGadsGetAds(),
        'gads_get_keywords', 'gadsgetkeywords' => handleGadsGetKeywords(),
        'check_relevance', 'checkrelevance' => handleCheckRelevance(),
        'extract_usps', 'extractusps' => handleExtractUSPs(),
        'cro_audit', 'croaudit' => handleCROAudit($industries),
        'clear_logs', 'clearlogs' => handleClearLogs(),
        'save_generation_to_client', 'savegenerationtoclient' => handleSaveGenerationToClient(),
        'get_client_generation', 'getclientgeneration' => handleGetClientGeneration(),
        'delete_generation', 'deletegeneration' => handleDeleteGeneration(),
        'save_edited_generation', 'saveeditedgeneration' => handleSaveEditedGeneration(),
        'assistant_chat', 'assistantchat' => handleAssistantChat($industries),
        'get_last_result', 'getlastresult' => handleGetLastResult(),
        'save_competitor_to_client', 'savecompetitortoclient' => handleSaveCompetitorToClient(),
        'save_current_campaign', 'savecurrentcampaign' => handleSaveCurrentCampaign(),
        default => print renderError('Ismeretlen művelet')
    };
} catch (Exception $e) {
    Security::log('error', 'API error: ' . $e->getMessage());
    echo renderError(APP_DEBUG ? $e->getMessage() : 'Váratlan hiba történt.');
}

// === CLIENT HANDLERS ===
function handleSaveClient(): void {
    $cm = new ClientManager();
    
    $id = $_POST['id'] ?? null;
    $newData = [
        'name' => Security::sanitizeInput($_POST['name'] ?? '', 'string'),
        'industry' => Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric'),
        'phone' => Security::sanitizeInput($_POST['phone'] ?? '', 'phone'),
        'area' => Security::sanitizeInput($_POST['area'] ?? 'budapest', 'alpha'),
        'website' => Security::sanitizeInput($_POST['website'] ?? '', 'url')
    ];
    
    if (empty($newData['name'])) {
        echo json_encode(['success' => false, 'error' => 'Cégnév kötelező']);
        return;
    }
    
    // Ha meglévő ügyfelet szerkesztünk, megtartjuk a többi mezőt
    if ($id) {
        $existing = $cm->getClient($id);
        if ($existing) {
            // Merge: meglévő adatok + új szerkesztett mezők
            $client = array_merge($existing, $newData);
            $client['id'] = $id;
        } else {
            $client = $newData;
            $client['id'] = $id;
        }
    } else {
        $client = $newData;
    }
    
    $savedId = $cm->saveClient($client);
    echo json_encode(['success' => true, 'id' => $savedId]);
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
    $industry = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    
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

// === KEYWORD HANDLERS ===
function handleSaveKeywords(): void {
    $cm = new ClientManager();
    $type = Security::sanitizeInput($_POST['type'] ?? 'positive', 'alpha');
    $industry = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $keywordsText = $_POST['keywords'] ?? '';
    
    // Sorokra bontás
    $keywords = array_filter(array_map('trim', explode("\n", $keywordsText)));
    
    if (empty($keywords)) {
        echo json_encode(['success' => false, 'error' => 'Nincs kulcsszó megadva']);
        return;
    }
    
    $added = $cm->addKeywords($keywords, $type, $industry);
    echo json_encode(['success' => true, 'added' => $added, 'total' => count($keywords)]);
}

function handleDeleteKeyword(): void {
    $cm = new ClientManager();
    $id = Security::sanitizeInput($_POST['id'] ?? '', 'string');
    $type = Security::sanitizeInput($_POST['type'] ?? 'positive', 'alpha');
    $result = $cm->deleteKeyword($id, $type);
    echo json_encode(['success' => $result]);
}

function handleAnalyzeKeywords(array $industries): void {
    $keywordsText = $_POST['keywords'] ?? '';
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $checkNegatives = isset($_POST['check_negatives']);
    $suggestVariations = isset($_POST['suggest_variations']);
    $findProblems = isset($_POST['find_problems']);
    
    $keywords = array_filter(array_map('trim', explode("\n", $keywordsText)));
    
    if (empty($keywords)) {
        echo renderError('Nincs kulcsszó megadva az elemzéshez.');
        return;
    }
    
    $cm = new ClientManager();
    $results = [
        'total' => count($keywords),
        'conflicts' => [],
        'problems' => [],
        'suggestions' => []
    ];
    
    // Negatív ütközések ellenőrzése
    if ($checkNegatives) {
        $results['conflicts'] = $cm->checkKeywordConflicts($keywords);
    }
    
    // AI elemzés
    $industry = $industry_key && isset($industries[$industry_key]) ? $industries[$industry_key] : null;
    
    $prompt = "Elemezd ezeket a magyar Google Ads kulcsszavakat" . ($industry ? " a(z) {$industry['name']} iparágban" : "") . ":

KULCSSZAVAK:
" . implode("\n", $keywords) . "

FELADATOK:
1. PROBLÉMÁK: Keress problémás kulcsszavakat (túl általános, rossz intent, alacsony minőség)
2. " . ($suggestVariations ? "VARIÁCIÓK: Javasolj jobb/bővebb variációkat" : "") . "
3. TIPPEK: Adj 2-3 gyakorlati tanácsot

Válaszolj JSON-ben:
{
    \"problems\": [{\"keyword\": \"...\", \"issue\": \"probléma leírása\", \"suggestion\": \"javaslat\"}],
    \"variations\": [{\"original\": \"...\", \"variations\": [\"...\", \"...\"]}],
    \"tips\": [\"...\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $aiData = parseJsonResponse($response);
    
    // Eredmények renderelése
    echo '<div class="analysis-results">';
    echo '<h3 class="card-title">📊 Elemzés Eredménye</h3>';
    
    // Statisztika
    echo '<div class="analysis-stats">';
    echo '<div class="stat-box"><span class="stat-num">' . count($keywords) . '</span><span class="stat-label">Kulcsszó</span></div>';
    echo '<div class="stat-box ' . (count($results['conflicts']) > 0 ? 'stat-danger' : 'stat-success') . '"><span class="stat-num">' . count($results['conflicts']) . '</span><span class="stat-label">Ütközés</span></div>';
    echo '<div class="stat-box ' . (count($aiData['problems'] ?? []) > 0 ? 'stat-warning' : 'stat-success') . '"><span class="stat-num">' . count($aiData['problems'] ?? []) . '</span><span class="stat-label">Probléma</span></div>';
    echo '</div>';
    
    // Ütközések
    if (!empty($results['conflicts'])) {
        echo '<div class="alert alert-error">';
        echo '<strong>⚠️ Negatív kulcsszó ütközések!</strong><br>';
        foreach ($results['conflicts'] as $c) {
            echo '<span class="tag tag-red">' . htmlspecialchars($c['keyword']) . '</span> ütközik: <span class="tag">' . htmlspecialchars($c['negative']) . '</span><br>';
        }
        echo '</div>';
    }
    
    // Problémák
    if (!empty($aiData['problems'])) {
        echo '<div class="card" style="margin-top:16px">';
        echo '<h4>⚠️ Problémás Kulcsszavak</h4>';
        echo '<div class="problems-list">';
        foreach ($aiData['problems'] as $p) {
            echo '<div class="problem-item">';
            echo '<span class="tag tag-orange">' . htmlspecialchars($p['keyword']) . '</span>';
            echo '<span class="problem-issue">' . htmlspecialchars($p['issue']) . '</span>';
            if (!empty($p['suggestion'])) {
                echo '<span class="problem-fix">💡 ' . htmlspecialchars($p['suggestion']) . '</span>';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }
    
    // Variációk
    if (!empty($aiData['variations'])) {
        echo '<div class="card" style="margin-top:16px">';
        echo '<h4>✨ Javasolt Variációk</h4>';
        foreach ($aiData['variations'] as $v) {
            echo '<div class="variation-item">';
            echo '<span class="tag">' . htmlspecialchars($v['original']) . '</span> → ';
            foreach ($v['variations'] as $var) {
                echo '<span class="tag tag-green">' . htmlspecialchars($var) . '</span> ';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Tippek
    if (!empty($aiData['tips'])) {
        echo '<div class="card card-tips" style="margin-top:16px">';
        echo '<h4>💡 Tippek</h4><ul>';
        foreach ($aiData['tips'] as $tip) {
            echo '<li>' . htmlspecialchars($tip) . '</li>';
        }
        echo '</ul></div>';
    }
    
    echo '</div>';
}

// === KEYWORD CLUSTERING ===
function handleClusterKeywords(array $industries): void {
    $keywordsText = $_POST['keywords'] ?? '';
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $num_groups = Security::sanitizeInput($_POST['num_groups'] ?? 'auto', 'alphanumeric');
    $suggest_names = isset($_POST['suggest_names']);
    $suggest_headlines = isset($_POST['suggest_headlines']);
    
    $keywords = array_filter(array_map('trim', explode("\n", $keywordsText)));
    
    if (count($keywords) < 3) {
        echo renderError('Legalább 3 kulcsszó kell a klaszterezéshez.');
        return;
    }
    
    $industry = isset($industries[$industry_key]) ? $industries[$industry_key] : null;
    
    $groupInstruction = match($num_groups) {
        '3' => '3-5 csoportba',
        '5' => '5-8 csoportba',
        '10' => '8-12 csoportba',
        default => 'logikus számú csoportba (te döntsd el)'
    };
    
    $prompt = "Te egy magyar Google Ads szakértő vagy. Klaszterezd ezeket a kulcsszavakat Ad Group-okba" . ($industry ? " ({$industry['name']} iparág)" : "") . ".

KULCSSZAVAK (" . count($keywords) . " db):
" . implode("\n", $keywords) . "

FELADAT:
1. Csoportosítsd a kulcsszavakat $groupInstruction
2. A csoportok legyenek logikusak (intent alapján):
   - Sürgősségi (azonnal, gyors, éjszakai)
   - Ár-érzékeny (olcsó, ár, árak, mennyibe)
   - Helyszín alapú (budapest, kerület, város)
   - Szolgáltatás típus (konkrét szolgáltatások)
   - Probléma alapú (dugulás, csőtörés, szivárgás)
   - Brand/általános
" . ($suggest_names ? "3. Adj magyar Ad Group nevet minden csoportnak" : "") . "
" . ($suggest_headlines ? "4. Javasolj 2 headline-t csoportonként (max 30 kar)" : "") . "

Válasz JSON:
{
    \"clusters\": [
        {
            \"name\": \"Ad Group név\",
            \"theme\": \"rövid leírás miért tartoznak össze\",
            \"keywords\": [\"kulcsszó1\", \"kulcsszó2\"],
            \"match_type_suggestion\": \"phrase/exact/broad\",
            \"headlines\": [\"Headline 1\", \"Headline 2\"],
            \"bid_suggestion\": \"magasabb/átlagos/alacsonyabb (miért)\"
        }
    ],
    \"unclustered\": [\"ha van ami nem illik sehova\"],
    \"tips\": [\"általános tanácsok\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    // Render eredmények
    echo '<div class="cluster-results">';
    echo '<h3>📊 Klaszterezés Eredménye</h3>';
    echo '<p class="help-text">' . count($keywords) . ' kulcsszó → ' . count($data['clusters'] ?? []) . ' Ad Group</p>';
    
    // Összefoglaló statisztika
    echo '<div class="cluster-stats">';
    foreach ($data['clusters'] ?? [] as $i => $cluster) {
        echo '<div class="cluster-stat">';
        echo '<span class="cluster-num">' . count($cluster['keywords']) . '</span>';
        echo '<span class="cluster-name">' . htmlspecialchars($cluster['name']) . '</span>';
        echo '</div>';
    }
    echo '</div>';
    
    // Klaszterek részletesen
    echo '<div class="clusters-list">';
    foreach ($data['clusters'] ?? [] as $i => $cluster) {
        $colorClass = ['cluster-blue', 'cluster-green', 'cluster-orange', 'cluster-purple', 'cluster-red', 'cluster-teal'][$i % 6];
        
        echo '<div class="cluster-card ' . $colorClass . '">';
        echo '<div class="cluster-header">';
        echo '<h4>' . htmlspecialchars($cluster['name']) . '</h4>';
        echo '<span class="cluster-count">' . count($cluster['keywords']) . ' kulcsszó</span>';
        echo '</div>';
        
        if (!empty($cluster['theme'])) {
            echo '<p class="cluster-theme">' . htmlspecialchars($cluster['theme']) . '</p>';
        }
        
        // Kulcsszavak
        echo '<div class="cluster-keywords">';
        foreach ($cluster['keywords'] as $kw) {
            echo '<span class="tag">' . htmlspecialchars($kw) . '</span>';
        }
        echo '</div>';
        
        // Match type javaslat
        if (!empty($cluster['match_type_suggestion'])) {
            echo '<div class="cluster-meta">';
            echo '<span class="meta-item">🎯 Match type: <strong>' . htmlspecialchars($cluster['match_type_suggestion']) . '</strong></span>';
            if (!empty($cluster['bid_suggestion'])) {
                echo '<span class="meta-item">💰 Bid: ' . htmlspecialchars($cluster['bid_suggestion']) . '</span>';
            }
            echo '</div>';
        }
        
        // Headlines
        if (!empty($cluster['headlines'])) {
            echo '<div class="cluster-headlines">';
            echo '<strong>Javasolt Headlines:</strong>';
            foreach ($cluster['headlines'] as $hl) {
                echo '<div class="headline-preview">"' . htmlspecialchars($hl) . '" <span class="char-count">' . mb_strlen($hl) . '/30</span></div>';
            }
            echo '</div>';
        }
        
        // Copy gomb
        echo '<button class="btn btn-sm btn-secondary" onclick="copyClusterKeywords(' . $i . ')" style="margin-top:12px">📋 Kulcsszavak másolása</button>';
        echo '<textarea class="hidden" id="cluster-kw-' . $i . '">' . implode("\n", $cluster['keywords']) . '</textarea>';
        
        echo '</div>';
    }
    echo '</div>';
    
    // Nem klaszterezett
    if (!empty($data['unclustered'])) {
        echo '<div class="card" style="margin-top:16px">';
        echo '<h4>⚠️ Nem besorolható kulcsszavak</h4>';
        echo '<div class="tags-list">';
        foreach ($data['unclustered'] as $kw) {
            echo '<span class="tag tag-orange">' . htmlspecialchars($kw) . '</span>';
        }
        echo '</div></div>';
    }
    
    // Tippek
    if (!empty($data['tips'])) {
        echo '<div class="card card-tips" style="margin-top:16px">';
        echo '<h4>💡 Tanácsok</h4><ul>';
        foreach ($data['tips'] as $tip) {
            echo '<li>' . htmlspecialchars($tip) . '</li>';
        }
        echo '</ul></div>';
    }
    
    // Export gombok
    echo '<div class="cluster-export" style="margin-top:20px">';
    echo '<button class="btn btn-primary" onclick="copyAllClusters()">📋 Összes másolása (Google Ads formátum)</button>';
    echo '</div>';
    
    // Hidden textarea az export-hoz
    $exportText = "";
    foreach ($data['clusters'] ?? [] as $cluster) {
        $exportText .= "=== " . $cluster['name'] . " ===\n";
        foreach ($cluster['keywords'] as $kw) {
            $exportText .= '"' . $kw . '"' . "\n"; // Phrase match
        }
        $exportText .= "\n";
    }
    echo '<textarea class="hidden" id="all-clusters-export">' . htmlspecialchars($exportText) . '</textarea>';
    
    echo '</div>';
}

// === COMPETITOR ANALYSIS ===
function handleAnalyzeCompetitors(array $industries): void {
    $keyword = Security::sanitizeInput($_POST['keyword'] ?? '', 'string');
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $own_ad = $_POST['own_ad'] ?? '';
    
    if (empty($keyword)) {
        echo renderError('Kulcsszó megadása kötelező.');
        return;
    }
    
    $competitorAds = [];
    
    // SerpApi lekérés ha van kulcs
    if (!empty(Settings::serpApiKey())) {
        $serpUrl = 'https://serpapi.com/search.json?' . http_build_query([
            'q' => $keyword,
            'location' => 'Budapest, Hungary',
            'hl' => 'hu',
            'gl' => 'hu',
            'api_key' => Settings::serpApiKey()
        ]);
        
        $serpResponse = @file_get_contents($serpUrl);
        if ($serpResponse) {
            $serpData = json_decode($serpResponse, true);
            
            // Hirdetések kinyerése
            foreach ($serpData['ads'] ?? [] as $ad) {
                $competitorAds[] = [
                    'title' => $ad['title'] ?? '',
                    'description' => $ad['description'] ?? '',
                    'link' => $ad['link'] ?? '',
                    'displayed_link' => $ad['displayed_link'] ?? ''
                ];
            }
        }
    }
    
    // Ha nincs hirdetés, jelezzük
    if (empty($competitorAds)) {
        echo '<div class="alert alert-warning">⚠️ Nem találtunk hirdetéseket a SerpApi-n keresztül. Használd a "Kézi Hirdetés Elemzés" funkciót!</div>';
        return;
    }
    
    // AI elemzés
    $industry = isset($industries[$industry_key]) ? $industries[$industry_key] : null;
    
    $prompt = "Elemezd ezeket a magyar Google Ads hirdetéseket" . ($industry ? " a(z) {$industry['name']} iparágban" : "") . ":

VERSENYTÁRS HIRDETÉSEK:
" . json_encode($competitorAds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

" . ($own_ad ? "SAJÁT HIRDETÉS:\n$own_ad\n" : "") . "

ELEMEZD:
1. Mi működik jól a versenytárs hirdetésekben?
2. Mik a gyengeségeik?
3. Milyen USP-ket használnak?
4. " . ($own_ad ? "Miben jobbak/rosszabbak nálam?" : "") . "
5. Adj javaslatokat a saját hirdetésem javítására

Válasz JSON:
{
    \"competitor_strengths\": [\"...\"],
    \"competitor_weaknesses\": [\"...\"],
    \"usps_found\": [\"...\"],
    \"comparison\": \"...\",
    \"recommendations\": [\"...\"],
    \"suggested_headlines\": [\"...\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    // Render
    echo '<div class="analysis-results">';
    echo '<h3>🔍 Versenytárs Elemzés Eredménye</h3>';
    echo '<p class="help-text">Talált hirdetések: ' . count($competitorAds) . '</p>';
    
    // Talált hirdetések megjelenítése
    echo '<div class="competitor-ads">';
    foreach ($competitorAds as $i => $ad) {
        echo '<div class="competitor-ad-card">';
        echo '<div class="ad-title">' . htmlspecialchars($ad['title']) . '</div>';
        echo '<div class="ad-url">' . htmlspecialchars($ad['displayed_link']) . '</div>';
        echo '<div class="ad-desc">' . htmlspecialchars($ad['description']) . '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    // AI elemzés eredményei
    if (!empty($data['competitor_strengths'])) {
        echo '<div class="card" style="margin-top:16px"><h4>✅ Erősségek</h4><ul>';
        foreach ($data['competitor_strengths'] as $s) echo '<li>' . htmlspecialchars($s) . '</li>';
        echo '</ul></div>';
    }
    
    if (!empty($data['competitor_weaknesses'])) {
        echo '<div class="card" style="margin-top:16px"><h4>❌ Gyengeségek</h4><ul>';
        foreach ($data['competitor_weaknesses'] as $w) echo '<li>' . htmlspecialchars($w) . '</li>';
        echo '</ul></div>';
    }
    
    if (!empty($data['recommendations'])) {
        echo '<div class="card card-tips" style="margin-top:16px"><h4>💡 Javaslatok</h4><ul>';
        foreach ($data['recommendations'] as $r) echo '<li>' . htmlspecialchars($r) . '</li>';
        echo '</ul></div>';
    }
    
    if (!empty($data['suggested_headlines'])) {
        echo '<div class="card" style="margin-top:16px"><h4>✨ Javasolt Headlines</h4><div class="tags-list">';
        foreach ($data['suggested_headlines'] as $h) echo '<span class="tag tag-green">' . htmlspecialchars($h) . '</span>';
        echo '</div></div>';
    }
    
    // Mentés ügyfélhez gomb
    $saveData = json_encode([
        'strengths' => $data['competitor_strengths'] ?? [],
        'weaknesses' => $data['competitor_weaknesses'] ?? [],
        'usps' => $data['usps_found'] ?? [],
        'recommendations' => $data['recommendations'] ?? [],
        'suggested_headlines' => $data['suggested_headlines'] ?? [],
        'query' => $keyword,
        'date' => date('Y-m-d H:i'),
    ], JSON_UNESCAPED_UNICODE);
    
    echo '<div class="card" style="margin-top:16px; text-align:center;">';
    echo '<button class="btn btn-primary" onclick=\'saveCompetitorToClient(' . htmlspecialchars($saveData, ENT_QUOTES) . ')\'>';
    echo '💾 Mentés Ügyfélhez (használható generálásnál)</button>';
    echo '<p class="help-text" style="margin-top:8px">Elmentve a kampánygenerátor is felhasználja ezeket az adatokat</p>';
    echo '</div>';
    
    echo '</div>';
}

function handleAnalyzeCompetitorManual(array $industries): void {
    $competitor_ads = $_POST['competitor_ads'] ?? '';
    $own_ad = $_POST['own_ad'] ?? '';
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    
    if (empty($competitor_ads)) {
        echo renderError('Illeszd be a versenytárs hirdetéseket.');
        return;
    }
    
    $industry = isset($industries[$industry_key]) ? $industries[$industry_key] : null;
    
    $prompt = "Elemezd ezeket a beillesztett versenytárs Google Ads hirdetéseket" . ($industry ? " ({$industry['name']} iparág)" : "") . ":

VERSENYTÁRS HIRDETÉSEK:
$competitor_ads

" . ($own_ad ? "SAJÁT HIRDETÉS:\n$own_ad\n" : "") . "

ELEMEZD részletesen:
1. Milyen headline-okat használnak?
2. Milyen USP-ket emelnek ki?
3. Milyen CTA-kat használnak?
4. Mik az erősségeik/gyengeségeik?
5. " . ($own_ad ? "Összehasonlítás a saját hirdetésemmel" : "") . "
6. Konkrét javaslatok

Válasz JSON:
{
    \"headlines_found\": [\"...\"],
    \"usps_found\": [\"...\"],
    \"ctas_found\": [\"...\"],
    \"strengths\": [\"...\"],
    \"weaknesses\": [\"...\"],
    \"comparison\": \"...\",
    \"recommendations\": [\"...\"],
    \"better_headlines\": [\"...\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    echo '<div class="analysis-results">';
    echo '<h3>🧠 AI Elemzés Eredménye</h3>';
    
    if (!empty($data['headlines_found'])) {
        echo '<div class="card"><h4>📝 Talált Headlines</h4><div class="tags-list">';
        foreach ($data['headlines_found'] as $h) echo '<span class="tag">' . htmlspecialchars($h) . '</span>';
        echo '</div></div>';
    }
    
    if (!empty($data['usps_found'])) {
        echo '<div class="card" style="margin-top:16px"><h4>⭐ Használt USP-k</h4><div class="tags-list">';
        foreach ($data['usps_found'] as $u) echo '<span class="tag tag-blue">' . htmlspecialchars($u) . '</span>';
        echo '</div></div>';
    }
    
    if (!empty($data['comparison'])) {
        echo '<div class="card" style="margin-top:16px"><h4>⚖️ Összehasonlítás</h4><p>' . htmlspecialchars($data['comparison']) . '</p></div>';
    }
    
    if (!empty($data['recommendations'])) {
        echo '<div class="card card-tips" style="margin-top:16px"><h4>💡 Javaslatok</h4><ul>';
        foreach ($data['recommendations'] as $r) echo '<li>' . htmlspecialchars($r) . '</li>';
        echo '</ul></div>';
    }
    
    if (!empty($data['better_headlines'])) {
        echo '<div class="card" style="margin-top:16px"><h4>✨ Javasolt Jobb Headlines</h4><div class="tags-list">';
        foreach ($data['better_headlines'] as $h) echo '<span class="tag tag-green">' . htmlspecialchars($h) . '</span>';
        echo '</div></div>';
    }
    
    // Mentés ügyfélhez gomb
    $saveData = json_encode([
        'strengths' => $data['strengths'] ?? [],
        'weaknesses' => $data['weaknesses'] ?? [],
        'usps' => $data['usps_found'] ?? [],
        'recommendations' => $data['recommendations'] ?? [],
        'suggested_headlines' => $data['better_headlines'] ?? [],
        'query' => 'manuális elemzés',
        'date' => date('Y-m-d H:i'),
    ], JSON_UNESCAPED_UNICODE);
    
    echo '<div class="card" style="margin-top:16px; text-align:center;">';
    echo '<button class="btn btn-primary" onclick=\'saveCompetitorToClient(' . htmlspecialchars($saveData, ENT_QUOTES) . ')\'>';
    echo '💾 Mentés Ügyfélhez (használható generálásnál)</button>';
    echo '<p class="help-text" style="margin-top:8px">Elmentve a kampánygenerátor is felhasználja ezeket az adatokat</p>';
    echo '</div>';
    
    echo '</div>';
}

// === LANDING PAGE FULL ANALYSIS ===
function handleAnalyzeLandingFull(array $industries): void {
    $url = Security::sanitizeInput($_POST['url'] ?? '', 'url');
    $keyword = Security::sanitizeInput($_POST['keyword'] ?? '', 'string');
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    
    $check_technical = isset($_POST['check_technical']);
    $check_cro = isset($_POST['check_cro']);
    $check_seo = isset($_POST['check_seo']);
    $rewrite_content = isset($_POST['rewrite_content']);
    
    if (empty($url)) {
        echo renderError('URL megadása kötelező.');
        return;
    }
    
    // Oldal letöltése - cURL-lel, SSL toleráns
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: hu-HU,hu;q=0.9,en;q=0.8',
        ],
        CURLOPT_ENCODING => '', // gzip elfogadás
    ]);
    $html = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$html || $httpCode >= 400) {
        $errorMsg = 'Az oldal nem elérhető.';
        if ($curlError) $errorMsg .= ' (' . $curlError . ')';
        elseif ($httpCode) $errorMsg .= ' (HTTP ' . $httpCode . ')';
        echo renderError($errorMsg);
        return;
    }
    
    // HTML elemzés - HTML5 kompatibilis módon
    libxml_use_internal_errors(true); // HTML5 hibák elnyomása
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
    $results = ['url' => $url, 'technical' => [], 'content' => []];
    
    // Technikai elemzés
    if ($check_technical) {
        // Title
        $title = $xpath->query('//title')->item(0);
        $results['technical']['title'] = $title ? trim($title->textContent) : 'HIÁNYZIK!';
        
        // Meta description
        $metaDesc = $xpath->query('//meta[@name="description"]/@content')->item(0);
        $results['technical']['meta_description'] = $metaDesc ? $metaDesc->textContent : 'HIÁNYZIK!';
        
        // H1
        $h1 = $xpath->query('//h1')->item(0);
        $results['technical']['h1'] = $h1 ? trim($h1->textContent) : 'HIÁNYZIK!';
        
        // H1 count
        $results['technical']['h1_count'] = $xpath->query('//h1')->length;
        
        // Telefon
        preg_match_all('/(\+36|06)[\s\-]?\d{1,2}[\s\-]?\d{3}[\s\-]?\d{3,4}/', $html, $phones);
        $results['technical']['phone_found'] = !empty($phones[0]);
        $results['technical']['phones'] = array_unique($phones[0] ?? []);
        
        // Form
        $results['technical']['has_form'] = $xpath->query('//form')->length > 0;
        
        // CTA gombok
        $ctas = $xpath->query('//a[contains(@class,"btn") or contains(@class,"button") or contains(@class,"cta")] | //button');
        $results['technical']['cta_count'] = $ctas->length;
    }
    
    // Tartalom kinyerése
    $bodyText = '';
    $paragraphs = $xpath->query('//p | //h1 | //h2 | //h3 | //li');
    foreach ($paragraphs as $p) {
        $bodyText .= trim($p->textContent) . "\n";
    }
    $bodyText = mb_substr($bodyText, 0, 5000); // Max 5000 kar
    
    $industry = isset($industries[$industry_key]) ? $industries[$industry_key] : null;
    
    // AI elemzés
    $prompt = "Elemezd ezt a magyar landing page-et" . ($industry ? " ({$industry['name']} iparág)" : "") . ":

URL: $url
" . ($keyword ? "FŐ KULCSSZÓ: $keyword\n" : "") . "

TECHNIKAI ADATOK:
" . json_encode($results['technical'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

OLDAL TARTALMA:
$bodyText

ELEMEZD:
" . ($check_cro ? "1. CRO szempontból: USP-k megjelennek? CTA-k jók? Trust elemek (vélemények, garancia)?" : "") . "
" . ($check_seo ? "2. SEO szempontból: Kulcsszó használat, struktúra" : "") . "
" . ($rewrite_content ? "3. KONKRÉT ÁTÍRÁSI JAVASLATOK: H1, meta description, főbb bekezdések" : "") . "

Válasz JSON:
{
    \"score\": 0-100,
    \"cro_issues\": [\"...\"],
    \"cro_strengths\": [\"...\"],
    \"seo_issues\": [\"...\"],
    \"rewrites\": [
        {\"element\": \"H1\", \"current\": \"...\", \"suggested\": \"...\", \"reason\": \"...\"},
        {\"element\": \"Meta Description\", \"current\": \"...\", \"suggested\": \"...\", \"reason\": \"...\"}
    ],
    \"quick_wins\": [\"...\"],
    \"priority_fixes\": [\"...\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $aiData = parseJsonResponse($response);
    
    // Render
    echo '<div class="analysis-results">';
    echo '<h3>🌐 Landing Page Elemzés</h3>';
    echo '<p class="help-text">' . htmlspecialchars($url) . '</p>';
    
    // Score
    $score = $aiData['score'] ?? 0;
    $scoreClass = $score >= 70 ? 'stat-success' : ($score >= 40 ? 'stat-warning' : 'stat-danger');
    echo '<div class="score-display ' . $scoreClass . '"><span class="big-score">' . $score . '</span>/100</div>';
    
    // Technikai eredmények
    if ($check_technical && !empty($results['technical'])) {
        echo '<div class="card" style="margin-top:20px"><h4>⚙️ Technikai Audit</h4>';
        echo '<table class="audit-table">';
        echo '<tr><td>Title</td><td>' . htmlspecialchars(mb_substr($results['technical']['title'], 0, 60)) . '</td><td>' . (strlen($results['technical']['title']) > 10 ? '✅' : '❌') . '</td></tr>';
        echo '<tr><td>Meta Desc</td><td>' . htmlspecialchars(mb_substr($results['technical']['meta_description'], 0, 60)) . '...</td><td>' . (strlen($results['technical']['meta_description']) > 50 ? '✅' : '❌') . '</td></tr>';
        echo '<tr><td>H1</td><td>' . htmlspecialchars($results['technical']['h1']) . '</td><td>' . ($results['technical']['h1_count'] == 1 ? '✅' : '⚠️ ' . $results['technical']['h1_count']) . '</td></tr>';
        echo '<tr><td>Telefon</td><td>' . implode(', ', $results['technical']['phones']) . '</td><td>' . ($results['technical']['phone_found'] ? '✅' : '❌') . '</td></tr>';
        echo '<tr><td>Form</td><td>' . ($results['technical']['has_form'] ? 'Van' : 'Nincs') . '</td><td>' . ($results['technical']['has_form'] ? '✅' : '⚠️') . '</td></tr>';
        echo '<tr><td>CTA gombok</td><td>' . $results['technical']['cta_count'] . ' db</td><td>' . ($results['technical']['cta_count'] > 0 ? '✅' : '❌') . '</td></tr>';
        echo '</table></div>';
    }
    
    // CRO problémák
    if (!empty($aiData['cro_issues'])) {
        echo '<div class="card" style="margin-top:16px"><h4>❌ CRO Problémák</h4><ul>';
        foreach ($aiData['cro_issues'] as $i) echo '<li>' . htmlspecialchars($i) . '</li>';
        echo '</ul></div>';
    }
    
    // Átírási javaslatok
    if (!empty($aiData['rewrites'])) {
        echo '<div class="card card-highlight" style="margin-top:16px"><h4>✍️ Átírási Javaslatok</h4>';
        foreach ($aiData['rewrites'] as $rw) {
            echo '<div class="rewrite-item">';
            echo '<div class="rw-element">' . htmlspecialchars($rw['element']) . '</div>';
            echo '<div class="rw-current"><span class="label">Jelenlegi:</span> ' . htmlspecialchars($rw['current']) . '</div>';
            echo '<div class="rw-suggested"><span class="label">Javasolt:</span> <strong>' . htmlspecialchars($rw['suggested']) . '</strong></div>';
            echo '<div class="rw-reason">💡 ' . htmlspecialchars($rw['reason']) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Quick wins
    if (!empty($aiData['quick_wins'])) {
        echo '<div class="card card-tips" style="margin-top:16px"><h4>⚡ Gyors Javítások</h4><ul>';
        foreach ($aiData['quick_wins'] as $q) echo '<li>' . htmlspecialchars($q) . '</li>';
        echo '</ul></div>';
    }
    
    echo '</div>';
}

// === PMAX GENERATOR ===
function handleGeneratePmax(array $industries): void {
    $company = Security::sanitizeInput($_POST['company_name'] ?? '', 'string');
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $website = Security::sanitizeInput($_POST['website'] ?? '', 'url');
    $services = $_POST['services'] ?? '';
    $usps = $_POST['usps'] ?? '';
    
    if (empty($company) || !isset($industries[$industry_key])) {
        echo renderError('Cégnév és iparág megadása kötelező.');
        return;
    }
    
    $industry = $industries[$industry_key];
    
    $prompt = "Generálj Performance Max kampány asseteket erre a magyar cégre:

CÉG: $company
IPARÁG: {$industry['name']}
WEBOLDAL: " . ($website ?: 'nincs megadva') . "
SZOLGÁLTATÁSOK: " . ($services ?: 'nincs megadva') . "
USP-K: " . ($usps ?: 'nincs megadva') . "

GENERÁLJ PMax asset-eket:

1. HEADLINES (15 db, max 30 kar) - változatos, különböző szögekből
2. LONG HEADLINES (5 db, max 90 kar) - részletesebb üzenetek
3. DESCRIPTIONS (5 db, max 90 kar)
4. BUSINESS NAME variációk (3 db, max 25 kar)
5. KÉP JAVASLATOK - milyen képeket kellene használni (típus, tartalom, hangulat)
6. CÉLKÖZÖNSÉG javaslatok

Válasz JSON:
{
    \"headlines\": [\"...\"],
    \"long_headlines\": [\"...\"],
    \"descriptions\": [\"...\"],
    \"business_names\": [\"...\"],
    \"image_suggestions\": [
        {\"type\": \"landscape/square/portrait\", \"content\": \"mit ábrázoljon\", \"mood\": \"hangulat\"}
    ],
    \"audience_signals\": [\"...\"],
    \"tips\": [\"...\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    echo '<div class="pmax-results">';
    echo '<h3>📦 PMax Asset-ek Elkészültek</h3>';
    
    // Headlines
    if (!empty($data['headlines'])) {
        echo '<div class="card"><h4>📝 Headlines (' . count($data['headlines']) . '/15)</h4>';
        echo '<div class="asset-list">';
        foreach ($data['headlines'] as $h) {
            $len = mb_strlen($h);
            echo '<div class="asset-item"><span>' . htmlspecialchars($h) . '</span><span class="char-count ' . ($len > 30 ? 'over' : '') . '">' . $len . '/30</span></div>';
        }
        echo '</div></div>';
    }
    
    // Long Headlines
    if (!empty($data['long_headlines'])) {
        echo '<div class="card" style="margin-top:16px"><h4>📝 Long Headlines (' . count($data['long_headlines']) . '/5)</h4>';
        echo '<div class="asset-list">';
        foreach ($data['long_headlines'] as $h) {
            $len = mb_strlen($h);
            echo '<div class="asset-item"><span>' . htmlspecialchars($h) . '</span><span class="char-count ' . ($len > 90 ? 'over' : '') . '">' . $len . '/90</span></div>';
        }
        echo '</div></div>';
    }
    
    // Descriptions
    if (!empty($data['descriptions'])) {
        echo '<div class="card" style="margin-top:16px"><h4>📝 Descriptions (' . count($data['descriptions']) . '/5)</h4>';
        echo '<div class="asset-list">';
        foreach ($data['descriptions'] as $d) {
            $len = mb_strlen($d);
            echo '<div class="asset-item"><span>' . htmlspecialchars($d) . '</span><span class="char-count ' . ($len > 90 ? 'over' : '') . '">' . $len . '/90</span></div>';
        }
        echo '</div></div>';
    }
    
    // Image suggestions
    if (!empty($data['image_suggestions'])) {
        echo '<div class="card" style="margin-top:16px"><h4>🖼️ Kép Javaslatok</h4>';
        echo '<div class="image-suggestions">';
        foreach ($data['image_suggestions'] as $img) {
            echo '<div class="img-suggestion">';
            echo '<span class="img-type">' . htmlspecialchars($img['type']) . '</span>';
            echo '<p><strong>Tartalom:</strong> ' . htmlspecialchars($img['content']) . '</p>';
            echo '<p><strong>Hangulat:</strong> ' . htmlspecialchars($img['mood']) . '</p>';
            echo '</div>';
        }
        echo '</div></div>';
    }
    
    // Audience
    if (!empty($data['audience_signals'])) {
        echo '<div class="card" style="margin-top:16px"><h4>🎯 Célközönség Javaslatok</h4><div class="tags-list">';
        foreach ($data['audience_signals'] as $a) echo '<span class="tag tag-blue">' . htmlspecialchars($a) . '</span>';
        echo '</div></div>';
    }
    
    // Tips
    if (!empty($data['tips'])) {
        echo '<div class="card card-tips" style="margin-top:16px"><h4>💡 PMax Tippek</h4><ul>';
        foreach ($data['tips'] as $t) echo '<li>' . htmlspecialchars($t) . '</li>';
        echo '</ul></div>';
    }
    
    echo '</div>';
}

// === BELSŐ LINKEK GENERÁTOR ===
function handleGenerateSitelinks(array $industries): void {
    $companyName = Security::sanitizeInput($_POST['company_name'] ?? '', 'string');
    $websiteUrl = Security::sanitizeInput($_POST['website_url'] ?? '', 'url');
    $industryKey = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '', 'phone');
    $services = Security::sanitizeInput($_POST['services'] ?? '', 'text');
    $count = (int)($_POST['count'] ?? 6);
    $style = Security::sanitizeInput($_POST['style'] ?? 'action', 'alpha');
    
    if (empty($companyName) || empty($websiteUrl)) {
        echo renderError('A cégnév és weboldal URL megadása kötelező.');
        return;
    }
    
    if (!isset($industries[$industryKey])) {
        echo renderError('Válassz iparágat!');
        return;
    }
    
    $industry = $industries[$industryKey];
    $count = max(4, min(8, $count)); // 4-8 között
    
    $styleText = match($style) {
        'action' => 'Cselekvésre ösztönző stílus - használj felszólító módot, sürgősséget sugalló szavakat',
        'info' => 'Informatív stílus - tiszta, leíró szövegek a szolgáltatásokról',
        'mixed' => 'Vegyes stílus - néhány cselekvésre ösztönző, néhány informatív',
        default => ''
    };
    
    // AI prompt
    $prompt = "Generálj Google Ads SITELINK EXTENSION-öket (webhelyhivatkozásokat) magyarul.

CÉG ADATOK:
- Cégnév: $companyName
- Weboldal: $websiteUrl
- Iparág: {$industry['name']}
- Telefon: " . ($phone ?: 'nincs megadva') . "
" . ($services ? "- Fő szolgáltatások/oldalak:\n$services" : "") . "

STÍLUS: $styleText

KARAKTERLIMITEK (SZIGORÚAN TARTSD BE!):
- Link szöveg: MAXIMUM 25 karakter
- Leírás 1. sor: MAXIMUM 35 karakter
- Leírás 2. sor: MAXIMUM 35 karakter

GENERÁLJ PONTOSAN $count DB SITELINK-ET!

Minden sitelinknek MÁS szolgáltatásra/oldalra kell mutatnia:
- Szolgáltatás oldalak (pl. /vizszereles, /dugulaselharitas)
- Árak oldal
- Kapcsolat/Ajánlatkérés
- Rólunk/Referenciák
- Akciók/Garancia

A válasz CSAK JSON legyen:
{
    \"sitelinks\": [
        {
            \"text\": \"Link Szövege\",
            \"text_chars\": 14,
            \"description1\": \"Első sor leírás\",
            \"desc1_chars\": 16,
            \"description2\": \"Második sor leírás\",
            \"desc2_chars\": 19,
            \"url_suggestion\": \"/oldal-url\"
        }
    ],
    \"tips\": [\"tanácsok a sitelink használatához\"]
}

FONTOS:
- A text_chars, desc1_chars, desc2_chars mezőkben add meg a PONTOS karakterszámot
- NE lépd túl a limiteket! Ha túl hosszú, rövidítsd!
- Magyar ékezetes karakterek is 1 karakternek számítanak
- Minden Title Case legyen (Minden Szó Nagybetűvel)
- Konkrét, specifikus szövegek kellenek, nem általánosságok";

    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    if (empty($data['sitelinks'])) {
        echo renderError('Nem sikerült generálni a sitelinkeket. Próbáld újra!');
        return;
    }
    
    // Eredmények renderelése
    echo '<div class="sitelink-results">';
    
    // Összefoglaló header
    echo '<div class="card" style="background:linear-gradient(135deg, #dbeafe, #bfdbfe); border-color:#3b82f6;">';
    echo '<div style="display:flex; justify-content:space-between; align-items:center;">';
    echo '<div>';
    echo '<h3 class="card-title" style="margin:0;">✅ ' . count($data['sitelinks']) . ' Sitelink Generálva</h3>';
    echo '<p style="margin:4px 0 0; font-size:13px; color:var(--text-muted);">Másold be a Google Ads-ba a hirdetésbővítmények közé</p>';
    echo '</div>';
    echo '<button class="btn btn-primary" onclick="copyAllSitelinks()">📋 Összes Másolása</button>';
    echo '</div></div>';
    
    // Sitelink kártyák
    echo '<div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-top:16px;">';
    
    foreach ($data['sitelinks'] as $i => $sl) {
        $textLen = mb_strlen($sl['text'] ?? '');
        $desc1Len = mb_strlen($sl['description1'] ?? '');
        $desc2Len = mb_strlen($sl['description2'] ?? '');
        
        $textClass = $textLen > 25 ? 'color:var(--red);' : '';
        $desc1Class = $desc1Len > 35 ? 'color:var(--red);' : '';
        $desc2Class = $desc2Len > 35 ? 'color:var(--red);' : '';
        
        echo '<div class="card" data-sitelink="' . $i . '" style="position:relative;">';
        echo '<div style="position:absolute; top:12px; right:12px;">';
        echo '<button class="btn btn-sm btn-secondary" onclick="copySitelink(' . $i . ')">📋</button>';
        echo '</div>';
        
        echo '<h4 style="margin-bottom:12px; color:var(--blue);">🔗 Sitelink ' . ($i + 1) . '</h4>';
        
        // Link szöveg
        echo '<div style="margin-bottom:12px;">';
        echo '<label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:4px;">Link szöveg</label>';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg); padding:10px 12px; border-radius:6px; border:1px solid var(--border);">';
        echo '<span class="sl-text" style="font-weight:600;">' . htmlspecialchars($sl['text'] ?? '') . '</span>';
        echo '<span style="font-size:11px; ' . $textClass . '">' . $textLen . '/25</span>';
        echo '</div></div>';
        
        // Leírás 1
        echo '<div style="margin-bottom:12px;">';
        echo '<label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:4px;">Leírás 1. sor</label>';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg); padding:10px 12px; border-radius:6px; border:1px solid var(--border);">';
        echo '<span class="sl-desc1">' . htmlspecialchars($sl['description1'] ?? '') . '</span>';
        echo '<span style="font-size:11px; ' . $desc1Class . '">' . $desc1Len . '/35</span>';
        echo '</div></div>';
        
        // Leírás 2
        echo '<div style="margin-bottom:12px;">';
        echo '<label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:4px;">Leírás 2. sor</label>';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg); padding:10px 12px; border-radius:6px; border:1px solid var(--border);">';
        echo '<span class="sl-desc2">' . htmlspecialchars($sl['description2'] ?? '') . '</span>';
        echo '<span style="font-size:11px; ' . $desc2Class . '">' . $desc2Len . '/35</span>';
        echo '</div></div>';
        
        // URL javaslat
        echo '<div>';
        echo '<label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:4px;">Javasolt URL</label>';
        echo '<code class="sl-url" style="display:block; background:#1e293b; color:#94a3b8; padding:8px 12px; border-radius:6px; font-size:12px;">';
        echo htmlspecialchars($websiteUrl . ($sl['url_suggestion'] ?? ''));
        echo '</code></div>';
        
        echo '</div>';
    }
    
    echo '</div>';
    
    // Mobil előnézet
    echo '<div class="card" style="margin-top:24px;">';
    echo '<h3 class="card-title">📱 Előnézet (ahogy megjelenik)</h3>';
    echo '<div style="max-width:400px; margin:0 auto; background:white; border:1px solid #ddd; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
    
    // Hirdetés preview
    echo '<div style="font-size:11px; color:#5f6368; margin-bottom:4px;">Szponzorált</div>';
    echo '<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">';
    echo '<div style="width:20px; height:20px; background:#4285f4; border-radius:50%; display:flex; align-items:center; justify-content:center;"><span style="color:white; font-size:10px;">🌐</span></div>';
    echo '<div>';
    echo '<div style="font-size:14px; color:#202124;">' . htmlspecialchars(parse_url($websiteUrl, PHP_URL_HOST)) . '</div>';
    echo '<div style="font-size:12px; color:#5f6368;">' . htmlspecialchars($websiteUrl) . '</div>';
    echo '</div></div>';
    
    echo '<div style="font-size:18px; color:#1a0dab; font-weight:400; line-height:1.3; margin-bottom:8px;">';
    echo htmlspecialchars($companyName) . ' | ' . htmlspecialchars($industry['name']);
    echo '</div>';
    
    echo '<div style="font-size:13px; color:#4d5156; line-height:1.5; margin-bottom:12px;">';
    echo 'Professzionális szolgáltatás. Hívjon most!';
    echo '</div>';
    
    // Sitelink-ek preview
    echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">';
    foreach (array_slice($data['sitelinks'], 0, 4) as $sl) {
        echo '<a href="#" style="color:#1a0dab; font-size:13px; text-decoration:none;">' . htmlspecialchars($sl['text'] ?? '') . '</a>';
    }
    echo '</div>';
    
    echo '</div></div>';
    
    // Tippek
    if (!empty($data['tips'])) {
        echo '<div class="card card-tips" style="margin-top:16px;">';
        echo '<h4>💡 Tippek a Sitelink-ekhez</h4>';
        echo '<ul>';
        foreach ($data['tips'] as $tip) {
            echo '<li>' . htmlspecialchars($tip) . '</li>';
        }
        echo '</ul></div>';
    }
    
    // Google Ads Editor export
    echo '<div class="card" style="margin-top:16px; text-align:center; padding:24px; background:linear-gradient(135deg, #fef3c7, #fde68a);">';
    echo '<h4>📤 Beillesztés a Google Ads-ba</h4>';
    echo '<p style="margin-bottom:16px; font-size:13px;">A sitelink-eket egyenként add hozzá a kampányhoz vagy hirdetéscsoporthoz:</p>';
    echo '<p style="font-size:13px;"><strong>Google Ads → Hirdetések és bővítmények → Bővítmények → + → Webhelyhivatkozás</strong></p>';
    echo '</div>';
    
    echo '</div>';
}

// === SCHEMA.ORG GENERÁTOR ===
function handleGenerateSchema(array $industries): void {
    $businessName = Security::sanitizeInput($_POST['business_name'] ?? '', 'string');
    $websiteUrl = Security::sanitizeInput($_POST['website_url'] ?? '', 'url');
    $industryKey = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '', 'phone');
    $address = Security::sanitizeInput($_POST['address'] ?? '', 'string');
    $openingHours = Security::sanitizeInput($_POST['opening_hours'] ?? '', 'string');
    $serviceArea = Security::sanitizeInput($_POST['service_area'] ?? '', 'string');
    $schemaTypes = $_POST['schema_types'] ?? ['LocalBusiness'];
    
    if (empty($businessName) || empty($websiteUrl)) {
        echo renderError('A cégnév és weboldal URL megadása kötelező.');
        return;
    }
    
    if (!isset($industries[$industryKey])) {
        echo renderError('Válassz iparágat!');
        return;
    }
    
    $industry = $industries[$industryKey];
    
    // AI prompt a schema generáláshoz
    $prompt = "Generálj Schema.org JSON-LD kódokat egy magyar vállalkozásnak.

CÉG ADATOK:
- Cégnév: $businessName
- Weboldal: $websiteUrl
- Iparág: {$industry['name']}
- Telefon: " . ($phone ?: 'nincs megadva') . "
- Cím: " . ($address ?: 'nincs megadva') . "
- Nyitvatartás: " . ($openingHours ?: 'nincs megadva') . "
- Szolgáltatási terület: " . ($serviceArea ?: 'nincs megadva') . "

KÉRT SCHEMA TÍPUSOK: " . implode(', ', $schemaTypes) . "

GENERÁLJ MINDEN KÉRT TÍPUSHOZ EGY KOMPLETT, VALID JSON-LD KÓDOT!

A válaszod legyen JSON formátumban:
{
    \"schemas\": [
        {
            \"type\": \"LocalBusiness\",
            \"name\": \"Helyi Vállalkozás\",
            \"description\": \"Mire jó ez a schema\",
            \"code\": \"<script type=\\\"application/ld+json\\\">...</script>\"
        }
    ],
    \"implementation_tips\": [\"beillesztési tanácsok\"],
    \"common_mistakes\": [\"gyakori hibák amiket kerülj\"]
}

FONTOS:
- A 'code' mezőben TELJES, MŰKÖDŐ JSON-LD kód legyen <script> taggal
- LocalBusiness-nél használj megfelelő altípust (pl. Plumber, Electrician, stb.)
- FAQPage-nél generálj 3-5 releváns kérdés-választ az iparágra szabva
- Service-nél adj meg árat (priceRange) ha releváns
- Minden kód legyen VALID és a Google Rich Results Test-en átmenjen!
- Magyar nyelvű tartalom (description, FAQ válaszok stb.)";

    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    if (empty($data['schemas'])) {
        echo renderError('Nem sikerült generálni a schema kódokat. Próbáld újra!');
        return;
    }
    
    // Eredmények renderelése
    echo '<div class="schema-results">';
    
    echo '<div class="card" style="background:linear-gradient(135deg, #f0fdf4, #dcfce7); border-color:#22c55e;">';
    echo '<h3 class="card-title">✅ ' . count($data['schemas']) . ' Schema Kód Generálva</h3>';
    echo '<p>Másold ki a kódokat és illeszd be a weboldalad <code>&lt;head&gt;</code> szekciójába!</p>';
    echo '</div>';
    
    foreach ($data['schemas'] as $i => $schema) {
        $codeId = 'schema_code_' . $i;
        echo '<div class="card" style="margin-top:16px;">';
        echo '<div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">';
        echo '<div>';
        echo '<h3 class="card-title" style="margin:0;">📋 ' . htmlspecialchars($schema['type'] ?? '') . '</h3>';
        echo '<p style="font-size:13px; color:var(--text-muted); margin:4px 0 0;">' . htmlspecialchars($schema['description'] ?? '') . '</p>';
        echo '</div>';
        echo '<button class="btn btn-primary btn-sm" onclick="copySchemaCode(\'' . $codeId . '\')">📋 Kód Másolása</button>';
        echo '</div>';
        
        echo '<div style="margin-top:16px;">';
        echo '<pre id="' . $codeId . '" style="background:#1e293b; color:#e2e8f0; padding:16px; border-radius:8px; overflow-x:auto; font-size:12px; line-height:1.5;">';
        
        // Kód szépítése
        $code = $schema['code'] ?? '';
        // Ha JSON van benne, formázzuk
        if (preg_match('/<script[^>]*>(.*?)<\/script>/s', $code, $jsonMatch)) {
            $jsonContent = $jsonMatch[1];
            $decoded = json_decode($jsonContent, true);
            if ($decoded) {
                $prettyJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $code = '<script type="application/ld+json">' . "\n" . $prettyJson . "\n" . '</script>';
            }
        }
        
        echo htmlspecialchars($code);
        echo '</pre>';
        echo '</div>';
        echo '</div>';
    }
    
    // Tippek
    if (!empty($data['implementation_tips'])) {
        echo '<div class="card card-tips" style="margin-top:16px;">';
        echo '<h4>💡 Beillesztési Tanácsok</h4>';
        echo '<ul>';
        foreach ($data['implementation_tips'] as $tip) {
            echo '<li>' . htmlspecialchars($tip) . '</li>';
        }
        echo '</ul></div>';
    }
    
    // Hibák
    if (!empty($data['common_mistakes'])) {
        echo '<div class="card" style="margin-top:16px; background:#fef2f2; border-color:#fecaca;">';
        echo '<h4 style="color:var(--red);">⚠️ Kerülendő Hibák</h4>';
        echo '<ul>';
        foreach ($data['common_mistakes'] as $mistake) {
            echo '<li>' . htmlspecialchars($mistake) . '</li>';
        }
        echo '</ul></div>';
    }
    
    // Tesztelés link
    echo '<div class="card" style="margin-top:16px; text-align:center; padding:24px;">';
    echo '<h4>🔍 Ellenőrizd a Kódokat!</h4>';
    echo '<p style="margin-bottom:16px;">Beillesztés után teszteld le, hogy helyesen működnek-e:</p>';
    echo '<a href="https://search.google.com/test/rich-results?url=' . urlencode($websiteUrl) . '" target="_blank" class="btn btn-primary">';
    echo '🔗 Google Rich Results Test Megnyitása</a>';
    echo '<a href="https://validator.schema.org/" target="_blank" class="btn btn-secondary" style="margin-left:8px;">';
    echo '🔗 Schema.org Validator</a>';
    echo '</div>';
    
    echo '</div>';
}

// === INDUSTRY GENERATOR ===
function handleGenerateIndustry(): void {
    $name = Security::sanitizeInput($_POST['name'] ?? '', 'string');
    $description = $_POST['description'] ?? '';
    $icon = $_POST['icon'] ?? '🏢';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Név megadása kötelező']);
        return;
    }
    
    $prompt = "Generálj egy teljes iparág konfigurációt egy magyar Google Ads kampány kezelő rendszerhez.

IPARÁG: $name
" . ($description ? "LEÍRÁS: $description\n" : "") . "

Generálj MAGYAR nyelven:
1. 8-10 USP (egyedi értékesítési pont) - amik jól működnek hirdetésekben
2. Benchmark adatok (CPA, CPC - magyar piacra)
3. 15-20 negatív kulcsszó - amiket ki kell zárni
4. 10-15 javasolt kulcsszó
5. 5-8 agresszív headline sablon

Válasz JSON:
{
    \"key\": \"" . strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . "\",
    \"name\": \"$name\",
    \"icon\": \"$icon\",
    \"usps\": [
        {\"id\": \"usp1\", \"text\": \"...\", \"impact\": \"high/medium\"}
    ],
    \"benchmarks\": {
        \"cpa_budapest\": \"X-Y.000 Ft\",
        \"cpa_videk\": \"X-Y.000 Ft\",
        \"cpc\": \"XXX-XXX Ft\",
        \"conversion_rate\": \"X-Y%\",
        \"daily_budget_min\": 5000,
        \"daily_budget_recommended\": 15000,
        \"daily_budget_max\": 50000
    },
    \"negative_keywords\": [\"...\"],
    \"suggested_keywords\": [\"...\"],
    \"headline_templates\": [\"...\"]
}";
    
    $response = callAnthropicAPI($prompt);
    $industryData = parseJsonResponse($response);
    
    if (empty($industryData['key'])) {
        echo json_encode(['success' => false, 'error' => 'AI nem tudta generálni']);
        return;
    }
    
    // Mentés custom_industries.json-be
    $customFile = __DIR__ . '/data/custom_industries.json';
    $customs = [];
    if (file_exists($customFile)) {
        $customs = json_decode(file_get_contents($customFile), true) ?: [];
    }
    
    $customs[$industryData['key']] = $industryData;
    file_put_contents($customFile, json_encode($customs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true, 'industry' => $industryData]);
}

function handleDeleteIndustry(): void {
    $key = Security::sanitizeInput($_POST['key'] ?? '', 'alphanumeric');
    
    $customFile = __DIR__ . '/data/custom_industries.json';
    if (!file_exists($customFile)) {
        echo json_encode(['success' => false]);
        return;
    }
    
    $customs = json_decode(file_get_contents($customFile), true) ?: [];
    if (isset($customs[$key])) {
        unset($customs[$key]);
        file_put_contents($customFile, json_encode($customs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    echo json_encode(['success' => true]);
}

// === CHROME EXTENSION API ===
function handleExtensionPing(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!verifyExtensionAuth()) return;
    
    echo json_encode([
        'success' => true,
        'version' => APP_VERSION,
        'name' => APP_NAME
    ]);
}

function handleGetExtensionData(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!verifyExtensionAuth()) return;
    
    // Load last generated data from session or file
    $dataFile = __DIR__ . '/data/extension_data.json';
    
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
        echo json_encode([
            'success' => true,
            'headlines' => $data['headlines'] ?? [],
            'descriptions' => $data['descriptions'] ?? [],
            'callonly' => $data['callonly'] ?? [],
            'lastUpdate' => $data['lastUpdate'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'headlines' => [],
            'descriptions' => [],
            'callonly' => [],
            'lastUpdate' => null
        ]);
    }
}

function handleSaveExtensionData(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!verifyExtensionAuth()) return;
    
    $headlines = json_decode($_POST['headlines'] ?? '[]', true) ?: [];
    $descriptions = json_decode($_POST['descriptions'] ?? '[]', true) ?: [];
    $callonly = json_decode($_POST['callonly'] ?? '[]', true) ?: [];
    
    $data = [
        'headlines' => $headlines,
        'descriptions' => $descriptions,
        'callonly' => $callonly,
        'lastUpdate' => date('Y-m-d H:i:s')
    ];
    
    $dataFile = __DIR__ . '/data/extension_data.json';
    $result = file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => $result !== false]);
}

// === COMPETITOR SPY (Chrome Extension) ===

function handleSaveCompetitorScan(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!verifyExtensionAuth()) return;
    
    $query = Security::sanitizeInput($_POST['query'] ?? '', 'text');
    $adsRaw = $_POST['ads'] ?? '[]';
    $scannedAt = Security::sanitizeInput($_POST['scanned_at'] ?? '', 'text');
    
    $ads = json_decode($adsRaw, true);
    if (!is_array($ads)) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen adat']);
        return;
    }
    
    // Hirdetések tisztítása és sanitizálása
    $cleanAds = [];
    foreach ($ads as $ad) {
        $cleanAds[] = [
            'headline' => Security::sanitizeInput($ad['headline'] ?? '', 'text'),
            'description' => Security::sanitizeInput($ad['description'] ?? '', 'text'),
            'url' => filter_var($ad['url'] ?? '', FILTER_SANITIZE_URL),
            'displayUrl' => Security::sanitizeInput($ad['displayUrl'] ?? '', 'text'),
            'sitelinks' => array_map(fn($s) => Security::sanitizeInput($s, 'text'), $ad['sitelinks'] ?? []),
            'callouts' => array_map(fn($c) => Security::sanitizeInput($c, 'text'), $ad['callouts'] ?? []),
            'position' => (int)($ad['position'] ?? 0),
        ];
    }
    
    $scan = [
        'id' => uniqid('scan_'),
        'query' => $query,
        'ads' => $cleanAds,
        'totalAds' => count($cleanAds),
        'scannedAt' => $scannedAt ?: date('c'),
        'savedAt' => date('Y-m-d H:i:s'),
    ];
    
    // Mentés fájlba
    $scanFile = __DIR__ . '/data/competitor_scans.json';
    $scans = [];
    if (file_exists($scanFile)) {
        $scans = json_decode(file_get_contents($scanFile), true);
        if (!is_array($scans)) $scans = [];
    }
    
    // Elejére, max 50 scan
    array_unshift($scans, $scan);
    $scans = array_slice($scans, 0, 50);
    
    $result = file_put_contents($scanFile, json_encode($scans, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // A legutolsó scan adatait tegyük elérhetővé a prompt számára is
    $latestFile = __DIR__ . '/data/latest_competitor_data.json';
    file_put_contents($latestFile, json_encode([
        'query' => $query,
        'ads' => $cleanAds,
        'totalAds' => count($cleanAds),
        'scannedAt' => $scan['scannedAt'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => $result !== false,
        'scanId' => $scan['id'],
        'totalAds' => count($cleanAds),
    ]);
}

function handleGetCompetitorScans(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!verifyExtensionAuth()) return;
    
    $scanFile = __DIR__ . '/data/competitor_scans.json';
    
    if (file_exists($scanFile)) {
        $scans = json_decode(file_get_contents($scanFile), true) ?: [];
        echo json_encode([
            'success' => true,
            'scans' => $scans,
            'totalScans' => count($scans),
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'scans' => [],
            'totalScans' => 0,
        ]);
    }
}

/**
 * Chrome extension-ből érkező Google Ads kampány import mentése
 */
function handleSaveGadsImport(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!verifyExtensionAuth()) return;
    
    $campaignJson = $_POST['campaign_data'] ?? '';
    $campaignData = json_decode($campaignJson, true);
    
    if (!$campaignData) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen kampány adat']);
        return;
    }
    
    // Mentés gads_imports.json fájlba
    $importFile = __DIR__ . '/data/gads_imports.json';
    $imports = file_exists($importFile) ? json_decode(file_get_contents($importFile), true) ?: [] : [];
    
    // Elejére
    array_unshift($imports, $campaignData);
    
    // Max 30 import megőrzése
    $imports = array_slice($imports, 0, 30);
    
    file_put_contents($importFile, json_encode($imports, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // Legutolsó import mentése latest fájlba is (amit a generátor is használ)
    $latestFile = __DIR__ . '/data/latest_gads_import.json';
    file_put_contents($latestFile, json_encode($campaignData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    $adCount = count($campaignData['ads'] ?? []);
    $kwCount = count($campaignData['keywords'] ?? []);
    
    echo json_encode([
        'success' => true,
        'message' => "Kampány importálva: {$adCount} hirdetés, {$kwCount} kulcsszó",
        'adsCount' => $adCount,
        'keywordsCount' => $kwCount
    ]);
}

// === AI DIAGNOSIS ===
function handleRunDiagnosis(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $landing_url = Security::sanitizeInput($_POST['landing_url'] ?? '', 'url');
    $headlines = $_POST['headlines'] ?? '';
    $keywords = $_POST['keywords'] ?? '';
    $clicks = (int)($_POST['clicks'] ?? 0);
    $leads = (int)($_POST['leads'] ?? 0);
    $spend = (int)($_POST['spend'] ?? 0);
    $bid_strategy = Security::sanitizeInput($_POST['bid_strategy'] ?? '', 'alphanumeric');
    
    if (!isset($industries[$industry_key])) {
        echo renderError('Válassz iparágat!');
        return;
    }
    
    $industry = $industries[$industry_key];
    
    // Landing page audit ha van URL
    $landingData = null;
    if ($landing_url) {
        $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'AdMaster Bot']]);
        $html = @file_get_contents($landing_url, false, $context);
        if ($html) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);
            
            $landingData = [
                'has_phone' => preg_match('/(\+36|06)[\s\-]?\d{1,2}[\s\-]?\d{3}[\s\-]?\d{3,4}/', $html),
                'has_form' => $xpath->query('//form')->length > 0,
                'h1' => $xpath->query('//h1')->item(0) ? trim($xpath->query('//h1')->item(0)->textContent) : null,
                'cta_count' => $xpath->query('//a[contains(@class,"btn") or contains(@class,"button")] | //button')->length
            ];
        }
    }
    
    // Teljesítmény számítások
    $cvr = $clicks > 0 ? round(($leads / $clicks) * 100, 2) : 0;
    $cpa = $leads > 0 ? round($spend / $leads) : 0;
    $benchmark_cpa = (int)preg_replace('/[^0-9]/', '', $industry['benchmarks']['cpa_budapest'] ?? '8000');
    
    // AI prompt
    $prompt = "Te egy magyar Google Ads szakértő vagy. Végezz MÉLYREHATÓ DIAGNÓZIST ezen a kampányon és mondd meg MIÉRT NEM HOZ LEADET.

IPARÁG: {$industry['name']}
BENCHMARK CPA: {$benchmark_cpa} Ft

KAMPÁNY ADATOK:
- Headlines: " . ($headlines ?: 'nincs megadva') . "
- Kulcsszavak: " . ($keywords ?: 'nincs megadva') . "
- Bid stratégia: " . ($bid_strategy ?: 'nincs megadva') . "

TELJESÍTMÉNY:
- Kattintások: $clicks
- Leadek: $leads
- Költés: $spend Ft
- Konverziós ráta: $cvr%
- Aktuális CPA: " . ($cpa ?: 'n/a') . " Ft

" . ($landingData ? "LANDING PAGE:
- Van telefon: " . ($landingData['has_phone'] ? 'igen' : 'NEM!') . "
- Van form: " . ($landingData['has_form'] ? 'igen' : 'NEM!') . "
- H1: " . ($landingData['h1'] ?: 'HIÁNYZIK!') . "
- CTA gombok: " . $landingData['cta_count'] : "") . "

ELEMEZD ÉS ADJ DIAGNÓZIST:
1. Mi a FŐ PROBLÉMA?
2. Kulcsszó intent elemzés
3. Headline minőség értékelés
4. Landing page problémák
5. Stratégia/beállítás hibák
6. KONKRÉT JAVÍTÁSI LÉPÉSEK (prioritás sorrendben)

Válasz JSON:
{
    \"overall_score\": 0-100,
    \"main_problem\": \"egyetlen mondatos diagnózis\",
    \"issues\": [
        {\"category\": \"keyword/headline/landing/strategy/budget\", \"severity\": \"critical/warning/info\", \"issue\": \"probléma\", \"fix\": \"megoldás\"}
    ],
    \"keyword_analysis\": {\"score\": 0-100, \"problems\": [\"...\"], \"suggestions\": [\"...\"]},
    \"headline_analysis\": {\"score\": 0-100, \"problems\": [\"...\"], \"suggestions\": [\"...\"]},
    \"landing_analysis\": {\"score\": 0-100, \"problems\": [\"...\"], \"suggestions\": [\"...\"]},
    \"strategy_analysis\": {\"score\": 0-100, \"problems\": [\"...\"], \"suggestions\": [\"...\"]},
    \"action_plan\": [
        {\"priority\": 1, \"action\": \"mit csinálj\", \"impact\": \"high/medium/low\", \"effort\": \"easy/medium/hard\"}
    ],
    \"expected_improvement\": \"ha mindent megcsinálsz, X% javulás várható\"
}";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    // Render eredmények
    echo '<div class="diagnosis-results">';
    
    // Overall score
    $score = $data['overall_score'] ?? 0;
    $scoreClass = $score >= 70 ? 'score-good' : ($score >= 40 ? 'score-warning' : 'score-bad');
    
    echo '<div class="diagnosis-header">';
    echo '<div class="diagnosis-score ' . $scoreClass . '">';
    echo '<span class="score-value">' . $score . '</span>';
    echo '<span class="score-label">/ 100</span>';
    echo '</div>';
    echo '<div class="diagnosis-main-problem">';
    echo '<h3>🔴 Fő Probléma</h3>';
    echo '<p>' . htmlspecialchars($data['main_problem'] ?? 'Nem sikerült azonosítani') . '</p>';
    echo '</div>';
    echo '</div>';
    
    // Issues list
    if (!empty($data['issues'])) {
        echo '<div class="card diagnosis-issues">';
        echo '<h3 class="card-title">❌ Azonosított Problémák</h3>';
        
        $severityIcons = ['critical' => '🔴', 'warning' => '🟡', 'info' => '🔵'];
        $categoryNames = ['keyword' => 'Kulcsszó', 'headline' => 'Headline', 'landing' => 'Landing', 'strategy' => 'Stratégia', 'budget' => 'Büdzsé'];
        
        foreach ($data['issues'] as $issue) {
            $icon = $severityIcons[$issue['severity']] ?? '⚪';
            $cat = $categoryNames[$issue['category']] ?? $issue['category'];
            
            echo '<div class="issue-item issue-' . $issue['severity'] . '">';
            echo '<div class="issue-header">';
            echo '<span class="issue-icon">' . $icon . '</span>';
            echo '<span class="issue-category">' . $cat . '</span>';
            echo '</div>';
            echo '<div class="issue-text">' . htmlspecialchars($issue['issue']) . '</div>';
            echo '<div class="issue-fix">💡 ' . htmlspecialchars($issue['fix']) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Scores breakdown
    echo '<div class="diagnosis-scores-grid">';
    
    $analyses = [
        'keyword_analysis' => ['🔤', 'Kulcsszavak'],
        'headline_analysis' => ['📝', 'Headlines'],
        'landing_analysis' => ['🌐', 'Landing Page'],
        'strategy_analysis' => ['🎯', 'Stratégia']
    ];
    
    foreach ($analyses as $key => $info) {
        if (isset($data[$key])) {
            $aScore = $data[$key]['score'] ?? 0;
            $aClass = $aScore >= 70 ? 'score-good' : ($aScore >= 40 ? 'score-warning' : 'score-bad');
            
            echo '<div class="analysis-card">';
            echo '<div class="analysis-header">';
            echo '<span class="analysis-icon">' . $info[0] . '</span>';
            echo '<span class="analysis-name">' . $info[1] . '</span>';
            echo '<span class="analysis-score ' . $aClass . '">' . $aScore . '</span>';
            echo '</div>';
            
            if (!empty($data[$key]['problems'])) {
                echo '<div class="analysis-problems">';
                foreach (array_slice($data[$key]['problems'], 0, 3) as $p) {
                    echo '<div class="problem-item">❌ ' . htmlspecialchars($p) . '</div>';
                }
                echo '</div>';
            }
            
            if (!empty($data[$key]['suggestions'])) {
                echo '<div class="analysis-suggestions">';
                foreach (array_slice($data[$key]['suggestions'], 0, 2) as $s) {
                    echo '<div class="suggestion-item">✅ ' . htmlspecialchars($s) . '</div>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
    }
    echo '</div>';
    
    // Action Plan
    if (!empty($data['action_plan'])) {
        echo '<div class="card diagnosis-action-plan">';
        echo '<h3 class="card-title">📋 Cselekvési Terv (Prioritás Sorrendben)</h3>';
        
        foreach ($data['action_plan'] as $action) {
            $impactClass = 'impact-' . ($action['impact'] ?? 'medium');
            $effortBadge = ['easy' => '🟢 Könnyű', 'medium' => '🟡 Közepes', 'hard' => '🔴 Nehéz'][$action['effort']] ?? '';
            
            echo '<div class="action-item ' . $impactClass . '">';
            echo '<div class="action-priority">#' . ($action['priority'] ?? '?') . '</div>';
            echo '<div class="action-content">';
            echo '<div class="action-text">' . htmlspecialchars($action['action']) . '</div>';
            echo '<div class="action-meta">';
            echo '<span class="action-impact">Hatás: ' . ucfirst($action['impact'] ?? 'medium') . '</span>';
            echo '<span class="action-effort">' . $effortBadge . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Expected improvement
    if (!empty($data['expected_improvement'])) {
        echo '<div class="diagnosis-conclusion">';
        echo '<h3>📈 Várható Javulás</h3>';
        echo '<p>' . htmlspecialchars($data['expected_improvement']) . '</p>';
        echo '</div>';
    }
    
    echo '</div>';
}

// === AI VISION LANDING PAGE ANALYSIS ===
function handleAnalyzeLandingVision(array $industries): void {
    require_once __DIR__ . '/includes/VisionAnalyzer.php';
    
    $url = Security::sanitizeInput($_POST['url'] ?? '', 'url');
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    
    if (empty($url)) {
        echo renderError('URL megadása kötelező.');
        return;
    }
    
    // URL validáció
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        // Próbáljuk https-sel
        if (filter_var('https://' . $url, FILTER_VALIDATE_URL)) {
            $url = 'https://' . $url;
        } else {
            echo renderError('Érvénytelen URL formátum.');
            return;
        }
    }
    
    // API key ellenőrzés
    if (empty(Settings::anthropicApiKey())) {
        echo renderError('Az Anthropic API kulcs nincs beállítva! Állítsd be a Beállítások oldalon.');
        return;
    }
    
    $visionAnalyzer = new VisionAnalyzer();
    
    // Iparág
    $industry = isset($industries[$industry_key]) ? $industries[$industry_key] : null;
    
    // Próbáljuk Screenshot API-val ha van
    $screenshot = null;
    if ($visionAnalyzer->isConfigured()) {
        echo '<div class="vision-progress">';
        echo '<p>📸 Screenshot készítése: <code>' . htmlspecialchars($url) . '</code></p>';
        ob_flush(); flush();
        
        $screenshot = $visionAnalyzer->captureScreenshot($url);
    }
    
    // Ha nincs screenshot, használjunk URL-alapú elemzést
    if (!$screenshot) {
        echo '<div class="vision-progress">';
        echo '<p>🔍 Landing page tartalom letöltése...</p>';
        ob_flush(); flush();
        
        // Oldal letöltése cURL-lel
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: hu-HU,hu;q=0.9,en;q=0.8',
            ],
            CURLOPT_ENCODING => '',
        ]);
        $html = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$html || $httpCode >= 400) {
            echo '</div>';
            $errMsg = 'Nem sikerült letölteni az oldalt.';
            if ($curlError) $errMsg .= ' (' . $curlError . ')';
            elseif ($httpCode) $errMsg .= ' (HTTP ' . $httpCode . ')';
            echo renderError($errMsg);
            return;
        }
        
        echo '<p>🤖 AI elemzés folyamatban...</p>';
        ob_flush(); flush();
        
        // HTML tisztítás - csak a fontos részek
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        // Adatok kinyerése
        $pageData = [
            'title' => $xpath->query('//title')->item(0) ? trim($xpath->query('//title')->item(0)->textContent) : '',
            'h1' => $xpath->query('//h1')->item(0) ? trim($xpath->query('//h1')->item(0)->textContent) : '',
            'meta_desc' => $xpath->query('//meta[@name="description"]/@content')->item(0) ? $xpath->query('//meta[@name="description"]/@content')->item(0)->textContent : '',
            'has_form' => $xpath->query('//form')->length > 0,
            'cta_count' => $xpath->query('//a[contains(@class,"btn") or contains(@class,"button")] | //button')->length,
            'phone_found' => preg_match('/(\+36|06)[\s\-]?\d{1,2}[\s\-]?\d{3}[\s\-]?\d{3,4}/', $html, $phoneMatch) ? $phoneMatch[0] : false,
            'https' => strpos($url, 'https://') === 0,
            'has_viewport' => (bool)preg_match('/<meta[^>]+viewport/i', $html)
        ];
        
        // Body szöveg (max 3000 karakter)
        $bodyText = '';
        $paragraphs = $xpath->query('//p | //h1 | //h2 | //h3 | //li');
        foreach ($paragraphs as $p) {
            $bodyText .= trim($p->textContent) . "\n";
        }
        $bodyText = mb_substr($bodyText, 0, 3000);
        
        // AI elemzés URL alapján
        $analysis = analyzePageWithAI($url, $pageData, $bodyText, $industry);
        
        echo '</div>';
        
        renderVisionResults($analysis, $url, null);
        return;
    }
    
    // Screenshot-os elemzés
    echo '<p>🤖 AI Vision elemzés...</p>';
    ob_flush(); flush();
    
    $analysis = $visionAnalyzer->analyzeWithVision($screenshot, $url, [
        'industry' => $industry
    ]);
    
    echo '</div>';
    
    if (isset($analysis['error'])) {
        echo renderError('Vision elemzés hiba: ' . $analysis['error']);
        return;
    }
    
    renderVisionResults($analysis, $url, $screenshot);
}

/**
 * URL-alapú AI elemzés (screenshot nélkül)
 */
function analyzePageWithAI(string $url, array $pageData, string $bodyText, ?array $industry): array {
    $industryInfo = $industry ? "Iparág: {$industry['name']}" : "";
    
    $prompt = "Te egy UX/UI és CRO szakértő vagy. Elemezd ezt a magyar landing page-et az alábbi adatok alapján!

URL: $url
$industryInfo

OLDAL ADATOK:
- Title: {$pageData['title']}
- H1: {$pageData['h1']}
- Meta description: {$pageData['meta_desc']}
- Van form: " . ($pageData['has_form'] ? 'Igen' : 'Nem') . "
- CTA gombok száma: {$pageData['cta_count']}
- Telefon látható: " . ($pageData['phone_found'] ?: 'Nem') . "
- HTTPS: " . ($pageData['https'] ? 'Igen' : 'Nem') . "
- Mobile viewport: " . ($pageData['has_viewport'] ? 'Van' : 'Nincs') . "

OLDAL TARTALMA (részlet):
$bodyText

ELEMEZD ÉS ADJ VISSZA JSON-T:
{
    \"first_impression\": {
        \"score\": 0-100,
        \"clear_value_prop\": true/false,
        \"visible_cta\": true/false,
        \"issues\": [\"probléma1\", \"probléma2\"]
    },
    \"cta_analysis\": {
        \"score\": 0-100,
        \"position\": \"jó / nem látható / hiányzik\",
        \"issues\": [\"...\"]
    },
    \"trust_signals\": {
        \"score\": 0-100,
        \"has_testimonials\": true/false,
        \"has_certifications\": true/false,
        \"issues\": [\"...\"]
    },
    \"contact_visibility\": {
        \"score\": 0-100,
        \"phone_visible\": true/false,
        \"phone_prominent\": true/false,
        \"has_form\": true/false,
        \"issues\": [\"...\"]
    },
    \"mobile_readiness\": {
        \"score\": 0-100,
        \"appears_responsive\": true/false,
        \"issues\": [\"...\"]
    },
    \"overall_score\": 0-100,
    \"top_3_fixes\": [
        {\"fix\": \"mit javíts\", \"impact\": \"high/medium/low\"}
    ],
    \"positive_aspects\": [\"ami jó\"]
}";

    $response = callAnthropicAPI($prompt);
    
    // JSON kinyerése
    if (preg_match('/\{[\s\S]*\}/m', $response, $matches)) {
        $result = json_decode($matches[0], true);
        if ($result) {
            return $result;
        }
    }
    
    return ['error' => 'Nem sikerült elemezni az oldalt'];
}

/**
 * Vision eredmények renderelése
 */
function renderVisionResults(array $analysis, string $url, ?string $screenshot): void {
    if (isset($analysis['error'])) {
        echo renderError($analysis['error']);
        return;
    }
    
    echo '<div class="vision-results">';
    
    // Overall score
    $score = $analysis['overall_score'] ?? 0;
    $scoreClass = $score >= 70 ? 'score-good' : ($score >= 40 ? 'score-warning' : 'score-bad');
    
    echo '<div class="vision-header">';
    
    // Screenshot ha van
    if ($screenshot) {
        echo '<div class="vision-screenshot">';
        echo '<img src="data:image/png;base64,' . $screenshot . '" alt="Screenshot">';
        echo '</div>';
    } else {
        echo '<div class="vision-url-box">';
        echo '<span class="url-icon">🌐</span>';
        echo '<a href="' . htmlspecialchars($url) . '" target="_blank">' . htmlspecialchars($url) . '</a>';
        echo '</div>';
    }
    
    echo '<div class="vision-score-panel">';
    echo '<div class="big-score ' . $scoreClass . '">' . $score . '<span>/100</span></div>';
    echo '<p>Landing Page Pontszám</p>';
    echo '</div>';
    echo '</div>';
    
    // Kategória scores
    $categories = [
        'first_impression' => ['👁️', 'Első Benyomás'],
        'cta_analysis' => ['🎯', 'CTA Elemzés'],
        'trust_signals' => ['🏆', 'Bizalom Jelek'],
        'contact_visibility' => ['📞', 'Kontakt Láthatóság'],
        'mobile_readiness' => ['📱', 'Mobil Készség']
    ];
    
    echo '<div class="vision-categories">';
    foreach ($categories as $key => $info) {
        if (isset($analysis[$key])) {
            $catScore = $analysis[$key]['score'] ?? 0;
            $catClass = $catScore >= 70 ? 'cat-good' : ($catScore >= 40 ? 'cat-warning' : 'cat-bad');
            
            echo '<div class="vision-category ' . $catClass . '">';
            echo '<div class="cat-icon">' . $info[0] . '</div>';
            echo '<div class="cat-score">' . $catScore . '</div>';
            echo '<div class="cat-name">' . $info[1] . '</div>';
            
            if (!empty($analysis[$key]['issues'])) {
                echo '<ul class="cat-issues">';
                foreach ($analysis[$key]['issues'] as $issue) {
                    echo '<li>' . htmlspecialchars($issue) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
    
    // Top 3 fixes
    if (!empty($analysis['top_3_fixes'])) {
        echo '<div class="vision-fixes">';
        echo '<h4>🔧 Top 3 Javítandó</h4>';
        echo '<div class="fixes-list">';
        foreach ($analysis['top_3_fixes'] as $fix) {
            $impact = $fix['impact'] ?? 'medium';
            $impactClass = $impact === 'high' ? 'impact-high' : ($impact === 'low' ? 'impact-low' : 'impact-medium');
            echo '<div class="fix-item ' . $impactClass . '">';
            echo '<span class="fix-impact">' . strtoupper($impact) . '</span>';
            echo '<span class="fix-text">' . htmlspecialchars($fix['fix']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
    
    // Positive aspects
    if (!empty($analysis['positive_aspects'])) {
        echo '<div class="vision-positives">';
        echo '<h4>✅ Ami Jó</h4>';
        echo '<ul>';
        foreach ($analysis['positive_aspects'] as $pos) {
            echo '<li>' . htmlspecialchars($pos) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    echo '</div>';
}

// === GOOGLE ADS API HANDLERS ===
function handlePublishToGoogleAds(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    if (!$gads->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Ads API nincs konfigurálva']);
        return;
    }
    
    $customerId = Security::sanitizeInput($_POST['customer_id'] ?? '', 'alphanumeric');
    $adGroupId = Security::sanitizeInput($_POST['ad_group_id'] ?? '', 'alphanumeric');
    $finalUrl = Security::sanitizeInput($_POST['final_url'] ?? '', 'url');
    $path1 = Security::sanitizeInput($_POST['path1'] ?? '', 'alphanumeric');
    $path2 = Security::sanitizeInput($_POST['path2'] ?? '', 'alphanumeric');
    
    $publishRsa = isset($_POST['publish_rsa']);
    $publishKeywords = isset($_POST['publish_keywords']);
    $publishNegatives = isset($_POST['publish_negatives']);
    
    // Utolsó generált adatok betöltése
    $dataFile = __DIR__ . '/data/extension_data.json';
    if (!file_exists($dataFile)) {
        echo json_encode(['success' => false, 'error' => 'Nincs generált tartalom']);
        return;
    }
    
    $data = json_decode(file_get_contents($dataFile), true);
    $results = ['rsa' => null, 'keywords' => null, 'negatives' => null];
    
    // RSA hirdetés létrehozása
    if ($publishRsa && !empty($data['headlines']) && !empty($data['descriptions'])) {
        $results['rsa'] = $gads->createResponsiveSearchAd(
            $customerId,
            $adGroupId,
            $data['headlines'],
            $data['descriptions'],
            $finalUrl,
            $path1,
            $path2
        );
    }
    
    // Kulcsszavak hozzáadása
    if ($publishKeywords && !empty($data['keywords'])) {
        $results['keywords'] = $gads->addKeywords($customerId, $adGroupId, $data['keywords']);
    }
    
    $success = ($results['rsa']['success'] ?? false) || ($results['keywords']['success'] ?? false);
    
    echo json_encode([
        'success' => $success,
        'results' => $results
    ]);
}

function handleGetGadsAccounts(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    if (!$gads->isConfigured()) {
        echo json_encode(['success' => false, 'accounts' => []]);
        return;
    }
    
    $accounts = $gads->getAccessibleAccounts();
    
    // Resource name-ből customer ID kinyerése
    $formatted = array_map(function($rn) {
        preg_match('/customers\/(\d+)/', $rn, $m);
        return ['id' => $m[1] ?? '', 'name' => $m[1] ?? $rn];
    }, $accounts);
    
    echo json_encode(['success' => true, 'accounts' => $formatted]);
}

function handleGetGadsCampaigns(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    $customerId = Security::sanitizeInput($_POST['customer_id'] ?? '', 'alphanumeric');
    
    if (!$gads->isConfigured() || empty($customerId)) {
        echo json_encode(['success' => false, 'campaigns' => []]);
        return;
    }
    
    $campaigns = $gads->getCampaigns($customerId);
    echo json_encode(['success' => true, 'campaigns' => $campaigns]);
}

function handleGetGadsAdGroups(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    $customerId = Security::sanitizeInput($_POST['customer_id'] ?? '', 'alphanumeric');
    $campaignId = Security::sanitizeInput($_POST['campaign_id'] ?? '', 'alphanumeric');
    
    if (!$gads->isConfigured() || empty($customerId) || empty($campaignId)) {
        echo json_encode(['success' => false, 'adGroups' => []]);
        return;
    }
    
    $adGroups = $gads->getAdGroups($customerId, $campaignId);
    echo json_encode(['success' => true, 'adGroups' => $adGroups]);
}

// === GET LAST RESULT ===
function handleGetLastResult(): void {
    header('Content-Type: application/json');
    
    $dataFile = __DIR__ . '/data/last_result.json';
    
    if (!file_exists($dataFile)) {
        echo json_encode(['success' => false, 'error' => 'Nincs mentett eredmény']);
        return;
    }
    
    $data = json_decode(file_get_contents($dataFile), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Hibás adatfájl']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function handleSaveCompetitorToClient(): void {
    header('Content-Type: application/json');
    
    $clientId = Security::sanitizeInput($_POST['client_id'] ?? '', 'alphanumeric');
    $analysisJson = $_POST['analysis'] ?? '';
    
    if (empty($clientId)) {
        echo json_encode(['success' => false, 'error' => 'Válassz ügyfelet!']);
        return;
    }
    
    $analysis = json_decode($analysisJson, true);
    if (!$analysis) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen elemzés adat']);
        return;
    }
    
    $cm = new ClientManager();
    $client = $cm->getClient($clientId);
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél nem található']);
        return;
    }
    
    // Competitor analysis mentése az ügyfélhez
    $client['competitor_analysis'] = $analysis;
    $cm->saveClient($client);
    
    echo json_encode(['success' => true, 'message' => 'Versenytárs elemzés mentve: ' . $client['name']]);
}

function handleSaveCurrentCampaign(): void {
    header('Content-Type: application/json');
    
    $clientId = Security::sanitizeInput($_POST['client_id'] ?? '', 'alphanumeric');
    $campaignJson = $_POST['campaign_data'] ?? '';
    
    if (empty($clientId)) {
        echo json_encode(['success' => false, 'error' => 'Válassz ügyfelet!']);
        return;
    }
    
    $campaignData = json_decode($campaignJson, true);
    if (!$campaignData) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen kampány adat']);
        return;
    }
    
    $cm = new ClientManager();
    $client = $cm->getClient($clientId);
    
    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél nem található']);
        return;
    }
    
    // Jelenlegi kampány mentése
    $client['current_campaign'] = $campaignData;
    $cm->saveClient($client);
    
    $adCount = count($campaignData['ads'] ?? []);
    $kwCount = count($campaignData['keywords'] ?? []);
    echo json_encode(['success' => true, 'message' => "Kampány mentve ({$client['name']}): {$adCount} hirdetés, {$kwCount} kulcsszó"]);
}

// === AI ASSISTANT CHAT ===
function handleAssistantChat(array $industries): void {
    header('Content-Type: application/json');
    
    // API key ellenőrzés
    if (empty(Settings::anthropicApiKey())) {
        echo json_encode(['success' => false, 'error' => 'Az Anthropic API kulcs nincs beállítva. Állítsd be a Beállítások oldalon!']);
        return;
    }
    
    $message = $_POST['message'] ?? '';
    $conversationHistory = json_decode($_POST['history'] ?? '[]', true) ?: [];
    
    if (empty(trim($message))) {
        echo json_encode(['success' => false, 'error' => 'Üres üzenet']);
        return;
    }
    
    // Iparágak lista a kontextushoz
    $industryList = array_map(function($ind) {
        return $ind['name'];
    }, $industries);
    
    $systemPrompt = "Te az AdMaster Pro AI asszisztense vagy, egy magyar Google Ads kampány tervező szakértő.

FELADATOD:
A felhasználó elmondja mit szeretne hirdetni, te pedig:
1. Megérted az üzleti igényt
2. Javasolsz kampány stratégiát
3. Generálsz konkrét hirdetésszövegeket
4. Adsz kulcsszó javaslatokat
5. Becsülsz költségeket

ELÉRHETŐ IPARÁGAK AZ ADATBÁZISBAN:
" . implode(', ', $industryList) . "

Ha a felhasználó iparága nincs a listában, javasolj hasonlót vagy mondd hogy generálunk újat.

⚠️ GOOGLE ADS SZIGORÚ SZABÁLYOK - KÖTELEZŐ:
1. HEADLINES: MAXIMUM 30 KARAKTER (szóközökkel együtt!) - számold meg!
2. DESCRIPTIONS: MAX 90 KARAKTER
3. TILOS: Emoji (🚨❌✓★ stb.)
4. TILOS: CSUPA NAGYBETŰ (\"DUGULÁS\" helyett \"Dugulás\")
5. Használj Title Case formátumot (Minden Szó Nagy Kezdőbetűvel)
6. Max 1 felkiáltójel headline-onként

VÁLASZ FORMÁTUM (mindig JSON + szöveg):
Ha elég információd van a kampány tervezéshez, a válaszod tartalmazzon egy ```json blokkot is:

```json
{
    \"understood\": true,
    \"business_summary\": \"Rövid összefoglaló az üzletről\",
    \"suggested_industry\": \"dugulas/vizszereles/biztonsag/uj\",
    \"campaign_type\": \"Search/Call-Only/Both\",
    \"target_audience\": \"Ki a célközönség\",
    \"unique_selling_points\": [\"USP1\", \"USP2\", \"USP3\"],
    \"suggested_headlines\": [\"Max 30 Kar Headline 1\", \"Max 30 Kar Headline 2\"],
    \"suggested_descriptions\": [\"Max 90 karakter description 1\", \"Max 90 karakter description 2\"],
    \"suggested_keywords\": [\"kulcsszó 1\", \"kulcsszó 2\", \"kulcsszó 3\"],
    \"suggested_negatives\": [\"negatív 1\", \"negatív 2\"],
    \"budget_estimate\": {
        \"daily_min\": 10000,
        \"daily_recommended\": 20000,
        \"cpa_estimate\": \"3000-5000 Ft\"
    },
    \"strategy_tips\": [\"Tipp 1\", \"Tipp 2\"],
    \"next_steps\": [\"Mit csináljon következőnek\"]
}
```

Ha még információra van szükséged, kérdezz rá (pl. terület, egyedi előnyök, célközönség).

FONTOS:
- Mindig magyarul válaszolj
- Légy barátságos és segítőkész
- NAGYON FONTOS: Headlines SZIGORÚAN max 30 karakter - inkább legyen rövidebb!
- A descriptions max 90 karakter
- Adj konkrét, használható javaslatokat
- NE használj emojit a headline-okban és description-ökben!";

    // Készítsük el a messages tömböt
    $messages = [];
    
    // Előző üzenetek hozzáadása
    foreach ($conversationHistory as $msg) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
    
    // Új üzenet
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];
    
    // Claude API hívás
    $url = 'https://api.anthropic.com/v1/messages';
    $data = [
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 4096,
        'system' => $systemPrompt,
        'messages' => $messages
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . Settings::anthropicApiKey(),
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'API hiba: ' . $httpCode]);
        return;
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['content'][0]['text'])) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen API válasz']);
        return;
    }
    
    $assistantMessage = $result['content'][0]['text'];
    
    // JSON kinyerése a válaszból (ha van)
    $suggestions = null;
    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $assistantMessage, $matches)) {
        $suggestions = json_decode($matches[1], true);
        // Tisztítsuk meg a szöveget a JSON blokktól a megjelenítéshez
        $cleanMessage = preg_replace('/```json\s*[\s\S]*?\s*```/', '', $assistantMessage);
        $cleanMessage = trim($cleanMessage);
    } else {
        $cleanMessage = $assistantMessage;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $cleanMessage,
        'suggestions' => $suggestions,
        'raw' => $assistantMessage
    ]);
}

// === GENERATE ALL - WIZARD HANDLER ===
function handleGenerateAll(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    $use_dki = isset($_POST['use_dki']);
    $save_client = isset($_POST['save_client']);
    $aggressiveness = (int)($_POST['aggressiveness'] ?? 2); // 1=soft, 2=balanced, 3=aggressive
    
    // ÚJ: Megszólítás és kommunikáció stílus
    $formality = Security::sanitizeInput($_POST['formality'] ?? 'informal', 'alpha'); // informal=tegezés, formal=magázás
    $voice = Security::sanitizeInput($_POST['voice'] ?? 'team', 'alpha'); // team=mi, solo=én
    
    // ÚJ: Pszichológiai tónus
    $psych_tone = Security::sanitizeInput($_POST['psychological_tone'] ?? 'urgent', 'alpha'); // urgent/trust/value
    
    // ÚJ: Ügyfél ID (ha ki van választva)
    $client_id = Security::sanitizeInput($_POST['client_id'] ?? '', 'alphanumeric');
    
    $usps = Security::sanitizeArray($_POST['usps'] ?? [], 'alphanumeric');
    
    if (empty($company_name)) {
        echo renderError('A cégnév megadása kötelező.');
        return;
    }
    
    // Ügyfél mentése ha kérték
    if ($save_client && empty($client_id)) {
        $cm = new ClientManager();
        $client_id = $cm->saveClient([
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
    
    // Pszichológiai tónus szöveg
    $psych_tone_text = match($psych_tone) {
        'urgent' => "🚨 SÜRGETŐ TÓNUS:
- Azonnali cselekvésre ösztönzés
- SOS, vészhelyzet szavak
- \"Most\", \"Azonnal\", \"Ma\" használata
- Időnyomás érzékeltetése
- Példák: \"Dugulás? 30 Percen Belül Ott Vagyunk!\", \"Ne Várj - Hívj Most!\"",
        
        'trust' => "🏆 BIZALOMÉPÍTŐ TÓNUS:
- Tapasztalat, megbízhatóság kiemelése
- Garancia, minőség hangsúlyozása
- Számok: évek száma, ügyfelek száma
- Nyugodt, professzionális hangvétel
- Példák: \"15 Éve a Szakmában\", \"2000+ Elégedett Ügyfél\"",
        
        'value' => "💰 ÁR-FÓKUSZÚ TÓNUS:
- Pénzügyi előnyök kiemelése
- Kedvezmények, akciók
- \"Ingyenes\", \"Kedvező\", \"Spórolj\" szavak
- Érték-arány hangsúlyozása
- Példák: \"Ingyenes Kiszállás\", \"Fix Árak Meglepetés Nélkül\""
    };
    
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
    
    // DKI instrukció
    $dki_instruction = '';
    if ($use_dki) {
        $dki_instruction = "
9. FONTOS: HASZNÁLJ Dynamic Keyword Insertion-t (DKI) 2-3 headline-ban!
   Formátum: {KeyWord:Alapértelmezett Szöveg}
   Az iparág alapján jó DKI-k:
   - {KeyWord:" . ucfirst($industry['name']) . "}
   - {KeyWord:" . ucfirst($industry['name']) . "} " . $area_text . "
   - {KeyWord:Szakértő} " . $area_text . "
   Ezek BESZÁMÍTANAK a 30 karakterbe, az alapértelmezett szöveggel számolj!";
    } else {
        $dki_instruction = "
9. NE használj Dynamic Keyword Insertion-t ({KeyWord:...})";
    }
    
    // Aggresszivitás szint
    $aggressivenessText = match($aggressiveness) {
        1 => "SOFT/PROFESSZIONÁLIS HANGNEM:
- Nyugodt, megbízható hangvétel
- Kevés felkiáltójel
- Informatív, nem nyomulós
- Példa: \"Professzionális Duguláselhárítás\"",
        3 => "ERŐS/SÜRGETŐ HANGNEM:
- Sürgető szavak: Most, Azonnal, Ma, Gyorsan
- Erős érzelmi triggerek
- Számok és konkrét ígéretek
- Példa: \"Dugulás? 25 Perc és Ott Vagyunk!\"",
        default => "BALANCED HANGNEM:
- Kiegyensúlyozott stílus
- Mérsékelt sürgősség
- Professzionális de meggyőző
- Példa: \"Gyors Duguláselhárítás Budapest\""
    };
    
    // Megszólítás és kommunikáció stílus
    $formalityText = $formality === 'formal' 
        ? "MAGÁZÁS: Önözés, udvarias forma (Ön, Önnek, tessék)"
        : "TEGEZÉS: Közvetlenebb hangvétel (Te, Neked, hívj)";
    
    $voiceText = $voice === 'solo'
        ? "EGYES SZÁM (ÉN): Személyes, egyéni vállalkozó stílus
   - \"Megoldom a Problémád\" / \"Nálam Fix Árak\"
   - \"Hívj és Segítek\" / \"Én Kiszállok Hozzád\""
        : "TÖBBES SZÁM (MI/CSAPAT): Profi csapat kommunikáció
   - \"Megoldjuk a Problémát\" / \"Nálunk Fix Árak\"
   - \"Hívjon és Segítünk\" / \"Csapatunk Kiszáll\"";
    
    // === PRO PROMPT FELÉPÍTÉS - v6.0 ===
    
    // Best practices betöltése (few-shot)
    $bestPractices = @include(__DIR__ . '/data/best_practices.php');
    $bpExamples = $bestPractices[$industry_key] ?? $bestPractices['general'] ?? [];
    
    // Tone instrukciók
    $toneInstructions = $GLOBALS['tone_instructions'] ?? [];
    $toneKey = match($aggressiveness) {
        1 => 'trust',
        3 => 'aggressive',
        default => match($psych_tone) { 'trust' => 'trust', 'value' => 'price', default => 'aggressive' }
    };
    $toneData = $toneInstructions[$toneKey] ?? [];
    
    // Copywriting keretrendszerek
    $frameworks = $GLOBALS['copywriting_frameworks'] ?? [];
    
    // === VERSENYTÁRS SPY ADATOK (Chrome Extension-ből vagy ügyfél-mentett) ===
    $competitorSpyData = null;
    $clientCompetitorInsight = null;
    
    // 1. Chrome Spy scan (ha use_spy_data checkbox be van pipálva)
    if (isset($_POST['use_spy_data'])) {
        $scanIndex = $_POST['spy_scan_index'] ?? 'latest';
        $scanFile = __DIR__ . '/data/competitor_scans.json';
        
        if ($scanIndex === 'latest') {
            // Legutolsó scan a latest_competitor_data.json-ból vagy scans[0]-ból
            $latestSpyFile = __DIR__ . '/data/latest_competitor_data.json';
            if (file_exists($latestSpyFile)) {
                $competitorSpyData = json_decode(file_get_contents($latestSpyFile), true);
            } elseif (file_exists($scanFile)) {
                $scans = json_decode(file_get_contents($scanFile), true) ?: [];
                $competitorSpyData = $scans[0] ?? null;
            }
        } elseif (file_exists($scanFile)) {
            // Kiválasztott scan index alapján
            $scans = json_decode(file_get_contents($scanFile), true) ?: [];
            $idx = (int)$scanIndex;
            $competitorSpyData = $scans[$idx] ?? $scans[0] ?? null;
        }
    }
    
    // 2. Ügyfélhez mentett versenytárs elemzés
    if (isset($_POST['use_client_competitor']) && !empty($client_id)) {
        $cm = new ClientManager();
        $clientData = $cm->getClient($client_id);
        if (!empty($clientData['competitor_analysis'])) {
            $clientCompetitorInsight = $clientData['competitor_analysis'];
        }
    }
    
    // 3. Jelenlegi kampány adatok (ügyfélhez mentve)
    $currentCampaignData = null;
    if (isset($_POST['use_current_campaign']) && !empty($client_id)) {
        if (!isset($cm)) $cm = new ClientManager();
        if (!isset($clientData)) $clientData = $cm->getClient($client_id);
        if (!empty($clientData['current_campaign']) && empty($clientData['current_campaign']['cleared'])) {
            $currentCampaignData = $clientData['current_campaign'];
        }
    }
    
    $prompt = "Te a magyar Google Ads piac LEGJOBB copywritere vagy. 15+ éves tapasztalattal írsz konvertáló RSA hirdetéseket ami ELAD, nem csak leír.

A különbség egy átlagos és egy vérprofi hirdetés között: az átlagos leírja a szolgáltatást, a profi viszont ÉRZELMEKRE hat, CSELEKVÉSRE ösztönöz, és KIEMELKEDIK a versenytársak közül.

🎯 KAMPÁNY ADATOK:
- Iparág: {$industry['name']}
- Cég: $company_name
- Telefon: " . ($phone ?: 'nem adott') . "
- Terület: $area_text
- USP-k: " . (count($usp_texts) ? implode(', ', $usp_texts) : 'általános') . "
$extra_instruction

📢 KOMMUNIKÁCIÓS STÍLUS:
$formalityText
$voiceText
";

    // === HANGNEM: Kombinált instrukció (aggressiveness + pszichológiai tónus) ===
    $prompt .= "
🎭 HANGNEM (NAGYON FONTOS):
$aggressivenessText
$psych_tone_text
";
    
    // === TONE INSTRUKCIÓ a strategies.php-ból ===
    if (!empty($toneData['ai_instruction'])) {
        $prompt .= "
💡 AI STÍLUS INSTRUKCIÓ:
{$toneData['ai_instruction']}
Erős szavak amiket használj: " . implode(', ', $toneData['power_words'] ?? []) . "
Szavak amiket KERÜLJ: " . implode(', ', $toneData['avoid'] ?? []) . "
";
    }

    // === FEW-SHOT PÉLDÁK (ez a legfontosabb!) ===
    if (!empty($bpExamples['winning_headlines']) || !empty($bpExamples['winning_descriptions'])) {
        $prompt .= "
═══ NYERTES MINTÁK - INSPIRÁCIÓ (NE másold 1:1!) ═══
Ezek valós, MAGAS CTR-ű hirdetések ebben az iparágban.
Tanulj a STÍLUSUKBÓL, STRUKTÚRÁJUKBÓL, RITMUSUKBÓL - de írj TELJESEN EREDETIT.

NYERTES HEADLINE-OK (figyeld a stílust!):
" . implode("\n", array_map(fn($h) => "✓ \"{$h}\"", array_slice($bpExamples['winning_headlines'] ?? [], 0, 6))) . "

NYERTES DESCRIPTION-ÖK:
" . implode("\n", array_map(fn($d) => "✓ \"{$d}\"", array_slice($bpExamples['winning_descriptions'] ?? [], 0, 3))) . "

Figyeld meg: konkrét számok, személyes hangvétel, cselekvésre ösztönzés!
";
    }

    // === PSZICHOLÓGIAI HORGOK ===
    if (!empty($bpExamples['hooks'])) {
        $prompt .= "
🧠 PSZICHOLÓGIAI HORGOK AMIKET ÉPÍTS BE:
";
        foreach ($bpExamples['hooks'] as $type => $hook) {
            $label = match($type) {
                'fear' => '😰 FÉLELEM',
                'social_proof' => '👥 TÁRSADALMI BIZONYÍTÉK',
                'urgency' => '⏰ SÜRGŐSSÉG',
                'price_anchor' => '💰 ÁR-HORGONYZÁS',
                default => strtoupper($type)
            };
            $prompt .= "- {$label}: {$hook}\n";
        }
        $prompt .= "Minden headline-ban legyen legalább 1 pszichológiai horog!\n";
    }

    // === MARKETING KERETRENDSZEREK (description-ökhöz) ===
    if (!empty($frameworks)) {
        $prompt .= "
═══ DESCRIPTION KERETRENDSZEREK ═══
Minden 4 description-t MÁS keretrendszer alapján írj:

";
        foreach ($frameworks as $key => $fw) {
            $prompt .= "📌 {$fw['name']}:
   {$fw['instruction']}
   Példa: \"{$fw['example']}\"
";
        }
    }

    // === VERSENYTÁRS SPY ADATOK (Chrome Extension-ből) ===
    if ($competitorSpyData && !empty($competitorSpyData['ads'])) {
        $prompt .= "\n═══ 🔍 VERSENYTÁRS HIRDETÉSEK (Chrome Spy-ból leszívva!) ═══
Keresési kifejezés: \"{$competitorSpyData['query']}\"
{$competitorSpyData['totalAds']} db versenytárs hirdetés talált:

";
        foreach (array_slice($competitorSpyData['ads'], 0, 5) as $i => $spyAd) {
            $prompt .= "KONKURENS #" . ($i+1) . ":\n";
            $prompt .= "  Headline: \"{$spyAd['headline']}\"\n";
            if (!empty($spyAd['description'])) {
                $prompt .= "  Description: \"{$spyAd['description']}\"\n";
            }
            if (!empty($spyAd['sitelinks'])) {
                $prompt .= "  Sitelinks: " . implode(', ', array_slice($spyAd['sitelinks'], 0, 4)) . "\n";
            }
            $prompt .= "\n";
        }
        $prompt .= "FELADAT: Elemezd mit csinálnak a versenytársak, és KÜLÖNBÖZZ tőlük!
- Amit MINDENKI ír → Te NE írd (pl. ha mindenki '24 órás' → te írj konkrét percet)
- Amit SENKI nem ír → Azt használd ki (pl. ha senki nem ír árat → te írj)
- Legyél MERÉSZEBB és KREATÍVABB náluk!

";
    }

    // === ÜGYFÉLHEZ MENTETT VERSENYTÁRS ELEMZÉS ===
    if ($clientCompetitorInsight) {
        $prompt .= "\n═══ 📊 KORÁBBI VERSENYTÁRS ELEMZÉS EREDMÉNYEI ═══\n";
        if (!empty($clientCompetitorInsight['strengths'])) {
            $prompt .= "Versenytársak ERŐSSÉGEI (ezeket utánozd vagy múld felül):\n";
            foreach ($clientCompetitorInsight['strengths'] as $s) {
                $prompt .= "- $s\n";
            }
        }
        if (!empty($clientCompetitorInsight['weaknesses'])) {
            $prompt .= "Versenytársak GYENGESÉGEI (itt nyerj előnyt!):\n";
            foreach ($clientCompetitorInsight['weaknesses'] as $w) {
                $prompt .= "- $w\n";
            }
        }
        if (!empty($clientCompetitorInsight['recommendations'])) {
            $prompt .= "AI JAVASLATOK a korábbi elemzésből:\n";
            foreach ($clientCompetitorInsight['recommendations'] as $r) {
                $prompt .= "- $r\n";
            }
        }
        if (!empty($clientCompetitorInsight['usps'])) {
            $prompt .= "Versenytársak által használt USP-k (KÜLÖNBÖZZ tőlük!):\n";
            foreach ($clientCompetitorInsight['usps'] as $u) {
                $prompt .= "- $u\n";
            }
        }
        $prompt .= "\n";
    }

    // === JELENLEGI KAMPÁNY ADATOK (amit le akar cserélni) ===
    if ($currentCampaignData && !empty($currentCampaignData['ads'])) {
        $prompt .= "\n═══ ♻️ JELENLEGI KAMPÁNY (ezt akarjuk LECSERÉLNI de tanulni belőle!) ═══
Az ügyfél jelenlegi hirdetései (ezek futnak most):

";
        foreach (array_slice($currentCampaignData['ads'], 0, 5) as $i => $cAd) {
            $prompt .= "JELENLEGI HIRDETÉS #" . ($i+1) . ":\n";
            if (!empty($cAd['headlines'])) {
                $prompt .= "  Headlines: " . implode(' | ', array_slice($cAd['headlines'], 0, 8)) . "\n";
            }
            if (!empty($cAd['descriptions'])) {
                $prompt .= "  Descriptions: " . implode(' | ', array_slice($cAd['descriptions'], 0, 4)) . "\n";
            }
            if (!empty($cAd['metrics']) && ($cAd['metrics']['clicks'] ?? 0) > 0) {
                $m = $cAd['metrics'];
                $prompt .= "  Teljesítmény: " . ($m['clicks'] ?? 0) . " katt, " . ($m['ctr'] ?? 0) . "% CTR, " . ($m['conversions'] ?? 0) . " konv.\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($currentCampaignData['keywords'])) {
            $kwTexts = array_map(fn($k) => $k['text'] ?? '', array_slice($currentCampaignData['keywords'], 0, 15));
            $prompt .= "Jelenlegi kulcsszavak: " . implode(', ', $kwTexts) . "\n\n";
        }
        
        $prompt .= "FELADAT AZ ÚJ SZÖVEGEKKEL:
- Tartsd meg ami JÓL MŰKÖDIK (magas CTR-ű elemek stílusa)
- Javítsd ami GYENGE (alacsony CTR → merészebb megfogalmazás)
- Az ÚJ headlines legyenek JOBBAK mint a régiek, de NE legyenek teljesen idegenek
- Építs a bevált üzenetekre, de tedd őket ÉLESEBBÉ és KONVERTÁLÓBBÁ
- A kulcsszavakat TERMÉSZETESEN építsd be a headline-okba

";
    }

    // === FELADAT ===
    $prompt .= "
🎨 HEADLINE KÖVETELMÉNYEK - LEGYÉL VÉRPROFI!

NE ÍRJ ilyen UNALMAS, sablon headline-okat:
❌ \"Professzionális Szolgáltatás\" (semmitmondó)
❌ \"Minőségi Munka\" (ezt mindenki írja)
❌ \"Megbízható Partner\" (klisé)
❌ \"Kedvező Árak\" (konkrétum nélkül üres)

EHELYETT írj FIGYELEMFELKELTŐ szövegeket:
✅ Konkrét szám/ígéret: \"30 Perc és Ott Vagyunk\"
✅ Provokáló kérdés: \"Már Megint Eldugult?\"
✅ Félelem trigger: \"Míg Olvasod, a Víz Folyik\"
✅ Social proof: \"A Szomszéd Tegnap Hívott\"
✅ Garancia: \"Nem Oldjuk Meg? Ingyen!\"

📝 15 HEADLINE - MINDEGYIK MÁS PSZICHOLÓGIAI MEGKÖZELÍTÉS:

1-3. 😰 FÁJDALOM/PROBLÉMA (trigger - az olvasó érezze hogy neki kell):
   Neveld meg a BAJ-t amire keres. Kérdés formában is lehet.

4-6. ⚡ MEGOLDÁS + SEBESSÉG (az ígéreted):
   Konkrét idő, konkrét eredmény. Számok kellenek!

7-9. 🏆 BIZALOM + SOCIAL PROOF (miért pont te):
   Évek, ügyfelek, csillagok, garancia. Számok és tények!

10-12. 💎 EGYEDI AJÁNLAT / GARANCIA (amitől különbözöl):
   Fix ár, pénzvisszafizetés, ingyenes kiszállás - ami MEGKÜLÖNBÖZTET.

13-15. 🎯 PATTERN INTERRUPT (kreatív, figyelemfelkeltő):
   Meglepő, szokatlan, ami MEGÁLLÍTJA a görgetést. Legyen merész!

⚠️ TECHNIKAI SZABÁLYOK (EZEKET MINDIG TARTSD BE!):
1. HEADLINE: SZIGORÚAN MAX 30 KARAKTER (szóközzel!) - számold meg MINDEGYIKET!
2. DESCRIPTION: SZIGORÚAN MAX 90 KARAKTER (szóközzel!)
3. TILOS: Emoji, speciális karakter a szövegben
4. Title Case (Minden Szó Nagybetűvel) - KIVÉVE: a, az, és, is, de, nem, se
5. Maximum 1 felkiáltójel headline-onként
6. KÖTELEZŐ magyar ékezetek (á, é, í, ó, ö, ő, ú, ü, ű)
7. Minden headline TELJESEN EGYEDI legyen - NE ismételj gondolatot!
$dki_instruction

📄 4 DESCRIPTION - MINDEGYIK MÁS KERETRENDSZER:
1. PAS: Probléma → Felnagyítás → Megoldás
2. FAB: Feature → Advantage → Benefit
3. AIDA: Attention → Interest → Desire → Action
4. Before-After-Bridge: Előtte fájdalom → Utána jó → Te vagy a híd

🔧 CALL-ONLY HIRDETÉSEK (5 db):
- business_name: max 25 kar
- desc1 + desc2: max 35-35 kar

📎 SITELINKS (4 db):
- title: max 25 kar, desc: max 35 kar

💬 CALLOUTS (6 db): max 25 kar/db

🔑 KEYWORDS (10 db) + NEGATIVES (10 db)

=== VÁLASZ: KIZÁRÓLAG VALID JSON! Semmi más szöveget ne írj! ===
{
    \"headlines\": [{\"text\": \"...\", \"type\": \"problem/speed/trust/guarantee/creative\"}],
    \"descriptions\": [{\"text\": \"...\", \"framework\": \"PAS/FAB/AIDA/BAB\"}],
    \"callonly\": [{\"business\": \"...\", \"desc1\": \"...\", \"desc2\": \"...\"}],
    \"sitelinks\": [{\"title\": \"...\", \"desc\": \"...\"}],
    \"callouts\": [\"...\"],
    \"keywords\": [\"...\"],
    \"negatives\": [\"...\"]
}

🚨 A SIKERHEZ:
- Számold meg MINDEN headline karakterszámát (max 30!)
- Minden headline MÁS pszichológiai megközelítést használjon
- A description-ök 4 KÜLÖNBÖZŐ keretrendszert kövessenek
- KONKRÉTUMOK kellenek: számok, idők, árak, garanciák
- Az olvasó érezze: MOST kell cselekednie!";
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    // === HEADLINE VALIDÁCIÓ ÉS TISZTÍTÁS ===
    if (isset($data['headlines'])) {
        $data['headlines'] = array_map(function($h) {
            $text = is_array($h) ? ($h['text'] ?? '') : $h;
            
            // ÖSSZES emoji eltávolítása (bővített regex)
            $text = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text); // Összes emoji blokk
            $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);   // Misc symbols & Dingbats
            $text = preg_replace('/[\x{FE00}-\x{FEFF}]/u', '', $text);   // Variation Selectors
            $text = preg_replace('/[\x{2300}-\x{23FF}]/u', '', $text);   // Misc Technical
            $text = preg_replace('/[\x{2B50}]/u', '', $text);            // Csillag
            $text = preg_replace('/[\x{203C}\x{2049}]/u', '', $text);    // Felkiáltójelek
            
            // Speciális karakterek tisztítása
            $text = str_replace(['★', '☆', '✓', '✔', '✗', '✘', '►', '▶', '●', '○', '■', '□', '🌙', '⭐', '🔥', '💯', '✨'], '', $text);
            
            // CAPS javítás - Title Case (de csak ha TELJESEN nagybetűs)
            // Megőrizzük az ékezeteket!
            if (preg_match('/^[A-ZÁÉÍÓÖŐÚÜŰ\s\d\?\!\=\-]+$/u', $text) && mb_strlen($text) > 3) {
                $text = mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            }
            
            // Dupla szóközök eltávolítása
            $text = preg_replace('/\s+/', ' ', trim($text));
            
            // Max 30 karakter - levágás ha szükséges (szóhatáron)
            if (mb_strlen($text) > 30) {
                $text = mb_substr($text, 0, 30);
                // Ha szó közepén vágtuk, visszamegyünk az utolsó szóközig
                $lastSpace = mb_strrpos($text, ' ');
                if ($lastSpace > 20) {
                    $text = mb_substr($text, 0, $lastSpace);
                }
            }
            
            if (is_array($h)) {
                $h['text'] = $text;
                return $h;
            }
            return ['text' => $text];
        }, $data['headlines']);
        
        // AGRESSZÍV DUPLIKÁTUM SZŰRÉS
        // 1. Exact match (ékezet-független)
        // 2. Hasonlóság szűrés (70%+ egyezés = duplikátum)
        $seen = [];
        $seenWords = [];
        
        $data['headlines'] = array_filter($data['headlines'], function($h) use (&$seen, &$seenWords) {
            $text = $h['text'] ?? '';
            if (empty(trim($text))) return false;
            
            // Normalizálás
            $normalized = mb_strtolower($text, 'UTF-8');
            $normalized = strtr($normalized, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
                'ú' => 'u', 'ü' => 'u', 'ű' => 'u'
            ]);
            $cleanNormalized = preg_replace('/[^a-z0-9]/u', '', $normalized);
            
            // 1. Exact match ellenőrzés
            if (isset($seen[$cleanNormalized])) return false;
            
            // 2. Hasonlóság ellenőrzés - szavak alapján
            $words = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/u', '', $normalized)));
            $significantWords = array_filter($words, fn($w) => mb_strlen($w) > 3); // Csak 4+ betűs szavak
            
            // Ha a jelentős szavak 70%+ egyezik egy korábbiéval, duplikátum
            foreach ($seenWords as $prevWords) {
                if (empty($significantWords) || empty($prevWords)) continue;
                $intersection = array_intersect($significantWords, $prevWords);
                $similarity = count($intersection) / max(count($significantWords), 1);
                if ($similarity >= 0.7) return false; // 70%+ egyezés = duplikátum
            }
            
            $seen[$cleanNormalized] = true;
            if (!empty($significantWords)) {
                $seenWords[] = $significantWords;
            }
            return true;
        });
        $data['headlines'] = array_values($data['headlines']);
    }
    
    // Description validáció
    if (isset($data['descriptions'])) {
        $data['descriptions'] = array_map(function($d) {
            $text = is_array($d) ? ($d['text'] ?? '') : $d;
            
            // ÖSSZES emoji eltávolítása
            $text = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
            $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);
            
            // Speciális karakterek tisztítása
            $text = str_replace(['★', '☆', '✓', '✔', '✗', '✘', '►', '▶', '🌙', '⭐'], '', $text);
            
            // Dupla szóközök
            $text = preg_replace('/\s+/', ' ', trim($text));
            
            // Max 90 karakter
            if (mb_strlen($text) > 90) {
                $text = mb_substr($text, 0, 90);
                $lastSpace = mb_strrpos($text, ' ');
                if ($lastSpace > 70) {
                    $text = mb_substr($text, 0, $lastSpace);
                }
            }
            
            if (is_array($d)) {
                $d['text'] = $text;
                return $d;
            }
            return ['text' => $text];
        }, $data['descriptions']);
    }
    
    // Extra headlines hozzáadása az eredményhez
    if (!empty($extra_texts) && isset($data['headlines'])) {
        foreach (array_slice($extra_texts, 0, 5) as $et) {
            // Extra headline-ok is validálva
            $et = preg_replace('/[\x{1F600}-\x{1F9FF}]/u', '', $et);
            $et = preg_replace('/\s+/', ' ', trim($et));
            if (mb_strlen($et) <= 30 && !empty($et)) {
                array_unshift($data['headlines'], ['text' => $et, 'extra' => true]);
            }
        }
    }
    
    // === QUALITY SCORE BECSLÉS ===
    $industryKeywords = array_merge(
        $industry['keywords']['primary'] ?? [],
        $industry['keywords']['secondary'] ?? []
    );
    $qsScore = calculateProjectedQS($data['headlines'] ?? [], $industryKeywords);
    $data['qs_score'] = $qsScore;
    
    // TELJES EREDMÉNY MENTÉSE - hogy visszalépéskor is látszódjon
    $savedResult = [
        'id' => uniqid('gen_'),
        'headlines' => array_map(function($h) { return is_array($h) ? $h['text'] : $h; }, $data['headlines'] ?? []),
        'descriptions' => array_map(function($d) { return is_array($d) ? $d['text'] : $d; }, $data['descriptions'] ?? []),
        'callonly' => $data['callonly'] ?? [],
        'sitelinks' => $data['sitelinks'] ?? [],
        'callouts' => $data['callouts'] ?? [],
        'keywords' => $data['keywords'] ?? [],
        'negatives' => $data['negatives'] ?? [],
        'qs_score' => $qsScore,
        'lastUpdate' => date('Y-m-d H:i:s'),
        'company' => $company_name,
        'industry' => $industry['name'],
        'industry_key' => $industry_key,
        'phone' => $phone,
        'area' => $area_text,
        'ad_type' => $ad_type,
        'bid_strategy' => $bid_strategy,
        'settings' => [
            'formality' => $formality,
            'voice' => $voice,
            'psychological_tone' => $psych_tone,
            'aggressiveness' => $aggressiveness,
            'tone_key' => $toneKey,
        ]
    ];
    
    // Mentés legutóbbi eredményként
    file_put_contents(__DIR__ . '/data/last_result.json', json_encode($savedResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // ÜGYFÉLHEZ MENTÉS (ha van client_id)
    if (!empty($client_id)) {
        $cm = new ClientManager();
        $cm->saveGenerationToClient($client_id, array_merge($savedResult, [
            'formality' => $formality,
            'voice' => $voice,
            'psychological_tone' => $psych_tone,
            'aggressiveness' => $aggressiveness
        ]));
    }
    
    // ELŐZMÉNYEK MENTÉSE (max 20 db)
    $historyFile = __DIR__ . '/data/generation_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    if (!is_array($history)) $history = [];
    
    // Új elem az elejére
    array_unshift($history, $savedResult);
    
    // Max 20 előzmény
    $history = array_slice($history, 0, 20);
    
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Mentés Chrome Extension számára is
    $extensionData = [
        'headlines' => $savedResult['headlines'],
        'descriptions' => $savedResult['descriptions'],
        'callonly' => $savedResult['callonly'],
        'lastUpdate' => $savedResult['lastUpdate'],
        'company' => $company_name,
        'industry' => $industry['name']
    ];
    file_put_contents(__DIR__ . '/data/extension_data.json', json_encode($extensionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Render results
    echo '<div class="results-wrapper">';
    echo '<div class="alert alert-success">✅ <strong>Kampány anyagok elkészültek!</strong>';
    if (!empty($client_id)) echo ' <span style="opacity:0.7">(Mentve az ügyfélhez)</span>';
    elseif ($save_client) echo ' <span style="opacity:0.7">(Ügyfél mentve)</span>';
    echo '</div>';
    
    // Settings summary
    $bid_names = ['maximize_conversions'=>'Konverzió Max.', 'target_cpa'=>'Cél CPA', 'maximize_clicks'=>'Kattintás Max.', 'manual_cpc'=>'Manuális CPC'];
    echo '<div class="card"><h3 class="card-title">⚙️ Beállítások</h3>';
    echo '<p><strong>Bid:</strong> '.($bid_names[$bid_strategy] ?? $bid_strategy).' | ';
    echo '<strong>Büdzsé:</strong> '.number_format($industry['benchmarks']['daily_budget_recommended'],0,',',' ').' Ft/nap</p></div>';
    
    // === RSA ELŐNÉZET ÉS AD STRENGTH ===
    $headlineTexts = array_map(fn($h) => is_array($h) ? ($h['text'] ?? '') : $h, $data['headlines'] ?? []);
    $descTexts = array_map(fn($d) => is_array($d) ? ($d['text'] ?? '') : $d, $data['descriptions'] ?? []);
    $keywordList = $data['keywords'] ?? [];
    $finalUrl = $_POST['website'] ?? 'example.com';
    
    echo '<div class="results-preview-row">';
    
    // RSA Előnézet
    echo '<div class="card rsa-preview-card">';
    echo '<h3 class="card-title">👁️ Élő RSA Előnézet</h3>';
    echo '<div id="rsaPreviewContainer"></div>';
    echo '<script>
        rsaPreview.setData(
            ' . json_encode($headlineTexts) . ',
            ' . json_encode($descTexts) . ',
            ' . json_encode($finalUrl) . '
        );
    </script>';
    echo '</div>';
    
    // Ad Strength
    echo '<div class="card ad-strength-card">';
    echo '<h3 class="card-title">💪 Hirdetés Ereje</h3>';
    echo '<div id="adStrengthContainer"></div>';
    echo '<script>
        adStrength.render(
            ' . json_encode($headlineTexts) . ',
            ' . json_encode($descTexts) . ',
            ' . json_encode($keywordList) . '
        );
    </script>';
    echo '</div>';
    
    echo '</div>'; // .results-preview-row
    
    // === QUALITY SCORE KIJELZŐ ===
    if (isset($data['qs_score'])) {
        $qs = $data['qs_score'];
        $qsColor = $qs >= 7 ? 'green' : ($qs >= 4 ? 'orange' : 'red');
        $qsClass = $qs >= 7 ? 'qs-good' : ($qs >= 4 ? 'qs-warning' : 'qs-bad');
        
        echo '<div class="card qs-card ' . $qsClass . '">';
        echo '<div class="qs-content">';
        echo '<div class="qs-info">';
        echo '<h3>🎯 Becsült Ad Relevance Score</h3>';
        echo '<p>Mennyire passzolnak a hirdetések a kulcsszavakhoz?</p>';
        echo '</div>';
        echo '<div class="qs-score">' . $qs . '<span>/10</span></div>';
        echo '</div>';
        
        if ($qs < 5) {
            echo '<div class="qs-warning">';
            echo '⚠️ <strong>Figyelem:</strong> A fő kulcsszavak nem szerepelnek elég gyakran a headline-okban. ';
            echo 'Használd őket többször a jobb Quality Score érdekében!';
            echo '</div>';
        } elseif ($qs >= 8) {
            echo '<div class="qs-success">';
            echo '✅ <strong>Kiváló!</strong> A headline-ok jól illeszkednek a kulcsszavakhoz.';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Headlines (RSA)
    if (!empty($data['headlines']) && $ad_type !== 'callonly') {
        echo '<div class="card"><h3 class="card-title">📝 Headlines ('.count($data['headlines']).')</h3>';
        echo '<p class="help-text">Google Ads szabály: max 30 karakter | <span class="dki-badge">🔄 DKI</span> = Dynamic Keyword Insertion</p>';
        echo '<div class="copy-list">';
        foreach ($data['headlines'] as $h) {
            $text = $h['text'] ?? $h;
            $len = mb_strlen($text);
            $isOver = $len > 30;
            $isDki = strpos($text, '{KeyWord:') !== false;
            $hasEmoji = preg_match('/[\x{1F600}-\x{1F9FF}]/u', $text);
            $class = ($isOver || $hasEmoji) ? 'copy-item error' : ($isDki ? 'copy-item dki' : 'copy-item');
            $dkiBadge = $isDki ? '<span class="dki-tag">🔄 DKI</span>' : '';
            echo '<div class="'.$class.'"><span>'.htmlspecialchars($text).'</span>'.$dkiBadge.'<small class="'.($isOver ? 'over' : '').'">'.$len.'/30</small></div>';
        }
        echo '</div></div>';
    }
    
    // Descriptions (RSA) - JAVÍTVA: HIÁNYZOTT!
    if (!empty($data['descriptions']) && $ad_type !== 'callonly') {
        echo '<div class="card"><h3 class="card-title">📝 Descriptions ('.count($data['descriptions']).')</h3>';
        echo '<p class="help-text">Google Ads szabály: max 90 karakter</p>';
        echo '<div class="copy-list">';
        foreach ($data['descriptions'] as $d) {
            $text = is_array($d) ? ($d['text'] ?? '') : $d;
            $len = mb_strlen($text);
            $isOver = $len > 90;
            $class = $isOver ? 'copy-item error' : 'copy-item';
            echo '<div class="'.$class.'"><span>'.htmlspecialchars($text).'</span><small class="'.($isOver ? 'over' : '').'">'.$len.'/90</small></div>';
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
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    $tone = Security::sanitizeInput($_POST['tone'] ?? 'aggressive', 'alpha');
    $goal = Security::sanitizeInput($_POST['goal'] ?? 'conversions', 'alpha');
    $landing_url = Security::sanitizeInput($_POST['landing_url'] ?? '', 'url');
    
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
    
    // Best practices betöltése
    $bestPractices = @include(__DIR__ . '/data/best_practices.php');
    $examples = $bestPractices[$industry_key] ?? $bestPractices['general'] ?? [];
    
    // Hangnem instrukciók
    $toneInstructions = $GLOBALS['tone_instructions'] ?? [];
    $toneData = $toneInstructions[$tone] ?? $toneInstructions['aggressive'] ?? [];
    
    // Copywriting keretrendszerek
    $frameworks = $GLOBALS['copywriting_frameworks'] ?? [];
    
    // Session mentés
    $_SESSION['form_data'] = $_POST;
    
    // AI Prompt - PRO verzió
    $prompt = buildAdsPrompt(
        $industry, $company_name, $phone, $area_text, $price, 
        $usp_texts, $competitors, $examples, $toneData, $frameworks,
        $goal, $landing_url
    );
    
    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    renderAdResults($data, count($competitors) > 0);
}

// === CALL-ONLY HANDLER ===
function handleGenerateCallOnly(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
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
    if (Settings::isDemoMode()) {
        return getDemoResponse($prompt);
    }
    
    $apiKey = Settings::anthropicApiKey();
    if (empty($apiKey)) {
        throw new Exception('API kulcs nincs beállítva! Állítsd be a Beállítások oldalon (⚙️) vagy kapcsold be a Demo módot.');
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
            'x-api-key: ' . $apiKey,
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

function buildAdsPrompt($industry, $company, $phone, $area, $price, $usps, $competitors, $examples = [], $toneData = [], $frameworks = [], $goal = 'conversions', $landingUrl = ''): string {
    
    // === 1. SZEREPKÖR ÉS KONTEXTUS ===
    $prompt = "Te a magyar piac egyik legjobb Google Ads copywritere vagy. 10+ éves tapasztalattal írsz konvertáló RSA hirdetéseket.

Nem sablon szövegeket generálsz, hanem ELADÓ, PSZICHOLÓGIAILAG HATÁSOS, KATTINTÁSRA ÖSZTÖNZŐ magyar hirdetéseket.

IPARÁG: {$industry['name']}
CÉGNÉV: {$company}
TELEFON: " . ($phone ?: 'nincs megadva') . "
TERÜLET: {$area}
" . ($price ? "ÁR/INDULÓ ÁR: {$price}" : "") . "
" . ($landingUrl ? "LANDING PAGE: {$landingUrl}" : "") . "
CÉL: " . ($goal === 'calls' ? 'Telefonhívás generálás' : ($goal === 'leads' ? 'Lead generálás (űrlap)' : 'Konverzió maximalizálás')) . "

ÜGYFÉL USP-I (az ő valódi erősségei):
" . (count($usps) ? implode("\n", array_map(fn($u) => "- {$u}", $usps)) : "- Általános szolgáltató, nincs specifikus USP megadva") . "
";

    // === 2. HANGNEM INSTRUKCIÓ ===
    if (!empty($toneData['ai_instruction'])) {
        $prompt .= "\n═══ HANGNEM ÉS STÍLUS ═══
{$toneData['ai_instruction']}

ERŐS SZAVAK amiket építs be: " . implode(', ', $toneData['power_words'] ?? []) . "
KERÜLENDŐ SZAVAK: " . implode(', ', $toneData['avoid'] ?? []) . "
";
    }

    // === 3. FEW-SHOT PÉLDÁK ===
    if (!empty($examples['winning_headlines']) || !empty($examples['winning_descriptions'])) {
        $prompt .= "\n═══ NYERTES PÉLDÁK (inspiráció, NE másold!) ═══
Ezek valós, magas CTR-ű hirdetések ebben az iparágban. Tanulj a STÍLUSUKBÓL és STRUKTÚRÁJUKBÓL, de írj EREDETIT.

PÉLDA HEADLINES:
" . implode("\n", array_map(fn($h) => "✓ \"{$h}\"", array_slice($examples['winning_headlines'] ?? [], 0, 5))) . "

PÉLDA DESCRIPTIONS:
" . implode("\n", array_map(fn($d) => "✓ \"{$d}\"", array_slice($examples['winning_descriptions'] ?? [], 0, 3))) . "
";
    }
    
    // === 4. PSZICHOLÓGIAI HORGOK ===
    if (!empty($examples['hooks'])) {
        $prompt .= "\nPSZICHOLÓGIAI HORGOK az iparághoz:
";
        foreach ($examples['hooks'] as $type => $hook) {
            $label = match($type) {
                'fear' => 'FÉLELEM',
                'social_proof' => 'TÁRSADALMI BIZONYÍTÉK',
                'urgency' => 'SÜRGŐSSÉG',
                'price_anchor' => 'ÁR-HORGONYZÁS',
                default => strtoupper($type)
            };
            $prompt .= "- {$label}: {$hook}\n";
        }
    }

    // === 5. MARKETING KERETRENDSZEREK ===
    if (!empty($frameworks)) {
        $prompt .= "\n═══ LEÍRÁSOK KERETRENDSZEREI ═══
Minden description-t az alábbi keretrendszerek EGYIKE alapján írj:

";
        foreach ($frameworks as $key => $fw) {
            $prompt .= "{$fw['name']}:
{$fw['instruction']}
Példa: \"{$fw['example']}\"

";
        }
    }

    // === 6. KONKURENCIA ELEMZÉS ===
    if ($competitors) {
        $prompt .= "\n═══ KONKURENSEK ═══
Ezek a versenytársak hirdetésszövegei. ELEMEZD, mit csinálnak, és KÜLÖNBÖZZ tőlük!
";
        foreach ($competitors as $i => $c) {
            $prompt .= "Konkurens " . ($i+1) . ": \"{$c}\"\n";
        }
        $prompt .= "\nFELADAT: Találd meg amit ŐK NEM mondanak, és azt emeld ki TE!\n";
    }

    // === 7. FELADAT ÉS SZABÁLYOK ===
    $prompt .= "
═══ FELADAT ═══
Generálj:
1. PONTOSAN 15 db headline (SZIGORÚAN MAX 30 karakter mindegyik!)
2. PONTOSAN 4 db description (SZIGORÚAN MAX 90 karakter mindegyik!)
3. PIN javaslat minden headline-hoz (H1/H2/H3/null)
" . ($competitors ? "4. Konkurencia elemzés és differenciálási stratégia" : "") . "

═══ HEADLINE SZABÁLYOK ═══
- MAXIMUM 30 karakter (szóközökkel együtt) - EZT TARTSD BE!
- Minden Szó Nagybetűvel Kezdődjön (Title Case)
- Legalább 3-nak tartalmaznia kell a cégnevet vagy annak rövidítését
- Legalább 2-nek tartalmaznia kell a területet ({$area})
- Legalább 2 legyen kérdés formájú (bevonás)
- Legalább 2 tartalmazzon konkrét számot (ár, idő, tapasztalat)
- NE ismételj - minden headline EGYEDI értéket közvetítsen
- Használj magyar ékezeteket

═══ DESCRIPTION SZABÁLYOK ═══
- MAXIMUM 90 karakter (szóközökkel együtt)
- Minden description KÜLÖNBÖZŐ marketing keretrendszert használjon (PAS, FAB, AIDA, Before-After-Bridge)
- Tartalmazzon CTA-t (Cselekvésre ösztönzés): 'Hívj most!', 'Kérj ajánlatot!'
- Legyen konkrét: számok, időkeretek, garanciák
- NE legyen általános, üres marketingszöveg

═══ VÁLASZ FORMÁTUM ═══
KIZÁRÓLAG az alábbi JSON struktúrában válaszolj, más szöveget NE írj:
{
  \"headlines\": [{\"text\": \"Példa Headline\", \"pin\": \"H1\", \"chars\": 15, \"framework\": \"urgency\"}],
  \"descriptions\": [{\"text\": \"Példa description szöveg amit ide kell írni.\", \"chars\": 45, \"framework\": \"PAS\"}]" . 
  ($competitors ? ",\n  \"competitorAnalysis\": {\"commonElements\": [], \"unusedOpportunities\": [], \"differentiationStrategy\": \"...\"}" : "") . "
}

FONTOS: Számold meg a karaktereket PONTOSAN! A 30-nál és 90-nél hosszabb szövegek HIBÁSAK és használhatatlanok!";
    
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


// === AGENCY-LEVEL FUNCTIONS ===

/**
 * Quality Score Kalkulátor
 * Becsült relevancia pontszám a kulcsszó-headline egyezés alapján
 */
function calculateProjectedQS(array $headlines, array $keywords): int {
    $matches = 0;
    $total_checked = 0;
    
    // Csak az első 5 legfontosabb kulcsszót nézzük
    $top_keywords = array_slice($keywords, 0, 5);
    
    foreach ($top_keywords as $kw) {
        $kw_clean = str_replace(['+', '"', '[', ']'], '', mb_strtolower($kw, 'UTF-8'));
        $found = false;
        
        foreach ($headlines as $h) {
            $text = is_array($h) ? ($h['text'] ?? '') : $h;
            if (mb_stripos($text, $kw_clean) !== false) {
                $found = true;
                break;
            }
        }
        
        if ($found) $matches++;
        $total_checked++;
    }
    
    if ($total_checked == 0) return 0;
    return (int)round(($matches / $total_checked) * 10);
}

/**
 * Snippet Generátor - Strukturált Kiemelések
 */
function handleGenerateSnippets(array $industries): void {
    $industry_key = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    
    if (!isset($industries[$industry_key])) {
        echo renderError('Válassz iparágat először!');
        return;
    }
    
    $industry = $industries[$industry_key];
    
    $prompt = "Készíts Google Ads Structured Snippets (Strukturált Kiemelések) listát ehhez az iparághoz: {$industry['name']}.

SZABÁLYOK:
1. Csak hivatalos Google Ads fejléceket használj:
   - Szolgáltatások / Services
   - Típusok / Types
   - Márkák / Brands
   - Modellek / Models
   - Stílusok / Styles
   - Tanfolyamok / Courses
   - Célállomások / Destinations
   - Felszereltség / Amenities
   - Biztosítási védelem / Insurance coverage

2. Minden fejléchez 4-8 értéket adj meg
3. Az értékek max 25 karakteresek legyenek
4. Magyar nyelvű, iparágra jellemző értékek

Válasz JSON:
{
  \"snippets\": [
      {\"header\": \"Szolgáltatások\", \"values\": [\"Érték1\", \"Érték2\", \"Érték3\", \"Érték4\"]},
      {\"header\": \"Típusok\", \"values\": [\"Érték1\", \"Érték2\", \"Érték3\", \"Érték4\"]}
  ],
  \"tip\": \"Egy mondat tanács a bővítmények használatához\"
}";

    $response = callAnthropicAPI($prompt);
    $data = parseJsonResponse($response);
    
    if (empty($data['snippets'])) {
        echo renderError('Nem sikerült snippeteket generálni.');
        return;
    }
    
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h3 class="card-title">🧩 Strukturált Kiemelések</h3>';
    echo '<p class="card-subtitle">Google Ads Structured Snippets - ' . htmlspecialchars($industry['name']) . '</p>';
    echo '</div>';
    
    foreach ($data['snippets'] as $snippet) {
        echo '<div class="snippet-section">';
        echo '<h4 class="snippet-header">' . htmlspecialchars($snippet['header']) . '</h4>';
        echo '<div class="tags-list">';
        foreach ($snippet['values'] as $value) {
            $charCount = mb_strlen($value);
            $charClass = $charCount > 25 ? 'tag-warning' : 'tag-blue';
            echo '<span class="tag ' . $charClass . '">' . htmlspecialchars($value) . ' <small>(' . $charCount . ')</small></span>';
        }
        echo '</div>';
        echo '<button class="btn btn-sm btn-secondary" onclick="copySnippetValues(this)">📋 Értékek másolása</button>';
        echo '</div>';
        echo '<hr class="snippet-divider">';
    }
    
    if (!empty($data['tip'])) {
        echo '<div class="alert alert-info">';
        echo '💡 <strong>Tipp:</strong> ' . htmlspecialchars($data['tip']);
        echo '</div>';
    }
    
    echo '<div class="snippet-actions">';
    echo '<button class="btn btn-primary" onclick="copyAllSnippets()">📋 Összes másolása</button>';
    echo '</div>';
    echo '</div>';
    
    echo '<script>
    function copySnippetValues(btn) {
        const section = btn.closest(".snippet-section");
        const values = Array.from(section.querySelectorAll(".tag")).map(t => t.textContent.split(" (")[0]).join(", ");
        navigator.clipboard.writeText(values).then(() => {
            btn.textContent = "✅ Másolva!";
            setTimeout(() => btn.textContent = "📋 Értékek másolása", 2000);
        });
    }
    function copyAllSnippets() {
        const sections = document.querySelectorAll(".snippet-section");
        let text = "";
        sections.forEach(s => {
            const header = s.querySelector(".snippet-header").textContent;
            const values = Array.from(s.querySelectorAll(".tag")).map(t => t.textContent.split(" (")[0]).join(", ");
            text += header + ": " + values + "\n";
        });
        navigator.clipboard.writeText(text).then(() => alert("✅ Összes snippet másolva!"));
    }
    </script>';
}

// === GOOGLE ADS SYNC HANDLERS ===

/**
 * Összes elérhető fiók szinkronizálása
 */
function handleGadsSyncAccounts(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    if (!$gads->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Ads API nincs konfigurálva. Állítsd be a config.php-ban!']);
        return;
    }
    
    $accounts = $gads->getAllAccountsWithDetails();
    
    if (isset($accounts['error'])) {
        echo json_encode(['success' => false, 'error' => $accounts['error']]);
        return;
    }
    
    // Mentés cache-be
    $cacheFile = __DIR__ . '/data/gads_accounts_cache.json';
    file_put_contents($cacheFile, json_encode([
        'accounts' => $accounts,
        'synced_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'accounts' => $accounts,
        'count' => count($accounts)
    ]);
}

/**
 * Teljes fiók szinkronizálás (kampányok, hirdetések, kulcsszavak)
 */
function handleGadsSyncFull(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    if (!$gads->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Ads API nincs konfigurálva']);
        return;
    }
    
    $customerId = Security::sanitizeInput($_POST['customer_id'] ?? $_GET['customer_id'] ?? '', 'alphanumeric');
    
    if (empty($customerId)) {
        echo json_encode(['success' => false, 'error' => 'Customer ID kötelező']);
        return;
    }
    
    $withMetrics = isset($_POST['with_metrics']) || isset($_GET['with_metrics']);
    
    $data = $gads->syncFullAccount($customerId, $withMetrics);
    
    if (isset($data['account']['error'])) {
        echo json_encode(['success' => false, 'error' => $data['account']['error']]);
        return;
    }
    
    // Mentés cache-be
    $cacheFile = __DIR__ . '/data/gads_sync_' . $customerId . '.json';
    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'summary' => [
            'campaigns' => count($data['campaigns'] ?? []),
            'ad_groups' => count($data['ad_groups'] ?? []),
            'ads' => count($data['ads'] ?? []),
            'keywords' => count($data['keywords'] ?? []),
            'negative_keywords' => count($data['negative_keywords'] ?? [])
        ]
    ]);
}

/**
 * Hirdetések lekérése
 */
function handleGadsGetAds(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    if (!$gads->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Ads API nincs konfigurálva']);
        return;
    }
    
    $customerId = Security::sanitizeInput($_POST['customer_id'] ?? $_GET['customer_id'] ?? '', 'alphanumeric');
    $adGroupId = Security::sanitizeInput($_POST['ad_group_id'] ?? $_GET['ad_group_id'] ?? '', 'alphanumeric');
    
    if (empty($customerId)) {
        echo json_encode(['success' => false, 'error' => 'Customer ID kötelező']);
        return;
    }
    
    $withMetrics = isset($_POST['with_metrics']) || isset($_GET['with_metrics']);
    
    $ads = $gads->getAds($customerId, $adGroupId ?: null, $withMetrics);
    
    if (isset($ads['error'])) {
        echo json_encode(['success' => false, 'error' => $ads['error']]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'ads' => $ads,
        'count' => count($ads)
    ]);
}

/**
 * Kulcsszavak lekérése
 */
function handleGadsGetKeywords(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/GoogleAdsManager.php';
    $gads = new GoogleAdsManager();
    
    if (!$gads->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Ads API nincs konfigurálva']);
        return;
    }
    
    $customerId = Security::sanitizeInput($_POST['customer_id'] ?? $_GET['customer_id'] ?? '', 'alphanumeric');
    $adGroupId = Security::sanitizeInput($_POST['ad_group_id'] ?? $_GET['ad_group_id'] ?? '', 'alphanumeric');
    
    if (empty($customerId)) {
        echo json_encode(['success' => false, 'error' => 'Customer ID kötelező']);
        return;
    }
    
    $withMetrics = isset($_POST['with_metrics']) || isset($_GET['with_metrics']);
    
    $keywords = $gads->getKeywords($customerId, $adGroupId ?: null, $withMetrics);
    
    if (isset($keywords['error'])) {
        echo json_encode(['success' => false, 'error' => $keywords['error']]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'keywords' => $keywords,
        'count' => count($keywords)
    ]);
}

// === RELEVANCE & USP HANDLERS ===

/**
 * Hirdetés-Weboldal Összhang Ellenőrzés
 */
function handleCheckRelevance(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/RelevanceChecker.php';
    
    $url = Security::sanitizeInput($_POST['url'] ?? '', 'url');
    $headlines = $_POST['headlines'] ?? [];
    $descriptions = $_POST['descriptions'] ?? [];
    $keywords = $_POST['keywords'] ?? [];
    
    if (empty($url)) {
        echo json_encode(['success' => false, 'error' => 'URL megadása kötelező']);
        return;
    }
    
    try {
        $checker = new RelevanceChecker($url);
        
        if (!$checker->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Nem sikerült elérni a weboldalt']);
            return;
        }
        
        // Headlines és descriptions tisztítása
        $cleanHeadlines = array_filter(array_map('trim', is_array($headlines) ? $headlines : []));
        $cleanDescriptions = array_filter(array_map('trim', is_array($descriptions) ? $descriptions : []));
        $cleanKeywords = array_filter(array_map('trim', is_array($keywords) ? $keywords : []));
        
        $checker->setAds($cleanHeadlines, $cleanDescriptions);
        $checker->setKeywords($cleanKeywords);
        
        $results = $checker->checkRelevance();
        
        echo json_encode($results, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * USP Automatikus Kivonatolás weboldalról
 */
function handleExtractUSPs(): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/RelevanceChecker.php';
    
    $url = Security::sanitizeInput($_POST['url'] ?? '', 'url');
    
    if (empty($url)) {
        echo json_encode(['success' => false, 'error' => 'URL megadása kötelező']);
        return;
    }
    
    try {
        $checker = new RelevanceChecker($url);
        
        if (!$checker->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Nem sikerült elérni a weboldalt']);
            return;
        }
        
        $usps = $checker->extractUSPs();
        
        echo json_encode([
            'success' => true,
            'url' => $url,
            'usps' => $usps,
            'count' => count($usps),
            'headline_suggestions' => array_filter(array_column($usps, 'headline_suggestion'))
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Vizuális CRO Audit
 */
function handleCROAudit(array $industries): void {
    header('Content-Type: application/json');
    
    require_once __DIR__ . '/includes/VisionAnalyzer.php';
    
    $url = Security::sanitizeInput($_POST['url'] ?? '', 'url');
    $industryKey = Security::sanitizeInput($_POST['industry'] ?? '', 'alphanumeric');
    $keyword = Security::sanitizeInput($_POST['keyword'] ?? '', 'text');
    $useVision = isset($_POST['use_vision']);
    
    if (empty($url)) {
        echo json_encode(['success' => false, 'error' => 'URL megadása kötelező']);
        return;
    }
    
    try {
        $vision = new VisionAnalyzer();
        $industry = $industries[$industryKey] ?? null;
        
        $options = [
            'industry' => $industry,
            'keyword' => $keyword
        ];
        
        // Ha van Screenshot API és vision kérve
        if ($useVision && $vision->isConfigured()) {
            $screenshot = $vision->captureScreenshot($url);
            
            if ($screenshot) {
                $results = $vision->analyzeWithVision($screenshot, $url, $options);
                $results['method'] = 'vision';
                echo json_encode(array_merge(['success' => true], $results), JSON_UNESCAPED_UNICODE);
                return;
            }
        }
        
        // Fallback: URL-alapú elemzés
        $results = $vision->analyzeWithoutScreenshot($url, $options);
        echo json_encode($results, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Log fájlok törlése
 */
function handleClearLogs(): void {
    header('Content-Type: application/json');
    
    $logDir = __DIR__ . '/logs/';
    $deleted = 0;
    
    if (is_dir($logDir)) {
        foreach (glob($logDir . '*.log') as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    
    Security::log('info', "Cleared $deleted log files");
    
    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'message' => "$deleted log fájl törölve"
    ]);
}

/**
 * Generálás mentése ügyfélhez
 */
function handleSaveGenerationToClient(): void {
    header('Content-Type: application/json');
    
    $clientId = $_POST['client_id'] ?? '';
    $generationJson = $_POST['generation'] ?? '';
    
    if (empty($clientId)) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél ID hiányzik']);
        return;
    }
    
    $generation = json_decode($generationJson, true);
    if (!$generation) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen generálás adat']);
        return;
    }
    
    require_once __DIR__ . '/includes/ClientManager.php';
    $cm = new ClientManager();
    
    $result = $cm->saveGenerationToClient($clientId, $generation);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Mentve']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Mentés sikertelen']);
    }
}

/**
 * Ügyfél generálás lekérése
 */
function handleGetClientGeneration(): void {
    header('Content-Type: application/json');
    
    $clientId = $_GET['client_id'] ?? $_POST['client_id'] ?? '';
    $generationId = $_GET['generation_id'] ?? $_POST['generation_id'] ?? '';
    
    if (empty($clientId)) {
        echo json_encode(['success' => false, 'error' => 'Ügyfél ID hiányzik']);
        return;
    }
    
    require_once __DIR__ . '/includes/ClientManager.php';
    $cm = new ClientManager();
    
    $generations = $cm->getClientGenerations($clientId);
    
    if ($generationId) {
        // Konkrét generálás keresése
        foreach ($generations as $gen) {
            if (($gen['id'] ?? '') === $generationId) {
                echo json_encode(['success' => true, 'generation' => $gen]);
                return;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Generálás nem található']);
    } else {
        // Összes generálás
        echo json_encode(['success' => true, 'generations' => $generations]);
    }
}

/**
 * Generálás törlése ügyfélnél
 */
function handleDeleteGeneration(): void {
    header('Content-Type: application/json');
    
    $clientId = $_POST['client_id'] ?? '';
    $generationId = $_POST['generation_id'] ?? '';
    
    if (empty($clientId) || empty($generationId)) {
        echo json_encode(['success' => false, 'error' => 'Hiányzó paraméterek']);
        return;
    }
    
    require_once __DIR__ . '/includes/ClientManager.php';
    $cm = new ClientManager();
    
    $result = $cm->deleteGeneration($clientId, $generationId);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Törölve']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Törlés sikertelen']);
    }
}

/**
 * Szerkesztett generálás mentése (Live Preview szerkesztés)
 */
function handleSaveEditedGeneration(): void {
    header('Content-Type: application/json');
    
    $headlinesJson = $_POST['headlines'] ?? '';
    $descriptionsJson = $_POST['descriptions'] ?? '';
    
    $headlines = json_decode($headlinesJson, true);
    $descriptions = json_decode($descriptionsJson, true);
    
    if (!is_array($headlines) || empty($headlines)) {
        echo json_encode(['success' => false, 'error' => 'Érvénytelen headlines adat']);
        return;
    }
    
    // last_result.json frissítése
    $lastResultFile = __DIR__ . '/data/last_result.json';
    if (!file_exists($lastResultFile)) {
        echo json_encode(['success' => false, 'error' => 'Nincs korábbi generálás']);
        return;
    }
    
    $lastResult = json_decode(file_get_contents($lastResultFile), true);
    if (!$lastResult) {
        echo json_encode(['success' => false, 'error' => 'Hibás last_result.json']);
        return;
    }
    
    // Frissítjük a szövegeket
    $lastResult['headlines'] = array_map(function($h) {
        return Security::sanitizeInput(is_string($h) ? $h : '', 'text');
    }, $headlines);
    
    if (is_array($descriptions)) {
        $lastResult['descriptions'] = array_map(function($d) {
            return Security::sanitizeInput(is_string($d) ? $d : '', 'text');
        }, $descriptions);
    }
    
    $lastResult['lastUpdate'] = date('Y-m-d H:i:s');
    $lastResult['edited'] = true;
    
    // Mentés
    $saved = file_put_contents($lastResultFile, json_encode($lastResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($saved !== false) {
        // History-ben is frissítsük a legutolsó bejegyzést
        $historyFile = __DIR__ . '/data/generation_history.json';
        if (file_exists($historyFile)) {
            $history = json_decode(file_get_contents($historyFile), true);
            if (is_array($history) && !empty($history)) {
                $history[0]['headlines'] = $lastResult['headlines'];
                $history[0]['descriptions'] = $lastResult['descriptions'];
                $history[0]['edited'] = true;
                file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Módosítások mentve']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Fájl írási hiba']);
    }
}
