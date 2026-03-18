<?php
defined('ABSPATH') || exit;

/**
 * Agent-Core — Zentrale Entscheidungslogik.
 * Steuert alle Loops, trifft Optimierungsentscheidungen
 * und protokolliert alles in der DB.
 */
class GAMI_Agent_Core {

    // Entscheidungs-Schwellenwerte
    const CTR_PAUSE_THRESHOLD       = 0.003;  // < 0.3% CTR bei 500+ Impressions → pausieren
    const CPL_PAUSE_THRESHOLD       = 15.00;  // > 15 EUR CPL nach 50 Conversions → überarbeiten
    const ROAS_ALERT_THRESHOLD      = 1.5;    // < 1.5 ROAS nach 500 EUR Spend → Alert
    const AB_WIN_CONFIDENCE         = 85.0;   // > 85% Konfidenz → Gewinner bestimmen
    const AB_MIN_CONVERSIONS        = 20;     // Mindest-Conversions für A/B-Entscheidung
    const BUDGET_SAFETY_MULTIPLIER  = 1.1;    // Max 10% über Tages-Budget erlaubt

    /**
     * 6-Stunden-Loop: KPI-Check aller aktiven Kampagnen
     */
    public static function run_6h_loop(): void {
        $platforms = self::get_active_platforms();
        foreach ($platforms as $platform_key) {
            $platform = self::get_platform($platform_key);
            if (!$platform) continue;

            // Aktuelle Stats holen
            $stats = $platform->fetch_campaign_stats();
            if (empty($stats)) continue;

            // Stats in DB speichern
            foreach ($stats as $stat) {
                self::save_stats($stat);
            }

            // Budget-Check
            self::check_budget_safety($platform_key, $stats);
        }
    }

    /**
     * Täglicher Loop (07:00 CET): Vollanalyse + Optimierungsentscheidungen
     */
    public static function run_daily_loop(): void {
        $platforms = self::get_active_platforms();
        $decisions = [];

        foreach ($platforms as $platform_key) {
            $platform = self::get_platform($platform_key);
            if (!$platform) continue;

            // Underperformer pausieren
            $paused = self::pause_underperformers($platform_key);
            $decisions = array_merge($decisions, $paused);

            // A/B-Tests auswerten
            $ab_decisions = self::evaluate_ab_tests($platform_key);
            $decisions = array_merge($decisions, $ab_decisions);

            // Gewinner skalieren
            $scaled = self::scale_winners($platform_key);
            $decisions = array_merge($decisions, $scaled);

            // Neue Varianten starten wenn nötig
            self::start_new_variants_if_needed($platform_key);
        }

        // Cross-Platform Learning
        GAMI_Learning_Engine::run_analysis();

        // Tages-Report via Telegram
        if (!empty($decisions)) {
            self::send_daily_report($decisions);
        }
    }

    /**
     * Wöchentlicher Loop (Sonntag 06:00): Vollbericht
     */
    public static function run_weekly_loop(): void {
        $report = self::generate_weekly_report();
        GAMI_Telegram_Interface::send_to_marco($report);
    }

