<?php
/**
 * AdMaster Pro - AI Vision Landing Page Analyzer
 * 
 * Funkciók:
 * - Screenshot készítés külső API-val
 * - Claude Vision elemzés
 * - UX/UI értékelés
 * - CRO javaslatok vizuális alapon
 * - Részletes CRO audit tippek
 */

class VisionAnalyzer {
    private string $screenshotApiKey;
    private string $screenshotApiUrl;
    
    // CRO Audit szabályok
    private array $croRules = [
        'cta_visibility' => [
            'name' => 'CTA Láthatóság',
            'icon' => '🔘',
            'checks' => [
                'A hívás gomb nem elég feltűnő',
                'A CTA szín nem emelkedik ki a háttérből',
                'A CTA gomb túl kicsi mobilon',
                'Nincs CTA a hajtás felett'
            ]
        ],
        'content_density' => [
            'name' => 'Tartalom Sűrűség',
            'icon' => '📝',
            'checks' => [
                'Túl sok a szöveg a hajtás felett',
                'Zsúfolt layout - nehéz fókuszálni',
                'Hiányzik a vizuális hierarchia',
                'Túl sok konkuráló elem'
            ]
        ],
        'trust_signals' => [
            'name' => 'Bizalmi Jelek',
            'icon' => '🏆',
            'checks' => [
                'Nincsenek ügyfélvélemények látható helyen',
                'Hiányoznak a tanúsítványok/logók',
                'Nincs "rólunk" vagy csapat szekció',
                'Hiányzik a garancia kiemelése'
            ]
        ],
        'contact_info' => [
            'name' => 'Kapcsolat Info',
            'icon' => '📞',
            'checks' => [
                'Telefonszám nem elég nagy/feltűnő',
                'Nincs kattintható telefonszám',
                'Kapcsolati adatok csak lent vannak',
                'Hiányzik a nyitvatartás'
            ]
        ],
        'visual_hierarchy' => [
            'name' => 'Vizuális Hierarchia',
            'icon' => '👁️',
            'checks' => [
                'Nincs egyértelmű főcímsor',
                'Képek és szöveg versenyeznek a figyelemért',
                'Színek nem vezetik a tekintetet',
                'Túl sok azonos méretű elem'
            ]
        ],
        'mobile_ux' => [
            'name' => 'Mobil UX',
            'icon' => '📱',
            'checks' => [
                'Gombok túl kicsik érintésre',
                'Szöveg nehezen olvasható kis kijelzőn',
                'Menü valószínűleg problémás mobilon',
                'Túl széles tartalom horizontális scrollal'
            ]
        ]
    ];
    
    public function __construct() {
        $this->screenshotApiKey = Settings::screenshotApiKey();
        $this->screenshotApiUrl = defined('SCREENSHOT_API_URL') ? SCREENSHOT_API_URL : '';
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
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $screenshotUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($imageData)) {
            return null;
        }
        
