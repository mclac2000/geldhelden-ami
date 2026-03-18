<?php
defined('ABSPATH') || exit;

/**
 * Generiert Ad-Varianten für alle Plattformen basierend auf Produktanalyse.
 * Claude erstellt plattformspezifische Texte unter Berücksichtigung von Cross-Platform-Learnings.
 */
class GAMI_Ad_Generator {

    // Zeichenlimits je Plattform
    const LIMITS = [
        'x'             => ['headline' => 0,    'body' => 280,  'cta' => 0],
        'meta'          => ['headline' => 40,   'body' => 125,  'cta' => 20],
        'instagram'     => ['headline' => 40,   'body' => 125,  'cta' => 20],
        'google'        => ['headline' => 30,   'body' => 90,   'cta' => 15],
        'bing'          => ['headline' => 30,   'body' => 90,   'cta' => 15],
        'pinterest'     => ['headline' => 100,  'body' => 500,  'cta' => 20],
        'tiktok'        => ['headline' => 0,    'body' => 100,  'cta' => 0],
        'linkedin'      => ['headline' => 70,   'body' => 150,  'cta' => 20],
        'youtube'       => ['headline' => 15,   'body' => 0,    'cta' => 10],
        'telegram_ads'  => ['headline' => 0,    'body' => 160,  'cta' => 0],
        'taboola'       => ['headline' => 75,   'body' => 0,    'cta' => 0],
        'outbrain'      => ['headline' => 75,   'body' => 0,    'cta' => 0],
        'spotify'       => ['headline' => 20,   'body' => 100,  'cta' => 20],
        'whatsapp'      => ['headline' => 0,    'body' => 1000, 'cta' => 0],
    ];

    // Plattform-Besonderheiten
    const PLATFORM_NOTES = [
        'x'            => 'Twitter/X: Direkt, meinungsstark, keine Emojis übertreiben. Hashtags sparsam (max 2). Provokation erlaubt.',
        'meta'         => 'Facebook: Emotional, Story-based, Emojis erlaubt. Kein "Facebook" im Text erwähnen.',
        'instagram'    => 'Instagram: Visuell, inspirierend, viele Emojis OK. Story-Format bevorzugt.',
        'google'       => 'Google Ads: Keyword-relevant, klar, benefit-fokussiert. Keine Ausrufezeichen in Headlines.',
        'bing'         => 'Bing/Microsoft Ads: Wie Google, aber etwas formeller. Ältere Zielgruppe.',
        'pinterest'    => 'Pinterest: Langform, inspirierend, Finanztipps-Format. Keywords wichtig.',
        'tiktok'       => 'TikTok: Sehr kurz, hook in Sekunde 1, Trend-Sprache, aber seriös bleiben.',
        'linkedin'     => 'LinkedIn: Professionell, Sie-Anrede, Business-Fokus (Holding, LLC, Vermögensschutz).',
        'youtube'      => 'YouTube Pre-Roll: Erste 5 Sek entscheidend — sofortiger Hook. Skip-resistant.',
        'telegram_ads' => 'Telegram Sponsored: Max 160 Zeichen, ein Link, sehr präzise Botschaft. Keine Emojis.',
        'taboola'      => 'Taboola Native: Headline wie Nachrichtenartikel ("Warum immer mehr Deutsche..."). Neugier-getrieben.',
        'outbrain'     => 'Outbrain Native: Wie Taboola, journalistischer Stil.',
        'spotify'      => 'Spotify Audio-Werbung: Text wird vorgelesen. Natürliche Sprache, keine Listen.',
        'whatsapp'     => 'WhatsApp Broadcast: Persönlich, Direktsprache, max 3 Absätze, einen Link.',
    ];

