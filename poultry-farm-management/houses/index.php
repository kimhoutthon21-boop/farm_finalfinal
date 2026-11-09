<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login(); $t = today();
$ids = allowed_house_ids();
$houses = $ids ? rows('SELECT * FROM houses WHERE id IN (' . in_placeholders($ids) . ') ORDER BY house_number', $ids) : [];
page_start(is_admin() ? 'Chicken houses' : 'My houses', 'houses');
page_head(is_admin() ? 'Chicken houses' : 'My houses', 'Select a house to see its flock, daily history and previous cycles.',
  is_admin() ? '<button class="btn btn-primary btn-featured" data-modal-open="m-house">' . icon('plus', 18) . ' Add house</button>' : '');
if (!$houses) echo '<div class="card chart-bento" data-spotlight>' . empty_state('No houses available', is_admin() ? 'Add your first house.' : 'Ask an administrator to give you access to a house.', 'house') . '</div>';
echo '<div class="grid g2">';
foreach ($houses as $h) {
    $c = active_cycle_for_house((int)$h['id']);
    $today = $c ? row("SELECT feed_bags, deaths FROM daily_reports WHERE cycle_id = ? AND report_date = ? AND status = 'submitted'", [$c['id'], $t]) : null;
    echo '<a class="card house-card chart-bento" data-spotlight href="' . e(url('houses/view.php?id=' . $h['id'])) . '"><div class="row"><h3>' . e(house_label($h['house_number'])) . '</h3>' . house_status_badge($h['status']) . '</div>';
    if ($c) {
        echo '<div class="small muted" style="margin-bottom:8px">' . e($c['cycle_name']) . ' · Day ' . $c['age_days'] . ' of ' . $c['expected_days'] . '</div>' . progress_bar($c['progress_pct'], $c['age_days'] > $c['expected_days'] ? 'warn' : '');
        echo '<div class="kv mt"><div><div class="k">Living</div><div class="v">' . fmt_int($c['living']) . '</div></div><div><div class="k">Deaths today</div><div class="v">' . ($today ? fmt_int($today['deaths']) : '—') . '</div></div><div><div class="k">Feed today</div><div class="v">' . ($today ? fmt_bags($today['feed_bags']) . ' bags' : '—') . '</div></div><div><div class="k">Mortality</div><div class="v">' . fmt_pct($c['mortality_pct']) . '</div></div></div>';
    } else echo '<p class="muted" style="margin:6px 0 0">No active flock. ' . (is_admin() ? 'Start one from Flock Cycles.' : '') . '</p>';
    echo '</a>';
}
echo '</div>';
if (is_admin()) {
    modal_start('m-house', 'Add a house', 'sm');
    echo '<form method="post" action="' . e(url('actions/houses.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="create"><div class="field"><label>House number</label><input type="number" name="house_number" min="1" max="999" required><div class="hint">Houses are identified by number, for example 5.</div></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary btn-featured">Add house</button></div></form>';
    modal_end();
}
page_end();
