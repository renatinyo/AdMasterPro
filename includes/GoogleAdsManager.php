<?php
/**
 * AdMaster Pro - Google Ads API Integration
 * 
 * Funkciók:
 * - OAuth2 authentikáció
 * - Több fiók kezelés (MCC)
 * - Kampányok, Ad Group-ok, Hirdetések, Kulcsszavak lekérése
 * - RSA hirdetések feltöltése
 * - Teljes fiók szinkronizálás
 */

class GoogleAdsManager {
    private string $developerToken;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private ?string $accessToken = null;
    private string $apiVersion = 'v15';
    private string $baseUrl = 'https://googleads.googleapis.com';
    private ?string $loginCustomerId = null; // MCC fiók ID
    
    public function __construct() {
        $this->developerToken = defined('GOOGLE_ADS_DEVELOPER_TOKEN') ? GOOGLE_ADS_DEVELOPER_TOKEN : '';
        $this->clientId = defined('GOOGLE_ADS_CLIENT_ID') ? GOOGLE_ADS_CLIENT_ID : '';
        $this->clientSecret = defined('GOOGLE_ADS_CLIENT_SECRET') ? GOOGLE_ADS_CLIENT_SECRET : '';
        $this->refreshToken = defined('GOOGLE_ADS_REFRESH_TOKEN') ? GOOGLE_ADS_REFRESH_TOKEN : '';
        $this->loginCustomerId = defined('GOOGLE_ADS_LOGIN_CUSTOMER_ID') ? GOOGLE_ADS_LOGIN_CUSTOMER_ID : null;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
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
            return ['error' => 'Nem sikerült authentikálni'];
        }
        
