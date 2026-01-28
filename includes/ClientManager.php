<?php
/**
 * AdMaster Pro - Client Manager
 * Ügyfelek, bevált szövegek, kulcsszavak kezelése
 */

class ClientManager {
    private string $dataDir;
    private string $clientsFile;
    private string $headlinesFile;
    private string $keywordsFile;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/';
        $this->clientsFile = $this->dataDir . 'clients.json';
        $this->headlinesFile = $this->dataDir . 'headlines.json';
        $this->keywordsFile = $this->dataDir . 'keywords.json';
        
        // Fájlok létrehozása ha nem léteznek
        if (!file_exists($this->clientsFile)) {
            file_put_contents($this->clientsFile, json_encode([], JSON_PRETTY_PRINT));
        }
        if (!file_exists($this->headlinesFile)) {
            file_put_contents($this->headlinesFile, json_encode([
                'headlines' => [],
                'descriptions' => []
            ], JSON_PRETTY_PRINT));
        }
        if (!file_exists($this->keywordsFile)) {
            file_put_contents($this->keywordsFile, json_encode([
                'positive' => [],
                'negative' => []
            ], JSON_PRETTY_PRINT));
        }
    }
    
    // === ÜGYFELEK ===
    
    public function getClients(): array {
        $data = file_get_contents($this->clientsFile);
        return json_decode($data, true) ?: [];
    }
    
    public function getClient(string $id): ?array {
        $clients = $this->getClients();
        return $clients[$id] ?? null;
    }
    
    public function saveClient(array $client): string {
        $clients = $this->getClients();
        
        $id = $client['id'] ?? uniqid('client_');
        $client['id'] = $id;
        $client['updated_at'] = date('Y-m-d H:i:s');
        
        if (!isset($client['created_at'])) {
            $client['created_at'] = date('Y-m-d H:i:s');
        }
        
        $clients[$id] = $client;
        file_put_contents($this->clientsFile, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $id;
    }
    
    public function deleteClient(string $id): bool {
        $clients = $this->getClients();
        if (isset($clients[$id])) {
            unset($clients[$id]);
            file_put_contents($this->clientsFile, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return true;
        }
        return false;
    }
    
    // === BEVÁLT SZÖVEGEK ===
    
    public function getHeadlinesBank(): array {
        $data = file_get_contents($this->headlinesFile);
        return json_decode($data, true) ?: ['headlines' => [], 'descriptions' => []];
    }
    
    public function addHeadline(string $text, int $rating = 3, string $industry = ''): bool {
        $bank = $this->getHeadlinesBank();
        $bank['headlines'][] = [
            'id' => uniqid('hl_'),
            'text' => $text,
            'rating' => $rating,
            'industry' => $industry,
            'created_at' => date('Y-m-d H:i:s')
        ];
        return file_put_contents($this->headlinesFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function addDescription(string $text, int $rating = 3, string $industry = ''): bool {
        $bank = $this->getHeadlinesBank();
        $bank['descriptions'][] = [
            'id' => uniqid('desc_'),
            'text' => $text,
            'rating' => $rating,
            'industry' => $industry,
            'created_at' => date('Y-m-d H:i:s')
        ];
        return file_put_contents($this->headlinesFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function deleteHeadline(string $id): bool {
        $bank = $this->getHeadlinesBank();
        $bank['headlines'] = array_filter($bank['headlines'], fn($h) => $h['id'] !== $id);
        $bank['headlines'] = array_values($bank['headlines']);
        return file_put_contents($this->headlinesFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function getHeadlinesForIndustry(string $industry = ''): array {
        $bank = $this->getHeadlinesBank();
        if (empty($industry)) return $bank['headlines'];
        return array_filter($bank['headlines'], fn($h) => empty($h['industry']) || $h['industry'] === $industry);
    }
    
    // === KULCSSZAVAK ===
    
    public function getKeywordsBank(): array {
        $data = file_get_contents($this->keywordsFile);
        return json_decode($data, true) ?: ['positive' => [], 'negative' => []];
    }
    
    public function addKeywords(array $keywords, string $type = 'positive', string $industry = ''): int {
        $bank = $this->getKeywordsBank();
        $added = 0;
        
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (empty($kw)) continue;
            
            // Duplikáció ellenőrzés
            $exists = false;
            foreach ($bank[$type] as $existing) {
                if (strtolower($existing['keyword']) === strtolower($kw)) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $bank[$type][] = [
                    'id' => uniqid('kw_'),
                    'keyword' => $kw,
                    'industry' => $industry,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $added++;
            }
        }
        
        file_put_contents($this->keywordsFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $added;
    }
    
    public function deleteKeyword(string $id, string $type = 'positive'): bool {
        $bank = $this->getKeywordsBank();
        $bank[$type] = array_filter($bank[$type], fn($k) => $k['id'] !== $id);
        $bank[$type] = array_values($bank[$type]);
        return file_put_contents($this->keywordsFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function getKeywordsForIndustry(string $industry = '', string $type = 'positive'): array {
        $bank = $this->getKeywordsBank();
        if (empty($industry)) return $bank[$type];
        return array_filter($bank[$type], fn($k) => empty($k['industry']) || $k['industry'] === $industry);
    }
    
    public function getAllKeywordsText(string $type = 'positive'): string {
        $bank = $this->getKeywordsBank();
        return implode("\n", array_map(fn($k) => $k['keyword'], $bank[$type]));
    }
    
    // Kulcsszó ütközés ellenőrzés
    public function checkKeywordConflicts(array $keywords): array {
        $bank = $this->getKeywordsBank();
        $conflicts = [];
        
        foreach ($keywords as $kw) {
            $kw = trim(strtolower($kw));
            if (empty($kw)) continue;
            
            foreach ($bank['negative'] as $neg) {
                $negKw = strtolower($neg['keyword']);
                // Pontos egyezés vagy részleges egyezés
                if ($kw === $negKw || strpos($kw, $negKw) !== false || strpos($negKw, $kw) !== false) {
                    $conflicts[] = [
                        'keyword' => $kw,
                        'negative' => $neg['keyword'],
                        'type' => $kw === $negKw ? 'exact' : 'partial'
                    ];
                }
            }
        }
        
        return $conflicts;
    }
}