        // Base64 kódolás
        return base64_encode($imageData);
    }
    
    /**
     * AI Vision elemzés Claude-dal
     */
    public function analyzeWithVision(string $base64Image, string $url, array $options = []): array {
        $industry = $options['industry'] ?? null;
        $keyword = $options['keyword'] ?? null;
        
        $prompt = $this->buildCROPrompt($url, $industry, $keyword);
        
        // Claude API hívás vision-nel
        $response = $this->callClaudeVision($base64Image, $prompt);
        
        // CRO tippek hozzáadása
        if (isset($response['visual_issues']) || isset($response['cta_analysis'])) {
            $response['cro_tips'] = $this->generateCROTips($response);
        }
        
        return $response;
    }
    
    /**
     * CRO Prompt építése
     */
    private function buildCROPrompt(string $url, ?array $industry, ?string $keyword): string {
        return "Te egy UX/UI és CRO (Conversion Rate Optimization) szakértő vagy. Elemezd ezt a magyar landing page screenshotot!

URL: $url
" . ($industry ? "Iparág: {$industry['name']}\n" : "") . "
" . ($keyword ? "Fő kulcsszó: $keyword\n" : "") . "

VIZUÁLIS CRO AUDIT - Nézd meg a képet részletesen:

1. ELSŐ BENYOMÁS (3 másodperc szabály)
   - Azonnal érthető, mit kínál az oldal?
   - Látható-e a fő CTA?
   - Van vizuális hierarchia?

2. CTA ELEMZÉS
   - Hol van a fő CTA gomb? (above fold / below fold)
   - Megfelelő-e a színe? (kontraszt a háttérrel)
   - Elég nagy és kattintható? (min 44x44px mobilon)
   - Mi a CTA szövege? (akció-orientált?)

3. TARTALOM SŰRŰSÉG
   - Zsúfolt-e a hajtás feletti rész?
   - Van-e elég whitespace?
   - Hány fő elem versenyez a figyelemért?

4. BIZALOMÉBRESZTÉS
   - Láthatóak vélemények/értékelések?
   - Vannak trust badge-ek?
   - Professzionális-e a design?

5. KONTAKT KIEMELÉS
   - Látható-e telefonszám?
   - Milyen nagy és hol van?
   - Kiemelkedik-e színnel?

6. MOBIL BARÁTSÁG (becsült)
   - Elég nagyok a gombok?
   - Olvasható lenne a szöveg?

Válasz CSAK tiszta JSON (nincs markdown, nincs kód blokk):
{
    \"first_impression\": {
        \"score\": 0-100,
        \"clear_value_prop\": true/false,
        \"visible_cta\": true/false,
        \"issues\": [\"konkrét problémák\"]
    },
    \"cta_analysis\": {
        \"score\": 0-100,
        \"position\": \"above fold / below fold / nem látható\",
        \"color_contrast\": \"jó / gyenge / rossz\",
        \"size\": \"megfelelő / kicsi / túl nagy\",
        \"text\": \"CTA szövege ha látható\",
        \"issues\": [\"konkrét problémák\"]
    },
    \"content_density\": {
        \"score\": 0-100,
        \"too_crowded\": true/false,
        \"whitespace_adequate\": true/false,
        \"issues\": [\"konkrét problémák\"]
    },
    \"trust_signals\": {
        \"score\": 0-100,
        \"has_testimonials\": true/false,
        \"has_certifications\": true/false,
        \"issues\": [\"konkrét problémák\"]
    },
    \"contact_visibility\": {
        \"score\": 0-100,
        \"phone_visible\": true/false,
        \"phone_prominent\": true/false,
        \"phone_location\": \"fejléc / oldalsáv / nem látható\",
        \"issues\": [\"konkrét problémák\"]
    },
    \"mobile_readiness\": {
        \"score\": 0-100,
        \"touch_friendly\": true/false,
        \"text_readable\": true/false,
        \"issues\": [\"konkrét problémák\"]
    },
    \"visual_issues\": [
        {\"issue\": \"A hívás gomb nem elég feltűnő\", \"severity\": \"critical/warning/info\", \"location\": \"header\"},
        {\"issue\": \"Túl sok a szöveg a hajtás felett\", \"severity\": \"warning\", \"location\": \"hero section\"}
    ],
    \"overall_score\": 0-100,
    \"top_3_fixes\": [
        {\"fix\": \"konkrét javítás\", \"impact\": \"high/medium/low\", \"effort\": \"easy/medium/hard\"}
    ],
    \"positive_aspects\": [\"ami jó az oldalon\"]
}";
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
            'x-api-key: ' . Settings::anthropicApiKey(),
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
        
        return ['error' => 'Nem sikerült JSON-t kinyerni', 'raw' => $text];
    }
    
    /**
     * CRO Tippek generálása az elemzés alapján
     */
    public function generateCROTips(array $analysis): array {
        $tips = [];
        
        // CTA problémák
        if (isset($analysis['cta_analysis'])) {
            $cta = $analysis['cta_analysis'];
            
            if (($cta['score'] ?? 100) < 60) {
                if ($cta['position'] === 'below fold' || $cta['position'] === 'nem látható') {
                    $tips[] = [
                        'category' => 'CTA',
                        'icon' => '🔘',
                        'severity' => 'critical',
                        'tip' => 'A hívás gomb nincs a hajtás felett!',
                        'action' => 'Helyezd a CTA gombot a képernyő felső részére, ahol azonnal látható.',
                        'impact' => 'Konverzió akár 30%-kal nőhet'
                    ];
                }
                
                if ($cta['color_contrast'] === 'gyenge' || $cta['color_contrast'] === 'rossz') {
                    $tips[] = [
                        'category' => 'CTA',
                        'icon' => '🎨',
                        'severity' => 'warning',
                        'tip' => 'A CTA gomb nem emelkedik ki eléggé!',
                        'action' => 'Használj kontrasztos színt (pl. narancssárga/zöld a kék háttéren)',
                        'impact' => 'Jobb vizuális hierarchia'
                    ];
                }
                
                if ($cta['size'] === 'kicsi') {
                    $tips[] = [
                        'category' => 'CTA',
                        'icon' => '📐',
                        'severity' => 'warning',
                        'tip' => 'A CTA gomb túl kicsi!',
                        'action' => 'Növeld a gomb méretét (min. 44x44px mobilon)',
                        'impact' => 'Könnyebb kattintás mobilon'
                    ];
                }
            }
        }
        
        // Tartalom sűrűség
        if (isset($analysis['content_density'])) {
            $density = $analysis['content_density'];
            
            if ($density['too_crowded'] ?? false) {
                $tips[] = [
                    'category' => 'Layout',
                    'icon' => '📝',
                    'severity' => 'warning',
                    'tip' => 'Túl sok a szöveg a hajtás felett!',
                    'action' => 'Csökkentsd a szöveget, használj bullet pointokat vagy ikonokat',
                    'impact' => 'Jobb átláthatóság'
                ];
            }
            
            if (!($density['whitespace_adequate'] ?? true)) {
                $tips[] = [
                    'category' => 'Layout',
                    'icon' => '⬜',
                    'severity' => 'info',
                    'tip' => 'Kevés a whitespace',
                    'action' => 'Adj több térközt az elemek közé (20-40px)',
                    'impact' => 'Professzionálisabb megjelenés'
                ];
            }
        }
        
        // Kontakt láthatóság
        if (isset($analysis['contact_visibility'])) {
            $contact = $analysis['contact_visibility'];
            
            if (!($contact['phone_visible'] ?? true)) {
                $tips[] = [
                    'category' => 'Kontakt',
                    'icon' => '📞',
                    'severity' => 'critical',
                    'tip' => 'Telefonszám nem látható a hajtás felett!',
                    'action' => 'Helyezd a fejlécbe nagy, kattintható telefonszámot',
                    'impact' => 'Több közvetlen hívás'
                ];
            } elseif (!($contact['phone_prominent'] ?? true)) {
                $tips[] = [
                    'category' => 'Kontakt',
                    'icon' => '📞',
                    'severity' => 'warning',
                    'tip' => 'A telefonszám nem elég feltűnő!',
                    'action' => 'Növeld a méretet és használj kontrasztos hátteret',
                    'impact' => 'Könnyebb megtalálni'
                ];
            }
        }
        
        // Trust jelek
        if (isset($analysis['trust_signals'])) {
            $trust = $analysis['trust_signals'];
            
            if (!($trust['has_testimonials'] ?? true)) {
                $tips[] = [
                    'category' => 'Bizalom',
                    'icon' => '⭐',
                    'severity' => 'warning',
                    'tip' => 'Nincsenek ügyfélvélemények látható helyen!',
                    'action' => 'Adj hozzá Google Reviews widgetet vagy idézeteket',
                    'impact' => 'Növeli a bizalmat'
                ];
            }
            
            if (!($trust['has_certifications'] ?? true)) {
                $tips[] = [
                    'category' => 'Bizalom',
                    'icon' => '🏆',
                    'severity' => 'info',
                    'tip' => 'Hiányoznak a tanúsítványok/díjak',
                    'action' => 'Jelenítsd meg a minősítéseket, partnerlogókat',
                    'impact' => 'Professzionálisabb benyomás'
                ];
            }
        }
        
        // Mobil
        if (isset($analysis['mobile_readiness'])) {
            $mobile = $analysis['mobile_readiness'];
            
            if (!($mobile['touch_friendly'] ?? true)) {
                $tips[] = [
                    'category' => 'Mobil',
                    'icon' => '📱',
                    'severity' => 'warning',
                    'tip' => 'Gombok túl kicsik érintésre!',
                    'action' => 'Növeld a kattintható területeket (min 44x44px)',
                    'impact' => 'Jobb mobil konverzió'
                ];
            }
        }
        
        // Visual issues-ból
        foreach ($analysis['visual_issues'] ?? [] as $issue) {
            if (!isset($issue['issue'])) continue;
            
            $tips[] = [
                'category' => 'Vizuális',
                'icon' => $issue['severity'] === 'critical' ? '🚨' : ($issue['severity'] === 'warning' ? '⚠️' : 'ℹ️'),
                'severity' => $issue['severity'] ?? 'info',
                'tip' => $issue['issue'],
                'action' => 'Ellenőrizd: ' . ($issue['location'] ?? 'az oldal'),
                'impact' => ''
            ];
        }
        
        // Rendezés severity szerint
        usort($tips, function($a, $b) {
            $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
            return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
        });
        
        return $tips;
    }
    
    /**
     * URL-alapú elemzés (Screenshot API nélkül)
     */
    public function analyzeWithoutScreenshot(string $url, array $options = []): array {
        // HTML letöltés
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'
        ]);
        
        $html = curl_exec($ch);
        curl_close($ch);
        
        if (empty($html)) {
            return ['error' => 'Nem sikerült letölteni az oldalt'];
        }
        
        // Tartalom-alapú CRO elemzés
        return $this->analyzeContentForCRO($html, $url, $options);
    }
    
    /**
     * Tartalom-alapú CRO elemzés (nem vizuális)
     */
    private function analyzeContentForCRO(string $html, string $url, array $options = []): array {
        $tips = [];
        $scores = [];
        
        // CTA gombok keresése
        $ctaCount = preg_match_all('/<(button|a)[^>]*(btn|button|cta|gomb)[^>]*>/i', $html);
        $ctaAboveFold = preg_match('/<(button|a)[^>]*(btn|cta)[^>]*>.*?(hív|rendel|kér|kapcsolat)/is', $html);
        
        $scores['cta'] = $ctaCount > 0 ? ($ctaAboveFold ? 80 : 50) : 20;
        if (!$ctaAboveFold) {
            $tips[] = [
                'category' => 'CTA',
                'icon' => '🔘',
                'severity' => 'warning',
                'tip' => 'Nincs egyértelmű CTA gomb az oldal elején',
                'action' => 'Adj hozzá "Hívj Most" vagy "Ajánlatot Kérek" gombot',
                'impact' => 'Több konverzió'
            ];
        }
        
        // Telefonszám
        $phonePattern = '/(\+36|06)[\s\-]?(\d{1,2})[\s\-]?(\d{3})[\s\-]?(\d{3,4})/';
        $hasPhone = preg_match($phonePattern, $html);
        $clickablePhone = preg_match('/href=["\']tel:/i', $html);
        
        $scores['phone'] = $hasPhone ? ($clickablePhone ? 100 : 60) : 0;
        if (!$hasPhone) {
            $tips[] = [
                'category' => 'Kontakt',
                'icon' => '📞',
                'severity' => 'critical',
                'tip' => 'Telefonszám nem található az oldalon!',
                'action' => 'Helyezd el jól látható helyen a telefont',
                'impact' => 'Közvetlen hívások'
            ];
        } elseif (!$clickablePhone) {
            $tips[] = [
                'category' => 'Kontakt',
                'icon' => '📞',
                'severity' => 'warning',
                'tip' => 'A telefonszám nem kattintható!',
                'action' => 'Használj tel: linket mobilos híváshoz',
                'impact' => 'Könnyebb kapcsolatfelvétel'
            ];
        }
        
        // Trust elemek
        $hasReviews = preg_match('/(vélemény|review|értékelés|csillag|★)/iu', $html);
        $hasCerts = preg_match('/(tanúsítvány|minősítés|díj|ISO|garancia)/iu', $html);
        
        $scores['trust'] = ($hasReviews ? 50 : 0) + ($hasCerts ? 50 : 0);
        if (!$hasReviews) {
            $tips[] = [
                'category' => 'Bizalom',
                'icon' => '⭐',
                'severity' => 'warning',
                'tip' => 'Ügyfélvélemények nem találhatóak',
                'action' => 'Adj hozzá Google Reviews vagy Facebook véleményeket',
                'impact' => 'Növeli a bizalmat'
            ];
        }
        
        // Mobil meta
        $hasMobileMeta = preg_match('/<meta[^>]+viewport/i', $html);
        $scores['mobile'] = $hasMobileMeta ? 100 : 0;
        if (!$hasMobileMeta) {
            $tips[] = [
                'category' => 'Mobil',
                'icon' => '📱',
                'severity' => 'critical',
                'tip' => 'Az oldal nem mobilbarát!',
                'action' => 'Adj hozzá viewport meta taget és responsive CSS-t',
                'impact' => '60%+ mobilról jön'
            ];
        }
        
        // Összesített score
        $overallScore = round(array_sum($scores) / count($scores));
        
        return [
            'success' => true,
            'url' => $url,
            'method' => 'content_analysis',
            'overall_score' => $overallScore,
            'scores' => $scores,
            'cro_tips' => $tips,
            'note' => 'Screenshot API nélküli elemzés - vizuális audit korlátozott'
        ];
    }
}