        // Customer ID tisztítása (kötőjelek eltávolítása)
        $customerId = str_replace('-', '', $customerId);
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/googleAds:searchStream";
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $this->developerToken
        ];
        
        // Ha van MCC login customer ID, használjuk
        if ($this->loginCustomerId) {
            $headers[] = 'login-customer-id: ' . str_replace('-', '', $this->loginCustomerId);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return [
            'error' => "HTTP $httpCode: " . ($error ?: $response),
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Mutáció végrehajtása (létrehozás, módosítás)
     */
    private function mutate(string $customerId, string $operation, array $data): ?array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['error' => 'Nem sikerült authentikálni'];
        }
        
        $customerId = str_replace('-', '', $customerId);
        $url = "{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/{$operation}:mutate";
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $this->developerToken
        ];
        
        if ($this->loginCustomerId) {
            $headers[] = 'login-customer-id: ' . str_replace('-', '', $this->loginCustomerId);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    // ========================================
    // FIÓKOK KEZELÉSE
    // ========================================
    
    /**
     * Elérhető fiókok listázása (MCC alatt lévő összes fiók)
     */
    public function getAccessibleAccounts(): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['error' => 'Nem sikerült authentikálni'];
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}/customers:listAccessibleCustomers";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $this->developerToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $resourceNames = $data['resourceNames'] ?? [];
            
            // Customer ID-k kinyerése
            $accounts = [];
            foreach ($resourceNames as $name) {
                // "customers/1234567890" -> "1234567890"
                $id = str_replace('customers/', '', $name);
                $accounts[] = $id;
            }
            
            return $accounts;
        }
        
        return ['error' => "HTTP $httpCode: $response"];
    }
    
    /**
     * Fiók részletes adatai
     */
    public function getAccountInfo(string $customerId): ?array {
        $query = "SELECT 
            customer.id,
            customer.descriptive_name,
            customer.currency_code,
            customer.time_zone,
            customer.manager
        FROM customer
        LIMIT 1";
        
        $result = $this->apiRequest($customerId, $query);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if ($result && isset($result[0]['results'][0])) {
            $customer = $result[0]['results'][0]['customer'];
            return [
                'id' => $customer['id'],
                'name' => $customer['descriptiveName'] ?? 'Névtelen fiók',
                'currency' => $customer['currencyCode'] ?? 'HUF',
                'timezone' => $customer['timeZone'] ?? 'Europe/Budapest',
                'is_manager' => $customer['manager'] ?? false
            ];
        }
        
        return null;
    }
    
    /**
     * Összes elérhető fiók részletes adatai
     */
    public function getAllAccountsWithDetails(): array {
        $accountIds = $this->getAccessibleAccounts();
        
        if (isset($accountIds['error'])) {
            return $accountIds;
        }
        
        $accounts = [];
        foreach ($accountIds as $id) {
            $info = $this->getAccountInfo($id);
            if ($info && !isset($info['error'])) {
                // Csak nem-manager fiókokat listázzuk (tényleges hirdetési fiókok)
                if (!($info['is_manager'] ?? false)) {
                    $accounts[] = $info;
                }
            }
        }
        
        return $accounts;
    }
    
    // ========================================
    // KAMPÁNYOK
    // ========================================
    
    /**
     * Kampányok listázása metrikákkal
     */
    public function getCampaigns(string $customerId, bool $withMetrics = false): array {
        $metricsFields = $withMetrics 
            ? ", metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.ctr, metrics.average_cpc"
            : "";
        
        $dateFilter = $withMetrics 
            ? " AND segments.date DURING LAST_30_DAYS" 
            : "";
        
        $query = "SELECT 
            campaign.id, 
            campaign.name, 
            campaign.status, 
            campaign.advertising_channel_type,
            campaign.bidding_strategy_type,
            campaign_budget.amount_micros
            $metricsFields
        FROM campaign 
        WHERE campaign.status != 'REMOVED'
        $dateFilter
        ORDER BY campaign.name";
        
        $result = $this->apiRequest($customerId, $query);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $campaigns = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $campaign = [
                    'id' => $row['campaign']['id'],
                    'name' => $row['campaign']['name'],
                    'status' => $row['campaign']['status'],
                    'type' => $row['campaign']['advertisingChannelType'] ?? 'UNKNOWN',
                    'bidding_strategy' => $row['campaign']['biddingStrategyType'] ?? 'UNKNOWN',
                    'daily_budget' => isset($row['campaignBudget']['amountMicros']) 
                        ? round($row['campaignBudget']['amountMicros'] / 1000000, 0) 
                        : 0
                ];
                
                if ($withMetrics && isset($row['metrics'])) {
                    $campaign['metrics'] = [
                        'impressions' => $row['metrics']['impressions'] ?? 0,
                        'clicks' => $row['metrics']['clicks'] ?? 0,
                        'cost' => isset($row['metrics']['costMicros']) 
                            ? round($row['metrics']['costMicros'] / 1000000, 0) 
                            : 0,
                        'conversions' => $row['metrics']['conversions'] ?? 0,
                        'ctr' => round(($row['metrics']['ctr'] ?? 0) * 100, 2),
                        'avg_cpc' => isset($row['metrics']['averageCpc']) 
                            ? round($row['metrics']['averageCpc'] / 1000000, 0) 
                            : 0
                    ];
                }
                
                $campaigns[] = $campaign;
            }
        }
        
        return $campaigns;
    }
    
    // ========================================
    // AD GROUPS
    // ========================================
    
    /**
     * Ad Group-ok listázása egy kampányban
     */
    public function getAdGroups(string $customerId, string $campaignId = null, bool $withMetrics = false): array {
        $metricsFields = $withMetrics 
            ? ", metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions"
            : "";
        
        $campaignFilter = $campaignId 
            ? "AND campaign.id = $campaignId" 
            : "";
        
        $dateFilter = $withMetrics 
            ? "AND segments.date DURING LAST_30_DAYS" 
            : "";
        
        $query = "SELECT 
            ad_group.id, 
            ad_group.name, 
            ad_group.status,
            ad_group.type,
            campaign.id,
            campaign.name
            $metricsFields
        FROM ad_group 
        WHERE ad_group.status != 'REMOVED'
        $campaignFilter
        $dateFilter
        ORDER BY ad_group.name";
        
        $result = $this->apiRequest($customerId, $query);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $adGroups = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $adGroup = [
                    'id' => $row['adGroup']['id'],
                    'name' => $row['adGroup']['name'],
                    'status' => $row['adGroup']['status'],
                    'type' => $row['adGroup']['type'] ?? 'UNKNOWN',
                    'campaign_id' => $row['campaign']['id'],
                    'campaign_name' => $row['campaign']['name']
                ];
                
                if ($withMetrics && isset($row['metrics'])) {
                    $adGroup['metrics'] = [
                        'impressions' => $row['metrics']['impressions'] ?? 0,
                        'clicks' => $row['metrics']['clicks'] ?? 0,
                        'cost' => isset($row['metrics']['costMicros']) 
                            ? round($row['metrics']['costMicros'] / 1000000, 0) 
                            : 0,
                        'conversions' => $row['metrics']['conversions'] ?? 0
                    ];
                }
                
                $adGroups[] = $adGroup;
            }
        }
        
        return $adGroups;
    }
    
    // ========================================
    // HIRDETÉSEK (ADS)
    // ========================================
    
    /**
     * Hirdetések lekérése (RSA és egyéb)
     */
    public function getAds(string $customerId, string $adGroupId = null, bool $withMetrics = false): array {
        $metricsFields = $withMetrics 
            ? ", metrics.impressions, metrics.clicks, metrics.conversions, metrics.ctr"
            : "";
        
        $adGroupFilter = $adGroupId 
            ? "AND ad_group.id = $adGroupId" 
            : "";
        
        $dateFilter = $withMetrics 
            ? "AND segments.date DURING LAST_30_DAYS" 
            : "";
        
        $query = "SELECT 
            ad_group_ad.ad.id,
            ad_group_ad.ad.type,
            ad_group_ad.ad.final_urls,
            ad_group_ad.ad.responsive_search_ad.headlines,
            ad_group_ad.ad.responsive_search_ad.descriptions,
            ad_group_ad.ad.responsive_search_ad.path1,
            ad_group_ad.ad.responsive_search_ad.path2,
            ad_group_ad.status,
            ad_group_ad.ad.expanded_text_ad.headline_part1,
            ad_group_ad.ad.expanded_text_ad.headline_part2,
            ad_group_ad.ad.expanded_text_ad.description,
            ad_group_ad.ad.call_ad.headline1,
            ad_group_ad.ad.call_ad.headline2,
            ad_group_ad.ad.call_ad.description1,
            ad_group_ad.ad.call_ad.description2,
            ad_group_ad.ad.call_ad.phone_number,
            ad_group.id,
            ad_group.name,
            campaign.id,
            campaign.name
            $metricsFields
        FROM ad_group_ad 
        WHERE ad_group_ad.status != 'REMOVED'
        $adGroupFilter
        $dateFilter
        ORDER BY ad_group.name";
        
        $result = $this->apiRequest($customerId, $query);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $ads = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $ad = $row['adGroupAd']['ad'];
                $adData = [
                    'id' => $ad['id'],
                    'type' => $ad['type'] ?? 'UNKNOWN',
                    'status' => $row['adGroupAd']['status'],
                    'final_urls' => $ad['finalUrls'] ?? [],
                    'ad_group_id' => $row['adGroup']['id'],
                    'ad_group_name' => $row['adGroup']['name'],
                    'campaign_id' => $row['campaign']['id'],
                    'campaign_name' => $row['campaign']['name']
                ];
                
                // RSA adatok
                if (isset($ad['responsiveSearchAd'])) {
                    $rsa = $ad['responsiveSearchAd'];
                    $adData['headlines'] = array_map(fn($h) => $h['text'] ?? '', $rsa['headlines'] ?? []);
                    $adData['descriptions'] = array_map(fn($d) => $d['text'] ?? '', $rsa['descriptions'] ?? []);
                    $adData['path1'] = $rsa['path1'] ?? '';
                    $adData['path2'] = $rsa['path2'] ?? '';
                }
                
                // ETA adatok (legacy)
                if (isset($ad['expandedTextAd'])) {
                    $eta = $ad['expandedTextAd'];
                    $adData['headlines'] = array_filter([
                        $eta['headlinePart1'] ?? '',
                        $eta['headlinePart2'] ?? '',
                        $eta['headlinePart3'] ?? ''
                    ]);
                    $adData['descriptions'] = array_filter([
                        $eta['description'] ?? '',
                        $eta['description2'] ?? ''
                    ]);
                }
                
                // Call-Only adatok
                if (isset($ad['callAd'])) {
                    $call = $ad['callAd'];
                    $adData['headlines'] = array_filter([
                        $call['headline1'] ?? '',
                        $call['headline2'] ?? ''
                    ]);
                    $adData['descriptions'] = array_filter([
                        $call['description1'] ?? '',
                        $call['description2'] ?? ''
                    ]);
                    $adData['phone_number'] = $call['phoneNumber'] ?? '';
                }
                
                if ($withMetrics && isset($row['metrics'])) {
                    $adData['metrics'] = [
                        'impressions' => $row['metrics']['impressions'] ?? 0,
                        'clicks' => $row['metrics']['clicks'] ?? 0,
                        'conversions' => $row['metrics']['conversions'] ?? 0,
                        'ctr' => round(($row['metrics']['ctr'] ?? 0) * 100, 2)
                    ];
                }
                
                $ads[] = $adData;
            }
        }
        
        return $ads;
    }
    
    // ========================================
    // KULCSSZAVAK
    // ========================================
    
    /**
     * Kulcsszavak lekérése
     */
    public function getKeywords(string $customerId, string $adGroupId = null, bool $withMetrics = false): array {
        $metricsFields = $withMetrics 
            ? ", metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.ctr, metrics.average_cpc"
            : "";
        
        $adGroupFilter = $adGroupId 
            ? "AND ad_group.id = $adGroupId" 
            : "";
        
        $dateFilter = $withMetrics 
            ? "AND segments.date DURING LAST_30_DAYS" 
            : "";
        
        $query = "SELECT 
            ad_group_criterion.criterion_id,
            ad_group_criterion.keyword.text,
            ad_group_criterion.keyword.match_type,
            ad_group_criterion.status,
            ad_group_criterion.quality_info.quality_score,
            ad_group.id,
            ad_group.name,
            campaign.id,
            campaign.name
            $metricsFields
        FROM ad_group_criterion 
        WHERE ad_group_criterion.type = 'KEYWORD'
        AND ad_group_criterion.status != 'REMOVED'
        $adGroupFilter
        $dateFilter
        ORDER BY ad_group_criterion.keyword.text";
        
        $result = $this->apiRequest($customerId, $query);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $keywords = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $criterion = $row['adGroupCriterion'];
                $keyword = [
                    'id' => $criterion['criterionId'],
                    'text' => $criterion['keyword']['text'] ?? '',
                    'match_type' => $criterion['keyword']['matchType'] ?? 'UNKNOWN',
                    'status' => $criterion['status'],
                    'quality_score' => $criterion['qualityInfo']['qualityScore'] ?? null,
                    'ad_group_id' => $row['adGroup']['id'],
                    'ad_group_name' => $row['adGroup']['name'],
                    'campaign_id' => $row['campaign']['id'],
                    'campaign_name' => $row['campaign']['name']
                ];
                
                if ($withMetrics && isset($row['metrics'])) {
                    $keyword['metrics'] = [
                        'impressions' => $row['metrics']['impressions'] ?? 0,
                        'clicks' => $row['metrics']['clicks'] ?? 0,
                        'cost' => isset($row['metrics']['costMicros']) 
                            ? round($row['metrics']['costMicros'] / 1000000, 0) 
                            : 0,
                        'conversions' => $row['metrics']['conversions'] ?? 0,
                        'ctr' => round(($row['metrics']['ctr'] ?? 0) * 100, 2),
                        'avg_cpc' => isset($row['metrics']['averageCpc']) 
                            ? round($row['metrics']['averageCpc'] / 1000000, 0) 
                            : 0
                    ];
                }
                
                $keywords[] = $keyword;
            }
        }
        
        return $keywords;
    }
    
    /**
     * Negatív kulcsszavak lekérése (kampány szinten)
     */
    public function getNegativeKeywords(string $customerId, string $campaignId = null): array {
        $campaignFilter = $campaignId 
            ? "AND campaign.id = $campaignId" 
            : "";
        
        $query = "SELECT 
            campaign_criterion.criterion_id,
            campaign_criterion.keyword.text,
            campaign_criterion.keyword.match_type,
            campaign.id,
            campaign.name
        FROM campaign_criterion 
        WHERE campaign_criterion.type = 'KEYWORD'
        AND campaign_criterion.negative = true
        $campaignFilter
        ORDER BY campaign_criterion.keyword.text";
        
        $result = $this->apiRequest($customerId, $query);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $keywords = [];
        if ($result && isset($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $keywords[] = [
                    'id' => $row['campaignCriterion']['criterionId'],
                    'text' => $row['campaignCriterion']['keyword']['text'] ?? '',
                    'match_type' => $row['campaignCriterion']['keyword']['matchType'] ?? 'UNKNOWN',
                    'campaign_id' => $row['campaign']['id'],
                    'campaign_name' => $row['campaign']['name']
                ];
            }
        }
        
        return $keywords;
    }
    
    // ========================================
    // TELJES FIÓK SYNC
    // ========================================
    
    /**
     * Teljes fiók adatok lekérése egyben
     */
    public function syncFullAccount(string $customerId, bool $withMetrics = true): array {
        $accountInfo = $this->getAccountInfo($customerId);
        
        if (isset($accountInfo['error'])) {
            return $accountInfo;
        }
        
        return [
            'account' => $accountInfo,
            'campaigns' => $this->getCampaigns($customerId, $withMetrics),
            'ad_groups' => $this->getAdGroups($customerId, null, $withMetrics),
            'ads' => $this->getAds($customerId, null, $withMetrics),
            'keywords' => $this->getKeywords($customerId, null, $withMetrics),
            'negative_keywords' => $this->getNegativeKeywords($customerId),
            'synced_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Több fiók szinkronizálása
     */
    public function syncMultipleAccounts(array $customerIds = null, bool $withMetrics = true): array {
        // Ha nincs megadva, összes elérhető fiók
        if ($customerIds === null) {
            $customerIds = $this->getAccessibleAccounts();
            if (isset($customerIds['error'])) {
                return $customerIds;
            }
        }
        
        $results = [];
        foreach ($customerIds as $customerId) {
            $results[$customerId] = $this->syncFullAccount($customerId, $withMetrics);
        }
        
        return $results;
    }
    
    // ========================================
    // LÉTREHOZÁS / MÓDOSÍTÁS
    // ========================================
    
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
        $customerId = str_replace('-', '', $customerId);
        
        // Headlines formázása (max 15)
        $headlineAssets = [];
        foreach (array_slice($headlines, 0, 15) as $i => $headline) {
            $asset = ['text' => mb_substr($headline, 0, 30)];
            // Első 3 pinelve pozícióhoz
            if ($i < 3) {
                $asset['pinnedField'] = 'HEADLINE_' . ($i + 1);
            }
            $headlineAssets[] = $asset;
        }
        
        // Descriptions formázása (max 4)
        $descriptionAssets = [];
        foreach (array_slice($descriptions, 0, 4) as $i => $desc) {
            $asset = ['text' => mb_substr($desc, 0, 90)];
            if ($i < 2) {
                $asset['pinnedField'] = 'DESCRIPTION_' . ($i + 1);
            }
            $descriptionAssets[] = $asset;
        }
        
        $adGroupResourceName = "customers/{$customerId}/adGroups/{$adGroupId}";
        
        $operations = [
            'operations' => [
                [
                    'create' => [
                        'adGroup' => $adGroupResourceName,
                        'status' => 'PAUSED',
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
        $customerId = str_replace('-', '', $customerId);
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
        $customerId = str_replace('-', '', $customerId);
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
    
    // ========================================
    // OAUTH HELPERS
    // ========================================
    
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
