<?php
/**
 * AdMaster Pro - CSV Analyzer
 * 
 * Google Ads riportok elemzése:
 * - Campaign report
 * - Ad report
 * - Keyword report
 * - Search terms report
 */

class CsvAnalyzer {
    
    private array $data = [];
    private string $reportType = '';
    private array $headers = [];
    
    // Elvárt oszlopok típusonként
    private array $expectedColumns = [
        'campaign' => ['Campaign', 'Cost', 'Clicks', 'Impressions', 'Conversions', 'Conv. value'],
        'ad' => ['Campaign', 'Ad group', 'Headline 1', 'Clicks', 'Impressions', 'CTR', 'Conversions'],
        'keyword' => ['Campaign', 'Ad group', 'Keyword', 'Match type', 'Clicks', 'Cost', 'Conversions'],
        'search_terms' => ['Campaign', 'Ad group', 'Search term', 'Clicks', 'Cost', 'Conversions']
    ];

    // Magyar oszlopnevek mapping
    private array $columnMappings = [
        'Kampány' => 'Campaign',
        'Költség' => 'Cost',
        'Kattintások' => 'Clicks',
        'Megjelenések' => 'Impressions',
        'Konverziók' => 'Conversions',
        'Konv. érték' => 'Conv. value',
        'Hirdetéscsoport' => 'Ad group',
        'Kulcsszó' => 'Keyword',
        'Egyezési típus' => 'Match type',
        'Keresési kifejezés' => 'Search term',
        'Címsor 1' => 'Headline 1',
        'Címsor 2' => 'Headline 2',
        'Leírás' => 'Description'
    ];

    /**
     * CSV fájl feldolgozása
     */
    public function parseFile(string $filepath): array {
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Fájl nem található.'];
        }

        // Fájl beolvasása
        $content = file_get_contents($filepath);
        
