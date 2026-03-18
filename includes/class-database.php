<?php
defined('ABSPATH') || exit;

class GAMI_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Produkte
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_products (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            url             VARCHAR(500) NOT NULL,
            name            VARCHAR(255),
            type            VARCHAR(50),
            extracted_usps  LONGTEXT,
            angles_json     LONGTEXT,
            raw_content     LONGTEXT,
            PRIMARY KEY (id),
            KEY idx_url (url(191))
        ) $charset;");

        // Kampagnen
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_campaigns (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            product_id      BIGINT UNSIGNED NOT NULL,
            platform        VARCHAR(50) NOT NULL,
            platform_id     VARCHAR(255),
            name            VARCHAR(255),
            status          VARCHAR(20) DEFAULT 'active',
            budget_day      DECIMAL(10,2) DEFAULT 0,
            total_spend     DECIMAL(10,2) DEFAULT 0,
            roas            DECIMAL(8,4) DEFAULT 0,
            notes           TEXT,
            PRIMARY KEY (id),
            KEY idx_platform (platform),
            KEY idx_status (status)
        ) $charset;");

        // Ads (Anzeigen-Varianten)
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_ads (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            campaign_id     BIGINT UNSIGNED,
            product_id      BIGINT UNSIGNED,
            platform        VARCHAR(50) NOT NULL,
            platform_ad_id  VARCHAR(255),
            variant_name    VARCHAR(10),
            headline        VARCHAR(500),
            body_text       TEXT,
            cta_text        VARCHAR(100),
            media_type      VARCHAR(20) DEFAULT 'text',
            media_url       VARCHAR(500),
            angle           VARCHAR(50),
            status          VARCHAR(20) DEFAULT 'active',
            reason_paused   TEXT,
            PRIMARY KEY (id),
            KEY idx_platform (platform),
            KEY idx_status (status),
            KEY idx_campaign (campaign_id)
        ) $charset;");

        // Tägliche Performance-Daten
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_ad_stats (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ad_id           BIGINT UNSIGNED NOT NULL,
            stat_date       DATE NOT NULL,
            platform        VARCHAR(50),
            impressions     BIGINT DEFAULT 0,
            clicks          BIGINT DEFAULT 0,
            ctr             DECIMAL(8,4) DEFAULT 0,
            spend           DECIMAL(10,2) DEFAULT 0,
            conversions     BIGINT DEFAULT 0,
            cpl             DECIMAL(10,2) DEFAULT 0,
            revenue         DECIMAL(10,2) DEFAULT 0,
            roas            DECIMAL(8,4) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_ad_date (ad_id, stat_date),
            KEY idx_date (stat_date)
        ) $charset;");

        // Landing Pages
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_landing_pages (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            product_id      BIGINT UNSIGNED,
            wp_post_id      BIGINT UNSIGNED,
            variant_name    VARCHAR(10),
            url             VARCHAR(500),
            headline        VARCHAR(500),
            cta_text        VARCHAR(100),
            bg_color        VARCHAR(20),
            status          VARCHAR(20) DEFAULT 'active',
            PRIMARY KEY (id),
            KEY idx_product (product_id)
        ) $charset;");

        // LP Performance
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_lp_stats (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lp_id           BIGINT UNSIGNED NOT NULL,
            stat_date       DATE NOT NULL,
            visits          BIGINT DEFAULT 0,
            conversions     BIGINT DEFAULT 0,
            conv_rate       DECIMAL(8,4) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_lp_date (lp_id, stat_date)
        ) $charset;");

        // A/B-Experimente
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_experiments (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            type            VARCHAR(50),
            platform        VARCHAR(50),
            product_id      BIGINT UNSIGNED,
            variant_a_id    BIGINT UNSIGNED,
            variant_b_id    BIGINT UNSIGNED,
            metric          VARCHAR(50) DEFAULT 'cpl',
            status          VARCHAR(20) DEFAULT 'running',
            winner_id       BIGINT UNSIGNED,
            confidence      DECIMAL(5,2) DEFAULT 0,
            started_at      DATETIME,
            ended_at        DATETIME,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_platform (platform)
        ) $charset;");

        // Cross-Platform Learnings
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_learnings (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            insight_type    VARCHAR(50),
            source_platform VARCHAR(50),
            target_platforms VARCHAR(255),
            finding         TEXT,
            finding_value   VARCHAR(255),
            lift_percent    DECIMAL(8,2) DEFAULT 0,
            confidence      DECIMAL(5,2) DEFAULT 0,
            applied_at      DATETIME,
            applied_platforms VARCHAR(255),
            status          VARCHAR(20) DEFAULT 'new',
            PRIMARY KEY (id),
            KEY idx_type (insight_type),
            KEY idx_source (source_platform)
        ) $charset;");

        // Telegram-Log
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_telegram_log (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            direction       VARCHAR(10),
            chat_id         VARCHAR(50),
            command         VARCHAR(100),
            message_in      TEXT,
            response_out    TEXT,
            status          VARCHAR(20) DEFAULT 'ok',
            PRIMARY KEY (id),
            KEY idx_created (created_at)
        ) $charset;");

        // Agent-Decisions Log
        dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ami_decisions (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            decision_type   VARCHAR(50),
            platform        VARCHAR(50),
            entity_id       BIGINT UNSIGNED,
            entity_type     VARCHAR(20),
            action          VARCHAR(50),
            reason          TEXT,
            claude_reasoning LONGTEXT,
            PRIMARY KEY (id),
            KEY idx_type (decision_type),
            KEY idx_created (created_at)
        ) $charset;");

        update_option('gami_db_version', GAMI_VERSION);
    }

    public static function get_table($name) {
        global $wpdb;
        return $wpdb->prefix . 'ami_' . $name;
    }

    public static function insert($table, $data) {
        global $wpdb;
        $wpdb->insert(self::get_table($table), $data);
        return $wpdb->insert_id;
    }

    public static function update($table, $data, $where) {
        global $wpdb;
        return $wpdb->update(self::get_table($table), $data, $where);
    }

    public static function get_row($table, $where_sql, $params = []) {
        global $wpdb;
        $t = self::get_table($table);
        if ($params) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE $where_sql", $params));
        }
        return $wpdb->get_row("SELECT * FROM $t WHERE $where_sql");
    }

    public static function get_results($table, $where_sql = '1=1', $params = [], $extra = '') {
        global $wpdb;
        $t = self::get_table($table);
        if ($params) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE $where_sql $extra", $params));
        }
        return $wpdb->get_results("SELECT * FROM $t WHERE $where_sql $extra");
    }
}
