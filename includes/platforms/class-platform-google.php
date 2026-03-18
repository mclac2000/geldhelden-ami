<?php
defined('ABSPATH') || exit;

/**
 * Google Ads Platform Integration
 * API: Google Ads API v17
 * Supports: Search, Display, YouTube, Performance Max
 */
class GAMI_Platform_Google extends GAMI_Platform_Base {

    const API_BASE    = 'https://googleads.googleapis.com/v17';
    const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    const SCOPE       = 'https://www.googleapis.com/auth/adwords';

    public function get_key(): string { return 'google'; }
    public function get_name(): string { return 'Google Ads'; }

    private function get_customer_id(): string {
        return preg_replace('/\D/', '', $this->get_option('customer_id'));
    }

    private function get_access_token(): ?string {
        $cached = get_transient('gami_google_access_token');
        if ($cached) return $cached;

        $result = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'client_id'     => $this->get_option('client_id'),
                'client_secret' => $this->get_option('client_secret'),
                'refresh_token' => $this->get_option('refresh_token'),
                'grant_type'    => 'refresh_token',
            ],
        ]);

        if (is_wp_error($result)) return null;
        $data = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($data['access_token'])) return null;

        set_transient('gami_google_access_token', $data['access_token'], intval($data['expires_in']) - 60);
        return $data['access_token'];
    }

    private function google_request(string $method, string $endpoint, array $data = []): ?array {
        $token = $this->get_access_token();
        if (!$token) return null;

        $cid = $this->get_customer_id();
        $url = self::API_BASE . "/customers/{$cid}/" . ltrim($endpoint, '/');

        return $this->request($method, $url, $data, [
            'Authorization'       => "Bearer $token",
            'developer-token'     => $this->get_option('developer_token'),
            'login-customer-id'   => $this->get_option('manager_id'),
        ]);
    }

    public function fetch_campaign_stats(): array {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $query = "SELECT campaign.id, campaign.name, campaign.status,
                         metrics.impressions, metrics.clicks, metrics.ctr,
                         metrics.cost_micros, metrics.conversions, metrics.cost_per_conversion
                  FROM campaign
                  WHERE segments.date = '{$yesterday}'
                  AND campaign.status = 'ENABLED'";

        $result = $this->google_request('POST', 'googleAds:searchStream', ['query' => $query]);
        if (!$result) return [];

        $stats = [];
        foreach ($result[0]['results'] ?? [] as $row) {
            $stats[] = [
                'platform'    => 'google',
                'impressions' => intval($row['metrics']['impressions'] ?? 0),
                'clicks'      => intval($row['metrics']['clicks'] ?? 0),
                'ctr'         => round(floatval($row['metrics']['ctr'] ?? 0) * 100, 4),
                'spend'       => ($row['metrics']['costMicros'] ?? 0) / 1000000,
                'conversions' => floatval($row['metrics']['conversions'] ?? 0),
                'cpl'         => ($row['metrics']['costPerConversion'] ?? 0) / 1000000,
                'campaign_platform_id' => $row['campaign']['id'] ?? '',
                'campaign_name'        => $row['campaign']['name'] ?? '',
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        // Google Ads Kampagne erstellen (Search)
        $result = $this->google_request('POST', 'campaigns:mutate', [
            'operations' => [[
                'create' => [
                    'name'               => $data['name'],
                    'status'             => 'PAUSED', // Erst reviewen, dann aktivieren
                    'advertisingChannelType' => $data['type'] ?? 'SEARCH',
                    'campaignBudget'     => $this->create_budget($data['budget_day'] ?? 10),
                    'targetSpend'        => [],
                    'networkSettings'    => [
                        'targetGoogleSearch'         => true,
                        'targetSearchNetwork'        => true,
                        'targetContentNetwork'       => false,
                    ],
                ]
            ]],
        ]);
        return $result['results'][0]['resourceName'] ?? null;
    }

    private function create_budget(float $amount_daily): string {
        $result = $this->google_request('POST', 'campaignBudgets:mutate', [
            'operations' => [[
                'create' => [
                    'name'           => 'Budget_' . time(),
                    'amountMicros'   => intval($amount_daily * 1000000),
                    'deliveryMethod' => 'STANDARD',
                ]
            ]],
        ]);
        return $result['results'][0]['resourceName'] ?? '';
    }

    public function create_ad(array $ad_data): ?string {
        // Responsive Search Ad
        $result = $this->google_request('POST', 'ads:mutate', [
            'operations' => [[
                'create' => [
                    'responsiveSearchAd' => [
                        'headlines' => array_map(fn($h) => ['text' => $h], $ad_data['headlines'] ?? [substr($ad_data['headline'], 0, 30)]),
                        'descriptions' => [
                            ['text' => substr($ad_data['body_text'], 0, 90)],
                            ['text' => substr($ad_data['body_text'], 0, 90)],
                        ],
                    ],
                    'finalUrls' => [$ad_data['landing_page_url'] ?? get_site_url()],
                ]
            ]],
        ]);
        return $result['results'][0]['resourceName'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db_campaign = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db_campaign || !$db_campaign->platform_id) return false;

        $result = $this->google_request('POST', 'campaigns:mutate', [
            'operations' => [['update' => ['resourceName' => $db_campaign->platform_id, 'status' => 'PAUSED'], 'updateMask' => 'status']],
        ]);
        return isset($result['results']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db_campaign = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db_campaign) return false;

        $new_budget = $db_campaign->budget_day * $multiplier;
        // Erst Budget-Resource-Name holen, dann updaten
        // Vereinfacht — in Produktion: Budget-Resource-Name in DB speichern
        GAMI_Database::update('campaigns', ['budget_day' => $new_budget], ['id' => $campaign_id]);
        return true;
    }

    /**
     * Für YouTube-Platform: Query-basierter Stats-Fetch
     */
    public function fetch_campaign_stats_with_query(string $query): array {
        $result = $this->google_request('POST', 'googleAds:searchStream', ['query' => $query]);
        if (!$result) return [];

        $stats = [];
        foreach ($result[0]['results'] ?? [] as $row) {
            $stats[] = [
                'platform'    => 'google',
                'impressions' => intval($row['metrics']['impressions'] ?? 0),
                'clicks'      => intval($row['metrics']['clicks'] ?? 0),
                'ctr'         => round(floatval($row['metrics']['ctr'] ?? 0) * 100, 4),
                'spend'       => ($row['metrics']['costMicros'] ?? 0) / 1000000,
                'conversions' => floatval($row['metrics']['conversions'] ?? 0),
                'cpl'         => ($row['metrics']['costPerConversion'] ?? 0) / 1000000,
                'campaign_platform_id' => $row['campaign']['id'] ?? '',
            ];
        }
        return $stats;
    }

    /**
     * YouTube Video Ad erstellen
     */
    public function create_video_ad(array $ad_data): ?string {
        $result = $this->google_request('POST', 'ads:mutate', [
            'operations' => [[
                'create' => [
                    'videoAd' => [
                        'video'           => ['id' => $ad_data['video_id'] ?? ''],
                        'inStreamAd'      => [
                            'actionButtonLabel' => 'Mehr erfahren',
                            'actionHeadline'    => substr($ad_data['headline'] ?? 'Geldhelden', 0, 15),
                        ],
                    ],
                    'finalUrls' => [$ad_data['final_url'] ?? get_site_url()],
                    'displayUrl' => $ad_data['display_url'] ?? 'geldhelden.org',
                ]
            ]],
        ]);
        return $result['results'][0]['resourceName'] ?? null;
    }

    /**
     * Keyword-Vorschläge via Google Keyword Planner
     */
    public function get_keyword_suggestions(array $seed_keywords): array {
        $result = $this->google_request('POST', 'keywordPlanIdeas:generateKeywordIdeas', [
            'keywordSeed' => ['keywords' => $seed_keywords],
            'language'    => 'languageConstants/1001', // Deutsch
            'geoTargetConstants' => ['geoTargetConstants/2276', 'geoTargetConstants/2040', 'geoTargetConstants/2756'], // DE, AT, CH
        ]);

        $keywords = [];
        foreach ($result['results'] ?? [] as $idea) {
            $keywords[] = [
                'keyword'         => $idea['text'],
                'avg_monthly'     => $idea['keywordIdeaMetrics']['avgMonthlySearches'] ?? 0,
                'competition'     => $idea['keywordIdeaMetrics']['competition'] ?? 'UNKNOWN',
                'suggested_bid'   => ($idea['keywordIdeaMetrics']['highTopOfPageBidMicros'] ?? 0) / 1000000,
            ];
        }
        return $keywords;
    }
}
