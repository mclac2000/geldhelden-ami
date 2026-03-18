<?php
/**
 * Plugin Name: Geldhelden AMI — Autonomous Marketing Intelligence
 * Plugin URI:  https://geldhelden.org
 * Description: Autonomes KI-Marketing-System. Claude analysiert Produkte, erstellt Ads, optimiert A/B-Tests und lernt plattformübergreifend. Unterstützt: X/Twitter, Google Ads, Meta, Instagram, Pinterest, TikTok, LinkedIn, Telegram Ads, WhatsApp, Bing, Taboola, Outbrain, YouTube, Spotify.
 * Version:     1.0.0
 * Author:      Marco Lachmann / Geldhelden
 * Text Domain: geldhelden-ami
 */

defined('ABSPATH') || exit;

define('GAMI_VERSION', '1.1.0');
define('GAMI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GAMI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GAMI_PREFIX', 'gami_');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'GAMI_';
    if (strpos($class, $prefix) !== 0) return;

    $relative = strtolower(str_replace(['GAMI_', '_'], ['', '-'], $class));
    $paths = [
        GAMI_PLUGIN_DIR . "includes/class-{$relative}.php",
        GAMI_PLUGIN_DIR . "includes/platforms/class-{$relative}.php",
        GAMI_PLUGIN_DIR . "admin/class-{$relative}.php",
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Core-Includes manuell laden (wegen abweichenden Dateinamen)
require_once GAMI_PLUGIN_DIR . 'includes/class-database.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-claude-client.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-product-analyzer.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-ad-generator.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-learning-engine.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-agent-core.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-cron-manager.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-telegram-interface.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/abstract-platform.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-x.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-google.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-meta.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-telegram-ads.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-whatsapp.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-bing.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-taboola.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-pinterest.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-tiktok.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-linkedin.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-youtube.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-outbrain.php';
require_once GAMI_PLUGIN_DIR . 'includes/platforms/class-platform-spotify.php';
require_once GAMI_PLUGIN_DIR . 'includes/class-stats-importer.php';
require_once GAMI_PLUGIN_DIR . 'admin/class-admin.php';

// Activation / Deactivation
register_activation_hook(__FILE__, ['GAMI_Database', 'install']);
register_deactivation_hook(__FILE__, ['GAMI_Cron_Manager', 'deactivate']);

// Bootstrap
add_action('plugins_loaded', function () {
    GAMI_Admin::init();
    GAMI_Cron_Manager::init();
    GAMI_Telegram_Interface::init();
});