    /**
     * Reagiert auf neuen Produkt-Input (manuell via Dashboard oder Telegram)
     */
    public static function process_new_product(string $url, array $options = []): array {
        $result = ['success' => false, 'product_id' => null, 'ad_ids' => [], 'message' => ''];

        // Produkt analysieren
        $product_id = GAMI_Product_Analyzer::analyze_url($url, $options['context'] ?? '');
        if (!$product_id) {
            $result['message'] = 'Produkt-Analyse fehlgeschlagen.';
            return $result;
        }

        $product_data = GAMI_Product_Analyzer::get_product_data($product_id);
        $platforms = $options['platforms'] ?? $product_data['best_platforms'] ?? ['x', 'meta'];

        $all_ad_ids = [];
        foreach ($platforms as $platform) {
            // Kampagne erstellen
            $campaign_id = GAMI_Database::insert('campaigns', [
                'product_id'   => $product_id,
                'platform'     => $platform,
                'name'         => ($product_data['name'] ?? 'Kampagne') . ' — ' . strtoupper($platform),
                'status'       => 'pending',
                'budget_day'   => $options['budget_day'] ?? 10.00,
            ]);

            // Ads generieren (3 Varianten pro Plattform)
            $ad_ids = GAMI_Ad_Generator::generate($product_id, $platform, 3, $campaign_id);
            $all_ad_ids = array_merge($all_ad_ids, $ad_ids);
        }

        $result['success']    = true;
        $result['product_id'] = $product_id;
        $result['ad_ids']     = $all_ad_ids;
        $result['message']    = "Produkt analysiert. " . count($all_ad_ids) . " Ad-Varianten erstellt für: " . implode(', ', $platforms);

        // Marco informieren
        GAMI_Telegram_Interface::send_to_marco(
            "✅ Neue Kampagne erstellt!\n" .
            "Produkt: {$product_data['name']}\n" .
            "Plattformen: " . implode(', ', $platforms) . "\n" .
            "Varianten: " . count($all_ad_ids) . "\n" .
            "Status: Entwurf — bereit zum Aktivieren"
        );

        return $result;
    }

    /**
     * Pausiert Underperformer basierend auf Schwellenwerten.
     */
    private static function pause_underperformers(string $platform): array {
        global $wpdb;
        $ads_t   = GAMI_Database::get_table('ads');
        $stats_t = GAMI_Database::get_table('ad_stats');
        $decisions = [];

        // Ads mit zu niedriger CTR
        $low_ctr = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.headline, a.variant_name,
                   SUM(s.impressions) as total_imp,
                   AVG(s.ctr) as avg_ctr,
                   AVG(s.cpl) as avg_cpl
            FROM $ads_t a
            JOIN $stats_t s ON s.ad_id = a.id
            WHERE a.platform = %s AND a.status = 'active'
            GROUP BY a.id
            HAVING total_imp > 500 AND avg_ctr < %f
        ", $platform, self::CTR_PAUSE_THRESHOLD));

        foreach ($low_ctr as $ad) {
            $reason = "CTR {$ad->avg_ctr}% < " . (self::CTR_PAUSE_THRESHOLD * 100) . "% bei {$ad->total_imp} Impressions";
            GAMI_Database::update('ads', ['status' => 'paused', 'reason_paused' => $reason], ['id' => $ad->id]);
            self::log_decision('pause_low_ctr', $platform, $ad->id, 'ad', 'pause', $reason);
            $decisions[] = "[$platform] Ad {$ad->variant_name} pausiert: $reason";
        }

        // Ads mit zu hohem CPL
        $high_cpl = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.variant_name,
                   SUM(s.conversions) as total_conv,
                   AVG(s.cpl) as avg_cpl
            FROM $ads_t a
            JOIN $stats_t s ON s.ad_id = a.id
            WHERE a.platform = %s AND a.status = 'active'
            GROUP BY a.id
            HAVING total_conv > 50 AND avg_cpl > %f
        ", $platform, self::CPL_PAUSE_THRESHOLD));

        foreach ($high_cpl as $ad) {
            $reason = "CPL €{$ad->avg_cpl} > €" . self::CPL_PAUSE_THRESHOLD . " bei {$ad->total_conv} Conversions";
            GAMI_Database::update('ads', ['status' => 'paused', 'reason_paused' => $reason], ['id' => $ad->id]);
            self::log_decision('pause_high_cpl', $platform, $ad->id, 'ad', 'pause', $reason);
            $decisions[] = "[$platform] Ad {$ad->variant_name} pausiert: $reason";
        }

        return $decisions;
    }

