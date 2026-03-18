<?php
defined('ABSPATH') || exit;

/**
 * Spotify Ads Studio Integration
 * API: Spotify Ad Analytics API
 * Format: Audio-Spots (15-30 Sek), Podcast Ads, Display-Ads
 * Besonderheit: Erreicht Podcast-Hörer — höchste Qualitätszielgruppe für Geldhelden
 */
class GAMI_Platform_Spotify extends GAMI_Platform_Base {

    const API_BASE  = 'https://api.spotify.com/v1/ad-studio';
    const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    public function get_key(): string { return 'spotify'; }
    public function get_name(): string { return 'Spotify Ads'; }

    private function get_access_token(): ?string {
        $cached = get_transient('gami_spotify_access_token');
        if ($cached) return $cached;

        $result = wp_remote_post(self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->get_option('client_id') . ':' . $this->get_option('client_secret')),
            ],
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        if (is_wp_error($result)) return null;
        $data = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($data['access_token'])) return null;

        set_transient('gami_spotify_access_token', $data['access_token'], intval($data['expires_in']) - 60);
        return $data['access_token'];
    }

    private function spotify_request(string $method, string $endpoint, array $data = []): ?array {
        $token = $this->get_access_token();
        if (!$token) return null;
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, ['Authorization' => "Bearer $token"]);
    }

    public function fetch_campaign_stats(): array {
        // Spotify Ad Studio hat eingeschränktes API — Reporting manuell
        return [];
    }

    public function create_campaign(array $data): ?string {
        $result = $this->spotify_request('POST', 'campaigns', [
            'name'        => $data['name'],
            'objective'   => 'AWARENESS',
            'dailyBudget' => intval(($data['budget_day'] ?? 10) * 100),
            'targeting'   => [
                'markets'    => ['DE', 'AT', 'CH'],
                'genders'    => ['MALE', 'FEMALE'],
                'ageRanges'  => [['minimum' => 45, 'maximum' => 65]],
                'genres'     => ['NEWS', 'SOCIETY_CULTURE', 'BUSINESS'], // Podcast-Genres
            ],
            'status'      => 'PAUSED',
        ]);
        return $result['id'] ?? null;
    }

    public function create_ad(array $ad_data): ?string {
        // Spotify Audio Ad
        $result = $this->spotify_request('POST', 'ads', [
            'type'         => 'AUDIO',
            'campaignId'   => $ad_data['platform_campaign_id'] ?? '',
            'name'         => $ad_data['variant_name'] ?? 'Spot',
            'audioUrl'     => $ad_data['media_url'] ?? '', // Fertig produzierter Audio-Spot
            'displayUrl'   => 'geldhelden.org',
            'clickUrl'     => $ad_data['landing_page_url'] ?? get_site_url(),
            'callToAction' => 'Jetzt informieren',
            'status'       => 'PAUSED',
        ]);
        return $result['id'] ?? null;
    }

    public function pause_campaign(int $campaign_id): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db || !$db->platform_id) return false;
        $result = $this->spotify_request('PATCH', "campaigns/{$db->platform_id}", ['status' => 'PAUSED']);
        return isset($result['id']);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        $db = GAMI_Database::get_row('campaigns', 'id = %d', [$campaign_id]);
        if (!$db) return false;
        $new = $db->budget_day * $multiplier;
        $result = $this->spotify_request('PATCH', "campaigns/{$db->platform_id}", [
            'dailyBudget' => intval($new * 100),
        ]);
        if (isset($result['id'])) {
            GAMI_Database::update('campaigns', ['budget_day' => $new], ['id' => $campaign_id]);
            return true;
        }
        return false;
    }

    /**
     * Radio-Spot-Skript für Spotify (wird vorgelesen / produziert)
     * Professioneller Tonfall, keine Auflistungen — Sprache für das Ohr
     */
    public static function generate_audio_script(int $product_id, int $duration_sec = 30): ?string {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return null;

        $word_count = intval($duration_sec * 2.5); // ~2.5 Wörter/Sekunde gesprochen

        $prompt = <<<PROMPT
Schreibe einen Spotify/Radio-Audio-Spot für Geldhelden.
Produkt: {$product_data['name']}
Länge: $duration_sec Sekunden (ca. $word_count Wörter)
Zielgruppe: 45-65 Jahre, staatsskeptisch, vermögend

Regeln für Audio-Werbung:
- KEIN Satzzeichen für Pausen — nutze "..." stattdessen
- Natürliche Sprache, die vorgelesen werden kann
- Kein Jargon, keine Zahlenreihen
- Eine klare Aussage, ein klarer CTA am Ende
- Musik-Hinweis am Anfang in [eckigen Klammern]
- Sprecher-Regieanweisungen in (runden Klammern)

Format:
[Musik: ...]
(Sprecher: ...)
"Spot-Text..."
[Outro: geldhelden.org]
PROMPT;

        return GAMI_Claude_Client::ask($prompt, GAMI_Claude_Client::get_agent_system_prompt(), 1024);
    }
}
