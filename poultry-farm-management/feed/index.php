<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin(); $t = today();
$R = resolve_range('30d'); $houseId = (int)get('house_id'); $cycleId = (int)get('cycle_id');
[$W, $P] = report_scope($R, $houseId, $cycleId);
$FROM = ' FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id ';
$tot = row("SELECT COALESCE(SUM(r.feed_bags),0) bags, COUNT(*) n, COUNT(DISTINCT r.report_date) days $FROM WHERE $W", $P);
$byHouse = rows("SELECT h.house_number, SUM(r.feed_bags) bags, COUNT(*) n $FROM WHERE $W GROUP BY h.house_number ORDER BY h.house_number", $P);
$byCycle = rows("SELECT c.id, c.cycle_name, c.status, h.house_number, SUM(r.feed_bags) bags, COUNT(*) n $FROM WHERE $W GROUP BY c.id, c.cycle_name, c.status, h.house_number ORDER BY h.house_number, c.placement_date DESC", $P);
$daily = rows("SELECT r.report_date d, h.house_number hn, SUM(r.feed_bags) bags $FROM WHERE $W GROUP BY r.report_date, h.house_number", $P);
foreach ($daily as &$dd) $dd['feed'] = $dd['bags']; unset($dd);
$chart = series_by_house($R, $daily, 'feed'); $chart['hasData'] = (bool)$daily;
$pg = paginate((int)$tot['n'], 15);
$hist = rows("SELECT r.id, r.report_date, r.feed_bags, h.house_number, c.cycle_name $FROM WHERE $W ORDER BY r.report_date DESC, h.house_number LIMIT " . $pg['per'] . ' OFFSET ' . $pg['offset'], $P);
$avgDay = $tot['days'] ? (float)$tot['bags'] / (int)$tot['days'] : 0;
// Stock: only an estimate, and only when stock movements have been recorded.
$base = val("SELECT COALESCE((SELECT MAX(txn_date) FROM feed_inventory_transactions WHERE type = 'opening'), (SELECT MIN(txn_date) FROM feed_inventory_transactions))");
$stock = null;
if ($base) {
    $in = (float)val('SELECT COALESCE(SUM(bags),0) FROM feed_inventory_transactions WHERE txn_date >= ?', [$base]);
    $used = (float)val("SELECT COALESCE(SUM(feed_bags),0) FROM daily_reports WHERE status = 'submitted' AND report_date >= ?", [$base]);
    $stock = ['base' => $base, 'in' => $in, 'used' => $used, 'est' => $in - $used];
}
$txns = rows('SELECT f.*, u.full_name FROM feed_inventory_transactions f JOIN users u ON u.id = f.created_by ORDER BY f.txn_date DESC, f.id DESC LIMIT 15');
page_start('Feed', 'feed');
page_head('Feed tracking', 'Consumption comes straight from daily reports. Unit: bags.', '<button class="btn btn-primary btn-featured" data-modal-open="m-stock">' . icon('plus', 18) . ' Record stock</button>');
echo range_filter_html($R, ['house_id' => $houseId ?: '', 'cycle_id' => $cycleId ?: '']);
echo house_cycle_filter_html($R, $houseId, $cycleId, 'feed/index.php');
echo '<div class="grid g4 mb">'
    . farm_metric_card('package', 'Total consumed', fmt_bags($tot['bags']), 'metric-gold', 'BAGS', e(fmt_date($R['from']) . ' – ' . fmt_date($R['to'])))
    . farm_metric_card('activity', 'Average per reporting day', fmt_bags($avgDay), 'metric-blue', 'BAGS / DAY')
    . farm_metric_card('clipboard', 'Average per report', $tot['n'] ? fmt_bags((float)$tot['bags'] / (int)$tot['n']) : '—', 'metric-violet', 'BAGS / REPORT')
    . farm_metric_card('package', 'Estimated stock now', $stock ? fmt_bags($stock['est']) : '—', 'metric-green', 'STOCK ESTIMATE', e($stock ? 'See stock details below' : 'Stock tracking not set up'))
    . '</div>';
