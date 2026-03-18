<?php
defined('ABSPATH') || exit;

/**
 * Cross-Platform Learning Engine.
 * Erkennt Muster aus Performance-Daten, speichert Learnings
 * und überträgt diese auf andere Plattformen.
 */
class GAMI_Learning_Engine {

    const MIN_CONFIDENCE = 75.0;  // Mindest-Konfidenz zum Übertragen
    const MIN_IMPRESSIONS = 500;  // Mindest-Daten für ein Learning
    const MIN_CONVERSIONS = 20;   // Mindest-Conversions für ein LP-Learning

    /**
     * Haupt-Analyse: Läuft täglich, extrahiert neue Learnings aus Performance-Daten.
     */
    public static function run_analysis(): void {
        self::analyze_visual_patterns();
        self::analyze_copy_patterns();
        self::analyze_angle_performance();
        self::analyze_platform_timing();
        self::analyze_product_funnel_fit();
        self::transfer_learnings_to_platforms();
    }

    /**
     * Visuelle Muster (Farben, Medientypen, Bildstile)
     */
    private static function analyze_visual_patterns(): void {
        global $wpdb;
        $ads_t  = GAMI_Database::get_table('ads');
        $stats_t = GAMI_Database::get_table('ad_stats');

        // Vergleich: Medientypen (video vs bild vs text)
        $results = $wpdb->get_results("
            SELECT a.platform, a.media_type,
                   AVG(s.ctr) as avg_ctr,
                   AVG(s.cpl) as avg_cpl,
                   SUM(s.impressions) as total_impressions,
                   SUM(s.conversions) as total_conversions,
                   COUNT(*) as num_ads
            FROM $ads_t a
            JOIN $stats_t s ON s.ad_id = a.id
            WHERE s.impressions > 100
            GROUP BY a.platform, a.media_type
            HAVING total_impressions > " . self::MIN_IMPRESSIONS . "
            ORDER BY a.platform, avg_cpl ASC
        ");

        if (empty($results)) return;

        // Claude analysiert die Muster
        $data_json = json_encode($results, JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
Analysiere diese Medientyp-Performance-Daten über Plattformen hinweg und extrahiere signifikante Learnings.

Daten (nach Plattform & Medientyp):
$data_json

Identifiziere:
1. Welcher Medientyp (video/bild/text) funktioniert wo am besten?
2. Gibt es plattformübergreifende Muster?
3. Welche Erkenntnisse können von Plattform A auf Plattform B übertragen werden?

Antworte mit JSON-Array von Learnings:
[
  {
    "insight_type": "media_type|color|visual_style",
    "source_platform": "x",
    "target_platforms": ["meta", "taboola"],
    "finding": "Video-Ads haben 34% niedrigere CPL als Bild-Ads",
    "finding_value": "video",
    "lift_percent": 34,
    "confidence": 87
  }
]
Nur Learnings mit Konfidenz >= 70 ausgeben.
PROMPT;

        $learnings = GAMI_Claude_Client::ask_json($prompt);
        if ($learnings) {
            foreach ($learnings as $l) {
                self::save_learning($l);
            }
        }
    }

    /**
     * Copy-Muster (Headlines, Hooks, CTAs)
     */
    private static function analyze_copy_patterns(): void {
        global $wpdb;
        $ads_t   = GAMI_Database::get_table('ads');
        $stats_t = GAMI_Database::get_table('ad_stats');

        $results = $wpdb->get_results("
            SELECT a.platform, a.angle,
                   AVG(s.ctr) as avg_ctr,
                   AVG(s.cpl) as avg_cpl,
                   SUM(s.impressions) as total_impressions,
                   COUNT(*) as num_ads
            FROM $ads_t a
            JOIN $stats_t s ON s.ad_id = a.id
            WHERE a.angle != '' AND s.impressions > 50
            GROUP BY a.platform, a.angle
            HAVING total_impressions > 200
        ");

        if (empty($results)) return;

        $data_json = json_encode($results, JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
Analysiere diese Angle-Performance-Daten (fear/benefit/curiosity/social_proof/urgency) für Geldhelden-Ads.

$data_json

Welche Angles funktionieren auf welchen Plattformen am besten?
Gibt es Muster, die plattformübergreifend gelten?

JSON-Array mit Learnings:
[
  {
    "insight_type": "angle",
    "source_platform": "x",
    "target_platforms": ["meta", "bing"],
    "finding": "Fear-Angle hat 28% höhere CTR als Benefit-Angle",
    "finding_value": "fear",
    "lift_percent": 28,
    "confidence": 82
  }
]
PROMPT;

        $learnings = GAMI_Claude_Client::ask_json($prompt);
        if ($learnings) {
            foreach ($learnings as $l) {
                self::save_learning($l);
            }
        }
    }

    /**
     * Angle-Performance
     */
    private static function analyze_angle_performance(): void {
        // Bereits in analyze_copy_patterns integriert
        // Hier: spezifisch für neue Varianten-Empfehlungen
    }

    /**
     * Timing-Muster (Wochentage, Uhrzeiten)
     */
    private static function analyze_platform_timing(): void {
        global $wpdb;
        $stats_t = GAMI_Database::get_table('ad_stats');
        $ads_t   = GAMI_Database::get_table('ads');

        $results = $wpdb->get_results("
            SELECT a.platform,
                   DAYOFWEEK(s.stat_date) as weekday,
                   AVG(s.ctr) as avg_ctr,
                   AVG(s.cpl) as avg_cpl,
                   SUM(s.impressions) as total_imp
            FROM $stats_t s
            JOIN $ads_t a ON a.id = s.ad_id
            GROUP BY a.platform, weekday
            HAVING total_imp > 1000
            ORDER BY a.platform, avg_cpl ASC
        ");

        if (empty($results)) return;

        $data_json = json_encode($results, JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
Analysiere Wochentag-Performance für Geldhelden-Ads (1=Sonntag, 7=Samstag).
Zielgruppe: Deutschsprachig, 45-70 Jahre, berufstätig oder Rentner.

$data_json

Extrahiere signifikante Timing-Learnings. JSON-Array:
[
  {
    "insight_type": "timing",
    "source_platform": "x",
    "target_platforms": ["meta", "google"],
    "finding": "Mittwoch und Donnerstag haben 22% niedrigere CPL",
    "finding_value": "wednesday_thursday",
    "lift_percent": 22,
    "confidence": 78
  }
]
PROMPT;

        $learnings = GAMI_Claude_Client::ask_json($prompt);
        if ($learnings) {
            foreach ($learnings as $l) {
                self::save_learning($l);
            }
        }
    }

    /**
     * Produkt-Funnel-Fit (welches Produkt auf welcher Plattform)
     */
    private static function analyze_product_funnel_fit(): void {
        global $wpdb;
        $campaigns_t = GAMI_Database::get_table('campaigns');
        $products_t  = GAMI_Database::get_table('products');

        $results = $wpdb->get_results("
            SELECT c.platform, p.type as product_type,
                   AVG(c.roas) as avg_roas,
                   SUM(c.total_spend) as total_spend,
                   COUNT(*) as num_campaigns
            FROM $campaigns_t c
            JOIN $products_t p ON p.id = c.product_id
            WHERE c.total_spend > 50
            GROUP BY c.platform, p.type
            ORDER BY avg_roas DESC
        ");

        if (empty($results)) return;

        $data_json = json_encode($results, JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
Analysiere welche Produkt-Typen (webinar/buch/kurs/membership) auf welchen Plattformen den besten ROAS erzielen.

$data_json

Produkt-Typen:
- webinar = kostenlose Webinare (niedrige Einstiegshürde, ideal für Cold Traffic)
- buch = kostenloses Buch + Versandkosten
- kurs = bezahlte Online-Kurse (€297-€997)
- membership = Academy Pro (monatlich/jährlich)

JSON-Array:
[
  {
    "insight_type": "product_platform_fit",
    "source_platform": "x",
    "target_platforms": ["meta", "telegram_ads"],
    "finding": "Webinar-Anmeldungen auf X haben 3.7x ROAS, Kurse nur 1.2x — Cold Traffic → Webinar priorisieren",
    "finding_value": "webinar_for_cold_traffic",
    "lift_percent": 208,
    "confidence": 91
  }
]
PROMPT;

        $learnings = GAMI_Claude_Client::ask_json($prompt);
        if ($learnings) {
            foreach ($learnings as $l) {
                self::save_learning($l);
            }
        }
    }

    /**
     * Überträgt bewährte Learnings als Empfehlungen an andere Plattformen.
     */
    private static function transfer_learnings_to_platforms(): void {
        global $wpdb;
        $t = GAMI_Database::get_table('learnings');

        // Neue, noch nicht übertragene Learnings
        $learnings = $wpdb->get_results("
            SELECT * FROM $t
            WHERE status = 'new' AND confidence >= " . self::MIN_CONFIDENCE . "
            ORDER BY confidence DESC
        ");

        foreach ($learnings as $learning) {
            $targets = explode(',', $learning->target_platforms);
            $applied = [];

            foreach ($targets as $platform) {
                $platform = trim($platform);
                if (!$platform) continue;

                // Anwenden bedeutet: Beim nächsten Ad-Generierung wird dieses Learning berücksichtigt
                // (wird automatisch in GAMI_Ad_Generator::generate() geladen)
                $applied[] = $platform;
            }

            if (!empty($applied)) {
                GAMI_Database::update('learnings', [
                    'status'            => 'applied',
                    'applied_at'        => current_time('mysql'),
                    'applied_platforms' => implode(',', $applied),
                ], ['id' => $learning->id]);
            }
        }
    }

    /**
     * Gibt anwendbare Learnings für eine Plattform zurück.
     */
    public static function get_applicable_learnings(string $platform, int $product_id = 0): array {
        global $wpdb;
        $t = GAMI_Database::get_table('learnings');

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $t
            WHERE (source_platform = %s OR target_platforms LIKE %s)
            AND status IN ('new', 'applied')
            AND confidence >= %f
            ORDER BY confidence DESC
            LIMIT 20
        ", $platform, '%' . $platform . '%', self::MIN_CONFIDENCE));
    }

    /**
     * Speichert ein Learning, verhindert Duplikate.
     */
    private static function save_learning(array $l): void {
        global $wpdb;
        $t = GAMI_Database::get_table('learnings');

        // Duplikat-Check
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t WHERE insight_type = %s AND source_platform = %s AND finding_value = %s",
            $l['insight_type'] ?? '',
            $l['source_platform'] ?? '',
            $l['finding_value'] ?? ''
        ));

        if ($existing) {
            // Update Konfidenz wenn höher
            if (($l['confidence'] ?? 0) > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $t SET confidence = GREATEST(confidence, %f), finding = %s WHERE id = %d",
                    $l['confidence'],
                    $l['finding'] ?? '',
                    $existing
                ));
            }
            return;
        }

        GAMI_Database::insert('learnings', [
            'insight_type'    => $l['insight_type'] ?? '',
            'source_platform' => $l['source_platform'] ?? '',
            'target_platforms' => is_array($l['target_platforms']) ? implode(',', $l['target_platforms']) : ($l['target_platforms'] ?? ''),
            'finding'         => $l['finding'] ?? '',
            'finding_value'   => $l['finding_value'] ?? '',
            'lift_percent'    => $l['lift_percent'] ?? 0,
            'confidence'      => $l['confidence'] ?? 0,
            'status'          => 'new',
        ]);
    }

    /**
     * Wöchentliche Zusammenfassung der Top-Learnings für Marco
     */
    public static function get_weekly_summary(): string {
        global $wpdb;
        $t = GAMI_Database::get_table('learnings');

        $learnings = $wpdb->get_results("
            SELECT * FROM $t
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY confidence DESC
            LIMIT 10
        ");

        if (empty($learnings)) return 'Keine neuen Learnings diese Woche.';

        $prompt = "Fasse diese Cross-Platform-Marketing-Learnings in einer prägnanten deutschen Zusammenfassung für Marco zusammen. Max 500 Wörter, bulletpoints, mit konkreten Handlungsempfehlungen:\n\n";
        foreach ($learnings as $l) {
            $prompt .= "- [{$l->source_platform} → {$l->target_platforms}] {$l->finding} (Lift: +{$l->lift_percent}%, Konfidenz: {$l->confidence}%)\n";
        }

        return GAMI_Claude_Client::ask($prompt) ?? 'Analyse nicht verfügbar.';
    }
}
