<?php defined('ABSPATH') || exit; ?>
<div class="wrap gami-wrap">
<div class="gami-header">
    <div class="gami-logo">◈ GELDHELDEN AMI <span>Einstellungen</span></div>
</div>

<div class="gami-settings-grid">

    <!-- Claude API -->
    <div class="gami-card">
        <div class="gami-card-header">🤖 CLAUDE API (Anthropic)</div>
        <div class="gami-card-body">
            <div class="gami-field">
                <label>API Key</label>
                <input type="password" name="gami_claude_api_key" value="<?= esc_attr(get_option('gami_claude_api_key')) ?>" placeholder="sk-ant-...">
            </div>
            <div class="gami-info">Modell: <strong>claude-opus-4-6</strong> — Stärkstes Reasoning für alle Entscheidungen</div>
        </div>
    </div>

    <!-- X / Twitter -->
    <div class="gami-card">
        <div class="gami-card-header">𝕏 X / TWITTER ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_x_active" <?= get_option('gami_x_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field">
                <label>Bearer Token</label>
                <input type="password" name="gami_x_bearer_token" value="<?= esc_attr(get_option('gami_x_bearer_token')) ?>">
            </div>
            <div class="gami-field">
                <label>Account ID</label>
                <input type="text" name="gami_x_account_id" value="<?= esc_attr(get_option('gami_x_account_id')) ?>">
            </div>
            <div class="gami-field">
                <label>Funding Instrument ID</label>
                <input type="text" name="gami_x_funding_instrument_id" value="<?= esc_attr(get_option('gami_x_funding_instrument_id')) ?>">
            </div>
        </div>
    </div>

    <!-- Google Ads -->
    <div class="gami-card">
        <div class="gami-card-header">🔵 GOOGLE ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_google_active" <?= get_option('gami_google_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Client ID</label><input type="text" name="gami_google_client_id" value="<?= esc_attr(get_option('gami_google_client_id')) ?>"></div>
            <div class="gami-field"><label>Client Secret</label><input type="password" name="gami_google_client_secret" value="<?= esc_attr(get_option('gami_google_client_secret')) ?>"></div>
            <div class="gami-field"><label>Refresh Token</label><input type="password" name="gami_google_refresh_token" value="<?= esc_attr(get_option('gami_google_refresh_token')) ?>"></div>
            <div class="gami-field"><label>Developer Token</label><input type="password" name="gami_google_developer_token" value="<?= esc_attr(get_option('gami_google_developer_token')) ?>"></div>
            <div class="gami-field"><label>Customer ID</label><input type="text" name="gami_google_customer_id" value="<?= esc_attr(get_option('gami_google_customer_id')) ?>" placeholder="123-456-7890"></div>
        </div>
    </div>

    <!-- Meta -->
    <div class="gami-card">
        <div class="gami-card-header">📘 META (Facebook + Instagram)</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_meta_active" <?= get_option('gami_meta_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Ad Account ID</label><input type="text" name="gami_meta_ad_account_id" value="<?= esc_attr(get_option('gami_meta_ad_account_id')) ?>"></div>
            <div class="gami-field"><label>Access Token</label><input type="password" name="gami_meta_access_token" value="<?= esc_attr(get_option('gami_meta_access_token')) ?>"></div>
            <div class="gami-field"><label>Page ID</label><input type="text" name="gami_meta_page_id" value="<?= esc_attr(get_option('gami_meta_page_id')) ?>"></div>
        </div>
    </div>

    <!-- WhatsApp -->
    <div class="gami-card">
        <div class="gami-card-header">💬 WHATSAPP BUSINESS</div>
        <div class="gami-card-body">
            <div class="gami-field"><label>Phone Number ID</label><input type="text" name="gami_whatsapp_phone_number_id" value="<?= esc_attr(get_option('gami_whatsapp_phone_number_id')) ?>"></div>
            <div class="gami-field"><label>Access Token</label><input type="password" name="gami_whatsapp_access_token" value="<?= esc_attr(get_option('gami_whatsapp_access_token')) ?>"></div>
            <div class="gami-field"><label>WABA ID</label><input type="text" name="gami_whatsapp_waba_id" value="<?= esc_attr(get_option('gami_whatsapp_waba_id')) ?>"></div>
        </div>
    </div>

    <!-- Bing -->
    <div class="gami-card">
        <div class="gami-card-header">🔷 BING / MICROSOFT ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_bing_active" <?= get_option('gami_bing_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Client ID</label><input type="text" name="gami_bing_client_id" value="<?= esc_attr(get_option('gami_bing_client_id')) ?>"></div>
            <div class="gami-field"><label>Client Secret</label><input type="password" name="gami_bing_client_secret" value="<?= esc_attr(get_option('gami_bing_client_secret')) ?>"></div>
            <div class="gami-field"><label>Refresh Token</label><input type="password" name="gami_bing_refresh_token" value="<?= esc_attr(get_option('gami_bing_refresh_token')) ?>"></div>
            <div class="gami-field"><label>Developer Token</label><input type="password" name="gami_bing_developer_token" value="<?= esc_attr(get_option('gami_bing_developer_token')) ?>"></div>
            <div class="gami-field"><label>Account ID</label><input type="text" name="gami_bing_account_id" value="<?= esc_attr(get_option('gami_bing_account_id')) ?>"></div>
        </div>
    </div>

    <!-- Taboola -->
    <div class="gami-card">
        <div class="gami-card-header">📰 TABOOLA NATIVE ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_taboola_active" <?= get_option('gami_taboola_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Client ID</label><input type="text" name="gami_taboola_client_id" value="<?= esc_attr(get_option('gami_taboola_client_id')) ?>"></div>
            <div class="gami-field"><label>Client Secret</label><input type="password" name="gami_taboola_client_secret" value="<?= esc_attr(get_option('gami_taboola_client_secret')) ?>"></div>
            <div class="gami-field"><label>Account ID</label><input type="text" name="gami_taboola_account_id" value="<?= esc_attr(get_option('gami_taboola_account_id')) ?>"></div>
        </div>
    </div>

    <!-- Telegram Ads -->
    <div class="gami-card">
        <div class="gami-card-header">✈️ TELEGRAM ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_telegram_ads_active" <?= get_option('gami_telegram_ads_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>API Token</label><input type="password" name="gami_telegram_ads_api_token" value="<?= esc_attr(get_option('gami_telegram_ads_api_token')) ?>"></div>
        </div>
    </div>

</div>

<div class="gami-settings-save">
    <button id="gami-save-settings" class="gami-btn gami-btn-primary">Alle Einstellungen speichern</button>
    <span id="gami-save-status"></span>
</div>

</div>