    /**
     * Wertet A/B-Tests aus und bestimmt Gewinner.
     */
    private static function evaluate_ab_tests(string $platform): array {
        global $wpdb;
        $exp_t   = GAMI_Database::get_table('experiments');
        $stats_t = GAMI_Database::get_table('ad_stats');
        $decisions = [];

        $experiments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $exp_t
            WHERE platform = %s AND status = 'running'
        ", $platform));

        foreach ($experiments as $exp) {
            // Stats für beide Varianten holen
            $a_stats = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(conversions) as convs, AVG(cpl) as cpl, SUM(impressions) as imps
                FROM $stats_t WHERE ad_id = %d
            ", $exp->variant_a_id));

            $b_stats = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(conversions) as convs, AVG(cpl) as cpl, SUM(impressions) as imps
                FROM $stats_t WHERE ad_id = %d
            ", $exp->variant_b_id));

            if (!$a_stats || !$b_stats) continue;
            if ($a_stats->convs < self::AB_MIN_CONVERSIONS || $b_stats->convs < self::AB_MIN_CONVERSIONS) continue;

            // Statistische Signifikanz berechnen (vereinfacht via Claude)
            $prompt = "Berechne ob dieser A/B-Test einen statistisch signifikanten Gewinner hat:\n"
                . "Variante A: {$a_stats->convs} Conversions, CPL €{$a_stats->cpl}, {$a_stats->imps} Impressions\n"
                . "Variante B: {$b_stats->convs} Conversions, CPL €{$b_stats->cpl}, {$b_stats->imps} Impressions\n"
                . "Metrik: {$exp->metric}\n\n"
                . "Antworte JSON: {\"winner\": \"a|b|none\", \"confidence\": 85.0, \"reason\": \"...\"}";

            $analysis = GAMI_Claude_Client::ask_json($prompt);
            if (!$analysis) continue;

            $confidence = floatval($analysis['confidence'] ?? 0);
            if ($confidence >= self::AB_WIN_CONFIDENCE && $analysis['winner'] !== 'none') {
                $winner_id = $analysis['winner'] === 'a' ? $exp->variant_a_id : $exp->variant_b_id;
                $loser_id  = $analysis['winner'] === 'a' ? $exp->variant_b_id : $exp->variant_a_id;

                // Experiment abschließen
                GAMI_Database::update('experiments', [
                    'status'     => 'completed',
                    'winner_id'  => $winner_id,
                    'confidence' => $confidence,
                    'ended_at'   => current_time('mysql'),
                ], ['id' => $exp->id]);

                // Verlierer pausieren
                GAMI_Database::update('ads', ['status' => 'paused', 'reason_paused' => "A/B-Test verloren ({$confidence}% Konfidenz)"], ['id' => $loser_id]);
                GAMI_Database::update('ads', ['status' => 'winner'], ['id' => $winner_id]);

                $decisions[] = "[$platform] A/B-Test Gewinner: Variante {$analysis['winner']} ({$confidence}% Konfidenz). Reason: {$analysis['reason']}";

                // Als Learning speichern
                self::extract_learning_from_ab_win($exp->id, $winner_id, $loser_id, $platform, $analysis);
            }
        }

        return $decisions;
    }

    /**
     * Skaliert Gewinner-Ads (Budget erhöhen).
     */
    private static function scale_winners(string $platform): array {
        global $wpdb;
        $ads_t   = GAMI_Database::get_table('ads');
        $stats_t = GAMI_Database::get_table('ad_stats');
        $decisions = [];

        $winners = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.variant_name, a.campaign_id,
                   AVG(s.cpl) as avg_cpl,
                   AVG(s.roas) as avg_roas
            FROM $ads_t a
            JOIN $stats_t s ON s.ad_id = a.id
            WHERE a.platform = %s AND a.status = 'winner'
            GROUP BY a.id
            HAVING avg_roas > 2.0
        ", $platform));

        foreach ($winners as $winner) {
            $platform_obj = self::get_platform($platform);
            if ($platform_obj && $winner->campaign_id) {
                $success = $platform_obj->increase_budget($winner->campaign_id, 1.3); // +30%
                if ($success) {
                    $decisions[] = "[$platform] Winner Ad {$winner->variant_name} skaliert +30% Budget. ROAS: {$winner->avg_roas}";
                    self::log_decision('scale_winner', $platform, $winner->id, 'ad', 'scale_budget', "ROAS {$winner->avg_roas} > 2.0");
                }
            }
        }

        return $decisions;
    }

