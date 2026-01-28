<?php
/**
 * AdMaster Pro - Client Manager
 * Ügyfelek, bevált szövegek és kampány előzmények kezelése
 */

class ClientManager {
    private string $dataDir;
    private string $clientsFile;
    private string $headlinesFile;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/';
        $this->clientsFile = $this->dataDir . 'clients.json';
        $this->headlinesFile = $this->dataDir . 'headlines.json';
        
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
    
    // Kampány anyag mentése ügyfélhez
    public function saveCampaignToClient(string $clientId, array $campaign): bool {
        $clients = $this->getClients();
        if (!isset($clients[$clientId])) return false;
        
        $campaign['date'] = date('Y-m-d H:i:s');
        $clients[$clientId]['campaigns'][] = $campaign;
        $clients[$clientId]['updated_at'] = date('Y-m-d H:i:s');
        
        file_put_contents($this->clientsFile, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
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
    
    public function deleteDescription(string $id): bool {
        $bank = $this->getHeadlinesBank();
        $bank['descriptions'] = array_filter($bank['descriptions'], fn($d) => $d['id'] !== $id);
        $bank['descriptions'] = array_values($bank['descriptions']);
        return file_put_contents($this->headlinesFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function updateHeadlineRating(string $id, int $rating): bool {
        $bank = $this->getHeadlinesBank();
        foreach ($bank['headlines'] as &$h) {
            if ($h['id'] === $id) {
                $h['rating'] = $rating;
                break;
            }
        }
        return file_put_contents($this->headlinesFile, json_encode($bank, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    // Bevált szövegek lekérése iparág szerint
    public function getHeadlinesForIndustry(string $industry = ''): array {
        $bank = $this->getHeadlinesBank();
        if (empty($industry)) return $bank['headlines'];
        
        return array_filter($bank['headlines'], fn($h) => empty($h['industry']) || $h['industry'] === $industry);
    }
    
    public function getDescriptionsForIndustry(string $industry = ''): array {
        $bank = $this->getHeadlinesBank();
        if (empty($industry)) return $bank['descriptions'];
        
        return array_filter($bank['descriptions'], fn($d) => empty($d['industry']) || $d['industry'] === $industry);
    }
}
