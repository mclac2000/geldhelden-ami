<?php
defined('ABSPATH') || exit;

class GAMI_Cron_Manager {

    public static function init(): void {
        // Cron-Schedules registrieren
        add_filter('cron_schedules', [self::class, 'add_schedules']);

        // Cron-Events registrieren
        add_action('gami_loop_6h',       [self::class, 'run_6h']);
        add_action('gami_loop_daily',    [self::class, 'run_daily']);
        add_action('gami_loop_weekly',   [self::class, 'run_weekly']);
        add_action('gami_telegram_poll', [self::class, 'run_telegram_poll']);

        // Crons einplanen wenn noch nicht geplant
        self::schedule_all();
    }

    public static function add_schedules(array $schedules): array {
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Alle 6 Stunden',
        ];
        $schedules['every_2_minutes'] = [
            'interval' => 2 * MINUTE_IN_SECONDS,
            'display'  => 'Alle 2 Minuten',
        ];
        return $schedules;
    }

    private static function schedule_all(): void {
        // 6h Loop
        if (!wp_next_scheduled('gami_loop_6h')) {
            wp_schedule_event(time(), 'every_6_hours', 'gami_loop_6h');
        }

        // Täglicher Loop: 07:00 CET
        if (!wp_next_scheduled('gami_loop_daily')) {
            $next_7am = strtotime('today 07:00 CET');
            if ($next_7am < time()) $next_7am = strtotime('tomorrow 07:00 CET');
            wp_schedule_event($next_7am, 'daily', 'gami_loop_daily');
        }

        // Wöchentlicher Loop: Sonntag 06:00 CET
        if (!wp_next_scheduled('gami_loop_weekly')) {
            $next_sunday = strtotime('next sunday 06:00 CET');
            wp_schedule_event($next_sunday, 'weekly', 'gami_loop_weekly');
        }

        // Telegram Polling alle 2 Minuten
        if (!wp_next_scheduled('gami_telegram_poll')) {
            wp_schedule_event(time(), 'every_2_minutes', 'gami_telegram_poll');
        }
    }

    public static function run_6h(): void {
        error_log('[GAMI] 6h Loop startet: ' . current_time('mysql'));
        GAMI_Agent_Core::run_6h_loop();
    }

    public static function run_daily(): void {
        error_log('[GAMI] Daily Loop startet: ' . current_time('mysql'));
        GAMI_Agent_Core::run_daily_loop();
    }

    public static function run_weekly(): void {
        error_log('[GAMI] Weekly Loop startet: ' . current_time('mysql'));
        GAMI_Agent_Core::run_weekly_loop();
    }

    public static function run_telegram_poll(): void {
        GAMI_Telegram_Interface::poll_commands();
    }

    public static function deactivate(): void {
        foreach (['gami_loop_6h', 'gami_loop_daily', 'gami_loop_weekly', 'gami_telegram_poll'] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
