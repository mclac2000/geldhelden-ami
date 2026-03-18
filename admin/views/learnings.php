<?php defined('ABSPATH') || exit; ?>
<div class="wrap gami-wrap">
<div class="gami-header">
    <div class="gami-logo">◈ GELDHELDEN AMI <span>Cross-Platform Learnings</span></div>
    <div class="gami-header-meta">
        <a href="#" id="gami-run-learn" class="gami-btn-sm">🔄 Analyse jetzt</a>
    </div>
</div>

<?php
global $wpdb;
$t = GAMI_Database::get_table('learnings');
$learnings = $wpdb->get_results("SELECT * FROM $t ORDER BY confidence DESC");

$by_type = [];
foreach ($learnings as $l) {
    $by_type[$l->insight_type][] = $l;
}

$type_labels = [
    'media_type'        => '📹 Medientyp',
    'angle'             => '🎯 Angle / Hook',
    'timing'            => '⏰ Timing',
    'product_platform_fit' => '🎪 Produkt-Plattform-Fit',
    'color'             => '🎨 Farben & Visuals',
    'copy'              => '✍️ Textelemente',
];
?>

<div class="gami-learnings-summary">
    <strong><?= count($learnings) ?> Learnings</strong> gespeichert |
    Durchschnittliche Konfidenz: <?= $learnings ? round(array_sum(array_column((array)$learnings, 'confidence')) / count($learnings), 1) : 0 ?>%
</div>

<?php foreach ($by_type as $type => $items): ?>
<div class="gami-card">
    <div class="gami-card-header"><?= $type_labels[$type] ?? strtoupper($type) ?> (<?= count($items) ?>)</div>
    <div class="gami-card-body">
        <table class="gami-table">
            <thead>
                <tr>
                    <th>Quelle</th>
                    <th>Ziel-Plattformen</th>
                    <th>Erkenntnis</th>
                    <th>Lift</th>
                    <th>Konfidenz</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $l): ?>
            <tr class="learning-row learning-<?= esc_attr($l->status) ?>">
                <td><span class="platform-badge platform-<?= esc_attr($l->source_platform) ?>"><?= esc_html(strtoupper($l->source_platform)) ?></span></td>
                <td>
                    <?php foreach (explode(',', $l->target_platforms) as $tp): ?>
                    <span class="platform-badge platform-<?= esc_attr(trim($tp)) ?>"><?= esc_html(strtoupper(trim($tp))) ?></span>
                    <?php endforeach; ?>
                </td>
                <td><?= esc_html($l->finding) ?></td>
                <td class="<?= $l->lift_percent > 0 ? 'positive' : '' ?>">+<?= esc_html($l->lift_percent) ?>%</td>
                <td>
                    <div class="confidence-bar">
                        <div class="confidence-fill" style="width:<?= esc_attr($l->confidence) ?>%"></div>
                        <span><?= esc_html($l->confidence) ?>%</span>
                    </div>
                </td>
                <td><span class="status-badge status-<?= esc_attr($l->status) ?>"><?= esc_html($l->status) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($learnings)): ?>
<div class="gami-card">
    <div class="gami-card-body gami-empty">
        <p>Noch keine Learnings. Starte die Learning-Analyse sobald Kampagnen-Daten vorliegen.</p>
        <button class="gami-btn gami-btn-primary" id="gami-run-learn-btn">🧠 Analyse starten</button>
    </div>
</div>
<?php endif; ?>

</div>
