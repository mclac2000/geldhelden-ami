<?php defined('ABSPATH') || exit; ?>
<div class="wrap gami-wrap">
<div class="gami-header">
    <div class="gami-logo">◈ GELDHELDEN AMI <span>Kampagnen</span></div>
</div>

<?php
global $wpdb;
$c_t = GAMI_Database::get_table('campaigns');
$a_t = GAMI_Database::get_table('ads');
$s_t = GAMI_Database::get_table('ad_stats');
$p_t = GAMI_Database::get_table('products');

$platform_filter = sanitize_text_field($_GET['platform'] ?? '');
$status_filter   = sanitize_text_field($_GET['status'] ?? 'active');

$where = $wpdb->prepare("WHERE c.status = %s", $status_filter);
if ($platform_filter) {
    $where .= $wpdb->prepare(" AND c.platform = %s", $platform_filter);
}

$campaigns = $wpdb->get_results("
    SELECT c.*, p.name as product_name,
           SUM(s.spend) as total_spend_all,
           AVG(s.cpl) as avg_cpl,
           AVG(s.roas) as avg_roas,
           SUM(s.conversions) as total_conv,
           COUNT(DISTINCT a.id) as ad_count
    FROM $c_t c
    LEFT JOIN $p_t p ON p.id = c.product_id
    LEFT JOIN $a_t a ON a.campaign_id = c.id
    LEFT JOIN $s_t s ON s.ad_id = a.id
    $where
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
?>

<div class="gami-filter-bar">
    <?php foreach (['all' => 'Alle', 'active' => 'Aktiv', 'paused' => 'Pausiert', 'pending' => 'Entwurf'] as $s => $l): ?>
    <a href="?page=geldhelden-ami-campaigns&status=<?= $s ?>&platform=<?= esc_attr($platform_filter) ?>"
       class="gami-filter-pill <?= $status_filter === $s ? 'active' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
    |
    <?php foreach (['', 'x', 'google', 'meta', 'bing', 'taboola', 'telegram_ads', 'whatsapp'] as $p): ?>
    <a href="?page=geldhelden-ami-campaigns&status=<?= esc_attr($status_filter) ?>&platform=<?= $p ?>"
       class="gami-filter-pill <?= $platform_filter === $p ? 'active' : '' ?>"><?= $p ?: 'Alle' ?></a>
    <?php endforeach; ?>
</div>

<div class="gami-card">
<div class="gami-card-body">
<table class="gami-table gami-campaigns-table">
    <thead>
        <tr>
            <th>Kampagne</th>
            <th>Plattform</th>
            <th>Produkt</th>
            <th>Budget/Tag</th>
            <th>Spend gesamt</th>
            <th>Conv.</th>
            <th>Ø CPL</th>
            <th>Ø ROAS</th>
            <th>Ads</th>
            <th>Status</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($campaigns as $c): ?>
    <tr>
        <td><strong><?= esc_html($c->name) ?></strong><br><small><?= date('d.m.Y', strtotime($c->created_at)) ?></small></td>
        <td><span class="platform-badge platform-<?= esc_attr($c->platform) ?>"><?= esc_html(strtoupper($c->platform)) ?></span></td>
        <td><?= esc_html($c->product_name ?? '—') ?></td>
        <td>€<?= number_format($c->budget_day, 2) ?></td>
        <td>€<?= number_format($c->total_spend_all ?? 0, 2) ?></td>
        <td><?= intval($c->total_conv ?? 0) ?></td>
        <td>€<?= number_format($c->avg_cpl ?? 0, 2) ?></td>
        <td><?= number_format($c->avg_roas ?? 0, 2) ?>x</td>
        <td><?= intval($c->ad_count) ?></td>
        <td><span class="status-badge status-<?= esc_attr($c->status) ?>"><?= esc_html($c->status) ?></span></td>
        <td>
            <a href="#" class="gami-action" data-action="pause" data-id="<?= $c->id ?>">⏸</a>
            <a href="?page=geldhelden-ami-campaigns&campaign_id=<?= $c->id ?>">📊</a>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($campaigns)): ?>
    <tr><td colspan="11" class="gami-empty-row">Keine Kampagnen gefunden.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>

<?php
// Wenn eine spezifische Kampagne ausgewählt
$campaign_id = intval($_GET['campaign_id'] ?? 0);
if ($campaign_id):
    $ads = $wpdb->get_results($wpdb->prepare("
        SELECT a.*, SUM(s.impressions) as imps, SUM(s.clicks) as clicks,
               AVG(s.ctr) as ctr, SUM(s.spend) as spend,
               SUM(s.conversions) as convs, AVG(s.cpl) as cpl
        FROM $a_t a
        LEFT JOIN $s_t s ON s.ad_id = a.id
        WHERE a.campaign_id = %d
        GROUP BY a.id ORDER BY cpl ASC
    ", $campaign_id));
?>
<div class="gami-card" style="margin-top:20px">
    <div class="gami-card-header">📢 ADS in Kampagne #<?= $campaign_id ?></div>
    <div class="gami-card-body">
    <?php foreach ($ads as $ad): ?>
    <div class="gami-ad-card ad-status-<?= esc_attr($ad->status) ?>">
        <div class="ad-meta">
            <span class="platform-badge platform-<?= esc_attr($ad->platform) ?>"><?= strtoupper($ad->platform) ?></span>
            <span class="status-badge status-<?= esc_attr($ad->status) ?>">Var.<?= esc_html($ad->variant_name) ?> — <?= esc_html($ad->angle) ?></span>
        </div>
        <?php if ($ad->headline): ?><div class="ad-headline"><?= esc_html($ad->headline) ?></div><?php endif; ?>
        <div class="ad-body"><?= esc_html($ad->body_text) ?></div>
        <?php if ($ad->cta_text): ?><div class="ad-cta">→ <?= esc_html($ad->cta_text) ?></div><?php endif; ?>
        <div class="ad-stats">
            Imps: <?= number_format($ad->imps ?? 0) ?> |
            CTR: <?= number_format($ad->ctr ?? 0, 3) ?>% |
            Spend: €<?= number_format($ad->spend ?? 0, 2) ?> |
            Conv: <?= intval($ad->convs ?? 0) ?> |
            CPL: €<?= number_format($ad->cpl ?? 0, 2) ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</div>
