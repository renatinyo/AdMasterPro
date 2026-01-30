<?php
/**
 * AdMaster Pro - Landing Page Analyzer
 * 
 * Landing page elemzés:
 * - Technikai ellenőrzés (HTTPS, mobile, speed)
 * - Tartalom elemzés (kulcsszavak, CTA, trust elemek)
 * - Google Ads relevancia scoring
 * - Konkrét javaslatok generálása
 */

require_once __DIR__ . '/Security.php';

class LandingPageAnalyzer {
    
    private string $url;
    private string $html = '';
    private array $headers = [];
    private array $industry = [];
    private float $loadTime = 0;
    private array $errors = [];
    
    private array $technical = [];
    private array $content = [];
    private array $seo = [];
    private array $trust = [];
    private array $cta = [];
    private array $tracking = [];
    
    private array $weights = [
        'https' => 10, 'mobile' => 15, 'speed' => 15,
        'phone_visible' => 10, 'cta_above_fold' => 10,
        'trust_elements' => 10, 'keyword_relevance' => 15, 'content_quality' => 15
    ];

    public function __construct(string $url, array $industry = []) {
        // SSRF védelem - URL validálás
        $validation = Security::validateExternalUrl($url);
        if (!$validation['valid']) {
            throw new InvalidArgumentException('Biztonsági hiba: ' . $validation['error']);
        }
        $this->url = $validation['url'];
        $this->industry = $industry;
    }

    public function fetch(): bool {
        $startTime = microtime(true);
        
        // Biztonságos lekérés Security osztályon keresztül
        $result = Security::fetchExternalUrl($this->url);
        $this->loadTime = microtime(true) - $startTime;
        
        if (!$result['success']) {
            $this->errors[] = $result['error'];
            return false;
        }
        
        // Header lekérés külön CURL hívásal (HEAD request)
        $ch = curl_init();
        curl_setopt_array($ch, array_merge(
            Security::getSecureCurlOptions($this->url),
            [
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true
            ]
        ));
        $headerResponse = curl_exec($ch);
        $this->url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        $this->headers = $this->parseHeaders($headerResponse);
        $this->html = $result['content'];
        return true;
    }

