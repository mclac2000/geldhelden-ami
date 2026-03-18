<?php
defined('ABSPATH') || exit;

/**
 * Pinterest Ads Platform Integration
 * API: Pinterest Marketing API v5
 * Besonderheit: Finanz-Interessen-Targeting stark, visuell, Longtail-Keywords
 * Zielgruppe: Frauen 35-55 mit Interesse an Finanzen/Sparen/Investieren
 */
class GAMI_Platform_Pinterest extends GAMI_Platform_Base {

    const API_BASE  = 'https://api.pinterest.com/v5';
    const TOKEN_URL = 'https://api.pinterest.com/v5/oauth/token';

    public function get_key(): string { return 'pinterest'; }
    public function get_name(): string { return 'Pinterest Ads'; }

    private function get_ad_account(): string { return $this->get_option('ad_account_id'); }
    private function get_token(): string       { return $this->get_option('access_token'); }

    private function pin_request(string $method, string $endpoint, array $data = []): ?array {
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, [
            'Authorization' => 'Bearer ' . $this->get_token(),
        ]);
    }

    public function fetch_campaign_stats(): array {
        $account  = $this->get_ad_account();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $result = $this->pin_request('GET',
            "ad_accounts/{$account}/campaigns/analytics?start_date={$yesterday}&end_date={$yesterday}&columns=SPEND_IN_DOLLAR,IMPRESSION_1,CLICK_1,CTR,ECPC_IN_DOLLAR,ENGAGEMENT_1"
        );
        if (!$result) return [];

        $stats = [];
        foreach ($result['items'] ?? [] as $item) {
            $m = $item['metrics'] ?? [];
            $stats[] = [
                'platform'    => 'pinterest',
                'impressions' => intval($m['IMPRESSION_1'] ?? 0),
                'clicks'      => intval($m['CLICK_1'] ?? 0),
                'ctr'         => round(floatval($m['CTR'] ?? 0) * 100, 4),
                'spend'       => floatval($m['SPEND_IN_DOLLAR'] ?? 0),
                'conversions' => 0,
                'cpl'         => floatval($m['ECPC_IN_DOLLAR'] ?? 0),
                'campaign_platform_id' => $item['id'] ?? '',
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $account = $this->get_ad_account();
        $result  = $this->pin_request('POST', "ad_accounts/{$account}/campaigns", [
            'name'              => $data['name'],
            'objective_type'    => 'WEB_CONVERSION',
            'status'            => 'PAUSED',
            'lifetime_spend_cap' => intval(($data['budget_total'] ?? 100) * 1000000),
            'daily_spend_cap'   => intval(($data['budget_day'] ?? 10) * 1000000),
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $account = $this->get_ad_account();

        // Pin (Creative) erstellen
        $pin = $this->pin_request('POST', 'pins', [
            'title'           => substr($ad_data['headline'] ?? '', 0, 100),
            'description'     => $ad_data['body_text'] ?? '',
            'link'            => $ad_data['landing_page_url'] ?? get_site_url(),
            'board_id'        => $this->get_option('board_id'),
            'media_source'    => [
                'source_type' => 'image_url',
                'url'         => $ad_data['media_url'] ?? '',
            ],
        ]);
        $pin_id = $pin['id'] ?? null;
        if (!$pin_id) return null;

        // Promoted Pin
        $result = $this->pin_request('POST', "ad_accounts/{$account}/ad_groups/{$ad_data['ad_group_id']}/ads", [
            'creative_type' => 'REGULAR',
            'pin_id'        => $pin_id,
            'status'        => 'PAUSED',
            'name'          => $ad_data['variant_name'] ?? 'Pin',
        ]);
        return $result['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->pin_request('PATCH', "ad_accounts/{$this->get_ad_account()}/campaigns", [
            'items' => [['id' => $db->platform_id, 'status' => 'PAUSED']],
        ]);
        return isset($result['items']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        $new = $db->budget_day * $multiplier;
        $this->pin_request('PATCH', "ad_accounts/{$this->get_ad_account()}/campaigns", [
            'items' => [['id' => $db->platform_id, 'daily_spend_cap' => intval($new * 1000000)]],
        ]);
        GAMI_Database::update('campaigns', ['budget_day' => $new], ['id' => $campaign_id]);
        return true;
    }

    /**
     * Pinterest-spezifisches Interesse-Targeting für Geldhelden
     */
    public static function get_geldhelden_interests(): array {
        return [
            'personal_finance', 'investing', 'retirement', 'real_estate',
            'frugal_living', 'financial_independence', 'gold_silver', 'cryptocurrency',
        ];
    }
}