    /**
     * Generiert Ad-Varianten für eine Plattform + Produkt.
     * Berücksichtigt Cross-Platform-Learnings automatisch.
     */
    public static function generate(int $product_id, string $platform, int $num_variants = 5, ?int $campaign_id = null): array {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return [];

        // Aktuelle Learnings für diese Plattform laden
        $learnings = GAMI_Learning_Engine::get_applicable_learnings($platform, $product_id);
        $learnings_text = self::format_learnings($learnings);

        $limits = self::LIMITS[$platform] ?? self::LIMITS['meta'];
        $notes  = self::PLATFORM_NOTES[$platform] ?? '';

        $prompt = <<<PROMPT
Erstelle $num_variants verschiedene Ad-Varianten für die Plattform "{$platform}" für folgendes Geldhelden-Produkt.

PRODUKT:
Name: {$product_data['name']}
Typ: {$product_data['type']}
Kernversprechen: {$product_data['core_promise']}
Zielgruppe: {$product_data['target_audience']}
USPs: {$product_data['usps']}
Schmerz-Punkte: {$product_data['pain_points']}
Preis: {$product_data['price']}

BEWÄHRTE ANGLES (nutze alle, je eine Variante pro Angle):
Fear: {$product_data['angles']['fear']}
Benefit: {$product_data['angles']['benefit']}
Curiosity: {$product_data['angles']['curiosity']}
Social Proof: {$product_data['angles']['social_proof']}
Urgency: {$product_data['angles']['urgency']}

PLATTFORM-SPEZIFIKA FÜR {$platform}:
$notes
Zeichenlimits: Headline={$limits['headline']}, Body={$limits['body']}, CTA={$limits['cta']} (0 = kein Limit/nicht vorhanden)

BEWÄHRTE LEARNINGS (aus bisherigen Kampagnen — diese Erkenntnisse haben funktioniert!):
$learnings_text

Erstelle JSON-Array mit $num_variants Varianten:
[
  {
    "variant_name": "A",
    "angle": "fear|benefit|curiosity|social_proof|urgency",
    "headline": "Headline (leer wenn nicht relevant)",
    "body_text": "Ad-Text",
    "cta_text": "CTA (leer wenn nicht relevant)",
    "reasoning": "Warum dieser Angle/Text gut funktionieren sollte"
  }
]
PROMPT;

        $variants = GAMI_Claude_Client::ask_json($prompt, GAMI_Claude_Client::get_agent_system_prompt());
        if (!$variants || !is_array($variants)) return [];

        $saved_ids = [];
        foreach ($variants as $v) {
            $id = GAMI_Database::insert('ads', [
                'campaign_id'   => $campaign_id,
                'product_id'    => $product_id,
                'platform'      => $platform,
                'variant_name'  => $v['variant_name'] ?? 'A',
                'headline'      => substr($v['headline'] ?? '', 0, 500),
                'body_text'     => $v['body_text'] ?? '',
                'cta_text'      => substr($v['cta_text'] ?? '', 0, 100),
                'angle'         => $v['angle'] ?? '',
                'status'        => 'draft',
            ]);
            if ($id) $saved_ids[] = $id;
        }

        return $saved_ids;
    }

    /**
     * Generiert WhatsApp Sprachnachricht-Skript
     */
    public static function generate_whatsapp_voice_script(int $product_id, string $occasion = 'webinar_reminder'): ?string {
        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        if (!$product_data) return null;

        $prompt = <<<PROMPT
Schreibe ein WhatsApp Sprachnachricht-Skript für Marco (Gründer Geldhelden).
Anlass: $occasion
Produkt: {$product_data['name']}

Das Skript soll:
- 30-45 Sekunden lang sein (gesprochen)
- Persönlich und direkt klingen (Marco spricht selbst)
- Einen klaren CTA haben
- Natürlich klingen (nicht wie gelesen)
- Mit Pausen und Betonungshinweisen in [eckigen Klammern]

Beispiel-Format:
"Hey, [kurze Pause] hier ist Marco von Geldhelden. Ich wollte dich kurz persönlich informieren, weil [Grund]..."

Erstelle das vollständige Skript.
PROMPT;

        return GAMI_Claude_Client::ask($prompt, GAMI_Claude_Client::get_agent_system_prompt(), 2048);
    }

    private static function format_learnings(array $learnings): string {
        if (empty($learnings)) return 'Noch keine gespeicherten Learnings für diese Plattform.';
        $lines = [];
        foreach ($learnings as $l) {
            $lines[] = "- [{$l->insight_type}] {$l->finding} (Lift: +{$l->lift_percent}%, Konfidenz: {$l->confidence}%)";
        }
        return implode("\n", $lines);
    }
}
