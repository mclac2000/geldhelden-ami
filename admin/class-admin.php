<?php
defined('ABSPATH') || exit;

class GAMI_Admin {

    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('wp_ajax_gami_new_product', [self::class, 'ajax_new_product']);
        add_action('wp_ajax_gami_get_stats', [self::class, 'ajax_get_stats']);
        add_action('wp_ajax_gami_save_settings', [self::class, 'ajax_save_settings']);
        add_action('wp_ajax_gami_run_loop', [self::class, 'ajax_run_loop']);
        add_action('wp_ajax_gami_get_learnings', [self::class, 'ajax_get_learnings']);
        add_action('wp_ajax_gami_generate_ads', [self::class, 'ajax_generate_ads']);
    }

    public static function add_menu(): void {
        add_menu_page(
            'AMI Marketing',
            'AMI Marketing',
            'manage_options',
            'geldhelden-ami',
            [self::class, 'page_dashboard'],
            'dashicons-chart-line',
            25
        );
        add_submenu_page('geldhelden-ami', 'Dashboard', 'Dashboard', 'manage_options', 'geldhelden-ami', [self::class, 'page_dashboard']);
        add_submenu_page('geldhelden-ami', 'Kampagnen', 'Kampagnen', 'manage_options', 'geldhelden-ami-campaigns', [self::class, 'page_campaigns']);
        add_submenu_page('geldhelden-ami', 'Learnings', 'Learnings', 'manage_options', 'geldhelden-ami-learnings', [self::class, 'page_learnings']);
        add_submenu_page('geldhelden-ami', 'Einstellungen', 'Einstellungen', 'manage_options', 'geldhelden-ami-settings', [self::class, 'page_settings']);
    }

    public static function enqueue_assets(string $hook): void {
        if (strpos($hook, 'geldhelden-ami') === false) return;
        wp_enqueue_style('gami-admin', GAMI_PLUGIN_URL . 'admin/assets/css/dashboard.css', [], GAMI_VERSION);
        wp_enqueue_script('gami-admin', GAMI_PLUGIN_URL . 'admin/assets/js/dashboard.js', ['jquery'], GAMI_VERSION, true);
        wp_localize_script('gami-admin', 'GAMI', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gami_nonce'),
        ]);
    }

    public static function page_dashboard(): void {
        include GAMI_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public static function page_campaigns(): void {
        include GAMI_PLUGIN_DIR . 'admin/views/campaigns.php';
    }

    public static function page_learnings(): void {
        include GAMI_PLUGIN_DIR . 'admin/views/learnings.php';
    }

    public static function page_settings(): void {
        include GAMI_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // AJAX: Neues Produkt/Kampagne
    public static function ajax_new_product(): void {
        check_ajax_referer('gami_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $url       = sanitize_url($_POST['url'] ?? '');
        $budget    = floatval($_POST['budget'] ?? 10);
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? []);
        $context   = sanitize_textarea_field($_POST['context'] ?? '');

        if (!$url) wp_send_json_error('URL fehlt');

        $result = GAMI_Agent_Core::process_new_product($url, [
            'budget_day' => $budget,
            'platforms'  => $platforms,
            'context'    => $context,
        ]);

        wp_send_json_success($result);
    }

    // AJAX: Performance-Stats für Dashboard
    public static function ajax_get_stats(): void {
        check_ajax_referer('gami_nonce', 'nonce');

        global $wpdb;
        $days = intval($_POST['days'] ?? 30);
        $s_t  = GAMI_Database::get_table('ad_stats');
        $c_t  = GAMI_Database::get_table('campaigns');
        $a_t  = GAMI_Database::get_table('ads');

        // Gesamt-Performance
        $totals = $wpdb->get_row($wpdb->prepare("
            SELECT SUM(spend) as spend, SUM(conversions) as conversions,
                   SUM(clicks) as clicks, SUM(impressions) as impressions,
                   AVG(cpl) as avg_cpl, AVG(roas) as avg_roas
            FROM $s_t WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
        ", $days));

        // Per Plattform
        $by_platform = $wpdb->get_results($wpdb->prepare("
            SELECT platform,
                   SUM(spend) as spend, SUM(conversions) as conversions,
                   AVG(cpl) as avg_cpl, AVG(roas) as avg_roas
            FROM $s_t WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            GROUP BY platform ORDER BY spend DESC
        ", $days));

        // Gewinner-Ads
        $winners = $wpdb->get_results("
            SELECT a.id, a.platform, a.variant_name, a.angle, a.headline, a.body_text,
                   AVG(s.ctr) as avg_ctr, AVG(s.cpl) as avg_cpl
            FROM $a_t a
            JOIN $s_t s ON s.ad_id = a.id
            WHERE a.status = 'winner'
            GROUP BY a.id ORDER BY avg_cpl ASC LIMIT 10
        ");

        // Laufende A/B-Tests
        $experiments = GAMI_Database::get_results('experiments', "status = 'running'");

        wp_send_json_success([
            'totals'      => $totals,
            'by_platform' => $by_platform,
            'winners'     => $winners,
            'experiments' => $experiments,
        ]);
    }

    // AJAX: Settings speichern
    public static function ajax_save_settings(): void {
        check_ajax_referer('gami_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $settings = $_POST['settings'] ?? [];
        $allowed_keys = [
            'gami_claude_api_key',
            // X
            'gami_x_bearer_token', 'gami_x_account_id', 'gami_x_funding_instrument_id', 'gami_x_active',
            // Google
            'gami_google_client_id', 'gami_google_client_secret', 'gami_google_refresh_token',
            'gami_google_developer_token', 'gami_google_customer_id', 'gami_google_manager_id', 'gami_google_active',
            // Meta
            'gami_meta_ad_account_id', 'gami_meta_access_token', 'gami_meta_page_id', 'gami_meta_active',
            // WhatsApp
            'gami_whatsapp_phone_number_id', 'gami_whatsapp_access_token', 'gami_whatsapp_waba_id',
            // Bing
            'gami_bing_client_id', 'gami_bing_client_secret', 'gami_bing_refresh_token',
            'gami_bing_developer_token', 'gami_bing_account_id', 'gami_bing_customer_id', 'gami_bing_active',
            // Taboola
            'gami_taboola_client_id', 'gami_taboola_client_secret', 'gami_taboola_account_id', 'gami_taboola_active',
            // Telegram Ads
            'gami_telegram_ads_api_token', 'gami_telegram_ads_active',
        ];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_keys, true)) {
                update_option(sanitize_key($key), sanitize_text_field($value));
            }
        }

        wp_send_json_success('Gespeichert.');
    }

    // AJAX: Loop manuell triggern
    public static function ajax_run_loop(): void {
        check_ajax_referer('gami_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $loop = sanitize_text_field($_POST['loop'] ?? 'daily');
        switch ($loop) {
            case '6h':     GAMI_Agent_Core::run_6h_loop();    break;
            case 'daily':  GAMI_Agent_Core::run_daily_loop(); break;
            case 'weekly': GAMI_Agent_Core::run_weekly_loop(); break;
            case 'learn':  GAMI_Learning_Engine::run_analysis(); break;
        }
        wp_send_json_success("Loop '$loop' ausgeführt.");
    }

    // AJAX: Learnings abrufen
    public static function ajax_get_learnings(): void {
        check_ajax_referer('gami_nonce', 'nonce');
        global $wpdb;
        $t = GAMI_Database::get_table('learnings');
        $learnings = $wpdb->get_results("SELECT * FROM $t ORDER BY confidence DESC LIMIT 50");
        wp_send_json_success($learnings);
    }

    // AJAX: Ads manuell generieren
    public static function ajax_generate_ads(): void {
        check_ajax_referer('gami_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $product_id = intval($_POST['product_id'] ?? 0);
        $platform   = sanitize_text_field($_POST['platform'] ?? '');
        $count      = intval($_POST['count'] ?? 3);

        if (!$product_id || !$platform) wp_send_json_error('Fehlende Parameter');

        $ad_ids = GAMI_Ad_Generator::generate($product_id, $platform, $count);
        wp_send_json_success(['ad_ids' => $ad_ids, 'count' => count($ad_ids)]);
    }
}
