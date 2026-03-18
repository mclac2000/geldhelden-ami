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

    <!-- Pinterest -->
    <div class="gami-card">
        <div class="gami-card-header">📌 PINTEREST ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_pinterest_active" <?= get_option('gami_pinterest_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Ad Account ID</label><input type="text" name="gami_pinterest_ad_account_id" value="<?= esc_attr(get_option('gami_pinterest_ad_account_id')) ?>"></div>
            <div class="gami-field"><label>Access Token</label><input type="password" name="gami_pinterest_access_token" value="<?= esc_attr(get_option('gami_pinterest_access_token')) ?>"></div>
            <div class="gami-field"><label>Board ID (für Pins)</label><input type="text" name="gami_pinterest_board_id" value="<?= esc_attr(get_option('gami_pinterest_board_id')) ?>"></div>
        </div>
    </div>

    <!-- TikTok -->
    <div class="gami-card">
        <div class="gami-card-header">🎵 TIKTOK FOR BUSINESS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_tiktok_active" <?= get_option('gami_tiktok_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Advertiser ID</label><input type="text" name="gami_tiktok_advertiser_id" value="<?= esc_attr(get_option('gami_tiktok_advertiser_id')) ?>"></div>
            <div class="gami-field"><label>Access Token</label><input type="password" name="gami_tiktok_access_token" value="<?= esc_attr(get_option('gami_tiktok_access_token')) ?>"></div>
        </div>
    </div>

    <!-- LinkedIn -->
    <div class="gami-card">
        <div class="gami-card-header">💼 LINKEDIN ADS (B2B)</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_linkedin_active" <?= get_option('gami_linkedin_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-info">Empfohlen für: Holding, Steueroptimierung, Freiheits-Business (B2B-Produkte)</div>
            <div class="gami-field"><label>Account ID</label><input type="text" name="gami_linkedin_account_id" value="<?= esc_attr(get_option('gami_linkedin_account_id')) ?>"></div>
            <div class="gami-field"><label>Access Token</label><input type="password" name="gami_linkedin_access_token" value="<?= esc_attr(get_option('gami_linkedin_access_token')) ?>"></div>
        </div>
    </div>

    <!-- YouTube -->
    <div class="gami-card">
        <div class="gami-card-header">▶️ YOUTUBE ADS</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_youtube_active" <?= get_option('gami_youtube_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-info">Nutzt Google Ads API-Credentials. Kein separater Key nötig.</div>
            <div class="gami-field"><label>YouTube Channel ID (für Placement-Targeting)</label><input type="text" name="gami_youtube_channel_id" value="<?= esc_attr(get_option('gami_youtube_channel_id')) ?>"></div>
        </div>
    </div>

    <!-- Outbrain -->
    <div class="gami-card">
        <div class="gami-card-header">🔗 OUTBRAIN NATIVE</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_outbrain_active" <?= get_option('gami_outbrain_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-field"><label>Marketer ID</label><input type="text" name="gami_outbrain_marketer_id" value="<?= esc_attr(get_option('gami_outbrain_marketer_id')) ?>"></div>
            <div class="gami-field"><label>Username</label><input type="text" name="gami_outbrain_username" value="<?= esc_attr(get_option('gami_outbrain_username')) ?>"></div>
            <div class="gami-field"><label>Passwort</label><input type="password" name="gami_outbrain_password" value="<?= esc_attr(get_option('gami_outbrain_password')) ?>"></div>
        </div>
    </div>

    <!-- Spotify -->
    <div class="gami-card">
        <div class="gami-card-header">🎙 SPOTIFY ADS STUDIO</div>
        <div class="gami-card-body">
            <div class="gami-toggle-row">
                <label>Aktiv</label>
                <input type="checkbox" name="gami_spotify_active" <?= get_option('gami_spotify_active') ? 'checked' : '' ?>>
            </div>
            <div class="gami-info">Für Podcast-Hörer — höchste Qualitätszielgruppe. Benötigt Audio-Produktion.</div>
            <div class="gami-field"><label>Client ID</label><input type="text" name="gami_spotify_client_id" value="<?= esc_attr(get_option('gami_spotify_client_id')) ?>"></div>
            <div class="gami-field"><label>Client Secret</label><input type="password" name="gami_spotify_client_secret" value="<?= esc_attr(get_option('gami_spotify_client_secret')) ?>"></div>
        </div>
    </div>

</div>

<!-- Test-Bereich -->
<div class="gami-card" style="margin-bottom:20px">
    <div class="gami-card-header">🧪 SYSTEM TESTEN (ohne echte API-Keys)</div>
    <div class="gami-card-body">
        <p style="color:var(--gami-text-dim);font-size:13px">Generiert realistische Demo-Daten (10 Tage Performance) für alle vorhandenen Ads. Damit kann Learning Engine, A/B-Testing und Agent-Loop getestet werden.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button id="gami-demo-data" class="gami-btn" style="background:var(--gami-accent2)">🎲 Demo-Daten generieren</button>
            <button id="gami-run-learn-settings" class="gami-btn" style="background:var(--gami-bg3);border:1px solid var(--gami-border)">🧠 Learning-Analyse starten</button>
            <button id="gami-run-daily-settings" class="gami-btn" style="background:var(--gami-bg3);border:1px solid var(--gami-border)">⚙️ Daily-Loop ausführen</button>
        </div>
        <div id="gami-test-result" style="margin-top:12px;font-size:13px"></div>
    </div>
</div>

<div class="gami-settings-save">
    <button id="gami-save-settings" class="gami-btn gami-btn-primary">Alle Einstellungen speichern</button>
    <span id="gami-save-status"></span>
</div>

</div>
