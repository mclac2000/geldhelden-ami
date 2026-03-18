<?php
defined('ABSPATH') || exit;

/**
 * LinkedIn Ads Platform Integration
 * API: LinkedIn Marketing API v202401
 * Besonderheit: B2B-Fokus, höhere CPCs aber sehr gezielte Zielgruppe
 * Geldhelden-Einsatz: Holding-Strukturen, GmbH, Steueroptimierung, Unternehmer
 */
class GAMI_Platform_Linkedin extends GAMI_Platform_Base {

    const API_BASE  = 'https://api.linkedin.com/rest';
    const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    public function get_key(): string { return 'linkedin'; }
    public function get_name(): string { return 'LinkedIn Ads'; }

    private function get_account(): string { return $this->get_option('account_id'); }
    private function get_token(): string   { return $this->get_option('access_token'); }

    private function li_request(string $method, string $endpoint, array $data = []): ?array {
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, [
            'Authorization'          => 'Bearer ' . $this->get_token(),
            'X-RestLi-Protocol-Version' => '2.0.0',
            'LinkedIn-Version'       => '202401',
        ]);
    }

    public function fetch_campaign_stats(): array {
        $account   = $this->get_account();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $result = $this->li_request('GET',
            "adAnalytics?q=analytics&pivot=CAMPAIGN&dateRange.start.day=" . date('j', strtotime($yesterday)) .
            "&dateRange.start.month=" . date('n', strtotime($yesterday)) .
            "&dateRange.start.year=" . date('Y', strtotime($yesterday)) .
            "&timeGranularity=DAILY&accounts=urn:li:sponsoredAccount:{$account}" .
            "&fields=impressions,clicks,costInLocalCurrency,leadGenerationMailContactInfoShares,pivotValues"
        );
        if (!$result) return [];

        $stats = [];
        foreach ($result['elements'] ?? [] as $el) {
            $stats[] = [
                'platform'    => 'linkedin',
                'impressions' => intval($el['impressions'] ?? 0),
                'clicks'      => intval($el['clicks'] ?? 0),
                'ctr'         => $el['impressions'] > 0 ? round($el['clicks'] / $el['impressions'] * 100, 4) : 0,
                'spend'       => floatval($el['costInLocalCurrency'] ?? 0),
                'conversions' => intval($el['leadGenerationMailContactInfoShares'] ?? 0),
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $account = $this->get_account();
        $result  = $this->li_request('POST', 'adCampaigns', [
            'account'        => "urn:li:sponsoredAccount:{$account}",
            'name'           => $data['name'],
            'type'           => 'SPONSORED_UPDATES',
            'objectiveType'  => 'LEAD_GENERATION',
            'status'         => 'PAUSED',
            'unitCost'       => ['amount' => '5.00', 'currencyCode' => 'EUR'],
            'costType'       => 'CPM',
            'dailyBudget'    => ['amount' => (string)($data['budget_day'] ?? 10), 'currencyCode' => 'EUR'],
            'targeting'      => self::get_geldhelden_targeting(),
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $result = $this->li_request('POST', 'adCreatives', [
            'campaign' => "urn:li:sponsoredCampaign:{$ad_data['campaign_platform_id']}",
            'status'   => 'PAUSED',
            'type'     => 'TEXT_AD',
            'variables' => [
                'data' => [
                    'com.linkedin.ads.SponsoredUpdateCreativeVariables' => [
                        'activity'    => 'urn:li:activity:12345',
                        'directSponsoredContent' => true,
                    ],
                ],
            ],
        ]);
        return $result['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->li_request('POST', "adCampaigns/{$db->platform_id}", ['status' => 'PAUSED']);
        return !empty($result);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        $new = $db->budget_day * $multiplier;
        $this->li_request('POST', "adCampaigns/{$db->platform_id}", [
            'dailyBudget' => ['amount' => (string)$new, 'currencyCode' => 'EUR'],
        ]);
        GAMI_Database::update('campaigns', ['budget_day' => $new], ['id' => $campaign_id]);
        return true;
    }

    /**
     * LinkedIn-Targeting für Geldhelden B2B-Produkte
     * (Holding, Steueroptimierung, Freiheits-Business)
     */
    private static function get_geldhelden_targeting(): array {
        return [
            'includedTargetingFacets' => [
                'locations'       => ['urn:li:geo:101282230', 'urn:li:geo:103883259', 'urn:li:geo:106693272'], // DE, AT, CH
                'seniorities'     => ['urn:li:seniority:9', 'urn:li:seniority:10'], // Owner, Partner
                'industries'      => ['urn:li:industry:4', 'urn:li:industry:41'], // Finance, Accounting
                'jobFunctions'    => ['urn:li:jobFunction:1', 'urn:li:jobFunction:12'], // Accounting, Entrepreneurship
            ],
            'excludedTargetingFacets' => [
                'jobTitles' => [], // Keine Ausschlüsse
            ],
        ];
    }
}