    private function parseHeaders(string $str): array {
        $headers = [];
        foreach (explode("\r\n", $str) as $line) {
            if (strpos($line, ':') !== false) {
                list($k, $v) = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        return $headers;
    }

    public function analyze(): array {
        if (empty($this->html)) {
            return ['success' => false, 'error' => implode(', ', $this->errors) ?: 'Nincs HTML.'];
        }
        
        $this->analyzeTechnical();
        $this->analyzeContent();
        $this->analyzeTrustElements();
        $this->analyzeCtaElements();
        $this->analyzeSeo();
        
        return [
            'success' => true,
            'url' => $this->url,
            'score' => $this->calculateScore(),
            'load_time' => round($this->loadTime, 2),
            'technical' => $this->technical,
            'content' => $this->content,
            'trust' => $this->trust,
            'cta' => $this->cta,
            'seo' => $this->seo,
            'tracking' => $this->tracking,
            'recommendations' => $this->generateRecommendations(),
            'industry' => $this->industry['name'] ?? null
        ];
    }

    private function analyzeTechnical(): void {
        $this->technical['https'] = [
            'status' => strpos($this->url, 'https://') === 0,
            'label' => 'HTTPS',
            'message' => strpos($this->url, 'https://') === 0 ? 'Biztonságos ✓' : 'HIÁNYZIK!'
        ];
        
        $hasMobile = preg_match('/<meta[^>]+viewport/i', $this->html);
        $this->technical['mobile'] = [
            'status' => (bool)$hasMobile,
            'label' => 'Mobile Responsive',
            'message' => $hasMobile ? 'Van viewport ✓' : 'Hiányzik!'
        ];
        
        $speedOk = $this->loadTime < 3;
        $this->technical['speed'] = [
            'status' => $speedOk,
            'label' => 'Betöltési idő',
            'value' => round($this->loadTime, 2) . 's',
            'message' => $speedOk ? 'Gyors ✓' : 'Lassú! (>' . round($this->loadTime, 1) . 's)'
        ];
        
        $hasGzip = isset($this->headers['content-encoding']) && strpos($this->headers['content-encoding'], 'gzip') !== false;
        $this->technical['gzip'] = ['status' => $hasGzip, 'label' => 'Gzip', 'message' => $hasGzip ? 'Aktív ✓' : 'Nincs'];
    }

    private function analyzeContent(): void {
        $text = $this->extractText();
        $textLower = mb_strtolower($text);
        
        // Title
        preg_match('/<title[^>]*>([^<]+)<\/title>/i', $this->html, $m);
        $title = $m[1] ?? '';
        $titleLen = mb_strlen($title);
        $this->content['title'] = [
            'status' => $titleLen >= 30 && $titleLen <= 60,
            'label' => 'Title tag',
            'value' => $title ?: 'Hiányzik!',
            'length' => $titleLen,
            'message' => empty($title) ? 'Hiányzik!' : ($titleLen < 30 ? 'Rövid' : ($titleLen > 60 ? 'Hosszú' : 'OK ✓'))
        ];
        
        // Meta desc
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $this->html, $m);
        $desc = $m[1] ?? '';
        $descLen = mb_strlen($desc);
        $this->content['meta_description'] = [
            'status' => $descLen >= 120 && $descLen <= 160,
            'label' => 'Meta Description',
            'value' => $desc ? mb_substr($desc, 0, 80) . '...' : 'Hiányzik!',
            'length' => $descLen,
            'message' => empty($desc) ? 'Hiányzik!' : ($descLen < 120 ? 'Rövid' : 'OK ✓')
        ];
        
        // H1
        preg_match('/<h1[^>]*>(.+?)<\/h1>/is', $this->html, $m);
        $h1 = trim(strip_tags($m[1] ?? ''));
        $this->content['h1'] = [
            'status' => !empty($h1),
            'label' => 'H1 címsor',
            'value' => $h1 ?: 'Hiányzik!',
            'message' => empty($h1) ? 'Nincs H1!' : 'Van ✓'
        ];
        
        // Telefon
        $phonePattern = '/(\+36|06)[\s\-]?(\d{1,2})[\s\-]?(\d{3})[\s\-]?(\d{3,4})/';
        preg_match_all($phonePattern, $this->html, $phones);
        $phoneList = array_unique($phones[0] ?? []);
        $phoneAbove = preg_match($phonePattern, substr($this->html, 0, 3000));
        
        $this->content['phone'] = [
            'status' => !empty($phoneList),
            'label' => 'Telefonszám',
            'value' => $phoneList ? implode(', ', array_slice($phoneList, 0, 2)) : 'Nincs!',
            'above_fold' => (bool)$phoneAbove,
            'message' => empty($phoneList) ? 'Nincs telefon!' : ($phoneAbove ? 'Above fold ✓' : 'Van, de lejjebb')
        ];
        
        // Iparági kulcsszavak
        if (!empty($this->industry['keywords'])) {
            $all = array_merge($this->industry['keywords']['primary'] ?? [], $this->industry['keywords']['secondary'] ?? []);
            $found = $missing = [];
            foreach ($all as $kw) {
                if (mb_strpos($textLower, mb_strtolower($kw)) !== false) {
                    $found[] = $kw;
                } else {
                    $missing[] = $kw;
                }
            }
            $this->content['keywords'] = [
                'status' => count($found) >= 3,
                'label' => 'Kulcsszavak',
                'found' => $found,
                'missing' => array_slice($missing, 0, 5),
                'message' => count($found) . '/' . count($all) . ' kulcsszó'
            ];
        }
        
        // Szöveghossz
        $words = str_word_count($text);
        $this->content['word_count'] = [
            'status' => $words >= 300,
            'label' => 'Tartalom',
            'value' => $words . ' szó',
            'message' => $words < 300 ? 'Kevés (min 300)' : 'OK ✓'
        ];
    }

    private function analyzeTrustElements(): void {
        $html = mb_strtolower($this->html);
        
        $this->trust['reviews'] = [
            'status' => (bool)preg_match('/(review|értékelés|vélemény|\d[\.,]\d\s*\/\s*5|csillag)/i', $this->html),
            'label' => 'Értékelések',
            'message' => preg_match('/(review|értékelés)/i', $this->html) ? 'Van ✓' : 'Nincs!'
        ];
        
        $this->trust['guarantee'] = [
            'status' => (bool)preg_match('/(garanci|szavatosság)/i', $this->html),
            'label' => 'Garancia',
            'message' => preg_match('/garanci/i', $this->html) ? 'Van ✓' : 'Nincs'
        ];
        
        $hasExp = preg_match('/(\d+)\s*(\+)?\s*(év|éve|éves)/i', $this->html, $m);
        $this->trust['experience'] = [
            'status' => (bool)$hasExp,
            'label' => 'Tapasztalat',
            'value' => $hasExp ? $m[0] : null,
            'message' => $hasExp ? 'Van ✓' : 'Nincs'
        ];
        
        $this->trust['pricing'] = [
            'status' => (bool)preg_match('/(ár|ft|forint|\d+\s*ft)/i', $this->html),
            'label' => 'Árak',
            'message' => preg_match('/(\d+\s*ft|árak)/i', $this->html) ? 'Van ✓' : 'Nincs'
        ];
    }

    private function analyzeCtaElements(): void {
        preg_match_all('/<(button|a)[^>]*>([^<]*(hívj|kér|kapcsolat|rendel|foglal|küld)[^<]*)</i', $this->html, $m);
        $btns = array_unique(array_map('trim', $m[2] ?? []));
        $btns = array_filter($btns, fn($b) => mb_strlen($b) > 2 && mb_strlen($b) < 50);
        
        $this->cta['buttons'] = [
            'status' => count($btns) > 0,
            'label' => 'CTA gombok',
            'value' => array_slice(array_values($btns), 0, 5),
            'count' => count($btns),
            'message' => count($btns) > 0 ? count($btns) . ' CTA ✓' : 'Nincs CTA!'
        ];
        
        $ctaAbove = preg_match('/(hívj|kér|kapcsolat|btn)/i', substr($this->html, 0, 3000));
        $this->cta['above_fold'] = [
            'status' => (bool)$ctaAbove,
            'label' => 'CTA Above Fold',
            'message' => $ctaAbove ? 'Van ✓' : 'Nincs felül!'
        ];
        
        $this->cta['clickable_phone'] = [
            'status' => (bool)preg_match('/href=["\']tel:/i', $this->html),
            'label' => 'Kattintható tel.',
            'message' => preg_match('/tel:/i', $this->html) ? 'Van ✓' : 'Nincs!'
        ];
        
        $this->cta['form'] = [
            'status' => (bool)preg_match('/<form/i', $this->html),
            'label' => 'Űrlap',
            'message' => preg_match('/<form/i', $this->html) ? 'Van ✓' : 'Nincs'
        ];
    }

    private function analyzeSeo(): void {
        preg_match_all('/<img[^>]+>/i', $this->html, $imgs);
        $total = count($imgs[0] ?? []);
        $withAlt = 0;
        foreach ($imgs[0] ?? [] as $img) {
            if (preg_match('/alt=["\'][^"\']+/', $img)) $withAlt++;
        }
        $this->seo['images_alt'] = [
            'status' => $total == 0 || $withAlt / max(1, $total) > 0.8,
            'label' => 'Képek alt',
            'value' => "$withAlt/$total",
            'message' => "$withAlt / $total képnek van alt"
        ];
        
        $this->seo['schema'] = [
            'status' => (bool)preg_match('/(ld\+json|schema\.org)/i', $this->html),
            'label' => 'Schema.org',
            'message' => preg_match('/schema\.org/i', $this->html) ? 'Van ✓' : 'Nincs'
        ];
        
        // GTM/Analytics detektálás
        $this->analyzeTracking();
    }
    
    private function analyzeTracking(): void {
        $this->tracking = [];
        
        // Google Tag Manager
        $hasGtm = (bool)preg_match('/googletagmanager\.com|gtm\.js|GTM-[A-Z0-9]+/i', $this->html);
        $gtmId = null;
        if (preg_match('/GTM-[A-Z0-9]+/', $this->html, $gtmMatch)) {
            $gtmId = $gtmMatch[0];
        }
        $this->tracking['gtm'] = [
            'status' => $hasGtm,
            'label' => 'Google Tag Manager',
            'message' => $hasGtm ? "Telepítve ✓" . ($gtmId ? " ($gtmId)" : "") : 'Nincs telepítve!',
            'id' => $gtmId
        ];
        
        // Google Analytics 4 (GA4)
        $hasGa4 = (bool)preg_match('/gtag\(|G-[A-Z0-9]+|googletagmanager.*gtag/i', $this->html);
        $ga4Id = null;
        if (preg_match('/G-[A-Z0-9]+/', $this->html, $ga4Match)) {
            $ga4Id = $ga4Match[0];
        }
        $this->tracking['ga4'] = [
            'status' => $hasGa4,
            'label' => 'Google Analytics 4',
            'message' => $hasGa4 ? "Telepítve ✓" . ($ga4Id ? " ($ga4Id)" : "") : 'Nincs telepítve!',
            'id' => $ga4Id
        ];
        
        // Universal Analytics (régi GA)
        $hasUa = (bool)preg_match('/UA-\d+-\d+|analytics\.js|google-analytics\.com\/analytics/i', $this->html);
        $uaId = null;
        if (preg_match('/UA-\d+-\d+/', $this->html, $uaMatch)) {
            $uaId = $uaMatch[0];
        }
        $this->tracking['ua'] = [
            'status' => $hasUa,
            'label' => 'Universal Analytics',
            'message' => $hasUa ? "Telepítve ($uaId) - ⚠️ Elavult!" : 'Nincs (OK - már GA4 kell)',
            'id' => $uaId,
            'deprecated' => true
        ];
        
        // Google Ads Conversion Tracking
        $hasGadsConv = (bool)preg_match('/AW-\d+|googleadservices\.com|gtag.*conversion/i', $this->html);
        $awId = null;
        if (preg_match('/AW-\d+/', $this->html, $awMatch)) {
            $awId = $awMatch[0];
        }
        $this->tracking['gads'] = [
            'status' => $hasGadsConv,
            'label' => 'Google Ads Konverzió',
            'message' => $hasGadsConv ? "Telepítve ✓" . ($awId ? " ($awId)" : "") : 'Nincs! Konverziókövetés hiányzik!',
            'id' => $awId,
            'critical' => !$hasGadsConv
        ];
        
        // Facebook Pixel
        $hasFbPixel = (bool)preg_match('/fbq\(|facebook\.com\/tr|fb-pixel/i', $this->html);
        $this->tracking['facebook'] = [
            'status' => $hasFbPixel,
            'label' => 'Facebook Pixel',
            'message' => $hasFbPixel ? 'Telepítve ✓' : 'Nincs (opcionális)'
        ];
        
        // Microsoft/Bing UET
        $hasBing = (bool)preg_match('/bat\.bing\.com|uet/i', $this->html);
        $this->tracking['bing'] = [
            'status' => $hasBing,
            'label' => 'Bing UET',
            'message' => $hasBing ? 'Telepítve ✓' : 'Nincs (opcionális)'
        ];
    }

    private function extractText(): string {
        $t = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $this->html);
        $t = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $t);
        return trim(preg_replace('/\s+/', ' ', strip_tags($t)));
    }

    private function calculateScore(): array {
        $score = 0;
        $max = array_sum($this->weights);
        
        if ($this->technical['https']['status'] ?? false) $score += $this->weights['https'];
        if ($this->technical['mobile']['status'] ?? false) $score += $this->weights['mobile'];
        if ($this->technical['speed']['status'] ?? false) $score += $this->weights['speed'];
        if ($this->content['phone']['above_fold'] ?? false) $score += $this->weights['phone_visible'];
        if ($this->cta['above_fold']['status'] ?? false) $score += $this->weights['cta_above_fold'];
        
        $trustCount = array_reduce($this->trust, fn($c, $i) => $c + ($i['status'] ? 1 : 0), 0);
        $score += ($trustCount / max(1, count($this->trust))) * $this->weights['trust_elements'];
        
        if ($this->content['keywords']['status'] ?? false) $score += $this->weights['keyword_relevance'];
        
        $contentScore = 0;
        if ($this->content['title']['status'] ?? false) $contentScore += 5;
        if ($this->content['meta_description']['status'] ?? false) $contentScore += 5;
        if ($this->content['h1']['status'] ?? false) $contentScore += 3;
        if ($this->content['word_count']['status'] ?? false) $contentScore += 2;
        $score += $contentScore;
        
        $pct = round($score / max(1, $max) * 100);
        
        return [
            'points' => round($score),
            'max' => $max,
            'percentage' => $pct,
            'grade' => $pct >= 90 ? 'Kiváló' : ($pct >= 75 ? 'Jó' : ($pct >= 60 ? 'Átlagos' : ($pct >= 40 ? 'Gyenge' : 'Kritikus'))),
            'color' => $pct >= 75 ? 'green' : ($pct >= 50 ? 'orange' : 'red')
        ];
    }

    private function generateRecommendations(): array {
        $recs = [];
        $p = 1;
        
        if (!($this->technical['https']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'critical', 'title' => 'HTTPS hiányzik!', 
                'description' => 'Google Ads megköveteli.', 'action' => 'Szerezz SSL tanúsítványt.', 'impact' => 'QS javulás'];
        }
        if (!($this->technical['mobile']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'critical', 'title' => 'Nem mobile-friendly!',
                'description' => '60%+ mobilról jön.', 'action' => 'Adj viewport meta taget.', 'impact' => 'Konverzió növekedés'];
        }
        if (!($this->content['phone']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'critical', 'title' => 'Nincs telefonszám!',
                'description' => 'Sürgősségi szolgáltatásnál kötelező.', 'action' => 'Tedd a fejlécbe.', 'impact' => 'Azonnali konverzió'];
        }
        if (!($this->technical['speed']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'warning', 'title' => 'Lassú betöltés',
                'description' => $this->technical['speed']['value'] ?? '', 'action' => 'Optimalizálj képeket, használj cache-t.', 'impact' => 'Bounce rate csökken'];
        }
        if (!($this->cta['above_fold']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'warning', 'title' => 'Nincs CTA felül',
                'description' => 'Azonnal látható CTA kell.', 'action' => 'Helyezz gombot a hajtás fölé.', 'impact' => 'Magasabb konverzió'];
        }
        if (!($this->cta['clickable_phone']['status'] ?? true) && ($this->content['phone']['status'] ?? false)) {
            $recs[] = ['priority' => $p++, 'type' => 'warning', 'title' => 'Telefon nem kattintható',
                'description' => 'Mobilon egy kattintás kell.', 'action' => 'Használj tel: linket.', 'impact' => 'Könnyebb hívás'];
        }
        if (!($this->trust['reviews']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'warning', 'title' => 'Nincsenek értékelések',
                'description' => '90% megnézi őket.', 'action' => 'Google Reviews widget.', 'impact' => 'Bizalom növekedés'];
        }
        if (!($this->trust['guarantee']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'info', 'title' => 'Garancia nincs kiemelve',
                'description' => 'Csökkenti a kockázatot.', 'action' => 'Emeld ki a garanciát.', 'impact' => 'Konverzió javulás'];
        }
        if (!($this->trust['pricing']['status'] ?? true)) {
            $recs[] = ['priority' => $p++, 'type' => 'info', 'title' => 'Nincs ár',
                'description' => 'Bizonytalanságot okoz.', 'action' => 'Adj orientációs árakat.', 'impact' => 'Jobb minőségű leadek'];
        }
        if (!empty($this->content['keywords']['missing'] ?? [])) {
            $recs[] = ['priority' => $p++, 'type' => 'info', 'title' => 'Hiányzó kulcsszavak',
                'description' => implode(', ', array_slice($this->content['keywords']['missing'], 0, 3)),
                'action' => 'Építsd be a tartalomba.', 'impact' => 'Relevancia javulás'];
        }
        
        return $recs;
    }

    public function getErrors(): array { return $this->errors; }
}
