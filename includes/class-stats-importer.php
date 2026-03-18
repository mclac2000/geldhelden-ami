<?php
defined('ABSPATH') || exit;

/**
 * Stats Importer — Manueller Import + CSV-Upload von Performance-Daten.
 * Ermöglicht Test des gesamten Systems (Learning Engine, A/B-Tests, Agent)
 * ohne echte Plattform-API-Credentials.
 *
 * Import-Quellen:
 * 1. Manuelles Formular (einzelne Einträge)
 * 2. CSV-Upload (Massenimport aus Excel/Google Sheets)
 * 3. Claude-generierte Testdaten (realistisches Szenario)
 */
class GAMI_Stats_Importer {

    /**
     * Einzelnen Stats-Datensatz importieren
     */
    public static function import_single(array $data): bool {
        $ad_id = intval($data['ad_id'] ?? 0);
        if (!$ad_id) return false;

        $impressions = intval($data['impressions'] ?? 0);
        $clicks      = intval($data['clicks'] ?? 0);
        $spend       = floatval($data['spend'] ?? 0);
        $conversions = intval($data['conversions'] ?? 0);

        $ctr = $impressions > 0 ? round($clicks / $impressions * 100, 4) : 0;
        $cpl = $conversions > 0 ? round($spend / $conversions, 2) : 0;
        $revenue = floatval($data['revenue'] ?? 0);
        $roas = $spend > 0 ? round($revenue / $spend, 4) : 0;

        $ad = GAMI_Database::get_row('ads', 'id = %d', [$ad_id]);
        if (!$ad) return false;

        global $wpdb;
        $t    = GAMI_Database::get_table('ad_stats');
        $date = sanitize_text_field($data['stat_date'] ?? current_time('Y-m-d'));

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t WHERE ad_id = %d AND stat_date = %s", $ad_id, $date
        ));

        $row = [
            'ad_id'       => $ad_id,
            'stat_date'   => $date,
            'platform'    => $ad->platform,
            'impressions' => $impressions,
            'clicks'      => $clicks,
            'ctr'         => $ctr,
            'spend'       => $spend,
            'conversions' => $conversions,
            'cpl'         => $cpl,
            'revenue'     => $revenue,
            'roas'        => $roas,
        ];

        if ($existing) {
            return (bool) GAMI_Database::update('ad_stats', $row, ['id' => $existing]);
        }
        return (bool) GAMI_Database::insert('ad_stats', $row);
    }

    /**
     * CSV importieren.
     * Spalten: ad_id, stat_date, impressions, clicks, spend, conversions, revenue
     */
    public static function import_csv(string $file_path): array {
        $results = ['imported' => 0, 'errors' => 0, 'rows' => []];

        if (!file_exists($file_path)) {
            $results['rows'][] = 'Datei nicht gefunden: ' . $file_path;
            return $results;
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) return $results;

        $header = null;
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if (!$header) {
                $header = array_map('trim', $row);
                continue;
            }
            $data = array_combine($header, $row);
            if (self::import_single($data)) {
                $results['imported']++;
                $results['rows'][] = "OK: Ad #{$data['ad_id']} am {$data['stat_date']}";
            } else {
                $results['errors']++;
                $results['rows'][] = "FEHLER: Ad #{$data['ad_id']}";
            }
        }
        fclose($handle);
        return $results;
    }

    /**
     * Realistische Test-Datensätze generieren lassen (via Claude).
     * Erstellt 30 Tage Performance-Daten für alle vorhandenen Ads.
     * Dabei: verschiedene Outcomes um A/B-Tests und Learning Engine zu testen.
     */
    public static function generate_test_data(int $days = 30): array {
        global $wpdb;
        $ads = GAMI_Database::get_results('ads', '1=1', [], 'LIMIT 20');

        if (empty($ads)) {
            return ['error' => 'Keine Ads in der Datenbank. Erst Produkt analysieren und Ads generieren.'];
        }

        $ads_info = [];
        foreach ($ads as $ad) {
            $ads_info[] = [
                'id'       => $ad->id,
                'platform' => $ad->platform,
                'angle'    => $ad->angle,
                'variant'  => $ad->variant_name,
            ];
        }

        $prompt = <<<PROMPT
Generiere realistische Marketing-Performance-Daten für $days Tage für diese Geldhelden-Ads.

Ads:
{$wpdb->prepare('%s', json_encode($ads_info))}

Regeln für realistische Daten:
- CTR: X/Twitter 0.1-1.5%, Google 2-8%, Meta 0.5-2%, Taboola 0.05-0.3%
- CPL: Webinar-Ads: 3-15€, Buch-Ads: 5-20€
- Trend: Neuere Ads starten schlechter, verbessern sich oder sterben
- Fear/Urgency-Angles performen tendenziell 15-30% besser als Benefit
- Video besser als Bild besser als Text (außer Google)
- Wochenende: 20-30% weniger Impressions
- Eine Variante pro Plattform sollte deutlich besser sein (Gewinner für A/B-Test)

Antworte mit JSON-Array:
[
  {
    "ad_id": 1,
    "stat_date": "2026-02-17",
    "impressions": 1240,
    "clicks": 18,
    "spend": 9.40,
    "conversions": 3,
    "revenue": 0
  },
  ...
]

Erstelle für JEDEN Ad JEDEN Tag einen Datensatz. Heute ist der {$days}. Tag.
PROMPT;

        $data = GAMI_Claude_Client::ask_json(
            str_replace($wpdb->prepare('%s', ''), '', $prompt),
        );

        if (!$data || !is_array($data)) {
            return ['error' => 'Claude konnte keine Testdaten generieren.'];
        }

        $imported = 0;
        $errors   = 0;
        foreach ($data as $row) {
            if (self::import_single($row)) {
                $imported++;
            } else {
                $errors++;
            }
        }

        // Ads auf 'active' setzen
        $wpdb->query("UPDATE " . GAMI_Database::get_table('ads') . " SET status = 'active' WHERE status = 'draft'");

        return ['imported' => $imported, 'errors' => $errors, 'total_rows' => count($data)];
    }

    /**
     * Schnelle Demo-Daten: 10 Tage, Fear-Angle als Winner
     * Für schnellen System-Test ohne Claude-Aufruf
     */
    public static function generate_quick_demo(): int {
        global $wpdb;
        $ads = GAMI_Database::get_results('ads', '1=1', [], 'LIMIT 10');
        if (empty($ads)) return 0;

        $imported = 0;
        $base_date = strtotime('-10 days');

        foreach ($ads as $ad) {
            // Fear/Urgency performt besser
            $ctr_base = in_array($ad->angle, ['fear', 'urgency']) ? 0.008 : 0.004;
            $cpl_base = in_array($ad->angle, ['fear', 'urgency']) ? 6.50 : 11.00;

            // Plattform-Anpassung
            if ($ad->platform === 'google') { $ctr_base *= 4; }
            if ($ad->platform === 'taboola') { $ctr_base *= 0.25; }

            for ($day = 0; $day < 10; $day++) {
                $date = date('Y-m-d', $base_date + $day * 86400);
                $is_weekend = in_array(date('N', strtotime($date)), [6, 7]);

                $impressions = intval(rand(800, 2000) * ($is_weekend ? 0.7 : 1.0));
                $clicks      = intval($impressions * $ctr_base * (1 + (rand(-20, 30) / 100)));
                $spend       = round($clicks * (rand(50, 150) / 100), 2);
                $conversions = intval($clicks * rand(15, 35) / 100);
                $revenue     = round($conversions * rand(8, 25), 2);

                if (self::import_single([
                    'ad_id'       => $ad->id,
                    'stat_date'   => $date,
                    'impressions' => $impressions,
                    'clicks'      => $clicks,
                    'spend'       => $spend,
                    'conversions' => $conversions,
                    'revenue'     => $revenue,
                ])) {
                    $imported++;
                }
            }
        }

        // Ads aktivieren
        $wpdb->query("UPDATE " . GAMI_Database::get_table('ads') . " SET status = 'active' WHERE status = 'draft'");

        return $imported;
    }
}
