<?php
defined('ABSPATH') || exit;

/**
 * Meta (Facebook + Instagram) Ads Platform Integration
 * API: Meta Marketing API v19.0
 * Supports: Facebook Ads, Instagram Ads, Click-to-WhatsApp
 */
class GAMI_Platform_Meta extends GAMI_Platform_Base {

    const API_VERSION = 'v19.0';
    const API_BASE    = 'https://graph.facebook.com/v19.0';

    public function get_key(): string { return 'meta'; }
    public function get_name(): string { return 'Meta (Facebook + Instagram)'; }

    private function get_ad_account(): string {
        return 'act_' . preg_replace('/\D/', '', $this->get_option('ad_account_id'));
    }

    private function get_token(): string {
        return $this->get_option('access_token');
    }

    private function meta_request(string $method, string $endpoint, array $data = []): ?array {
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        $data['access_token'] = $this->get_token();
        return $this->request($method, $url, $data);
    }

    public function fetch_campaign_stats(): array {
        $account = $this->get_ad_account();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $result = $this->meta_request('GET',
            "{$account}/insights?fields=campaign_id,campaign_name,impressions,clicks,ctr,spend,conversions,cost_per_result&time_range={'since':'{$yesterday}','until':'{$yesterday}'}&level=campaign"
        );
        if (!$result) return [];

        $stats = [];
        foreach ($result['data'] ?? [] as $row) {
            $stats[] = [
                'platform'    => 'meta',
                'impressions' => intval($row['impressions'] ?? 0),
                'clicks'      => intval($row['clicks'] ?? 0),
                'ctr'         => round(floatval($row['ctr'] ?? 0), 4),
                'spend'       => floatval($row['spend'] ?? 0),
                'conversions' => intval($row['conversions'][0]['value'] ?? 0),
                'cpl'         => floatval($row['cost_per_result'][0]['value'] ?? 0),
                'campaign_platform_id' => $row['campaign_id'] ?? '',
                'campaign_name'        => $row['campaign_name'] ?? '',
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $account = $this->get_ad_account();
        $result = $this->meta_request('POST', "{$account}/campaigns", [
            'name'       => $data['name'],
            'objective'  => $data['objective'] ?? 'LEAD_GENERATION',
            'status'     => 'PAUSED',
            'special_ad_categories' => [],
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad_set(string $campaign_id, array $data): ?string {
        $account = $this->get_ad_account();

        $targeting = [
            'geo_locations' => [
                'countries' => ['DE', 'AT', 'CH'],
            ],
            'age_min' => $data['age_min'] ?? 40,
            'age_max' => $data['age_max'] ?? 70,
            'genders' => [1, 2], // All
        ];

        // Interesse-Targeting hinzufügen
        if (!empty($data['interests'])) {
            $targeting['flexible_spec'] = [['interests' => $data['interests']]];
        }

        $result = $this->meta_request('POST', "{$account}/adsets", [
            'name'               => $data['name'],
            'campaign_id'        => $campaign_id,
            'billing_event'      => 'IMPRESSIONS',
            'optimization_goal'  => 'LEAD_GENERATION',
            'daily_budget'       => intval(($data['budget_day'] ?? 10) * 100), // in Cent
            'targeting'          => $targeting,
            'status'             => 'PAUSED',
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $account = $this->get_ad_account();

        // Creative erstellen
        $creative_result = $this->meta_request('POST', "{$account}/adcreatives", [
            'name'      => $ad_data['variant_name'] ?? 'Ad',
            'object_story_spec' => [
                'page_id'   => $this->get_option('page_id'),
                'link_data' => [
                    'message'     => $ad_data['body_text'],
                    'name'        => $ad_data['headline'],
                    'call_to_action' => [
                        'type'  => 'LEARN_MORE',
                        'value' => ['link' => $ad_data['landing_page_url'] ?? get_site_url()],
                    ],
                    'link'        => $ad_data['landing_page_url'] ?? get_site_url(),
                    'picture'     => $ad_data['media_url'] ?? '',
                ],
            ],
        ]);

        $creative_id = $creative_result['id'] ?? null;
        if (!$creative_id) return null;

        // Ad erstellen
        $result = $this->meta_request('POST', "{$account}/ads", [
            'name'       => $ad_data['variant_name'] ?? 'Ad',
            'adset_id'   => $ad_data['adset_id'] ?? '',
            'creative'   => ['creative_id' => $creative_id],
            'status'     => 'PAUSED',
        ]);
        return $result['id'] ?? null;
    }

    /**
     * Click-to-WhatsApp Kampagne
     */
    public function create_whatsapp_campaign(array $data): ?string {
        $account = $this->get_ad_account();
        $result = $this->meta_request('POST', "{$account}/campaigns", [
            'name'       => $data['name'],
            'objective'  => 'MESSAGES',
            'status'     => 'PAUSED',
            'special_ad_categories' => [],
        ]);
        return $result['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->meta_request('POST', "{$db->platform_id}", ['status' => 'PAUSED']);
        return isset($result['success']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        // Ad Set Budget updaten
        $new_budget = intval($db->budget_day * $multiplier * 100);
        // In Produktion: Ad Set IDs separat speichern
        GAMI_Database::update('campaigns', ['budget_day' => $db->budget_day * $multiplier], ['id' => $campaign_id]);
        return true;
    }

    /**
     * Custom Audience erstellen (z.B. für Retargeting von Webinar-Teilnehmern)
     */
    public function create_custom_audience(string $name, array $emails): ?string {
        $account = $this->get_ad_account();
        $result = $this->meta_request('POST', "{$account}/customaudiences", [
            'name'        => $name,
            'subtype'     => 'CUSTOM',
            'description' => 'Geldhelden Audience — ' . $name,
            'customer_file_source' => 'USER_PROVIDED_ONLY',
        ]);
        return $result['id'] ?? null;
    }
}
