<?php
defined('ABSPATH') || exit;

/**
 * YouTube Ads Platform Integration (via Google Ads API)
 * Kampagnentyp: Video-Kampagne (Instream Skippable, Non-Skippable, Bumper)
 * Besonderheit: Einzige Plattform mit echtem Video-First-Format, Pre-Roll vor Finanz-Videos
 */
class GAMI_Platform_Youtube extends GAMI_Platform_Base {

    // YouTube Ads laufen über Google Ads API — gleiche Credentials
    public function get_key(): string { return 'youtube'; }
    public function get_name(): string { return 'YouTube Ads'; }

    private function get_google(): GAMI_Platform_Google {
        return new GAMI_Platform_Google();
    }

    public function fetch_campaign_stats(): array {
        $google = $this->get_google();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // YouTube-Kampagnen über Google Ads Query Language
        $query = "SELECT campaign.id, campaign.name, metrics.impressions, metrics.video_views,
                         metrics.clicks, metrics.ctr, metrics.cost_micros, metrics.conversions
                  FROM campaign
                  WHERE segments.date = '{$yesterday}'
                  AND campaign.advertising_channel_type = 'VIDEO'
                  AND campaign.status = 'ENABLED'";

        // Nutzt Google-Platform intern
        $result = $google->fetch_campaign_stats_with_query($query);
        if (!$result) return [];

        foreach ($result as &$stat) {
            $stat['platform'] = 'youtube';
        }
        return $result;
    }

    public function create_campaign(array $data): ?string {
        $google = $this->get_google();

        $campaign_data = array_merge($data, [
            'type' => 'VIDEO',
        ]);

        return $google->create_campaign($campaign_data);
    }

    public function create_ad(array $ad_data): ?string {
        // YouTube Ad benötigt Video-Asset auf YouTube
        // Video-URL wird als YouTube-Link gespeichert
        $google = $this->get_google();

        $yt_ad_data = array_merge($ad_data, [
            'video_id'     => $ad_data['youtube_video_id'] ?? '',
            'display_url'  => 'geldhelden.org',
            'final_url'    => $ad_data['landing_page_url'] ?? get_site_url(),
        ]);

        return $google->create_video_ad($yt_ad_data);
    }

    public function pause_campaign(int $campaign_id): bool {
        return $this->get_google()->pause_campaign($campaign_id);
    }

    public function increase_budget(int $campaign_id, float $multiplier): bool {
        return $this->get_google()->increase_budget($campaign_id, $multiplier);
    }

    /**
     * YouTube Pre-Roll-Script generieren (erste 5 Sekunden = entscheidend)
     * Skip-resistant: Hook muss in 5 Sek Interesse wecken
     */
    public static function generate_preroll_script(int $product_id, string $format = 'skippable'): ?string {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return null;

        $hook_time = $format === 'bumper' ? 6 : 5;
        $total_time = $format === 'bumper' ? 6 : ($format === 'non_skippable' ? 15 : 30);

        $prompt = <<<PROMPT
Schreibe ein YouTube {$format} Pre-Roll-Skript für Geldhelden.
Produkt: {$product_data['name']}
Kernversprechen: {$product_data['core_promise']}
Zielgruppe: Deutsche/Österreicher/Schweizer, 45-70 Jahre, staatsskeptisch

WICHTIG: Sekunde 0-{$hook_time} entscheiden ob geskippt wird. Der Hook MUSS sofort fesseln.
Format: {$format} | Länge: {$total_time} Sekunden

[0-{$hook_time} Sek — HOOK, wird VOR dem Skip-Button gesehen]:
"..."

[{$hook_time}-{$total_time} Sek — Content + CTA]:
"..."

Sprecher-Hinweise in (Klammern). Visuell-Beschreibungen in {{Klammern}}.
PROMPT;

        return GAMI_Claude_Client::ask($prompt, GAMI_Claude_Client::get_agent_system_prompt(), 2048);
    }

    /**
     * Passende YouTube-Kanäle für Placement-Targeting finden
     */
    public static function get_placement_channels(): array {
        return [
            'UCHkhzHCnmvb_IgmNzJfKQEg', // Finanzfluss
            'UCjQNbOO5UdQ0Fq9bOxhk1bQ', // Sparkojote
            'UCddiUEpeqJcYeBxX1IVBKvQ', // Krasse Nummer
            'UCVOlRTIqOuMhBFAv3c9phtA', // Finanztip
        ];
    }
}
