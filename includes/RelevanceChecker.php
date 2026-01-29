<?php
/**
 * AdMaster Pro - Relevance Checker
 * 
 * Hirdetés-Weboldal Összhang Ellenőrző
 * - Kulcsszó relevancia vizsgálat
 * - USP kivonatolás weboldalról
 * - Ígéret-tartalom egyezés
 * - Quality Score előrejelzés
 */

class RelevanceChecker {
    
    private string $url;
    private string $html = '';
    private string $text = '';
    private array $headlines = [];
    private array $descriptions = [];
    private array $keywords = [];
    
    /**
     * Konstruktor
     */
    public function __construct(string $url) {
        $this->url = $this->normalizeUrl($url);
    }
    
    private function normalizeUrl(string $url): string {
        $url = trim($url);
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }
    
    /**
     * Weboldal letöltése
     */
    public function fetch(): bool {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        
        $this->html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($this->html)) {
            return false;
        }
        
        $this->text = $this->extractText($this->html);
        return true;
    }
    
    /**
     * Szöveg kinyerése HTML-ből
     */
    private function extractText(string $html): string {
        // Script és style eltávolítása
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
        $text = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $text);
        $text = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $text);
        
        // HTML tagek eltávolítása
        $text = strip_tags($text);
        
        // Whitespace normalizálása
        $text = preg_replace('/\s+/', ' ', $text);
        
        return mb_strtolower(trim($text));
    }
    
    /**
     * Hirdetések beállítása
     */
    public function setAds(array $headlines, array $descriptions): void {
        $this->headlines = array_map(function($h) {
            return is_array($h) ? ($h['text'] ?? '') : $h;
        }, $headlines);
        
        $this->descriptions = array_map(function($d) {
            return is_array($d) ? ($d['text'] ?? '') : $d;
        }, $descriptions);
    }
    
    /**
     * Kulcsszavak beállítása
     */
    public function setKeywords(array $keywords): void {
        $this->keywords = $keywords;
    }
    
    /**
     * FŐ FUNKCIÓ: Teljes relevancia ellenőrzés
     */
    public function checkRelevance(): array {
        if (empty($this->text)) {
            return ['success' => false, 'error' => 'Weboldal nem elérhető'];
        }
        
        $results = [
            'success' => true,
            'url' => $this->url,
            'keyword_relevance' => $this->checkKeywordRelevance(),
            'headline_relevance' => $this->checkHeadlineRelevance(),
            'promise_match' => $this->checkPromiseMatch(),
            'extracted_usps' => $this->extractUSPs(),
            'missing_elements' => $this->findMissingElements(),
            'qs_prediction' => null,
            'recommendations' => []
        ];
        
        // Quality Score előrejelzés
        $results['qs_prediction'] = $this->predictQualityScore($results);
        
        // Javaslatok generálása
        $results['recommendations'] = $this->generateRecommendations($results);
        
        return $results;
    }
    
    /**
     * Kulcsszó relevancia ellenőrzés
     */
    private function checkKeywordRelevance(): array {
        $found = [];
        $missing = [];
        $partial = [];
        
        foreach ($this->keywords as $kw) {
            $kwLower = mb_strtolower(trim($kw));
            
            if (empty($kwLower)) continue;
            
            // Pontos egyezés
            if (mb_strpos($this->text, $kwLower) !== false) {
                $found[] = $kw;
            }
            // Részleges egyezés (szavak külön)
            elseif ($this->checkPartialMatch($kwLower, $this->text)) {
                $partial[] = $kw;
            }
            else {
                $missing[] = $kw;
            }
        }
        
        $total = count($this->keywords);
        $foundCount = count($found) + (count($partial) * 0.5);
        $score = $total > 0 ? round(($foundCount / $total) * 100) : 0;
        
        return [
            'score' => $score,
            'found' => $found,
            'partial' => $partial,
            'missing' => $missing,
            'status' => $score >= 70 ? 'good' : ($score >= 40 ? 'warning' : 'critical'),
            'message' => $score >= 70 
                ? "✅ Jó relevancia ({$score}%)" 
                : ($score >= 40 
                    ? "⚠️ Közepes relevancia ({$score}%)" 
                    : "❌ Gyenge relevancia ({$score}%)")
        ];
    }
    
    /**
     * Részleges egyezés ellenőrzés
     */
    private function checkPartialMatch(string $keyword, string $text): bool {
        $words = explode(' ', $keyword);
        if (count($words) < 2) return false;
        
        $matches = 0;
        foreach ($words as $word) {
            if (mb_strlen($word) > 3 && mb_strpos($text, $word) !== false) {
                $matches++;
            }
        }
        
        return $matches >= count($words) * 0.6;
    }
    
    /**
     * Headline relevancia ellenőrzés
     */
    private function checkHeadlineRelevance(): array {
        $results = [];
        
        foreach ($this->headlines as $headline) {
            $headlineLower = mb_strtolower($headline);
            
            // Szavak kinyerése (min 4 karakter)
            preg_match_all('/[a-záéíóöőúüű]{4,}/u', $headlineLower, $matches);
            $words = $matches[0] ?? [];
            
            $foundWords = 0;
            $totalWords = count($words);
            
            foreach ($words as $word) {
                if (mb_strpos($this->text, $word) !== false) {
                    $foundWords++;
                }
            }
            
            $score = $totalWords > 0 ? round(($foundWords / $totalWords) * 100) : 0;
            
            $results[] = [
                'headline' => $headline,
                'score' => $score,
                'found_words' => $foundWords,
                'total_words' => $totalWords,
                'status' => $score >= 60 ? 'good' : ($score >= 30 ? 'warning' : 'critical')
            ];
        }
        
        // Összesített pontszám
        $avgScore = count($results) > 0 
            ? round(array_sum(array_column($results, 'score')) / count($results)) 
            : 0;
        
        return [
            'average_score' => $avgScore,
            'details' => $results,
            'status' => $avgScore >= 60 ? 'good' : ($avgScore >= 30 ? 'warning' : 'critical')
        ];
    }
    
    /**
     * Ígéret-tartalom egyezés ellenőrzés
     */
    private function checkPromiseMatch(): array {
        $promises = [];
        $issues = [];
        
        // Gyakori ígéretek keresése a hirdetésekben
        $promisePatterns = [
            'ingyenes' => ['ingyenes', 'ingyen', 'díjmentes', '0 ft', '0ft'],
            'garancia' => ['garancia', 'garanciá', 'garantál'],
            '24_ora' => ['24 óra', '24/7', 'non-stop', 'éjjel-nappal', '0-24'],
            'azonnal' => ['azonnal', 'percen belül', 'gyors', 'expressz', 'azonnali'],
            'kedvezmeny' => ['kedvezmény', 'akció', '% off', 'leárazás', 'olcsó'],
            'szallitas' => ['kiszállás', 'kiszállítás', 'házhozszállítás', 'szállítás'],
            'szakerto' => ['szakértő', 'tapasztalt', 'professzionális', 'képzett'],
            'ar' => ['ár', 'árak', 'árlista', 'díjszabás', 'költség']
        ];
        
        $allAdText = mb_strtolower(implode(' ', $this->headlines) . ' ' . implode(' ', $this->descriptions));
        
        foreach ($promisePatterns as $type => $patterns) {
            // Van-e ilyen ígéret a hirdetésben?
            $foundInAd = false;
            foreach ($patterns as $pattern) {
                if (mb_strpos($allAdText, $pattern) !== false) {
                    $foundInAd = true;
                    break;
                }
            }
            
            if ($foundInAd) {
                // Van-e a weboldalon?
                $foundOnPage = false;
                foreach ($patterns as $pattern) {
                    if (mb_strpos($this->text, $pattern) !== false) {
                        $foundOnPage = true;
                        break;
                    }
                }
                
                $promises[$type] = [
                    'in_ad' => true,
                    'on_page' => $foundOnPage,
                    'status' => $foundOnPage ? 'matched' : 'missing'
                ];
                
                if (!$foundOnPage) {
                    $issues[] = [
                        'type' => $type,
                        'message' => $this->getPromiseWarning($type)
                    ];
                }
            }
        }
        
        $matchedCount = count(array_filter($promises, fn($p) => $p['status'] === 'matched'));
        $totalPromises = count($promises);
        $score = $totalPromises > 0 ? round(($matchedCount / $totalPromises) * 100) : 100;
        
        return [
            'score' => $score,
            'promises' => $promises,
            'issues' => $issues,
            'status' => empty($issues) ? 'good' : (count($issues) <= 2 ? 'warning' : 'critical'),
            'message' => empty($issues) 
                ? "✅ Minden hirdetési ígéret megtalálható a weboldalon"
                : "⚠️ " . count($issues) . " ígéret hiányzik a weboldalról!"
        ];
    }
    
    /**
     * Ígéret figyelmeztetés szöveg
     */
    private function getPromiseWarning(string $type): string {
        return match($type) {
            'ingyenes' => '❌ "Ingyenes" szerepel a hirdetésben, de a weboldalon nem találtam!',
            'garancia' => '❌ "Garancia" ígéret nem található a landing page-en!',
            '24_ora' => '❌ "24 órás/Non-stop" szolgáltatás nincs kiemelve a weboldalon!',
            'azonnal' => '❌ "Azonnali" szolgáltatás nem látható a weboldalon!',
            'kedvezmeny' => '❌ Kedvezmény/akció a hirdetésben van, de a weboldalon nem!',
            'szallitas' => '❌ Kiszállás/szállítás nincs részletezve a weboldalon!',
            'szakerto' => '❌ Szakértői tapasztalat nincs kommunikálva!',
            'ar' => '❌ Árak/díjszabás hiányzik a weboldalról!',
            default => '❌ Ígéret nem található a weboldalon!'
        };
    }
    
    /**
     * USP-k automatikus kivonatolása a weboldalról
     */
    public function extractUSPs(): array {
        $usps = [];
        
        // Gyakori USP minták
        $patterns = [
            // 24 órás szolgáltatás
            '/(24\s*(óra|\/7|h)|non[\s\-]?stop|éjjel[\s\-]?nappal|0[\s\-]?24)/iu' => [
                'type' => 'availability',
                'icon' => '⏰',
                'template' => '24 Órás Elérhetőség'
            ],
            // Garancia
            '/(garancia|garantál[a-z]*|pénz[\s\-]?vissza)/iu' => [
                'type' => 'guarantee',
                'icon' => '✅',
                'template' => 'Garanciális Szolgáltatás'
            ],
            // Gyors kiszállás
            '/(\d+\s*(perc|óra)[a-z]*\s*(belül|kiszállás|érkezés)|gyors\s*kiszállás|expressz)/iu' => [
                'type' => 'speed',
                'icon' => '⚡',
                'template' => 'Gyors Kiszállás'
            ],
            // Ingyenes
            '/(ingyenes|díjmentes|0\s*ft)/iu' => [
                'type' => 'free',
                'icon' => '🎁',
                'template' => 'Ingyenes Szolgáltatás'
            ],
            // Tapasztalat évek
            '/(\d+)\s*(év|éve|éves)\s*(tapasztalat|gyakorlat|a\s*szakmában)/iu' => [
                'type' => 'experience',
                'icon' => '🏆',
                'template' => '{1} Év Tapasztalat'
            ],
            // Elégedett ügyfelek száma
            '/(\d+[\s\.\,]?\d*)\s*(\+)?\s*(elégedett|boldog)?\s*(ügyfél|megrendelő|vásárló)/iu' => [
                'type' => 'clients',
                'icon' => '👥',
                'template' => '{1}+ Elégedett Ügyfél'
            ],
            // Fix/kedvező árak
            '/(fix\s*ár|kedvező\s*ár|olcsó|árlista|áraink)/iu' => [
                'type' => 'pricing',
                'icon' => '💰',
                'template' => 'Fix Árak'
            ],
            // Szakképzett/Profi
            '/(szak[ké]pzett|szakért[őo]|professzionális|minősített|okleveles)/iu' => [
                'type' => 'expert',
                'icon' => '🎓',
                'template' => 'Szakképzett Csapat'
            ],
            // Országos lefedettség
            '/(országos|egész\s*magyarország|minden\s*megy[eé])/iu' => [
                'type' => 'coverage',
                'icon' => '🗺️',
                'template' => 'Országos Lefedettség'
            ],
            // Hétvégén is
            '/(hétvég[eé]n|szombat|vasárnap|ünnepnap)/iu' => [
                'type' => 'weekend',
                'icon' => '📅',
                'template' => 'Hétvégén is Elérhetőek'
            ]
        ];
        
        foreach ($patterns as $pattern => $config) {
            if (preg_match($pattern, $this->text, $matches)) {
                $text = $config['template'];
                
                // Számok behelyettesítése
                if (isset($matches[1]) && is_numeric(str_replace(['.', ',', ' '], '', $matches[1]))) {
                    $text = str_replace('{1}', $matches[1], $text);
                }
                
                // Kontextus kinyerése
                $context = $this->extractContext($matches[0], 50);
                
                $usps[] = [
                    'type' => $config['type'],
                    'icon' => $config['icon'],
                    'text' => $text,
                    'original' => $matches[0],
                    'context' => $context,
                    'headline_suggestion' => $this->createHeadlineSuggestion($config['type'], $matches)
                ];
            }
        }
        
        // Telefonszám kiemelés
        if (preg_match('/(\+36|06)[\s\-]?(\d{1,2})[\s\-]?(\d{3})[\s\-]?(\d{3,4})/', $this->html, $phone)) {
            $usps[] = [
                'type' => 'phone',
                'icon' => '📞',
                'text' => 'Telefonos Elérhetőség',
                'original' => $phone[0],
                'headline_suggestion' => 'Hívj: ' . $phone[0]
            ];
        }
        
        return $usps;
    }
    
    /**
     * Kontextus kinyerése találat körül
     */
    private function extractContext(string $match, int $chars = 50): string {
        $pos = mb_strpos($this->text, mb_strtolower($match));
        if ($pos === false) return '';
        
        $start = max(0, $pos - $chars);
        $length = mb_strlen($match) + ($chars * 2);
        
        $context = mb_substr($this->text, $start, $length);
        
        if ($start > 0) $context = '...' . $context;
        if ($start + $length < mb_strlen($this->text)) $context .= '...';
        
        return $context;
    }
    
    /**
     * Headline javaslat készítése USP-ből
     */
    private function createHeadlineSuggestion(string $type, array $matches): string {
        return match($type) {
            'availability' => '24/7 Elérhetőség',
            'guarantee' => 'Garanciával Dolgozunk',
            'speed' => isset($matches[1]) ? $matches[1] . ' Percen Belül Nálad' : 'Gyors Kiszállás',
            'free' => 'Ingyenes Felmérés',
            'experience' => isset($matches[1]) ? $matches[1] . ' Év Tapasztalat' : 'Tapasztalt Szakemberek',
            'clients' => isset($matches[1]) ? $matches[1] . '+ Elégedett Ügyfél' : 'Elégedett Ügyfelek',
            'pricing' => 'Fix Árak - Nincs Rejtett Költség',
            'expert' => 'Szakképzett Csapat',
            'coverage' => 'Országos Lefedettség',
            'weekend' => 'Hétvégén is Jövünk',
            default => ''
        };
    }
    
    /**
     * Hiányzó elemek keresése
     */
    private function findMissingElements(): array {
        $missing = [];
        
        // Telefonszám
        if (!preg_match('/(\+36|06)[\s\-]?\d/', $this->html)) {
            $missing[] = [
                'element' => 'phone',
                'icon' => '📞',
                'message' => 'Telefonszám nem található a weboldalon',
                'impact' => 'high',
                'suggestion' => 'Helyezd el a telefont jól látható helyen'
            ];
        }
        
        // CTA gomb
        if (!preg_match('/(btn|button|cta|gomb)/i', $this->html) && 
            !preg_match('/<a[^>]+(call|contact|hívj|kapcsolat)/i', $this->html)) {
            $missing[] = [
                'element' => 'cta',
                'icon' => '🔘',
                'message' => 'Nincs egyértelmű CTA (Call-to-Action) gomb',
                'impact' => 'high',
                'suggestion' => 'Adj hozzá feltűnő "Hívj Most" vagy "Ajánlatot Kérek" gombot'
            ];
        }
        
        // Árak
        if (!preg_match('/(ár|ft|forint|díj|költség|\d+[\s\.]?\d*\s*ft)/iu', $this->text)) {
            $missing[] = [
                'element' => 'pricing',
                'icon' => '💰',
                'message' => 'Árinformáció nem található',
                'impact' => 'medium',
                'suggestion' => 'Adj meg orientációs árakat vagy "Árajánlat" linket'
            ];
        }
        
        // Bizalmi elemek
        if (!preg_match('/(vélemény|értékelés|review|google|facebook|★|csillag)/iu', $this->text)) {
            $missing[] = [
                'element' => 'reviews',
                'icon' => '⭐',
                'message' => 'Ügyfélvélemények nincsenek',
                'impact' => 'medium',
                'suggestion' => 'Helyezz el Google Reviews widgetet'
            ];
        }
        
        // SSL
        if (strpos($this->url, 'https://') !== 0) {
            $missing[] = [
                'element' => 'ssl',
                'icon' => '🔒',
                'message' => 'HTTPS hiányzik!',
                'impact' => 'critical',
                'suggestion' => 'SSL tanúsítvány KÖTELEZŐ a Google Ads-hoz'
            ];
        }
        
        return $missing;
    }
    
    /**
     * Quality Score előrejelzés
     */
    private function predictQualityScore(array $results): array {
        $score = 5; // Alap
        $factors = [];
        
        // Kulcsszó relevancia (+/- 2)
        $kwScore = $results['keyword_relevance']['score'] ?? 0;
        if ($kwScore >= 70) {
            $score += 2;
            $factors[] = ['factor' => 'Keyword relevancia', 'impact' => '+2', 'status' => 'good'];
        } elseif ($kwScore >= 40) {
            $factors[] = ['factor' => 'Keyword relevancia', 'impact' => '0', 'status' => 'neutral'];
        } else {
            $score -= 2;
            $factors[] = ['factor' => 'Keyword relevancia', 'impact' => '-2', 'status' => 'bad'];
        }
        
        // Headline relevancia (+/- 1)
        $hScore = $results['headline_relevance']['average_score'] ?? 0;
        if ($hScore >= 60) {
            $score += 1;
            $factors[] = ['factor' => 'Headline-oldal egyezés', 'impact' => '+1', 'status' => 'good'];
        } elseif ($hScore < 30) {
            $score -= 1;
            $factors[] = ['factor' => 'Headline-oldal egyezés', 'impact' => '-1', 'status' => 'bad'];
        }
        
        // Ígéret-tartalom egyezés (+/- 1)
        $pScore = $results['promise_match']['score'] ?? 100;
        if ($pScore >= 80) {
            $score += 1;
            $factors[] = ['factor' => 'Ígéret teljesülés', 'impact' => '+1', 'status' => 'good'];
        } elseif ($pScore < 50) {
            $score -= 1;
            $factors[] = ['factor' => 'Ígéret teljesülés', 'impact' => '-1', 'status' => 'bad'];
        }
        
        // Hiányzó elemek (-1 per critical)
        $criticalMissing = count(array_filter($results['missing_elements'] ?? [], fn($m) => $m['impact'] === 'critical'));
        if ($criticalMissing > 0) {
            $score -= $criticalMissing;
            $factors[] = ['factor' => 'Kritikus hiányok', 'impact' => "-{$criticalMissing}", 'status' => 'bad'];
        }
        
        // Határok
        $score = max(1, min(10, $score));
        
        return [
            'score' => $score,
            'factors' => $factors,
            'label' => $score >= 7 ? 'Jó' : ($score >= 4 ? 'Közepes' : 'Gyenge'),
            'color' => $score >= 7 ? 'green' : ($score >= 4 ? 'orange' : 'red'),
            'cpc_impact' => $score >= 7 
                ? 'Alacsonyabb CPC várható' 
                : ($score >= 4 
                    ? 'Átlagos CPC' 
                    : '⚠️ Magasabb CPC várható!')
        ];
    }
    
    /**
     * Javaslatok generálása
     */
    private function generateRecommendations(array $results): array {
        $recs = [];
        $priority = 1;
        
        // Kritikus hiányok
        foreach ($results['missing_elements'] ?? [] as $missing) {
            if ($missing['impact'] === 'critical') {
                $recs[] = [
                    'priority' => $priority++,
                    'type' => 'critical',
                    'icon' => $missing['icon'],
                    'title' => $missing['message'],
                    'action' => $missing['suggestion'],
                    'impact' => 'QS jelentősen romlik'
                ];
            }
        }
        
        // Ígéret problémák
        foreach ($results['promise_match']['issues'] ?? [] as $issue) {
            $recs[] = [
                'priority' => $priority++,
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => $issue['message'],
                'action' => 'Vagy távolítsd el a hirdetésből, vagy add hozzá a weboldalhoz!',
                'impact' => 'Google büntetheti a relevanciát'
            ];
        }
        
        // Hiányzó kulcsszavak
        $missingKw = $results['keyword_relevance']['missing'] ?? [];
        if (!empty($missingKw)) {
            $recs[] = [
                'priority' => $priority++,
                'type' => 'warning',
                'icon' => '🔤',
                'title' => 'Kulcsszavak hiányoznak a weboldalról',
                'action' => 'Építsd be: ' . implode(', ', array_slice($missingKw, 0, 5)),
                'impact' => 'Kulcsszó relevancia javulhat'
            ];
        }
        
        // Medium hiányok
        foreach ($results['missing_elements'] ?? [] as $missing) {
            if ($missing['impact'] === 'medium') {
                $recs[] = [
                    'priority' => $priority++,
                    'type' => 'info',
                    'icon' => $missing['icon'],
                    'title' => $missing['message'],
                    'action' => $missing['suggestion'],
                    'impact' => 'Konverzió javulhat'
                ];
            }
        }
        
        // USP javaslatok
        $usps = $results['extracted_usps'] ?? [];
        if (!empty($usps)) {
            $suggestions = array_filter(array_column($usps, 'headline_suggestion'));
            if (!empty($suggestions)) {
                $recs[] = [
                    'priority' => $priority++,
                    'type' => 'tip',
                    'icon' => '💡',
                    'title' => 'Használd ezeket a headline-okban',
                    'action' => implode(' | ', array_slice($suggestions, 0, 3)),
                    'impact' => 'Jobb CTR és relevancia'
                ];
            }
        }
        
        return $recs;
    }
}
