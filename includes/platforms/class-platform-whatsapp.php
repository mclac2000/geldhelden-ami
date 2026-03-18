<?php
defined('ABSPATH') || exit;

/**
 * WhatsApp Business Cloud API Integration
 * Supports:
 * - Broadcast Messages (Text + Voice-Drop-Skripte)
 * - Template Messages (für Webinar-Reminder)
 * - Click-to-WhatsApp Ads (via Meta-Plattform)
 */
class GAMI_Platform_Whatsapp extends GAMI_Platform_Base {

    const API_BASE = 'https://graph.facebook.com/v19.0';

    public function get_key(): string { return 'whatsapp'; }
    public function get_name(): string { return 'WhatsApp Business'; }

    private function get_phone_id(): string { return $this->get_option('phone_number_id'); }
    private function get_token(): string    { return $this->get_option('access_token'); }
    private function get_waba_id(): string  { return $this->get_option('waba_id'); }

    private function wa_request(string $method, string $endpoint, array $data = []): ?array {
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        return $this->request($method, $url, $data, [
            'Authorization' => 'Bearer ' . $this->get_token(),
        ]);
    }

    public function fetch_campaign_stats(): array {
        // WhatsApp hat kein direktes Ad-Stats-System — Tracking via UTM-Links
        return [];
    }

    public function create_campaign(array $data): ?string {
        // WhatsApp-Kampagnen = Broadcast-Listen oder Click-to-WA via Meta
        return null;
    }

    public function create_ad(array $ad_data): ?string {
        return null;
    }

    public function pause_campaign(int $campaign_id): bool { return false; }
    public function increase_budget(int $campaign_id, float $multiplier): bool { return false; }

    /**
     * Text-Nachricht an einzelnen Kontakt senden
     */
    public function send_text(string $phone, string $message): bool {
        $phone_id = $this->get_phone_id();
        $result = $this->wa_request('POST', "{$phone_id}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);
        return isset($result['messages'][0]['id']);
    }

    /**
     * Template-Nachricht senden (für Webinar-Reminder, vorher Meta-genehmigt)
     */
    public function send_template(string $phone, string $template_name, array $params = []): bool {
        $phone_id = $this->get_phone_id();

        $components = [];
        if (!empty($params)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => $p], $params),
            ];
        }

        $result = $this->wa_request('POST', "{$phone_id}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'       => $template_name,
                'language'   => ['code' => 'de'],
                'components' => $components,
            ],
        ]);
        return isset($result['messages'][0]['id']);
    }

    /**
     * Broadcast an Kontaktliste
     * (WhatsApp erlaubt max. 250/Tag für neue Kontakte, unbegrenzt für bestehende)
     */
    public function send_broadcast(array $phone_numbers, string $message, bool $use_template = false, string $template = ''): array {
        $results = ['sent' => 0, 'failed' => 0];

        foreach ($phone_numbers as $phone) {
            $phone = preg_replace('/\D/', '', $phone);
            if (!str_starts_with($phone, '49') && !str_starts_with($phone, '43') && !str_starts_with($phone, '41')) {
                $phone = '49' . ltrim($phone, '0'); // DE als Default
            }

            if ($use_template && $template) {
                $success = $this->send_template($phone, $template);
            } else {
                $success = $this->send_text($phone, $message);
            }

            $success ? $results['sent']++ : $results['failed']++;

            // Rate Limiting: Max 80 Nachrichten pro Sekunde
            if ($results['sent'] % 50 === 0) usleep(100000); // 100ms Pause
        }

        return $results;
    }

    /**
     * Voice-Drop Skript generieren und als Nachricht vorbereiten
     * Marco spricht es auf, wir senden die Audio-URL
     */
    public function send_voice_message(string $phone, string $audio_url): bool {
        $phone_id = $this->get_phone_id();
        $result = $this->wa_request('POST', "{$phone_id}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'audio',
            'audio'             => ['link' => $audio_url],
        ]);
        return isset($result['messages'][0]['id']);
    }

    /**
     * Webinar-Reminder Sequenz (automatisch vor dem Webinar)
     * T-3 Tage: Text "Nächsten Sonntag..."
     * T-1 Tag:  Template "Morgen, 19 Uhr..."
     * T-2h:     Voice Drop (Marco persönlich)
     */
    public function schedule_webinar_reminders(array $registrations, string $webinar_date, string $webinar_topic, int $product_id): void {
        $webinar_time = strtotime($webinar_date);

        // Reminder-Texte via Claude generieren
        $prompt = "Erstelle 3 WhatsApp-Reminder für ein Geldhelden-Webinar:\n"
            . "Thema: $webinar_topic\n"
            . "Datum: $webinar_date\n\n"
            . "Erstelle JSON:\n"
            . '{"r3d": "Text 3 Tage vorher (max 300 Zeichen)", "r1d": "Text 1 Tag vorher (max 200 Zeichen)", "r2h": "Text 2 Stunden vorher (max 160 Zeichen, sehr direkt)"}';

        $texts = GAMI_Claude_Client::ask_json($prompt, GAMI_Claude_Client::get_agent_system_prompt());
        if (!$texts) return;

        foreach ($registrations as $reg) {
            $phone = $reg['phone'] ?? '';
            if (!$phone) continue;

            // Cron-Jobs für Reminder planen
            if (!empty($texts['r3d'])) {
                wp_schedule_single_event($webinar_time - (3 * 86400), 'gami_send_whatsapp', [$phone, $texts['r3d']]);
            }
            if (!empty($texts['r1d'])) {
                wp_schedule_single_event($webinar_time - 86400, 'gami_send_whatsapp', [$phone, $texts['r1d']]);
            }
            if (!empty($texts['r2h'])) {
                wp_schedule_single_event($webinar_time - 7200, 'gami_send_whatsapp', [$phone, $texts['r2h']]);
            }
        }
    }

    /**
     * Voice-Drop Skript generieren (Marco spricht es ein)
     */
    public function generate_voice_script(int $product_id, string $context = 'webinar_reminder'): ?string {
        return GAMI_Ad_Generator::generate_whatsapp_voice_script($product_id, $context);
    }

    /**
     * Webhook-Handler für eingehende WhatsApp-Nachrichten
     */
    public static function handle_webhook(array $payload): void {
        $messages = $payload['entry'][0]['changes'][0]['value']['messages'] ?? [];
        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $text = $msg['text']['body'] ?? '';
            if (!$from || !$text) continue;

            // Einfache Bot-Antworten
            $lower = strtolower($text);
            if (strpos($lower, 'webinar') !== false || strpos($lower, 'anmelden') !== false) {
                $platform = new self();
                $platform->send_text($from, "Hier ist dein Link zum kostenlosen Webinar: " . get_site_url() . "/webinar\n\nBis Sonntag!");
            }
        }
    }
}

// WhatsApp Cron-Action
add_action('gami_send_whatsapp', function ($phone, $message) {
    $wa = new GAMI_Platform_Whatsapp();
    $wa->send_text($phone, $message);
});