        // BOM eltávolítása
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // UTF-8 konverzió ha szükséges
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-2');
        }

        return $this->parseContent($content);
    }

    /**
     * CSV string feldolgozása
     */
    public function parseContent(string $content): array {
        // Sorok szétválasztása
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_filter($lines, fn($line) => trim($line) !== '');

        if (count($lines) < 2) {
            return ['success' => false, 'error' => 'A fájl üres vagy nincs elég sor.'];
        }

        // Header feldolgozás
        $delimiter = $this->detectDelimiter($lines[0]);
        $this->headers = str_getcsv($lines[0], $delimiter);
        $this->headers = array_map('trim', $this->headers);
        
        // Magyar oszlopnevek lefordítása
        $this->headers = array_map(fn($h) => $this->columnMappings[$h] ?? $h, $this->headers);

        // Riport típus detektálása
        $this->reportType = $this->detectReportType();
        
        if (empty($this->reportType)) {
            return [
                'success' => false, 
                'error' => 'Nem sikerült felismerni a riport típusát. Győződj meg róla, hogy Google Ads exportot töltöttél fel.',
                'detected_columns' => $this->headers
            ];
        }

        // Adatok feldolgozása
        $this->data = [];
        for ($i = 1; $i < count($lines); $i++) {
            $values = str_getcsv($lines[$i], $delimiter);
            
            if (count($values) !== count($this->headers)) {
                continue; // Hibás sor kihagyása
            }

            $row = [];
            foreach ($this->headers as $idx => $header) {
                $row[$header] = $this->parseValue($values[$idx], $header);
            }
            $this->data[] = $row;
        }

        if (empty($this->data)) {
            return ['success' => false, 'error' => 'Nem sikerült adatokat kinyerni a fájlból.'];
        }

        return [
            'success' => true,
            'report_type' => $this->reportType,
            'row_count' => count($this->data),
            'columns' => $this->headers
        ];
    }

    /**
     * Delimiter detektálás
     */
    private function detectDelimiter(string $line): string {
        $delimiters = [',', ';', "\t"];
        $counts = [];
        
        foreach ($delimiters as $d) {
            $counts[$d] = substr_count($line, $d);
        }
        
        return array_search(max($counts), $counts);
    }

    /**
     * Riport típus detektálása
     */
    private function detectReportType(): string {
        foreach ($this->expectedColumns as $type => $columns) {
            $matches = 0;
            foreach ($columns as $col) {
                if (in_array($col, $this->headers)) {
                    $matches++;
                }
            }
            // Ha legalább 3 oszlop egyezik
            if ($matches >= 3) {
                return $type;
            }
        }
        return '';
    }

    /**
     * Érték parsing (számok, százalékok)
     */
    private function parseValue(string $value, string $header): mixed {
        $value = trim($value);
        
        // Üres érték
        if ($value === '' || $value === '--') {
            return null;
        }

        // Pénzügyi mezők
        if (in_array($header, ['Cost', 'Conv. value', 'CPC', 'CPA'])) {
            // "1 234,56 Ft" vagy "$1,234.56" formátum
            $value = preg_replace('/[^\d,.\-]/', '', $value);
            $value = str_replace(',', '.', str_replace('.', '', $value));
            return (float) $value;
        }

        // Százalék mezők
        if (in_array($header, ['CTR', 'Conv. rate', 'Search impr. share'])) {
            $value = str_replace(['%', ','], ['', '.'], $value);
            return (float) $value;
        }

        // Numerikus mezők
        if (in_array($header, ['Clicks', 'Impressions', 'Conversions'])) {
            $value = preg_replace('/[^\d.\-]/', '', $value);
            $value = str_replace(',', '.', $value);
            return (float) $value;
        }

        return $value;
    }

    /**
     * Elemzés futtatása
     */
    public function analyze(): array {
        if (empty($this->data)) {
            return ['success' => false, 'error' => 'Nincs adat az elemzéshez.'];
        }

        $analysis = match($this->reportType) {
            'campaign' => $this->analyzeCampaigns(),
            'ad' => $this->analyzeAds(),
            'keyword' => $this->analyzeKeywords(),
            'search_terms' => $this->analyzeSearchTerms(),
            default => ['error' => 'Ismeretlen riport típus']
        };

        return [
            'success' => true,
            'report_type' => $this->reportType,
            'summary' => $this->getSummary(),
            'analysis' => $analysis,
            'recommendations' => $this->getRecommendations($analysis)
        ];
    }

    /**
     * Összesítés
     */
    private function getSummary(): array {
        $summary = [
            'total_cost' => 0,
            'total_clicks' => 0,
            'total_impressions' => 0,
            'total_conversions' => 0,
            'avg_ctr' => 0,
            'avg_cpc' => 0,
            'avg_cpa' => 0,
            'row_count' => count($this->data)
        ];

        foreach ($this->data as $row) {
            $summary['total_cost'] += $row['Cost'] ?? 0;
            $summary['total_clicks'] += $row['Clicks'] ?? 0;
            $summary['total_impressions'] += $row['Impressions'] ?? 0;
            $summary['total_conversions'] += $row['Conversions'] ?? 0;
        }

        // Számított metrikák
        if ($summary['total_impressions'] > 0) {
            $summary['avg_ctr'] = round(($summary['total_clicks'] / $summary['total_impressions']) * 100, 2);
        }
        if ($summary['total_clicks'] > 0) {
            $summary['avg_cpc'] = round($summary['total_cost'] / $summary['total_clicks'], 0);
        }
        if ($summary['total_conversions'] > 0) {
            $summary['avg_cpa'] = round($summary['total_cost'] / $summary['total_conversions'], 0);
        }

        return $summary;
    }

    /**
     * Kampány elemzés
     */
    private function analyzeCampaigns(): array {
        $analysis = [
            'best_performers' => [],
            'worst_performers' => [],
            'issues' => []
        ];

        $withConversions = array_filter($this->data, fn($r) => ($r['Conversions'] ?? 0) > 0);
        
        // CPA szerint rendezés
        usort($withConversions, function($a, $b) {
            $cpaA = ($a['Conversions'] ?? 0) > 0 ? ($a['Cost'] ?? 0) / $a['Conversions'] : PHP_INT_MAX;
            $cpaB = ($b['Conversions'] ?? 0) > 0 ? ($b['Cost'] ?? 0) / $b['Conversions'] : PHP_INT_MAX;
            return $cpaA <=> $cpaB;
        });

        // Top 5 legjobb
        $analysis['best_performers'] = array_slice(array_map(function($row) {
            $cpa = ($row['Conversions'] ?? 0) > 0 ? round(($row['Cost'] ?? 0) / $row['Conversions'], 0) : 0;
            return [
                'name' => $row['Campaign'] ?? 'N/A',
                'cost' => $row['Cost'] ?? 0,
                'conversions' => $row['Conversions'] ?? 0,
                'cpa' => $cpa
            ];
        }, $withConversions), 0, 5);

        // Top 5 legrosszabb (van költés de nincs vagy kevés konverzió)
        $lowPerformers = array_filter($this->data, fn($r) => 
            ($r['Cost'] ?? 0) > 1000 && ($r['Conversions'] ?? 0) < 1
        );
        usort($lowPerformers, fn($a, $b) => ($b['Cost'] ?? 0) <=> ($a['Cost'] ?? 0));
        
        $analysis['worst_performers'] = array_slice(array_map(function($row) {
            return [
                'name' => $row['Campaign'] ?? 'N/A',
                'cost' => $row['Cost'] ?? 0,
                'clicks' => $row['Clicks'] ?? 0,
                'conversions' => $row['Conversions'] ?? 0,
                'issue' => 'Magas költés, nincs konverzió'
            ];
        }, $lowPerformers), 0, 5);

        return $analysis;
    }

    /**
     * Hirdetés elemzés
     */
    private function analyzeAds(): array {
        $analysis = [
            'low_ctr_ads' => [],
            'best_ads' => [],
            'issues' => []
        ];

        // Alacsony CTR hirdetések
        $lowCtr = array_filter($this->data, fn($r) => 
            ($r['Impressions'] ?? 0) > 100 && 
            ($r['CTR'] ?? (($r['Clicks'] ?? 0) / max(1, $r['Impressions'] ?? 1) * 100)) < 2
        );
        
        $analysis['low_ctr_ads'] = array_slice(array_map(function($row) {
            $ctr = $row['CTR'] ?? (($row['Clicks'] ?? 0) / max(1, $row['Impressions'] ?? 1) * 100);
            return [
                'campaign' => $row['Campaign'] ?? 'N/A',
                'ad_group' => $row['Ad group'] ?? 'N/A',
                'headline' => $row['Headline 1'] ?? 'N/A',
                'ctr' => round($ctr, 2),
                'impressions' => $row['Impressions'] ?? 0
            ];
        }, $lowCtr), 0, 10);

        // Legjobb CTR hirdetések
        $highCtr = array_filter($this->data, fn($r) => ($r['Impressions'] ?? 0) > 100);
        usort($highCtr, function($a, $b) {
            $ctrA = $a['CTR'] ?? (($a['Clicks'] ?? 0) / max(1, $a['Impressions'] ?? 1) * 100);
            $ctrB = $b['CTR'] ?? (($b['Clicks'] ?? 0) / max(1, $b['Impressions'] ?? 1) * 100);
            return $ctrB <=> $ctrA;
        });

        $analysis['best_ads'] = array_slice(array_map(function($row) {
            $ctr = $row['CTR'] ?? (($row['Clicks'] ?? 0) / max(1, $row['Impressions'] ?? 1) * 100);
            return [
                'campaign' => $row['Campaign'] ?? 'N/A',
                'headline' => $row['Headline 1'] ?? 'N/A',
                'ctr' => round($ctr, 2),
                'clicks' => $row['Clicks'] ?? 0
            ];
        }, $highCtr), 0, 5);

        return $analysis;
    }

    /**
     * Kulcsszó elemzés
     */
    private function analyzeKeywords(): array {
        $analysis = [
            'expensive_no_conversion' => [],
            'top_performers' => [],
            'suggested_negatives' => [],
            'match_type_analysis' => []
        ];

        // Drága, konverzió nélküli kulcsszavak
        $expensive = array_filter($this->data, fn($r) => 
            ($r['Cost'] ?? 0) > 2000 && ($r['Conversions'] ?? 0) < 1
        );
        usort($expensive, fn($a, $b) => ($b['Cost'] ?? 0) <=> ($a['Cost'] ?? 0));

        $analysis['expensive_no_conversion'] = array_slice(array_map(function($row) {
            return [
                'keyword' => $row['Keyword'] ?? 'N/A',
                'match_type' => $row['Match type'] ?? 'N/A',
                'cost' => $row['Cost'] ?? 0,
                'clicks' => $row['Clicks'] ?? 0,
                'action' => 'Pausáld vagy add negatívnak'
            ];
        }, $expensive), 0, 10);

        // Legjobb kulcsszavak
        $withConv = array_filter($this->data, fn($r) => ($r['Conversions'] ?? 0) > 0);
        usort($withConv, function($a, $b) {
            $cpaA = ($a['Cost'] ?? 0) / max(1, $a['Conversions'] ?? 1);
            $cpaB = ($b['Cost'] ?? 0) / max(1, $b['Conversions'] ?? 1);
            return $cpaA <=> $cpaB;
        });

        $analysis['top_performers'] = array_slice(array_map(function($row) {
            $cpa = round(($row['Cost'] ?? 0) / max(1, $row['Conversions'] ?? 1), 0);
            return [
                'keyword' => $row['Keyword'] ?? 'N/A',
                'conversions' => $row['Conversions'] ?? 0,
                'cpa' => $cpa,
                'cost' => $row['Cost'] ?? 0
            ];
        }, $withConv), 0, 10);

        // Match type elemzés
        $matchTypes = [];
        foreach ($this->data as $row) {
            $type = $row['Match type'] ?? 'Unknown';
            if (!isset($matchTypes[$type])) {
                $matchTypes[$type] = ['cost' => 0, 'conversions' => 0, 'clicks' => 0];
            }
            $matchTypes[$type]['cost'] += $row['Cost'] ?? 0;
            $matchTypes[$type]['conversions'] += $row['Conversions'] ?? 0;
            $matchTypes[$type]['clicks'] += $row['Clicks'] ?? 0;
        }

        foreach ($matchTypes as $type => $data) {
            $cpa = $data['conversions'] > 0 ? round($data['cost'] / $data['conversions'], 0) : null;
            $analysis['match_type_analysis'][] = [
                'type' => $type,
                'cost' => $data['cost'],
                'conversions' => $data['conversions'],
                'cpa' => $cpa
            ];
        }

        return $analysis;
    }

    /**
     * Keresési kifejezés elemzés
     */
    private function analyzeSearchTerms(): array {
        $analysis = [
            'wasted_spend' => [],
            'suggested_negatives' => [],
            'suggested_keywords' => []
        ];

        // Irreleváns kifejezések (kattintás de nincs konverzió)
        $wasted = array_filter($this->data, fn($r) => 
            ($r['Clicks'] ?? 0) >= 3 && ($r['Conversions'] ?? 0) < 1
        );
        usort($wasted, fn($a, $b) => ($b['Cost'] ?? 0) <=> ($a['Cost'] ?? 0));

        $analysis['wasted_spend'] = array_slice(array_map(function($row) {
            return [
                'search_term' => $row['Search term'] ?? 'N/A',
                'cost' => $row['Cost'] ?? 0,
                'clicks' => $row['Clicks'] ?? 0
            ];
        }, $wasted), 0, 20);

        // Javasolt negatív kulcsszavak (pattern alapján)
        $negativePatterns = ['állás', 'munka', 'fizetés', 'tanfolyam', 'ingyen', 'video', 'youtube'];
        $suggestedNegatives = [];
        
        foreach ($analysis['wasted_spend'] as $item) {
            $term = strtolower($item['search_term']);
            foreach ($negativePatterns as $pattern) {
                if (strpos($term, $pattern) !== false) {
                    $suggestedNegatives[] = $item['search_term'];
                    break;
                }
            }
        }
        $analysis['suggested_negatives'] = array_unique($suggestedNegatives);

        // Jó teljesítményű kifejezések -> új kulcsszó jelöltek
        $good = array_filter($this->data, fn($r) => ($r['Conversions'] ?? 0) >= 1);
        usort($good, function($a, $b) {
            $cpaA = ($a['Cost'] ?? 0) / max(1, $a['Conversions'] ?? 1);
            $cpaB = ($b['Cost'] ?? 0) / max(1, $b['Conversions'] ?? 1);
            return $cpaA <=> $cpaB;
        });

        $analysis['suggested_keywords'] = array_slice(array_map(function($row) {
            return [
                'search_term' => $row['Search term'] ?? 'N/A',
                'conversions' => $row['Conversions'] ?? 0,
                'cpa' => round(($row['Cost'] ?? 0) / max(1, $row['Conversions'] ?? 1), 0)
            ];
        }, $good), 0, 10);

        return $analysis;
    }

    /**
     * Ajánlások generálása
     */
    private function getRecommendations(array $analysis): array {
        $recommendations = [];
        $priority = 1;

        // Drága, nem konvertáló elemek
        if (!empty($analysis['expensive_no_conversion'] ?? $analysis['worst_performers'] ?? [])) {
            $items = $analysis['expensive_no_conversion'] ?? $analysis['worst_performers'];
            $totalWaste = array_sum(array_column($items, 'cost'));
            $recommendations[] = [
                'priority' => $priority++,
                'type' => 'critical',
                'title' => 'Pazarló költések',
                'description' => number_format($totalWaste, 0, ',', '.') . ' Ft-ot költöttél konverzió nélkül.',
                'action' => 'Pausáld vagy optimalizáld ezeket az elemeket.',
                'items' => array_slice($items, 0, 5)
            ];
        }

        // Alacsony CTR
        if (!empty($analysis['low_ctr_ads'])) {
            $recommendations[] = [
                'priority' => $priority++,
                'type' => 'warning',
                'title' => 'Alacsony CTR hirdetések',
                'description' => count($analysis['low_ctr_ads']) . ' hirdetés CTR-je 2% alatt van.',
                'action' => 'Írd át a headline-okat, próbálj erősebb CTA-t.',
                'items' => array_slice($analysis['low_ctr_ads'], 0, 5)
            ];
        }

        // Negatív kulcsszó javaslatok
        if (!empty($analysis['suggested_negatives'])) {
            $recommendations[] = [
                'priority' => $priority++,
                'type' => 'info',
                'title' => 'Javasolt negatív kulcsszavak',
                'description' => count($analysis['suggested_negatives']) . ' kifejezés negatívnak javasolt.',
                'action' => 'Add hozzá negatív kulcsszóként.',
                'items' => $analysis['suggested_negatives']
            ];
        }

        // Match type optimalizálás
        if (!empty($analysis['match_type_analysis'])) {
            $broad = array_filter($analysis['match_type_analysis'], fn($m) => 
                stripos($m['type'], 'broad') !== false && $m['cpa'] !== null
            );
            if (!empty($broad)) {
                $broadCpa = array_column($broad, 'cpa')[0] ?? 0;
                $phraseCpa = array_column(array_filter($analysis['match_type_analysis'], 
                    fn($m) => stripos($m['type'], 'phrase') !== false), 'cpa')[0] ?? 0;
                
                if ($broadCpa > $phraseCpa * 1.3) {
                    $recommendations[] = [
                        'priority' => $priority++,
                        'type' => 'warning',
                        'title' => 'Broad match túl drága',
                        'description' => "Broad match CPA ({$broadCpa} Ft) sokkal magasabb mint Phrase ({$phraseCpa} Ft).",
                        'action' => 'Fontold meg a Phrase match-re váltást.'
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Getter az adatokhoz
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Getter a report type-hoz
     */
    public function getReportType(): string {
        return $this->reportType;
    }
}
