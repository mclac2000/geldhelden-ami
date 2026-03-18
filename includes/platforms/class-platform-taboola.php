<?php
defined('ABSPATH') || exit;

/**
 * Taboola Native Ads Integration
 * API: Taboola Backstage API v1
 * Platzierung: N-TV, Focus, T-Online, Stern, Spiegel, Bild, etc.
 * Besonderheit: Journalistischer Headline-Stil, native Content-Look
 */
class GAMI_Platform_Taboola extends GAMI_Platform_Base {

    const API_BASE  = 'https://backstage.taboola.com/backstage/api/1.0';
    const TOKEN_URL = 'https://backstage.taboola.com/backstage/oauth/token';

    public function get_key(): string { return 'taboola'; }
    public function get_name(): string { return 'Taboola Native'; }

    private function get_account_id(): string { return $this->get_option('account_id'); }

    private function get_access_token(): ?string {
        $cached = get_transient('gami_taboola_access_token');
        if ($cached) return $cached;

        $result = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'client_id'     => $this->get_option('client_id'),
                'client_secret' => $this->get_option('client_secret'),
                'grant_type'    => 'client_credentials',
            ],
        ]);

        if (is_wp_error($result)) return null;
        $data = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($data['access_token'])) return null;

        set_transient('gami_taboola_access_token', $data['access_token'], intval($data['expires_in']) - 60);
        return $data['access_token'];
    }

    private function taboola_request(string $method, string $endpoint, array $data = []): ?array {
        $token = $this->get_access_token();
        if (!$token) return null;
        $account = $this->get_account_id();
        $url = self::API_BASE . "/{$account}/" . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, ['Authorization' => "Bearer $token"]);
    }

    public function fetch_campaign_stats(): array {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $result = $this->taboola_request('GET', "reports/top-campaign-content/dimensions/campaign_day_breakdown?start_date={$yesterday}&end_date={$yesterday}");

        if (!$result) return [];

        $stats = [];
        foreach ($result['results'] ?? [] as $row) {
            $stats[] = [
                'platform'    => 'taboola',
                'impressions' => intval($row['impressions'] ?? 0),
                'clicks'      => intval($row['clicks'] ?? 0),
                'ctr'         => round(floatval($row['ctr'] ?? 0) * 100, 4),
                'spend'       => floatval($row['spent'] ?? 0),
                'conversions' => intval($row['actions_num'] ?? 0),
                'cpl'         => floatval($row['cpa'] ?? 0),
                'campaign_platform_id' => $row['campaign'] ?? '',
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $result = $this->taboola_request('POST', 'campaigns', [
            'name'             => $data['name'],
            'branding_text'    => 'Geldhelden',
            'cpc'              => $data['cpc'] ?? 0.50,
            'spending_limit'   => $data['budget_total'] ?? 100,
            'daily_cap'        => $data['budget_day'] ?? 10,
            'country_targeting' => ['type' => 'INCLUDE', 'value' => ['DE', 'AT', 'CH']],
            'platform_targeting' => ['type' => 'ALL'],
            'status'           => 'PAUSED',
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $campaign_id = $ad_data['platform_campaign_id'] ?? '';
        if (!$campaign_id) return null;

        $result = $this->taboola_request('POST', "campaigns/{$campaign_id}/items", [
            'type'        => 'TEXT',
            'status'      => 'PAUSED',
            'title'       => substr($ad_data['headline'], 0, 75), // Taboola Max: 75 Zeichen
            'url'         => $ad_data['landing_page_url'] ?? get_site_url(),
            'thumbnail_url' => $ad_data['media_url'] ?? '',
        ]);
        return $result['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->taboola_request('PUT', "campaigns/{$db->platform_id}", ['status' => 'PAUSED']);
        return isset($result['id']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        $new_budget = $db->budget_day * $multiplier;
        $result = $this->taboola_request('PUT', "campaigns/{$db->platform_id}", ['daily_cap' => $new_budget]);
        if (isset($result['id'])) {
            GAMI_Database::update('campaigns', ['budget_day' => $new_budget], ['id' => $campaign_id]);
            return true;
        }
        return false;
    }

    /**
     * Native Headline im Nachrichtenstil generieren (via Claude)
     * Taboola-spezifisch: "Warum immer mehr Deutsche..." Stil
     */
    public static function generate_native_headlines(int $product_id, int $count = 5): array {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return [];

        $prompt = <<<PROMPT
Erstelle $count Native-Ad-Headlines im Nachrichtenstil für Taboola/Outbrain.

Produkt: {$product_data['name']}
Kernversprechen: {$product_data['core_promise']}
Zielgruppe: Deutsche/Österreicher/Schweizer, 45-70 Jahre, staatsskeptisch

Regeln für Taboola-Headlines:
- Klingen wie echte Nachrichtenartikel oder Reportagen
- Neugier wecken ("Warum...", "Das passiert...", "Was niemand sagt über...")
- Max 75 Zeichen
- Kein direktes Selling — Informations-Framing
- Beispiele: "Warum immer mehr Deutsche ihr Geld ins Ausland schaffen", "Was die EZB nicht will, dass du weißt"

Antworte JSON: ["headline1", "headline2", ...]
PROMPT;

        $headlines = GAMI_Claude_Client::ask_json($prompt, GAMI_Claude_Client::get_agent_system_prompt());
        return is_array($headlines) ? $headlines : [];
    }
}
