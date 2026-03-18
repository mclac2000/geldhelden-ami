<?php
defined('ABSPATH') || exit;

/**
 * X/Twitter Ads Platform Integration
 * API: https://ads-api.x.com/12/
 * OAuth2 Bearer Token Authentication
 */
class GAMI_Platform_X extends GAMI_Platform_Base {

    const API_BASE = 'https://ads-api.x.com/12';

    public function get_key(): string { return 'x'; }
    public function get_name(): string { return 'X / Twitter'; }

    private function get_account_id(): string {
        return $this->get_option('account_id');
    }

    private function auth_headers(): array {
        return ['Authorization' => 'Bearer ' . $this->get_option('bearer_token')];
    }

    public function fetch_campaign_stats(): array {
        $account = $this->get_account_id();
        if (!$account) return [];

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $url = self::API_BASE . "/stats/accounts/{$account}"
            . "?entity=LINE_ITEM"
            . "&metric_groups=ENGAGEMENT,BILLING"
            . "&granularity=DAY"
            . "&placement=ALL_ON_TWITTER"
            . "&start_time={$yesterday}T00:00:00Z"
            . "&end_time={$today}T23:59:59Z";

        $result = $this->request('GET', $url, [], $this->auth_headers());
        if (!$result) return [];

        $stats = [];
        foreach ($result['data'] ?? [] as $item) {
            $metrics = $item['id_data'][0]['metrics'] ?? [];
            $stats[] = [
                'ad_id'       => $this->find_local_ad_id($item['id'] ?? ''),
                'platform'    => 'x',
                'impressions' => $metrics['impressions'][0] ?? 0,
                'clicks'      => $metrics['clicks'][0] ?? 0,
                'spend'       => ($metrics['billed_charge_local_micro'][0] ?? 0) / 1000000,
                'conversions' => 0, // Via Conversion-Tracking separat
                'ctr'         => $metrics['impressions'][0] > 0
                    ? round($metrics['clicks'][0] / $metrics['impressions'][0] * 100, 4)
                    : 0,
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $account = $this->get_account_id();
        $result = $this->request('POST', self::API_BASE . "/accounts/{$account}/campaigns", [
            'name'              => $data['name'],
            'funding_instrument_id' => $this->get_option('funding_instrument_id'),
            'daily_budget_amount_local_micro' => intval(($data['budget_day'] ?? 10) * 1000000),
            'status'            => 'ACTIVE',
            'entity_status'     => 'ACTIVE',
            'start_time'        => date('c'),
        ], $this->auth_headers());

        return $result['data']['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $account = $this->get_account_id();

        // Tweet erstellen
        $tweet_result = $this->request('POST', self::API_BASE . "/accounts/{$account}/tweet",
            ['text' => $ad_data['body_text']],
            $this->auth_headers()
        );
        $tweet_id = $tweet_result['data']['id_str'] ?? null;
        if (!$tweet_id) return null;

        // Promoted Tweet
        $result = $this->request('POST', self::API_BASE . "/accounts/{$account}/promoted_tweets", [
            'line_item_id' => $ad_data['line_item_id'],
            'tweet_ids'    => $tweet_id,
        ], $this->auth_headers());

        return $result['data']['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db_campaign = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db_campaign || !$db_campaign->platform_id) return false;

        $account = $this->get_account_id();
        $result = $this->request('PUT',
            self::API_BASE . "/accounts/{$account}/campaigns/{$db_campaign->platform_id}",
            ['entity_status' => 'PAUSED'],
            $this->auth_headers()
        );
        return isset($result['data']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db_campaign = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db_campaign || !$db_campaign->platform_id) return false;

        $new_budget = intval($db_campaign->budget_day * $multiplier * 1000000);
        $account = $this->get_account_id();
        $result = $this->request('PUT',
            self::API_BASE . "/accounts/{$account}/campaigns/{$db_campaign->platform_id}",
            ['daily_budget_amount_local_micro' => $new_budget],
            $this->auth_headers()
        );

        if (isset($result['data'])) {
            GAMI_Database::update('campaigns', ['budget_day' => $db_campaign->budget_day * $multiplier], ['id' => $campaign_id]);
            return true;
        }
        return false;
    }

    private function find_local_ad_id(string $platform_id): ?int {
        global $wpdb;
        $t = GAMI_Database::get_table('ads');
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE platform_ad_id = %s AND platform = 'x'", $platform_id));
    }
}
