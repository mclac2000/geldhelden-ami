<?php defined('ABSPATH') || exit; ?>
<div class="wrap gami-wrap">

<div class="gami-header">
    <div class="gami-logo">◈ GELDHELDEN <span>AUTONOMOUS MARKETING INTELLIGENCE</span></div>
    <div class="gami-header-meta">
        <?php echo date('d.m.Y H:i'); ?> |
        <a href="#" id="gami-run-daily" class="gami-btn-sm">🔄 Loop jetzt</a>
        <a href="#" id="gami-send-report" class="gami-btn-sm">📤 Report senden</a>
    </div>
</div>

<!-- KPI-Overview -->
<div class="gami-kpi-grid" id="gami-kpis">
    <div class="gami-kpi" data-key="spend">
        <div class="kpi-label">AUSGABEN (30T)</div>
        <div class="kpi-value" id="kpi-spend">—</div>
        <div class="kpi-sub" id="kpi-spend-sub"></div>
    </div>
    <div class="gami-kpi" data-key="conversions">
        <div class="kpi-label">CONVERSIONS</div>
        <div class="kpi-value" id="kpi-conv">—</div>
        <div class="kpi-sub" id="kpi-conv-sub"></div>
    </div>
    <div class="gami-kpi" data-key="cpl">
        <div class="kpi-label">Ø CPL</div>
        <div class="kpi-value" id="kpi-cpl">—</div>
    </div>
    <div class="gami-kpi" data-key="roas">
        <div class="kpi-label">Ø ROAS</div>
        <div class="kpi-value" id="kpi-roas">—</div>
    </div>
    <div class="gami-kpi" data-key="clicks">
        <div class="kpi-label">KLICKS</div>
        <div class="kpi-value" id="kpi-clicks">—</div>
    </div>
</div>

<div class="gami-main-grid">

<!-- Linke Spalte -->
<div class="gami-col-main">

    <!-- Gewinner-Ads -->
    <div class="gami-card">
        <div class="gami-card-header">🏆 AKTIVE GEWINNER-ADS</div>
        <div class="gami-card-body" id="gami-winners">
            <div class="gami-loading">Lade...</div>
        </div>
    </div>

    <!-- Plattform-Performance -->
    <div class="gami-card">
        <div class="gami-card-header">📊 PERFORMANCE NACH PLATTFORM</div>
        <div class="gami-card-body" id="gami-platforms">
            <div class="gami-loading">Lade...</div>
        </div>
    </div>

    <!-- A/B-Tests -->
    <div class="gami-card">
        <div class="gami-card-header">🧪 LAUFENDE A/B-TESTS</div>
        <div class="gami-card-body" id="gami-experiments">
            <div class="gami-loading">Lade...</div>
        </div>
    </div>

</div>

<!-- Rechte Spalte: Command Center -->
<div class="gami-col-side">

    <!-- Neue Kampagne -->
    <div class="gami-card gami-command-center">
        <div class="gami-card-header">⚡ NEUE KAMPAGNE STARTEN</div>
        <div class="gami-card-body">
            <form id="gami-new-campaign">
                <div class="gami-field">
                    <label>Produkt-URL</label>
                    <input type="url" id="new-url" placeholder="https://geldhelden.org/..." required>
                </div>
                <div class="gami-field">
                    <label>Kontext (optional)</label>
                    <textarea id="new-context" placeholder="Besonderheiten, Zielgruppe, Hinweise..."></textarea>
                </div>
                <div class="gami-field">
                    <label>Plattformen</label>
                    <div class="gami-platform-toggle">
                        <?php
                        $platforms = [
                            'x'           => 'X/Twitter',
                            'google'      => 'Google',
                            'meta'        => 'Meta',
                            'bing'        => 'Bing',
                            'taboola'     => 'Taboola',
                            'telegram_ads'=> 'Telegram Ads',
                            'whatsapp'    => 'WhatsApp',
                            'pinterest'   => 'Pinterest',
                            'tiktok'      => 'TikTok',
                            'linkedin'    => 'LinkedIn',
                            'youtube'     => 'YouTube',
                        ];
                        foreach ($platforms as $key => $label):
                        ?>
                        <label class="gami-toggle-pill">
                            <input type="checkbox" name="platforms[]" value="<?= esc_attr($key) ?>"
                                <?= in_array($key, ['x','google','meta']) ? 'checked' : '' ?>>
                            <?= esc_html($label) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="gami-field gami-field-row">
                    <div>
                        <label>Budget/Tag (€)</label>
                        <input type="number" id="new-budget" value="10" min="1" max="1000" step="1">
                    </div>
                    <div>
                        <label>Priorität</label>
                        <select id="new-priority">
                            <option value="normal">Normal</option>
                            <option value="high">Hoch</option>
                            <option value="test">Test-only</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="gami-btn gami-btn-primary" id="gami-submit-campaign">
                    🤖 KI BEAUFTRAGEN →
                </button>
            </form>
            <div id="gami-new-result" style="display:none;margin-top:12px;"></div>
        </div>
    </div>

    <!-- Top Learnings -->
    <div class="gami-card">
        <div class="gami-card-header">🧠 TOP LEARNINGS</div>
        <div class="gami-card-body" id="gami-learnings-preview">
            <div class="gami-loading">Lade...</div>
        </div>
        <div class="gami-card-footer">
            <a href="<?= admin_url('admin.php?page=geldhelden-ami-learnings') ?>">Alle Learnings →</a>
        </div>
    </div>

    <!-- Agent-Aktionen -->
    <div class="gami-card">
        <div class="gami-card-header">🔧 AGENT MANUELL STEUERN</div>
        <div class="gami-card-body">
            <div class="gami-actions">
                <button class="gami-btn gami-btn-sm" data-loop="6h">6h-Loop</button>
                <button class="gami-btn gami-btn-sm" data-loop="daily">Daily-Loop</button>
                <button class="gami-btn gami-btn-sm" data-loop="learn">Learning-Analyse</button>
                <button class="gami-btn gami-btn-sm" data-loop="weekly">Wochenbericht</button>
            </div>
            <div id="gami-action-result"></div>
        </div>
    </div>

</div>
</div><!-- .gami-main-grid -->

</div><!-- .wrap -->
