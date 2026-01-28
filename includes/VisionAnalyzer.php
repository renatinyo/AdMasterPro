<?php
/**
 * AdMaster Pro - AI Vision Landing Page Analyzer
 * 
 * Funkciók:
 * - Screenshot készítés külső API-val
 * - Claude Vision elemzés
 * - UX/UI értékelés
 * - CRO javaslatok vizuális alapon
 */

class VisionAnalyzer {
    private string $screenshotApiKey;
    private string $screenshotApiUrl;
    
    public function __construct() {
        $this->screenshotApiKey = SCREENSHOT_API_KEY;
        $this->screenshotApiUrl = SCREENSHOT_API_URL;
    }
    
    /**
     * Ellenőrzi, hogy konfigurálva van-e
     */
    public function isConfigured(): bool {
        return !empty($this->screenshotApiKey);
    }
    
    /**
     * Screenshot készítése egy URL-ről
     */
    public function captureScreenshot(string $url): ?string {
        if (!$this->isConfigured()) {
            return null;
        }
        
        // Screenshot API URL összeállítása
        $screenshotUrl = sprintf($this->screenshotApiUrl, $this->screenshotApiKey, urlencode($url));
        
        // Képletöltés
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AdMaster Pro Vision Analyzer'
            ]
        ]);
        
        $imageData = @file_get_contents($screenshotUrl, false, $context);
        
        if ($imageData === false) {
            return null;
        }
        
        // Base64 kódolás
        return base64_encode($imageData);
    }
    
    /**
     * Alternatív: Puppeteer-szerű megoldás (ha telepítve van)
     */
    public function captureWithPuppeteer(string $url): ?string {
        $outputPath = sys_get_temp_dir() . '/screenshot_' . md5($url) . '.png';
        
        // Node.js script futtatása (ha elérhető)
        $script = escapeshellarg(__DIR__ . '/../scripts/screenshot.js');
        $urlArg = escapeshellarg($url);
        $outputArg = escapeshellarg($outputPath);
        
        exec("node $script $urlArg $outputArg 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputPath)) {
            $imageData = file_get_contents($outputPath);
            unlink($outputPath);
            return base64_encode($imageData);
        }
        
        return null;
    }
    
    /**
     * AI Vision elemzés Claude-dal
     */
    public function analyzeWithVision(string $base64Image, string $url, array $options = []): array {
        $industry = $options['industry'] ?? null;
        $keyword = $options['keyword'] ?? null;
        
        $prompt = "Te egy UX/UI és CRO (Conversion Rate Optimization) szakértő vagy. Elemezd ezt a magyar landing page screenshotot!

URL: $url
" . ($industry ? "Iparág: {$industry['name']}\n" : "") . "
" . ($keyword ? "Fő kulcsszó: $keyword\n" : "") . "

VIZUÁLIS ELEMZÉS - Nézd meg a képet és válaszolj:

1. ELSŐ BENYOMÁS (3 másodperc szabály)
   - Azonnal érthető, mit kínál az oldal?
   - Látható-e a fő CTA?
   - Van vizuális hierarchia?

2. CTA ELEMZÉS
   - Hol van a fő CTA gomb?
   - Megfelelő-e a színe (kiemelkedik)?
   - Elég nagy és kattintható?
   - Mi a CTA szövege?

3. BIZALOMÉBRESZTÉS
   - Vannak trust jelzések (logók, tanúsítványok)?
   - Láthatóak vélemények/értékelések?
   - Professzionális-e a design?

4. KONTAKT INFORMÁCIÓK
   - Látható-e telefonszám?
   - Ha igen, hol és milyen méretben?
   - Van-e kontakt form?

5. MOBIL KOMPATIBILITÁS (becsült)
   - A design adaptívnak tűnik?
   - Olvashatóak lennének a szövegek mobilon?
   - A gombok elég nagyok érintésre?

6. VIZUÁLIS PROBLÉMÁK
   - Zsúfolt-e a layout?
   - Van-e kontrasztprobléma?
   - Vannak zavaró elemek?

7. UX SCORE és JAVASLATOK

Válasz JSON:
{
    \"first_impression\": {
        \"score\": 0-100,
        \"clear_value_prop\": true/false,
        \"visible_cta\": true/false,
        \"issues\": [\"...\"]
    },
    \"cta_analysis\": {
        \"score\": 0-100,
        \"position\": \"above fold / below fold / nem látható\",
        \"color_contrast\": \"jó / gyenge / rossz\",
        \"size\": \"megfelelő / kicsi / túl nagy\",
        \"text\": \"CTA szövege\",
        \"issues\": [\"...\"]
    },
    \"trust_signals\": {
        \"score\": 0-100,
        \"has_testimonials\": true/false,
        \"has_certifications\": true/false,
        \"has_social_proof\": true/false,
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
        \"touch_friendly\": true/false,
        \"issues\": [\"...\"]
    },
    \"visual_issues\": [
        {\"issue\": \"probléma\", \"severity\": \"critical/warning/info\", \"location\": \"hol\"}
    ],
    \"overall_score\": 0-100,
    \"top_3_fixes\": [
        {\"fix\": \"mit javíts\", \"impact\": \"high/medium/low\", \"effort\": \"easy/medium/hard\"}
    ],
    \"positive_aspects\": [\"ami jó az oldalon\"]
}";
        
        // Claude API hívás vision-nel
        $response = $this->callClaudeVision($base64Image, $prompt);
        
        return $response;
    }
    
    /**
     * Claude Vision API hívás
     */
    private function callClaudeVision(string $base64Image, string $prompt): array {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $data = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'image/png',
                                'data' => $base64Image
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['error' => 'API hiba: ' . $httpCode];
        }
        
        $result = json_decode($response, true);
        $text = $result['content'][0]['text'] ?? '';
        
        // JSON kinyerése a válaszból
        if (preg_match('/\{[\s\S]*\}/m', $text, $matches)) {
            return json_decode($matches[0], true) ?? ['error' => 'JSON parse hiba'];
        }
        
        return ['error' => 'Nem sikerült JSON-t kinyerni'];
    }
    
    /**
     * Heatmap-szerű elemzés (hol nézne a user először)
     */
    public function analyzeAttentionFlow(string $base64Image): array {
        $prompt = "Elemezd ezt a weboldal screenshotot és becsüld meg, hova néz először a felhasználó szeme!

Használd az F-pattern és Z-pattern szemkövetési mintákat.

Válasz JSON:
{
    \"primary_attention\": \"hova néz először\",
    \"secondary_attention\": \"hova néz másodszor\",
    \"attention_flow\": [\"1. pont\", \"2. pont\", \"3. pont\"],
    \"cta_in_attention_path\": true/false,
    \"distracting_elements\": [\"elemek amik elvonják a figyelmet\"],
    \"recommendations\": [\"javaslatok\"]
}";
        
        return $this->callClaudeVision($base64Image, $prompt);
    }
    
    /**
     * Competitor összehasonlítás (2 screenshot)
     */
    public function compareWithCompetitor(string $ourScreenshot, string $competitorScreenshot): array {
        // Ez egy fejlettebb funkció - 2 képet küld egyszerre
        // A jelenlegi Claude API 1 képet támogat üzenetenként,
        // de több üzenettel megoldható
        
        return [
            'note' => 'Competitor comparison requires multiple API calls',
            'our_analysis' => $this->analyzeWithVision($ourScreenshot, 'our-site'),
            // competitor_analysis külön hívásban
        ];
    }
}
