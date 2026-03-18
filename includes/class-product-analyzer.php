<?php
defined('ABSPATH') || exit;

/**
 * Analysiert eine Produkt-URL und extrahiert USPs, Zielgruppen, Angles.
 * Claude liest die Seite und erstellt die Kampagnenbasis.
 */
class GAMI_Product_Analyzer {

    /**
     * Vollständige Produktanalyse aus URL.
     * Speichert Ergebnis in der DB und gibt Produkt-ID zurück.
     */
    public static function analyze_url(string $url, string $extra_context = ''): ?int {
        // Seite scrapen
        $html = self::fetch_page($url);
        if (!$html) return null;

        $text = self::html_to_text($html);

        // Claude analysiert
        $prompt = <<<PROMPT
Analysiere diese Produktseite von Geldhelden und erstelle eine vollständige Marketing-Grundlage.

URL: $url

Seiteninhalt:
$text

Zusätzlicher Kontext vom User: $extra_context

Erstelle JSON mit folgender Struktur:
{
  "name": "Produktname",
  "type": "webinar|buch|kurs|membership|lead_magnet|podcast|sonstiges",
  "core_promise": "Die Kernaussage in einem Satz",
  "target_audience": "Beschreibung der Zielgruppe",
  "usps": ["USP1", "USP2", "USP3"],
  "pain_points": ["Problem das gelöst wird 1", "Problem 2"],
  "price": "kostenlos|Versandkosten|€X",
  "angles": {
    "fear": "Fear-basierter Hook (max 120 Zeichen)",
    "benefit": "Benefit-basierter Hook (max 120 Zeichen)",
    "curiosity": "Neugier-basierter Hook (max 120 Zeichen)",
    "social_proof": "Social-Proof-Hook (max 120 Zeichen)",
    "urgency": "Dringlichkeits-Hook (max 120 Zeichen)"
  },
  "best_platforms": ["x", "meta", "google", "telegram_ads", "bing", "taboola"],
  "funnel_stage": "cold|warm|hot",
  "cta_suggestions": ["CTA 1", "CTA 2", "CTA 3"],
  "keywords_google": ["keyword1", "keyword2"],
  "negative_keywords_google": ["keyword_ausschliessen1"],
  "estimated_cpl_eur": 8
}
PROMPT;

        $result = GAMI_Claude_Client::ask_json($prompt, GAMI_Claude_Client::get_agent_system_prompt());
        if (!$result) return null;

        $id = GAMI_Database::insert('products', [
            'url'            => $url,
            'name'           => $result['name'] ?? '',
            'type'           => $result['type'] ?? 'sonstiges',
            'extracted_usps' => json_encode($result['usps'] ?? []),
            'angles_json'    => json_encode($result),
            'raw_content'    => substr($text, 0, 50000),
        ]);

        return $id ?: null;
    }

    private static function fetch_page(string $url): ?string {
        $response = wp_remote_get($url, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        ]);
        if (is_wp_error($response)) return null;
        if (wp_remote_retrieve_response_code($response) !== 200) return null;
        return wp_remote_retrieve_body($response);
    }

    private static function html_to_text(string $html): string {
        // Scripts + Styles entfernen
        $html = preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $html = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $html);
        $html = preg_replace('/<nav[^>]*>[\s\S]*?<\/nav>/i', '', $html);
        $html = preg_replace('/<footer[^>]*>[\s\S]*?<\/footer>/i', '', $html);
        // HTML → Text
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        // Max 15.000 Zeichen für Claude
        return substr($text, 0, 15000);
    }

    public static function get_product(int $id): ?object {
        return GAMI_Database::get_row('products', 'id = %d', [$id]);
    }

    public static function get_product_data(int $id): ?array {
        $product = self::get_product($id);
        if (!$product) return null;
        $angles = json_decode($product->angles_json, true);
        return $angles;
    }
}
