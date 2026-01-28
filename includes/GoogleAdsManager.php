<?php
/**
 * AdMaster Pro - Google Ads API Integration
 * 
 * Funkciók:
 * - OAuth2 authentikáció
 * - Kampányok listázása
 * - Ad Group-ok kezelése
 * - RSA hirdetések feltöltése
 * - Kulcsszavak hozzáadása
 */

class GoogleAdsManager {
    private string $developerToken;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private ?string $accessToken = null;
    private string $apiVersion = 'v15';
    private string $baseUrl = 'https://googleads.googleapis.com';
    
    public function __construct() {
        $this->developerToken = GOOGLE_ADS_DEVELOPER_TOKEN;
        $this->clientId = GOOGLE_ADS_CLIENT_ID;
        $this->clientSecret = GOOGLE_ADS_CLIENT_SECRET;
        $this->refreshToken = GOOGLE_ADS_REFRESH_TOKEN;
    }
    
    /**
     * Ellenőrzi, hogy a konfiguráció teljes-e
     */
    public function isConfigured(): bool {
        return !empty($this->developerToken) 
            && !empty($this->clientId) 
            && !empty($this->clientSecret)
            && !empty($this->refreshToken);
    }
    
    /**
     * OAuth2 Access Token lekérése
     */
    public function getAccessToken(): ?string {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        $url = 'https://oauth2.googleapis.com/token';
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $this->accessToken = $result['access_token'] ?? null;
            return $this->accessToken;
        }
        
        return null;
    }
    
    /**
     * API kérés küldése
     */
    private function apiRequest(string $customerId, string $query): ?array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/googleAds:searchStream";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $this->developerToken,
            'login-customer-id: ' . $customerId
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Mutáció végrehajtása (létrehozás, módosítás)
     */
    private function mutate(string $customerId, string $operation, array $data): ?array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['error' => 'Nem sikerült authentikálni'];
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/{$operation}:mutate";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $this->developerToken,
            'login-customer-id: ' . $customerId
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    /**
     * Elérhető fiókok listázása
     */
    public function getAccessibleAccounts(): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [];
        }
        
        // Ezt MCC fiókkal kell hívni
        $url = "{$this->baseUrl}/{$this->apiVersion}/customers:listAccessibleCustomers";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $this->developerToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['resourceNames'] ?? [];
        }
        
        return [];
    }
    
    /**
     * Kampányok listázása
     */
    public function getCampaigns(string $customerId): array {
        $query = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type 
                  FROM campaign 
                  WHERE campaign.status != 'REMOVED'
                  ORDER BY campaign.name";
        
        $result = $this->apiRequest($customerId, $query);
        
        $campaigns = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $campaigns[] = [
                    'id' => $row['campaign']['id'],
                    'name' => $row['campaign']['name'],
                    'status' => $row['campaign']['status'],
                    'type' => $row['campaign']['advertisingChannelType']
                ];
            }
        }
        
        return $campaigns;
    }
    
    /**
     * Ad Group-ok listázása egy kampányban
     */
    public function getAdGroups(string $customerId, string $campaignId): array {
        $query = "SELECT ad_group.id, ad_group.name, ad_group.status 
                  FROM ad_group 
                  WHERE campaign.id = $campaignId
                  AND ad_group.status != 'REMOVED'
                  ORDER BY ad_group.name";
        
        $result = $this->apiRequest($customerId, $query);
        
        $adGroups = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $adGroups[] = [
                    'id' => $row['adGroup']['id'],
                    'name' => $row['adGroup']['name'],
                    'status' => $row['adGroup']['status']
                ];
            }
        }
        
        return $adGroups;
    }
    
    /**
     * RSA hirdetés létrehozása
     */
    public function createResponsiveSearchAd(
        string $customerId,
        string $adGroupId,
        array $headlines,
        array $descriptions,
        string $finalUrl,
        string $path1 = '',
        string $path2 = ''
    ): array {
        // Headlines formázása (max 15)
        $headlineAssets = [];
        foreach (array_slice($headlines, 0, 15) as $i => $headline) {
            $headlineAssets[] = [
                'text' => mb_substr($headline, 0, 30),
                'pinnedField' => $i < 3 ? 'HEADLINE_' . ($i + 1) : null // Első 3 pinelve
            ];
        }
        
        // Descriptions formázása (max 4)
        $descriptionAssets = [];
        foreach (array_slice($descriptions, 0, 4) as $i => $desc) {
            $descriptionAssets[] = [
                'text' => mb_substr($desc, 0, 90),
                'pinnedField' => $i < 2 ? 'DESCRIPTION_' . ($i + 1) : null
            ];
        }
        
        $adGroupResourceName = "customers/{$customerId}/adGroups/{$adGroupId}";
        
        $operations = [
            'operations' => [
                [
                    'create' => [
                        'adGroup' => $adGroupResourceName,
                        'status' => 'PAUSED', // Biztonsági okból pauseolva
                        'ad' => [
                            'responsiveSearchAd' => [
                                'headlines' => $headlineAssets,
                                'descriptions' => $descriptionAssets,
                                'path1' => $path1,
                                'path2' => $path2
                            ],
                            'finalUrls' => [$finalUrl]
                        ]
                    ]
                ]
            ]
        ];
        
        return $this->mutate($customerId, 'adGroupAds', $operations);
    }
    
    /**
     * Kulcsszavak hozzáadása
     */
    public function addKeywords(
        string $customerId,
        string $adGroupId,
        array $keywords,
        string $matchType = 'PHRASE'
    ): array {
        $adGroupResourceName = "customers/{$customerId}/adGroups/{$adGroupId}";
        
        $operations = ['operations' => []];
        
        foreach ($keywords as $keyword) {
            $operations['operations'][] = [
                'create' => [
                    'adGroup' => $adGroupResourceName,
                    'status' => 'ENABLED',
                    'keyword' => [
                        'text' => $keyword,
                        'matchType' => $matchType
                    ]
                ]
            ];
        }
        
        return $this->mutate($customerId, 'adGroupCriteria', $operations);
    }
    
    /**
     * Negatív kulcsszavak hozzáadása kampány szinten
     */
    public function addNegativeKeywords(
        string $customerId,
        string $campaignId,
        array $keywords
    ): array {
        $campaignResourceName = "customers/{$customerId}/campaigns/{$campaignId}";
        
        $operations = ['operations' => []];
        
        foreach ($keywords as $keyword) {
            $operations['operations'][] = [
                'create' => [
                    'campaign' => $campaignResourceName,
                    'negative' => true,
                    'keyword' => [
                        'text' => $keyword,
                        'matchType' => 'PHRASE'
                    ]
                ]
            ];
        }
        
        return $this->mutate($customerId, 'campaignCriteria', $operations);
    }
    
    /**
     * OAuth2 URL generálása (első beállításhoz)
     */
    public static function getOAuthUrl(string $clientId, string $redirectUri): string {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    
    /**
     * Authorization code cseréje tokenekre
     */
    public static function exchangeCodeForTokens(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): ?array {
        $url = 'https://oauth2.googleapis.com/token';
        $data = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
