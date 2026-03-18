<?php
defined('ABSPATH') || exit;

/**
 * Microsoft/Bing Ads Platform Integration
 * API: Bing Ads API v13
 * Besonderheit: Ältere Zielgruppe, günstigere CPCs als Google, DACH-Abdeckung stark
 */
class GAMI_Platform_Bing extends GAMI_Platform_Base {

    const API_BASE  = 'https://api.bingads.microsoft.com/api/advertiser/v13';
    const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    public function get_key(): string { return 'bing'; }
    public function get_name(): string { return 'Bing / Microsoft Ads'; }

    private function get_access_token(): ?string {
        $cached = get_transient('gami_bing_access_token');
        if ($cached) return $cached;

        $result = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'client_id'     => $this->get_option('client_id'),
                'client_secret' => $this->get_option('client_secret'),
                'refresh_token' => $this->get_option('refresh_token'),
                'grant_type'    => 'refresh_token',
                'scope'         => 'https://ads.microsoft.com/ads.manage',
            ],
        ]);

        if (is_wp_error($result)) return null;
        $data = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($data['access_token'])) return null;

        set_transient('gami_bing_access_token', $data['access_token'], intval($data['expires_in']) - 60);
        return $data['access_token'];
    }

    private function bing_request(string $method, string $endpoint, array $data = []): ?array {
        $token = $this->get_access_token();
        if (!$token) return null;

        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, [
            'Authorization'          => "Bearer $token",
            'DeveloperToken'         => $this->get_option('developer_token'),
            'CustomerId'             => $this->get_option('customer_id'),
            'CustomerAccountId'      => $this->get_option('account_id'),
        ]);
    }

    public function fetch_campaign_stats(): array {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $result = $this->bing_request('POST', 'reporting/GenerateReportRequest', [
            'ReportRequest' => [
                'ReportName' => 'DailyPerformance',
                'ReturnOnlyCompleteData' => true,
                'Aggregation' => 'Daily',
                'Columns' => ['CampaignName', 'Impressions', 'Clicks', 'Ctr', 'Spend', 'Conversions', 'CostPerConversion'],
                'Scope' => ['AccountIds' => [$this->get_option('account_id')]],
                'Time' => ['CustomDateRangeStart' => $yesterday, 'CustomDateRangeEnd' => $yesterday],
            ],
        ]);

        if (!$result) return [];

        // Report-ID für späteres Abholen
        $report_id = $result['ReportRequestId'] ?? null;
        if (!$report_id) return [];

        // Vereinfacht: In Produktion Report-URL pollen und CSV parsen
        return [];
    }

    public function create_campaign(array $data): ?string {
        $result = $this->bing_request('POST', 'campaign-management/AddCampaigns', [
            'AccountId' => $this->get_option('account_id'),
            'Campaigns' => [[
                'Name'          => $data['name'],
                'BudgetType'    => 'DailyBudgetStandard',
                'DailyBudget'   => $data['budget_day'] ?? 10,
                'TimeZone'      => 'BerlinStockholmRomeBernVienna',
                'CampaignType'  => 'Search',
                'Status'        => 'Paused',
                'Languages'     => ['German'],
            ]],
        ]);
        return $result['CampaignIds'][0] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $result = $this->bing_request('POST', 'campaign-management/AddAds', [
            'AdGroupId' => $ad_data['ad_group_id'] ?? '',
            'Ads' => [[
                'Type'           => 'ExpandedText',
                'TitlePart1'     => substr($ad_data['headline'], 0, 30),
                'TitlePart2'     => substr($ad_data['headline'], 30, 30),
                'Text'           => substr($ad_data['body_text'], 0, 80),
                'TextPart2'      => substr($ad_data['body_text'], 80, 80),
                'FinalUrls'      => [$ad_data['landing_page_url'] ?? get_site_url()],
                'Status'         => 'Paused',
            ]],
        ]);
        return $result['AdIds'][0] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;

        $result = $this->bing_request('POST', 'campaign-management/UpdateCampaigns', [
            'AccountId' => $this->get_option('account_id'),
            'Campaigns' => [['Id' => intval($db->platform_id), 'Status' => 'Paused']],
        ]);
        return !empty($result);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;

        $new_budget = $db->budget_day * $multiplier;
        $result = $this->bing_request('POST', 'campaign-management/UpdateCampaigns', [
            'AccountId' => $this->get_option('account_id'),
            'Campaigns' => [['Id' => intval($db->platform_id), 'DailyBudget' => $new_budget]],
        ]);

        if ($result !== null) {
            GAMI_Database::update('campaigns', ['budget_day' => $new_budget], ['id' => $campaign_id]);
            return true;
        }
        return false;
    }
}
