/* Geldhelden AMI — Dashboard JS */
(function($) {
    'use strict';

    // === INIT ===
    $(document).ready(function() {
        if ($('#gami-kpis').length) {
            loadStats();
            loadLearningsPreview();
        }

        bindEvents();
        autoRefresh();
    });

    // === STATS LADEN ===
    function loadStats() {
        $.post(GAMI.ajax_url, {
            action: 'gami_get_stats',
            nonce: GAMI.nonce,
            days: 30
        }, function(res) {
            if (!res.success) return;
            var d = res.data;

            // KPIs
            if (d.totals) {
                var t = d.totals;
                $('#kpi-spend').text('€' + fmt(t.spend, 0));
                $('#kpi-conv').text(fmtInt(t.conversions));
                $('#kpi-cpl').text('€' + fmt(t.avg_cpl, 2));
                $('#kpi-roas').text(fmt(t.avg_roas, 2) + 'x');
                $('#kpi-clicks').text(fmtInt(t.clicks));
            }

            // Plattformen
            var pHtml = '<table class="gami-table">';
            pHtml += '<thead><tr><th>Plattform</th><th>Spend</th><th>Conv.</th><th>Ø CPL</th><th>Ø ROAS</th></tr></thead><tbody>';
            (d.by_platform || []).forEach(function(p) {
                pHtml += '<tr>';
                pHtml += '<td><span class="platform-badge platform-' + p.platform + '">' + p.platform.toUpperCase() + '</span></td>';
                pHtml += '<td>€' + fmt(p.spend, 2) + '</td>';
                pHtml += '<td>' + fmtInt(p.conversions) + '</td>';
                pHtml += '<td>€' + fmt(p.avg_cpl, 2) + '</td>';
                pHtml += '<td>' + fmt(p.avg_roas, 2) + 'x</td>';
                pHtml += '</tr>';
            });
            if (!d.by_platform || !d.by_platform.length) {
                pHtml += '<tr><td colspan="5" class="gami-empty-row">Noch keine Daten</td></tr>';
            }
            pHtml += '</tbody></table>';
            $('#gami-platforms').html(pHtml);

            // Gewinner
            var wHtml = '';
            (d.winners || []).forEach(function(ad) {
                wHtml += '<div class="gami-ad-card ad-status-winner">';
                wHtml += '<div class="ad-meta"><span class="platform-badge platform-' + ad.platform + '">' + ad.platform.toUpperCase() + '</span>';
                wHtml += ' <span class="status-badge status-winner">Var.' + ad.variant_name + ' — ' + ad.angle + '</span></div>';
                if (ad.headline) wHtml += '<div class="ad-headline">' + esc(ad.headline) + '</div>';
                if (ad.body_text) wHtml += '<div class="ad-body">' + esc(ad.body_text.substring(0, 120)) + '...</div>';
                wHtml += '<div class="ad-stats">CTR: ' + fmt(ad.avg_ctr, 3) + '% | CPL: €' + fmt(ad.avg_cpl, 2) + '</div>';
                wHtml += '</div>';
            });
            $('#gami-winners').html(wHtml || '<div class="gami-empty">Noch keine Gewinner-Ads</div>');

            // Experimente
            var eHtml = '';
            (d.experiments || []).forEach(function(e) {
                eHtml += '<div style="padding:8px 0;border-bottom:1px solid var(--gami-border)">';
                eHtml += '<span class="platform-badge platform-' + e.platform + '">' + e.platform.toUpperCase() + '</span> ';
                eHtml += e.type + ' | Metric: ' + e.metric;
                eHtml += '</div>';
            });
            $('#gami-experiments').html(eHtml || '<div class="gami-empty">Keine laufenden A/B-Tests</div>');
        });
    }

    // === LEARNINGS PREVIEW ===
    function loadLearningsPreview() {
        $.post(GAMI.ajax_url, {
            action: 'gami_get_learnings',
            nonce: GAMI.nonce
        }, function(res) {
            if (!res.success) return;
            var html = '';
            var items = (res.data || []).slice(0, 5);
            items.forEach(function(l) {
                html += '<div style="padding:8px 0;border-bottom:1px solid var(--gami-border);font-size:12px">';
                html += '<span class="platform-badge platform-' + l.source_platform + '">' + l.source_platform.toUpperCase() + ' →</span> ';
                html += esc(l.finding.substring(0, 100));
                html += '<br><small style="color:var(--gami-text-dim)">Lift +' + l.lift_percent + '% | ' + l.confidence + '% Konfidenz</small>';
                html += '</div>';
            });
            $('#gami-learnings-preview').html(html || '<div class="gami-empty">Noch keine Learnings</div>');
        });
    }

    // === EVENTS ===
    function bindEvents() {
        // Neue Kampagne
        $('#gami-new-campaign').on('submit', function(e) {
            e.preventDefault();
            var platforms = [];
            $('input[name="platforms[]"]:checked').each(function() {
                platforms.push($(this).val());
            });

            var btn = $('#gami-submit-campaign');
            btn.prop('disabled', true).text('🤖 Analysiere...');
            $('#gami-new-result').show().text('Claude analysiert Produkt...');

            $.post(GAMI.ajax_url, {
                action: 'gami_new_product',
                nonce: GAMI.nonce,
                url: $('#new-url').val(),
                budget: $('#new-budget').val(),
                context: $('#new-context').val(),
                platforms: platforms
            }, function(res) {
                btn.prop('disabled', false).text('🤖 KI BEAUFTRAGEN →');
                if (res.success) {
                    $('#gami-new-result').css('color', '#00c9a7').text('✅ ' + res.data.message);
                    loadStats();
                } else {
                    $('#gami-new-result').css('color', '#ff4d4f').text('❌ Fehler: ' + res.data);
                }
            });
        });

        // Agent-Loops manuell
        $('[data-loop]').on('click', function(e) {
            e.preventDefault();
            var loop = $(this).data('loop');
            var btn = $(this);
            btn.prop('disabled', true);
            $('#gami-action-result').text('Loop wird ausgeführt...');

            $.post(GAMI.ajax_url, {
                action: 'gami_run_loop',
                nonce: GAMI.nonce,
                loop: loop
            }, function(res) {
                btn.prop('disabled', false);
                $('#gami-action-result').text(res.success ? '✅ ' + res.data : '❌ Fehler');
                if (res.success) loadStats();
            });
        });

        // Learning-Analyse
        $('#gami-run-learn, #gami-run-learn-btn').on('click', function(e) {
            e.preventDefault();
            $(this).prop('disabled', true).text('Analysiere...');
            $.post(GAMI.ajax_url, {
                action: 'gami_run_loop',
                nonce: GAMI.nonce,
                loop: 'learn'
            }, function(res) {
                location.reload();
            });
        });

        // Demo-Daten generieren
        $('#gami-demo-data').on('click', function() {
            var btn = $(this).prop('disabled', true).text('Generiere...');
            $('#gami-test-result').text('Erstelle realistische Test-Daten...');
            $.post(GAMI.ajax_url, {action: 'gami_demo_data', nonce: GAMI.nonce}, function(res) {
                btn.prop('disabled', false).text('🎲 Demo-Daten generieren');
                $('#gami-test-result').css('color', res.success ? '#00c9a7' : '#ff4d4f')
                    .text(res.success ? res.data : '❌ ' + res.data);
            });
        });

        // Learning + Daily-Loop aus Settings
        $('#gami-run-learn-settings, #gami-run-daily-settings').on('click', function() {
            var loop = $(this).is('#gami-run-learn-settings') ? 'learn' : 'daily';
            var btn = $(this).prop('disabled', true);
            $.post(GAMI.ajax_url, {action: 'gami_run_loop', nonce: GAMI.nonce, loop: loop}, function(res) {
                btn.prop('disabled', false);
                $('#gami-test-result').css('color', res.success ? '#00c9a7' : '#ff4d4f')
                    .text(res.success ? '✅ ' + res.data : '❌ ' + res.data);
            });
        });

        // Settings speichern
        $('#gami-save-settings').on('click', function() {
            var settings = {};
            $('.gami-settings-grid input, .gami-settings-grid select').each(function() {
                var name = $(this).attr('name');
                if (!name) return;
                settings[name] = $(this).is(':checkbox') ? ($(this).is(':checked') ? '1' : '0') : $(this).val();
            });

            $(this).prop('disabled', true).text('Speichert...');
            $.post(GAMI.ajax_url, {
                action: 'gami_save_settings',
                nonce: GAMI.nonce,
                settings: settings
            }, function(res) {
                $('#gami-save-settings').prop('disabled', false).text('Alle Einstellungen speichern');
                $('#gami-save-status').text(res.success ? '✅ Gespeichert' : '❌ Fehler');
                setTimeout(function() { $('#gami-save-status').text(''); }, 3000);
            });
        });
    }

    // === AUTO-REFRESH alle 5 Min ===
    function autoRefresh() {
        setInterval(function() {
            if ($('#gami-kpis').length) loadStats();
        }, 5 * 60 * 1000);
    }

    // === HELPERS ===
    function fmt(n, dec) {
        if (!n || isNaN(n)) return '0.' + '0'.repeat(dec);
        return parseFloat(n).toLocaleString('de-DE', {minimumFractionDigits: dec, maximumFractionDigits: dec});
    }
    function fmtInt(n) {
        if (!n) return '0';
        return parseInt(n).toLocaleString('de-DE');
    }
    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})(jQuery);
