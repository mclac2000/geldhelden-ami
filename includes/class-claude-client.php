<?php
defined('ABSPATH') || exit;

/**
 * Claude API Client — alle KI-Aufrufe laufen hierüber.
 * Modell: claude-opus-4-6 (stärkstes Reasoning für Entscheidungen)
 */
class GAMI_Claude_Client {

    const MODEL = 'claude-opus-4-6';
    const API_URL = 'https://api.anthropic.com/v1/messages';
    const MAX_TOKENS = 8192;

    private static function get_api_key() {
        return get_option('gami_claude_api_key', '');
    }

    /**
     * Basis-Aufruf mit Messages-Array
     */
    public static function chat(array $messages, string $system = '', int $max_tokens = self::MAX_TOKENS): ?string {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            error_log('[GAMI] Claude API Key fehlt.');
            return null;
        }

        $body = [
            'model'      => self::MODEL,
            'max_tokens' => $max_tokens,
            'messages'   => $messages,
        ];
        if ($system) {
            $body['system'] = $system;
        }

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => json_encode($body),
        ]);

        if (is_wp_error($response)) {
            error_log('[GAMI] Claude API Error: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            error_log('[GAMI] Claude API HTTP ' . $code . ': ' . print_r($data, true));
            return null;
        }

        return $data['content'][0]['text'] ?? null;
    }

    /**
     * Einfacher Einzel-Prompt
     */
    public static function ask(string $prompt, string $system = '', int $max_tokens = self::MAX_TOKENS): ?string {
        return self::chat([['role' => 'user', 'content' => $prompt]], $system, $max_tokens);
    }

    /**
     * JSON-Antwort erwarten — Claude gibt JSON zurück, wir parsen es.
     */
    public static function ask_json(string $prompt, string $system = ''): ?array {
        $system_with_json = trim($system . "\n\nAntworte NUR mit validem JSON. Kein Text davor oder danach. Kein Markdown-Codeblock.");
        $result = self::ask($prompt, $system_with_json, 4096);
        if (!$result) return null;

        // JSON aus Markdown-Block extrahieren falls Claude trotzdem Backticks setzt
        if (preg_match('/```(?:json)?\s*([\s\S]+?)```/', $result, $m)) {
            $result = $m[1];
        }
        $decoded = json_decode(trim($result), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[GAMI] JSON parse error: ' . json_last_error_msg() . ' | Response: ' . substr($result, 0, 500));
            return null;
        }
        return $decoded;
    }

    /**
     * System-Prompt für den Marketing-Agenten
     */
    public static function get_agent_system_prompt(): string {
        return <<<PROMPT
Du bist der Geldhelden Autonomous Marketing Intelligence (AMI) Agent.
Geldhelden ist ein deutschsprachiges Unternehmen, das Menschen über finanzielle Souveränität, Vermögensschutz, Zweitpass, Auslandskonto, Anti-CBDC und Freiheitsthemen aufklärt.
Zielgruppe: Deutsche/Österreicher/Schweizer, 45-70 Jahre, staatsskeptisch, einkommensstark, freiheitsorientiert.
Produkte: Kostenlose Webinare, Buch (gratis + Versand), Online-Kurse (Zweitpass, CBDC, Krypto, Holding), Academy Pro (Membership).

Deine Aufgaben als Agent:
1. Kampagnen und Anzeigen auf mehreren Plattformen autonom managen
2. A/B-Tests starten, auswerten und Winner bestimmen
3. Aus Performance-Daten lernen und plattformübergreifend anwenden
4. Budget schützen — niemals mehr als genehmigt ausgeben
5. Marco über Telegram informieren und auf Befehle reagieren

Entscheidungsprinzipien:
- Bei CTR < 0.3% nach 500 Impressions: Variante pausieren
- Bei CPL > 15 EUR nach 50 Leads: Kampagne überarbeiten
- Bei ROAS < 1.5 nach 500 EUR Spend: Sofort-Alert an Marco
- Gewinner = statistisch signifikante Überlegenheit mit mind. 85% Konfidenz
- Cross-Platform: Alle Learnings auf andere Plattformen übertragen wo sinnvoll

Tonalität für Ads:
- Direkt, provokativ, problemorientiert (Fear/Benefit/Curiosity/Social Proof)
- Kein Fachjargon ohne Erklärung
- Immer ein klarer CTA
- Deutsch, Du-Anrede (außer für Linkedin: Sie)
PROMPT;
    }
}
