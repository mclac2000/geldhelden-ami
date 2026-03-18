<?php
defined('ABSPATH') || exit;

/**
 * Outbrain Native Ads Integration
 * API: Outbrain Amplify API v1
 * Platzierung: Ähnlich Taboola — Spiegel, Focus, Stern, T-Online etc.
 * Stärke: Älteres, wohlhabenderes Publikum als Taboola
 */
class GAMI_Platform_Outbrain extends GAMI_Platform_Base {

    const API_BASE  = 'https://api.outbrain.com/amplify/v0.1';
    const TOKEN_URL = 'https://api.outbrain.com/amplify/v0.1/login';

    public function get_key(): string { return 'outbrain'; }
    public function get_name(): string { return 'Outbrain Native'; }

    private function get_marketer_id(): string { return $this->get_option('marketer_id'); }

    private function get_session_token(): ?string {
        $cached = get_transient('gami_outbrain_token');
        if ($cached) return $cached;

        $result = $this->request('GET', self::TOKEN_URL, [], [
            'OB-USER-TOKEN' => base64_encode($this->get_option('username') . ':' . $this->get_option('password')),
        ]);

        if (!$result || empty($result['OB-TOKEN-V1'])) return null;
        set_transient('gami_outbrain_token', $result['OB-TOKEN-V1'], 3600);
        return $result['OB-TOKEN-V1'];
    }

    private function ob_request(string $method, string $endpoint, array $data = []): ?array {
        $token = $this->get_session_token();
        if (!$token) return null;
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, ['OB-TOKEN-V1' => $token]);
    }

    public function fetch_campaign_stats(): array {
        $marketer = $this->get_marketer_id();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $result = $this->ob_request('GET',
            "reports/marketers/{$marketer}/campaigns?from={$yesterday}&to={$yesterday}&breakdown=daily&includeArchivedCampaigns=false"
        );
        if (!$result) return [];

        $stats = [];
        foreach ($result['results'] ?? [] as $row) {
            $stats[] = [
                'platform'    => 'outbrain',
                'impressions' => intval($row['impressions'] ?? 0),
                'clicks'      => intval($row['clicks'] ?? 0),
                'ctr'         => round(floatval($row['ctr'] ?? 0) * 100, 4),
                'spend'       => floatval($row['spend'] ?? 0),
                'conversions' => intval($row['conversions'] ?? 0),
                'cpl'         => floatval($row['cpa'] ?? 0),
                'campaign_platform_id' => $row['campaignId'] ?? '',
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $marketer = $this->get_marketer_id();
        $result   = $this->ob_request('POST', "marketers/{$marketer}/campaigns", [
            'name'        => $data['name'],
            'budget'      => ['amount' => $data['budget_day'] ?? 10, 'type' => 'daily'],
            'targeting'   => [
                'geo' => ['locations' => [['type' => 'country', 'id' => 'DE'], ['type' => 'country', 'id' => 'AT'], ['type' => 'country', 'id' => 'CH']]],
            ],
            'status'      => 'Paused',
            'branding'    => ['text' => 'Geldhelden'],
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        $campaign_id = $ad_data['platform_campaign_id'] ?? '';
        if (!$campaign_id) return null;

        $result = $this->ob_request('POST', "campaigns/{$campaign_id}/promotedLinks", [
            'headline' => substr($ad_data['headline'] ?? '', 0, 75),
            'url'      => $ad_data['landing_page_url'] ?? get_site_url(),
            'imageUrl' => $ad_data['media_url'] ?? '',
            'status'   => 'Paused',
        ]);
        return $result['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->ob_request('PUT', "campaigns/{$db->platform_id}", ['status' => 'Paused']);
        return isset($result['id']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        $new = $db->budget_day * $multiplier;
        $result = $this->ob_request('PUT', "campaigns/{$db->platform_id}", [
            'budget' => ['amount' => $new, 'type' => 'daily'],
        ]);
        if (isset($result['id'])) {
            GAMI_Database::update('campaigns', ['budget_day' => $new], ['id' => $campaign_id]);
            return true;
        }
        return false;
    }

    /**
     * Outbrain Headlines generieren (wie Taboola, aber etwas seriöser)
     */
    public static function generate_native_headlines(int $product_id, int $count = 5): array {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return [];

        $prompt = <<<PROMPT
Erstelle $count Native-Ad-Headlines für Outbrain.

Produkt: {$product_data['name']}
Zielgruppe: Deutsche/Österreicher/Schweizer, 50-70 Jahre, einkommensstark, staatsskeptisch

Outbrain-Headlines sind etwas seriöser als Taboola:
- Wirken wie Artikel aus Wirtschaftszeitung oder Finanzmagazin
- Keine reißerischen Clickbait-Muster
- Informativ, aber mit klarem Interesse-Hook
- Max 75 Zeichen
- Beispiele: "Was Finanzexperten über CBDC wirklich denken", "Warum wohlhabende Österreicher jetzt umdenken"

Antworte JSON: ["headline1", "headline2", ...]
PROMPT;

        $headlines = GAMI_Claude_Client::ask_json($prompt, GAMI_Claude_Client::get_agent_system_prompt());
        return is_array($headlines) ? $headlines : [];
    }
}
