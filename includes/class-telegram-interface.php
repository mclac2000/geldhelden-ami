<?php
defined('ABSPATH') || exit;

/**
 * Telegram Interface — Marco kommuniziert via @marsLA_bot mit dem AMI-Agent.
 * Nutzt die bestehende Mars-Bot-Infrastruktur (tg_messages DB auf Hostpoint).
 * Polling alle 2 Minuten via WP-Cron.
 */
class GAMI_Telegram_Interface {

    const MARCO_CHAT_ID  = '382863507';
    const BOT_TOKEN      = '1899544057:AAHDjOBwTmDu4Tc3kYKJKJEorlas5poHgmo';
    const COMMAND_PREFIX = '!';

    // Verfügbare Befehle
    const COMMANDS = [
        '!status'   => 'Aktueller Kampagnen-Status aller Plattformen',
        '!report'   => 'Vollständiger Performance-Bericht',
        '!new'      => 'Neue Kampagne starten: !new [url] [budget]',
        '!pause'    => 'Plattform pausieren: !pause [platform]',
        '!resume'   => 'Plattform fortsetzen: !resume [platform]',
        '!learn'    => 'Aktuelle Cross-Platform-Learnings anzeigen',
        '!ads'      => 'Aktive Ads anzeigen: !ads [platform]',
        '!stop'     => 'ALLE Kampagnen sofort pausieren (Notfall)',
        '!voice'    => 'WhatsApp Voice-Skript generieren: !voice [produkt_url]',
        '!budget'   => 'Budget anzeigen/ändern: !budget [platform] [betrag]',
    ];

    public static function init(): void {
        // Cron für Telegram-Polling registrieren
        add_action('gami_telegram_poll', [self::class, 'poll_commands']);

        // Eingehende WhatsApp-Webhooks
        add_action('wp_ajax_nopriv_gami_whatsapp_webhook', [self::class, 'handle_whatsapp_webhook']);
        add_action('wp_ajax_nopriv_gami_telegram_webhook', [self::class, 'handle_telegram_webhook']);
    }

    /**
     * Sendet Nachricht an Marco via Telegram
     */
    public static function send_to_marco(string $text, bool $silent = false): bool {
        $url = 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';

        $result = wp_remote_post($url, [
            'timeout' => 15,
            'body'    => [
                'chat_id'              => self::MARCO_CHAT_ID,
                'text'                 => $text,
                'parse_mode'           => 'HTML',
                'disable_notification' => $silent,
            ],
        ]);

        $success = !is_wp_error($result) && wp_remote_retrieve_response_code($result) === 200;

        // Log
        GAMI_Database::insert('telegram_log', [
            'direction'    => 'out',
            'chat_id'      => self::MARCO_CHAT_ID,
            'response_out' => $text,
            'status'       => $success ? 'ok' : 'error',
        ]);

        return $success;
    }

    /**
     * Pollt neue Befehle von Marco (via Mars-Bot-DB)
     * Läuft via WP-Cron alle 2 Minuten
     */
    public static function poll_commands(): void {
        // Letzte verarbeitete Message-ID
        $last_id = intval(get_option('gami_telegram_last_msg_id', 0));

        // Neueste Nachrichten von Marco aus der Mars-Bot-DB holen
        // (Die DB läuft auf Hostpoint — wir nutzen einen lokalen API-Endpunkt
        // ODER lesen über den WordPress-eigenen DB-Connector falls gleiche DB)
        $messages = self::fetch_recent_commands($last_id);

        foreach ($messages as $msg) {
            self::process_command($msg['text'], $msg['id']);
            $last_id = max($last_id, intval($msg['id']));
        }

        if ($last_id > intval(get_option('gami_telegram_last_msg_id', 0))) {
            update_option('gami_telegram_last_msg_id', $last_id);
        }
    }

    /**
     * Neueste Commands von Marco via Telegram Bot API
     */
    private static function fetch_recent_commands(int $after_update_id = 0): array {
        $url = 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/getUpdates?offset=' . ($after_update_id + 1) . '&limit=100&timeout=0&allowed_updates=["message"]';

        $result = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($result)) return [];