echo '<div class="card chart-bento mb" data-spotlight><div class="card-head"><h2>Daily consumption by house</h2></div>' . ($chart['hasData'] ? '<div class="chart-box"><canvas id="ch-feed"></canvas></div>' : empty_state('No feed reports in this range', 'Try a wider range or a different house.', 'package')) . '</div>';
echo '<div class="grid g2 mb"><div class="card chart-bento" data-spotlight><div class="card-head"><h2>By house</h2></div>';
if (!$byHouse) echo '<p class="muted">No data.</p>'; else { echo '<div class="table-wrap"><table><thead><tr><th>House</th><th class="num">Bags</th><th class="num">Reports</th><th class="num">Avg / report</th></tr></thead><tbody>'; foreach ($byHouse as $x) echo '<tr><td>' . e(house_label($x['house_number'])) . '</td><td class="num">' . fmt_bags($x['bags']) . '</td><td class="num">' . (int)$x['n'] . '</td><td class="num">' . fmt_bags($x['bags'] / $x['n']) . '</td></tr>'; echo '</tbody></table></div>'; }
echo '</div><div class="card chart-bento" data-spotlight><div class="card-head"><h2>By production cycle</h2></div>';
if (!$byCycle) echo '<p class="muted">No data.</p>'; else { echo '<div class="table-wrap"><table><thead><tr><th>Cycle</th><th>House</th><th class="num">Bags</th><th class="num">Reports</th></tr></thead><tbody>'; foreach ($byCycle as $x) echo '<tr><td><a href="' . e(url('cycles/view.php?id=' . $x['id'])) . '">' . e($x['cycle_name']) . '</a> ' . cycle_status_badge($x['status']) . '</td><td>' . e(house_label($x['house_number'])) . '</td><td class="num">' . fmt_bags($x['bags']) . '</td><td class="num">' . (int)$x['n'] . '</td></tr>'; echo '</tbody></table></div>'; }
echo '</div></div><div class="card chart-bento mb" data-spotlight><div class="card-head"><h2>Consumption history</h2></div>';
if (!$hist) echo empty_state('No feed records', '', 'package'); else { echo '<div class="table-wrap"><table><thead><tr><th>Date</th><th>House</th><th>Cycle</th><th class="num">Bags</th><th></th></tr></thead><tbody>'; foreach ($hist as $x) echo '<tr><td>' . e(fmt_date($x['report_date'])) . '</td><td>' . e(house_label($x['house_number'])) . '</td><td class="small">' . e($x['cycle_name']) . '</td><td class="num">' . fmt_bags($x['feed_bags']) . '</td><td class="act"><a class="btn btn-ghost btn-sm" href="' . e(url('reports/view.php?id=' . $x['id'])) . '">Report</a></td></tr>'; echo '</tbody></table></div>' . pagination_html($pg); }
echo '</div><div class="card chart-bento" data-spotlight><div class="card-head"><h2>Feed stock</h2></div>';
if (!$stock) echo '<div class="notice">' . icon('info', 18) . '<span>Stock tracking is not set up yet, so no stock figure is shown. Use “Record stock” to enter an <strong>opening stock</strong> count, then add each feed purchase. Without these the system cannot know how much feed is left.</span></div>';
else echo '<div class="notice warn">' . icon('alert', 18) . '<span>Estimated stock may be inaccurate if any purchases or stock counts are missing.</span></div>';
if ($txns) { echo '<div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th class="num">Bags</th><th>Reference</th><th>Notes</th><th>By</th></tr></thead><tbody>'; foreach ($txns as $x) echo '<tr><td>' . e(fmt_date($x['txn_date'])) . '</td><td>' . badge(ucfirst($x['type']), $x['type'] === 'purchase' ? 'success' : 'info') . '</td><td class="num">' . ((float)$x['bags'] > 0 ? '+' : '') . fmt_bags($x['bags']) . '</td><td>' . e($x['reference'] ?? '—') . '</td><td class="small">' . e($x['notes'] ?? '') . '</td><td class="small">' . e($x['full_name']) . '</td></tr>'; echo '</tbody></table></div>'; }
echo '</div>';
modal_start('m-stock', 'Record feed stock', 'sm');
echo '<form method="post" action="' . e(url('actions/feed.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="add"><div class="field"><label>Type</label><select name="type"><option value="opening">Opening stock count (bags in store)</option><option value="purchase">Feed purchase (bags received)</option><option value="adjustment">Adjustment (+ or − bags)</option></select></div><div class="field"><label>Bags</label><input type="number" name="bags" step="any" required><div class="hint">An opening count is taken at the start of the chosen date.</div></div><div class="field"><label>Date</label><input type="date" name="txn_date" value="' . $t . '" max="' . $t . '" required></div><div class="field"><label>Reference (optional)</label><input name="reference" maxlength="120" placeholder="Invoice no. or supplier"></div><div class="field"><label>Notes (optional)</label><input name="notes" maxlength="500"></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div></form>';
modal_end();
echo '<script>window.__feed = ' . json_encode($chart, JSON_HEX_TAG) . ';</script>';
page_end(['assets/js/vendor/chart.umd.min.js', 'assets/js/charts.js', 'assets/js/feed.js']);