    /**
     * Startet neue Varianten wenn eine Plattform zu wenig aktive Ads hat.
     */
    private static function start_new_variants_if_needed(string $platform): void {
        global $wpdb;
        $ads_t = GAMI_Database::get_table('ads');

        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $ads_t WHERE platform = %s AND status = 'active'",
            $platform
        ));

        if ($active_count < 2) {
            // Aktive Kampagnen finden und neue Varianten generieren
            $campaigns = GAMI_Database::get_results('campaigns', 'platform = %s AND status = %s', [$platform, 'active'], 'LIMIT 3');
            foreach ($campaigns as $c) {
                GAMI_Ad_Generator::generate($c->product_id, $platform, 2, $c->id);
            }
        }
    }

    /**
     * Budget-Sicherheits-Check
     */
    private static function check_budget_safety(string $platform, array $stats): void {
        foreach ($stats as $stat) {
            if (!isset($stat['spend'], $stat['budget_day'])) continue;
            if ($stat['spend'] > $stat['budget_day'] * self::BUDGET_SAFETY_MULTIPLIER) {
                GAMI_Telegram_Interface::send_to_marco(
                    "🚨 BUDGET-ALERT [$platform]\n" .
                    "Ausgaben: €{$stat['spend']} bei Budget €{$stat['budget_day']}\n" .
                    "Kampagne wird pausiert bis du bestätigst."
                );
                $platform_obj = self::get_platform($platform);
                if ($platform_obj) $platform_obj->pause_campaign($stat['campaign_id'] ?? 0);
            }
        }
    }

    private static function save_stats(array $stat): void {
        if (!isset($stat['ad_id'])) return;
        global $wpdb;
        $t = GAMI_Database::get_table('ad_stats');
        $today = current_time('Y-m-d');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t WHERE ad_id = %d AND stat_date = %s",
            $stat['ad_id'], $today
        ));

        $data = array_merge($stat, ['stat_date' => $today]);
        if ($existing) {
            GAMI_Database::update('ad_stats', $data, ['id' => $existing]);
        } else {
            GAMI_Database::insert('ad_stats', $data);
        }
    }

    private static function log_decision(string $type, string $platform, int $entity_id, string $entity_type, string $action, string $reason): void {
        GAMI_Database::insert('decisions', [
            'decision_type' => $type,
            'platform'      => $platform,
            'entity_id'     => $entity_id,
            'entity_type'   => $entity_type,
            'action'        => $action,
            'reason'        => $reason,
        ]);
    }

    private static function extract_learning_from_ab_win(int $exp_id, int $winner_id, int $loser_id, string $platform, array $analysis): void {
        $winner_ad = GAMI_Database::get_row('ads', 'id = %d', [$winner_id]);
        $loser_ad  = GAMI_Database::get_row('ads', 'id = %d', [$loser_id]);
        if (!$winner_ad || !$loser_ad) return;

        // Learning: Welcher Angle hat gewonnen?
        if ($winner_ad->angle && $loser_ad->angle && $winner_ad->angle !== $loser_ad->angle) {
            global $wpdb;
            $t = GAMI_Database::get_table('learnings');

            GAMI_Database::insert('learnings', [
                'insight_type'    => 'angle',
                'source_platform' => $platform,
                'target_platforms' => 'meta,google,bing,taboola',
                'finding'         => "Angle '{$winner_ad->angle}' schlägt '{$loser_ad->angle}' — {$analysis['reason']}",
                'finding_value'   => $winner_ad->angle,
                'lift_percent'    => 0,
                'confidence'      => $analysis['confidence'],
                'status'          => 'new',
            ]);
        }
    }

    private static function send_daily_report(array $decisions): void {
        if (empty($decisions)) return;
        $text = "📊 Geldhelden AMI — Tages-Update\n\n";
        foreach ($decisions as $d) {
            $text .= "• $d\n";
        }
        $text .= "\n/report für vollständigen Bericht";
        GAMI_Telegram_Interface::send_to_marco($text);
    }

    private static function generate_weekly_report(): string {
        global $wpdb;
        $stats_t     = GAMI_Database::get_table('ad_stats');
        $campaigns_t = GAMI_Database::get_table('campaigns');
        $decisions_t = GAMI_Database::get_table('decisions');

        // Letzte 7 Tage
        $weekly_stats = $wpdb->get_row("
            SELECT SUM(spend) as total_spend,
                   SUM(conversions) as total_conversions,
                   AVG(cpl) as avg_cpl,
                   AVG(roas) as avg_roas,
                   SUM(clicks) as total_clicks,
                   SUM(impressions) as total_impressions
            FROM $stats_t
            WHERE stat_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        $decision_count = $wpdb->get_var("
            SELECT COUNT(*) FROM $decisions_t
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        $learnings_summary = GAMI_Learning_Engine::get_weekly_summary();

        $report = "📊 GELDHELDEN AMI — WOCHENBERICHT\n";
        $report .= date('d.m.Y') . "\n\n";
        $report .= "💰 PERFORMANCE (7 Tage)\n";
        $report .= "Ausgaben: €" . number_format($weekly_stats->total_spend ?? 0, 2) . "\n";
        $report .= "Conversions: " . ($weekly_stats->total_conversions ?? 0) . "\n";
        $report .= "Ø CPL: €" . number_format($weekly_stats->avg_cpl ?? 0, 2) . "\n";
        $report .= "Ø ROAS: " . number_format($weekly_stats->avg_roas ?? 0, 2) . "x\n";
        $report .= "Klicks: " . number_format($weekly_stats->total_clicks ?? 0) . "\n";
        $report .= "Impressions: " . number_format($weekly_stats->total_impressions ?? 0) . "\n\n";
        $report .= "🤖 AGENT-AKTIONEN: $decision_count Entscheidungen\n\n";
        $report .= "🧠 LEARNINGS DIESE WOCHE:\n$learnings_summary";

        return $report;
    }

    private static function get_active_platforms(): array {
        $all = ['x', 'google', 'meta', 'bing', 'taboola', 'telegram_ads', 'pinterest', 'tiktok', 'linkedin'];
        $active = [];
        foreach ($all as $p) {
            if (get_option("gami_{$p}_active", false)) {
                $active[] = $p;
            }
        }
        return $active;
    }

    public static function get_platform(string $key): ?GAMI_Platform_Base {
        $map = [
            'x'            => 'GAMI_Platform_X',
            'google'       => 'GAMI_Platform_Google',
            'meta'         => 'GAMI_Platform_Meta',
            'telegram_ads' => 'GAMI_Platform_Telegram_Ads',
            'whatsapp'     => 'GAMI_Platform_Whatsapp',
            'bing'         => 'GAMI_Platform_Bing',
            'taboola'      => 'GAMI_Platform_Taboola',
            'pinterest'    => 'GAMI_Platform_Pinterest',
            'tiktok'       => 'GAMI_Platform_Tiktok',
            'linkedin'     => 'GAMI_Platform_Linkedin',
            'youtube'      => 'GAMI_Platform_Youtube',
            'outbrain'     => 'GAMI_Platform_Outbrain',
            'spotify'      => 'GAMI_Platform_Spotify',
        ];
        if (!isset($map[$key])) return null;
        $class = $map[$key];
        return class_exists($class) ? new $class() : null;
    }
}
