<?php
defined('ABSPATH') || exit;

/**
 * Telegram Ads Platform Integration
 * Telegram Ad Platform (fragment.com) — TON-basiert
 * Primär: Sponsored Messages in Kanälen mit thematischem Fit
 */
class GAMI_Platform_Telegram_Ads extends GAMI_Platform_Base {

    const API_BASE = 'https://api.business.telegram.org';

    public function get_key(): string { return 'telegram_ads'; }
    public function get_name(): string { return 'Telegram Ads'; }

    private function get_token(): string {
        return $this->get_option('api_token');
    }

    private function tg_request(string $method, string $endpoint, array $data = []): ?array {
        $token = $this->get_token();
        if (!$token) return null;
        $url = self::API_BASE . '/bot' . $token . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data);
    }

    public function fetch_campaign_stats(): array {
        // Telegram Ads stats via API
        $result = $this->tg_request('GET', 'getAdStats');
        if (!$result || !isset($result['result'])) return [];

        $stats = [];
        foreach ($result['result'] as $ad) {
            $stats[] = [
                'platform'    => 'telegram_ads',
                'impressions' => intval($ad['views'] ?? 0),
                'clicks'      => intval($ad['clicks'] ?? 0),
                'ctr'         => $ad['views'] > 0 ? round($ad['clicks'] / $ad['views'] * 100, 4) : 0,
                'spend'       => floatval($ad['spent'] ?? 0),
                'conversions' => 0, // Via UTM tracken
            ];
        }
        return $stats;
    }

    public function create_campaign(array $data): ?string {
        // Telegram Sponsored Message
        // Max 160 Zeichen, ein Link
        $text = substr($data['body_text'] ?? '', 0, 160);

        $result = $this->tg_request('POST', 'createAd', [
            'ad' => [
                'message' => $text,
                'url'     => $data['url'] ?? get_site_url(),
            ],
            'budget' => intval(($data['budget_day'] ?? 5) * 1000), // in Nano-TON
            'targeting' => $this->get_targeting($data),
        ]);

        return $result['result']['ad_id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        return $this->create_campaign($ad_data);
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->tg_request('POST', 'pauseAd', ['ad_id' => $db->platform_id]);
        return isset($result['ok']) && $result['ok'];
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        GAMI_Database::update('campaigns', ['budget_day' => $db->budget_day * $multiplier], ['id' => $campaign_id]);
        return true;
    }

    /**
     * Targeting-Konfiguration: Kanäle mit Finanz/Freiheits-Themen
     */
    private function get_targeting(array $data): array {
        // Telegram Ads targeting nach Kanal-Kategorien
        $categories = $data['categories'] ?? ['finance', 'cryptocurrency', 'economics', 'news'];

        return [
            'languages'  => ['de'],
            'topics'     => $categories,
        ];
    }

    /**
     * Passende Telegram-Kanäle finden (nach Thema)
     * Nutzt Telegram-Suche und Claude für Relevanz-Bewertung
     */
    public function find_relevant_channels(array $topics): array {
        // In Produktion: Telegram-Kanal-Verzeichnis abfragen
        // Für Geldhelden relevante Kanäle:
        $known_channels = [
            ['username' => 'Finanznachrichten', 'topic' => 'finance'],
            ['username' => 'CryptoDE', 'topic' => 'cryptocurrency'],
            ['username' => 'FreiheitKompass', 'topic' => 'liberty'],
            ['username' => 'GoldSilberNews', 'topic' => 'precious_metals'],
            ['username' => 'SteuerStrategien', 'topic' => 'tax_optimization'],
        ];

        // Claude bewertet Relevanz
        $channels_json = json_encode($known_channels);
        $topics_str = implode(', ', $topics);

        $prompt = "Welche dieser Telegram-Kanäle sind relevant für Geldhelden-Ads zu den Themen: $topics_str?\n\n$channels_json\n\nBewerte die Relevanz (0-100) für jede Channel. Antworte als JSON-Array.";
        $rated = GAMI_Claude_Client::ask_json($prompt);

        return $rated ?? $known_channels;
    }
}
