<?php
defined('ABSPATH') || exit;

/**
 * TikTok for Business Ads Integration
 * API: TikTok Marketing API v1.3
 * Besonderheit: Günstigste CPMs, jüngere Zielgruppe (18-35), aber Video-Pflicht
 * Geldhelden-Einsatz: FBA, Freiheits-Business (Zielgruppe 25-45)
 */
class GAMI_Platform_Tiktok extends GAMI_Platform_Base {

    const API_BASE = 'https://business-api.tiktok.com/open_api/v1.3';

    public function get_key(): string { return 'tiktok'; }
    public function get_name(): string { return 'TikTok Ads'; }

    private function get_advertiser_id(): string { return $this->get_option('advertiser_id'); }
    private function get_token(): string          { return $this->get_option('access_token'); }

    private function tt_request(string $method, string $endpoint, array $data = []): ?array {
        $url = self::API_BASE . '/' . ltrim($endpoint, '/') . '/';
        $headers = [
            'Access-Token' => $this->get_token(),
        ];

        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
            $data = [];
        }

        return $this->request($method, $url, $data, $headers);
    }

    public function fetch_campaign_stats(): array {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $result = $this->tt_request('GET', 'report/integrated/get', [
            'advertiser_id' => $this->get_advertiser_id(),
            'report_type'   => 'BASIC',
            'dimensions'    => json_encode(['campaign_id']),
            'metrics'       => json_encode(['impressions', 'clicks', 'ctr', 'spend', 'cpc']),
            'start_date'    => $yesterday,
            'end_date'      => $yesterday,
            'page_size'     => 50,
        ]);

        if (!$result || $result['code'] !== 0) return [];

        $stats = [];
        foreach ($result['data']['list'] ?? [] as $row) {
            $m = $row['metrics'] ?? [];
            $stats[] = [
                'platform'    => 'tiktok',
                'impressions' => intval($m['impressions'] ?? 0),
                'clicks'      => intval($m['clicks'] ?? 0),
                'ctr'         => round(floatval($m['ctr'] ?? 0), 4),
                'spend'       => floatval($m['spend'] ?? 0),
                'conversions' => 0,
                'cpl'         => floatval($m['cpc'] ?? 0),
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        $result = $this->tt_request('POST', 'campaign/create', [
            'advertiser_id'     => $this->get_advertiser_id(),
            'campaign_name'     => $data['name'],
            'objective_type'    => 'TRAFFIC',
            'budget_mode'       => 'BUDGET_MODE_DAY',
            'budget'            => $data['budget_day'] ?? 10,
            'operation_status'  => 'DISABLE',
        ]);
        return $result['data']['campaign_id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        // TikTok benötigt Video-Creative — Skript generieren
        $result = $this->tt_request('POST', 'ad/create', [
            'advertiser_id' => $this->get_advertiser_id(),
            'adgroup_id'    => $ad_data['adgroup_id'] ?? '',
            'ad_name'       => $ad_data['variant_name'] ?? 'TikTok Ad',
            'ad_text'       => substr($ad_data['body_text'] ?? '', 0, 100),
            'call_to_action' => 'LEARN_MORE',
            'landing_page_url' => $ad_data['landing_page_url'] ?? get_site_url(),
            'operation_status' => 'DISABLE',
        ]);
        return $result['data']['ad_id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->tt_request('POST', 'campaign/status/update', [
            'advertiser_id' => $this->get_advertiser_id(),
            'campaign_ids'  => [$db->platform_id],
            'operation_status' => 'DISABLE',
        ]);
        return ($result['code'] ?? -1) === 0;
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        $new = $db->budget_day * $multiplier;
        $result = $this->tt_request('POST', 'campaign/update', [
            'advertiser_id' => $this->get_advertiser_id(),
            'campaign_id'   => $db->platform_id,
            'budget'        => $new,
        ]);
        if (($result['code'] ?? -1) === 0) {
            GAMI_Database::update('campaigns', ['budget_day' => $new], ['id' => $campaign_id]);
            return true;
        }
        return false;
    }

    /**
     * TikTok Video-Script generieren (Claude schreibt Hook + Content + CTA)
     */
    public static function generate_video_script(int $product_id, int $duration_sec = 30): ?string {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return null;

        $prompt = <<<PROMPT
Schreibe ein TikTok-Video-Skript für Geldhelden.
Produkt: {$product_data['name']}
Kernversprechen: {$product_data['core_promise']}
Zielgruppe: 25-45 Jahre, finanzbewusst, kritisch gegenüber System
Video-Länge: $duration_sec Sekunden

Regeln für TikTok-Scripts:
- Hook in Sekunde 1-3 MUSS fesseln (Frage, Schock, Versprechen)
- Sehr direktes, persönliches Sprechen
- Keine Auflistungen — Storytelling
- Am Ende klarer CTA ("Link in der Bio")
- Mit Zeitstempeln in [eckigen Klammern]

Format:
[0-3 Sek] Hook: "..."
[3-20 Sek] Content: "..."
[20-$duration_sec Sek] CTA: "..."

Sprecher-Hinweise in (runden Klammern).
PROMPT;

        return GAMI_Claude_Client::ask($prompt, GAMI_Claude_Client::get_agent_system_prompt(), 2048);
    }
}