        $data = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($data['ok']) || empty($data['result'])) return [];

        $commands = [];
        foreach ($data['result'] as $update) {
            $chat_id = $update['message']['chat']['id'] ?? 0;
            $text    = $update['message']['text'] ?? '';

            // Nur Nachrichten von Marco
            if ((string)$chat_id !== self::MARCO_CHAT_ID) continue;
            if (empty($text)) continue;

            $commands[] = [
                'id'   => $update['update_id'],
                'text' => $text,
            ];
        }
        return $commands;
    }

    /**
     * Verarbeitet einen Befehl
     */
    public static function process_command(string $text, int $update_id = 0): void {
        // Log
        GAMI_Database::insert('telegram_log', [
            'direction'  => 'in',
            'chat_id'    => self::MARCO_CHAT_ID,
            'command'    => substr($text, 0, 100),
            'message_in' => $text,
        ]);

        $lower = strtolower(trim($text));
        $parts = preg_split('/\s+/', trim($text));
        $cmd   = strtolower($parts[0] ?? '');

        // Bekannte Befehle
        switch ($cmd) {
            case '!status':
                self::cmd_status();
                break;

            case '!report':
                self::cmd_report();
                break;

            case '!new':
                $url = $parts[1] ?? '';
                $budget = floatval($parts[2] ?? 10);
                if ($url) self::cmd_new($url, $budget);
                else self::send_to_marco('Syntax: !new [produkt-url] [budget/tag]');
                break;

            case '!pause':
                $platform = $parts[1] ?? 'all';
                self::cmd_pause($platform);
                break;

            case '!resume':
                $platform = $parts[1] ?? '';
                self::cmd_resume($platform);
                break;

            case '!learn':
                self::cmd_learnings();
                break;

            case '!ads':
                $platform = $parts[1] ?? '';
                self::cmd_ads($platform);
                break;

            case '!stop':
                self::cmd_emergency_stop();
                break;

            case '!voice':
                $url = $parts[1] ?? '';
                if ($url) self::cmd_voice($url);
                break;

            case '!budget':
                $platform = $parts[1] ?? '';
                $amount   = floatval($parts[2] ?? 0);
                self::cmd_budget($platform, $amount);
                break;

            case '!help':
            case '/help':
                self::cmd_help();
                break;

            default:
                // Freitext → Claude interpretiert
                if (strlen($text) > 3) {
                    self::cmd_freetext($text);
                }
                break;
        }
    }

    private static function cmd_status(): void {
        global $wpdb;
        $c_t = GAMI_Database::get_table('campaigns');
        $s_t = GAMI_Database::get_table('ad_stats');

        $rows = $wpdb->get_results("
            SELECT c.platform, c.status, COUNT(c.id) as num,
                   SUM(s.spend) as spend_today,
                   SUM(s.conversions) as conv_today,
                   AVG(s.cpl) as avg_cpl
            FROM $c_t c
            LEFT JOIN $s_t s ON s.platform = c.platform AND s.stat_date = CURDATE()
            WHERE c.status = 'active'
            GROUP BY c.platform, c.status
        ");

        $text = "📊 <b>AMI STATUS</b> — " . date('d.m.Y H:i') . "\n\n";
        if (empty($rows)) {
            $text .= "Keine aktiven Kampagnen.";
        } else {
            foreach ($rows as $r) {
                $text .= "▪️ <b>" . strtoupper($r->platform) . "</b> ({$r->num} Kampagnen)\n";
                $text .= "  Spend heute: €" . number_format($r->spend_today ?? 0, 2) . "\n";
                $text .= "  Conversions: " . ($r->conv_today ?? 0) . " | Ø CPL: €" . number_format($r->avg_cpl ?? 0, 2) . "\n\n";
            }
        }
        self::send_to_marco($text);
    }

    private static function cmd_report(): void {
        // Delegiert an Agent-Core
        $report = GAMI_Agent_Core::run_weekly_loop();
        // run_weekly_loop sendet bereits, aber wir können auch direkt antworten
        self::send_to_marco("Bericht wird generiert und in Kürze geschickt...");
    }

    private static function cmd_new(string $url, float $budget): void {
        self::send_to_marco("Analysiere Produkt: $url...");
        $result = GAMI_Agent_Core::process_new_product($url, ['budget_day' => $budget]);
        // Antwort wird in process_new_product via Telegram geschickt
    }

    private static function cmd_pause(string $platform): void {
        if ($platform === 'all') {
            $platforms = ['x', 'google', 'meta', 'bing', 'taboola'];
            foreach ($platforms as $p) {
                update_option("gami_{$p}_active", false);
            }
            self::send_to_marco("⏸ Alle Plattformen pausiert.");
        } else {
            update_option("gami_{$platform}_active", false);
            self::send_to_marco("⏸ {$platform} pausiert.");
        }
    }

    private static function cmd_resume(string $platform): void {
        update_option("gami_{$platform}_active", true);
        self::send_to_marco("▶️ {$platform} wieder aktiv.");
    }

    private static function cmd_learnings(): void {
        global $wpdb;
        $t = GAMI_Database::get_table('learnings');
        $learnings = $wpdb->get_results("SELECT * FROM $t ORDER BY confidence DESC LIMIT 10");

        $text = "🧠 <b>TOP LEARNINGS</b>\n\n";
        foreach ($learnings as $l) {
            $text .= "▪️ [{$l->source_platform}→{$l->target_platforms}] {$l->finding}\n";
            $text .= "  Lift: +{$l->lift_percent}% | Konfidenz: {$l->confidence}%\n\n";
        }
        self::send_to_marco($text);
    }

    private static function cmd_ads(string $platform): void {
        global $wpdb;
        $t = GAMI_Database::get_table('ads');
        $where = $platform ? $wpdb->prepare("WHERE platform = %s AND status = 'active'", $platform) : "WHERE status = 'active'";
        $ads = $wpdb->get_results("SELECT * FROM $t $where LIMIT 10");

        $text = "📢 <b>AKTIVE ADS</b>" . ($platform ? " ($platform)" : "") . "\n\n";
        foreach ($ads as $ad) {
            $text .= "▪️ [{$ad->platform}] Var.{$ad->variant_name} ({$ad->angle})\n";
            $text .= "  " . substr($ad->headline ?: $ad->body_text, 0, 80) . "...\n\n";
        }
        self::send_to_marco($text);
    }

    private static function cmd_emergency_stop(): void {
        global $wpdb;
        $t = GAMI_Database::get_table('campaigns');
        $wpdb->query("UPDATE $t SET status = 'paused' WHERE status = 'active'");
        self::send_to_marco("🛑 NOTFALL-STOP: Alle Kampagnen pausiert. Bestätige mit !resume [platform] zum Fortsetzen.");
    }

    private static function cmd_voice(string $url): void {
        $product_id = GAMI_Product_Analyzer::analyze_url($url);
        if (!$product_id) {
            self::send_to_marco("Fehler: Produkt-URL konnte nicht analysiert werden.");
            return;
        }
        $script = GAMI_Ad_Generator::generate_whatsapp_voice_script($product_id);
        self::send_to_marco("🎙 <b>WhatsApp Voice-Skript:</b>\n\n" . ($script ?? 'Fehler beim Generieren.'));
    }

    private static function cmd_budget(string $platform, float $amount): void {
        if (!$platform) {
            self::send_to_marco("Syntax: !budget [platform] [betrag/tag]\nPlattformen: x, google, meta, bing, taboola");
            return;
        }
        if ($amount > 0) {
            global $wpdb;
            $t = GAMI_Database::get_table('campaigns');
            $wpdb->query($wpdb->prepare("UPDATE $t SET budget_day = %f WHERE platform = %s AND status = 'active'", $amount, $platform));
            self::send_to_marco("💰 Budget für $platform auf €$amount/Tag gesetzt.");
        } else {
            $t = GAMI_Database::get_table('campaigns');
            $total = $wpdb->get_var($wpdb->prepare("SELECT SUM(budget_day) FROM $t WHERE platform = %s AND status = 'active'", $platform));
            self::send_to_marco("💰 Aktuelles Budget $platform: €$total/Tag gesamt");
        }
    }

    private static function cmd_freetext(string $text): void {
        $prompt = "Du bist der Geldhelden AMI Agent. Marco schreibt dir:\n\n\"$text\"\n\n"
            . "Was meint er damit und was sollst du tun? "
            . "Antworte auf Deutsch, max 200 Wörter, und beschreibe was du jetzt tust/empfiehlst.";
        $response = GAMI_Claude_Client::ask($prompt, GAMI_Claude_Client::get_agent_system_prompt(), 1024);
        self::send_to_marco($response ?? "Konnte Anfrage nicht verarbeiten.");
    }

    private static function cmd_help(): void {
        $text = "🤖 <b>AMI-Befehle:</b>\n\n";
        foreach (self::COMMANDS as $cmd => $desc) {
            $text .= "<code>$cmd</code> — $desc\n";
        }
        self::send_to_marco($text);
    }

    public static function handle_whatsapp_webhook(): void {
        $payload = json_decode(file_get_contents('php://input'), true);
        GAMI_Platform_Whatsapp::handle_webhook($payload ?? []);
        wp_die('OK');
    }
}
