<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login(); $t = today();
$hid = (int)get('id'); $h = row('SELECT * FROM houses WHERE id = ?', [$hid]);
if (!$h) { http_response_code(404); page_start('Not found', 'houses'); echo '<div class="card">' . empty_state('House not found', '', 'house', '<a class="btn" href="' . e(url('houses/index.php')) . '">Back to houses</a>') . '</div>'; page_end(); exit; }
require_house_access($hid);
$c = active_cycle_for_house($hid);
$all = cycle_stats('house_id = ?', [$hid], 'placement_date DESC');
$sel = null; $selId = (int)get('cycle');
foreach ($all as $x) if ($selId && (int)$x['id'] === $selId) $sel = $x;
$sel = $sel ?? $c ?? ($all[0] ?? null);
$today = $c ? row("SELECT feed_bags, deaths FROM daily_reports WHERE cycle_id = ? AND report_date = ? AND status = 'submitted'", [$c['id'], $t]) : null;
page_start(house_label($h['house_number']), 'houses');
$act = '';
if ($c) $act .= '<a class="btn btn-primary" href="' . e(url('reports/create.php?house=' . $hid)) . '">' . icon('clipboard', 18) . ' Daily report</a>';
if (is_admin() && !$c) $act .= '<a class="btn btn-primary btn-featured" href="' . e(url('cycles/index.php?new=' . $hid)) . '">' . icon('plus', 18) . ' Add New Cycle</a>';
page_head(house_label($h['house_number']), 'Status: ' . ucfirst($h['status']) . ($c ? ' · ' . $c['cycle_name'] : ''), $act);
if (is_admin()) {
    echo '<div class="card mb"><form method="post" action="' . e(url('actions/houses.php')) . '" class="filters" style="margin:0"><input type="hidden" name="op" value="status"><input type="hidden" name="id" value="' . $hid . '">' . csrf_field() . '<div><label>House status</label><select name="status"' . ($c ? ' disabled' : '') . '>';
    foreach (['active' => 'Active', 'empty' => 'Empty', 'cleaning' => 'Cleaning', 'maintenance' => 'Maintenance'] as $k => $l) echo '<option value="' . $k . '"' . ($h['status'] === $k ? ' selected' : '') . '>' . $l . '</option>';
    echo '</select></div>' . ($c ? '' : '<button class="btn btn-primary">Update status</button>') . '</form></div>';
}
if ($c) {
    $stats = [['Current flock', $c['cycle_name']], ['Living population', fmt_int($c['living'])], ['Initial population', fmt_int($c['initial_population'])], ['Cycle start', fmt_date($c['placement_date'])], ['Cycle day', 'Day ' . $c['age_days'] . ' of ' . $c['expected_days']],
      ['Feed today', $today ? fmt_bags($today['feed_bags']) . ' bags' : 'No report yet'], ['Feed this cycle', fmt_bags($c['feed_total']) . ' bags'], ['Deaths today', $today ? fmt_int($today['deaths']) : 'No report yet'], ['Cumulative deaths', fmt_int($c['deaths'])], ['Mortality rate', fmt_pct($c['mortality_pct'])]];
    echo '<div class="card mb" data-spotlight><div class="kv">';
    foreach ($stats as [$k, $v]) echo '<div><div class="k">' . e($k) . '</div><div class="v">' . e($v) . '</div></div>';
    echo '</div><div class="mt">' . progress_bar($c['progress_pct'], $c['age_days'] > $c['expected_days'] ? 'warn' : '') . '</div>' . (is_admin() ? '<div class="mt"><a class="btn btn-sm" href="' . e(url('cycles/view.php?id=' . $c['id'])) . '">Open cycle details</a></div>' : '') . '</div>';
} else echo '<div class="card mb">' . empty_state('No active flock in this house', is_admin() ? 'Add a new cycle to start recording reports.' : 'There is nothing to report right now.', 'house') . '</div>';

if ($sel) {
    $f = ['cycle_id' => $sel['id']]; $st = sort_state(['date' => 'r.report_date', 'house' => 'h.house_number', 'worker' => 'w.full_name', 'feed' => 'r.feed_bags', 'deaths' => 'r.deaths'], 'date');
    $pg = paginate(count_reports($f), 15);
    echo '<div class="card mb"><div class="card-head"><h2>Daily history · ' . e($sel['cycle_name']) . '</h2>';
    if (count($all) > 1) { echo '<form method="get"><input type="hidden" name="id" value="' . $hid . '"><select name="cycle" onchange="this.form.submit()" aria-label="Choose cycle">'; foreach ($all as $x) echo '<option value="' . $x['id'] . '"' . ($x['id'] == $sel['id'] ? ' selected' : '') . '>' . e($x['cycle_name']) . ' (' . $x['status'] . ')</option>'; echo '</select></form>'; }
    echo '</div>'; reports_table(fetch_reports($f, $st['sql'] . ', r.id DESC', $pg['per'], $pg['offset']), false, $st); echo pagination_html($pg) . '</div>';
}
$prev = array_filter($all, fn($x) => $x['status'] !== 'active');
echo '<div class="card"><div class="card-head"><h2>Previous production cycles</h2></div>';
if (!$prev) echo empty_state('No previous cycles', 'Closed cycles are kept here with their full history.', 'archive');
else { echo '<div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Placed</th><th>Closed</th><th class="num">Placed birds</th><th class="num">Deaths</th><th class="num">Mortality</th><th class="num">Feed (bags)</th><th></th></tr></thead><tbody>';
  foreach ($prev as $x) echo '<tr><td>' . e($x['cycle_name']) . ' ' . cycle_status_badge($x['status']) . '</td><td>' . e(fmt_date($x['placement_date'])) . '</td><td>' . e(fmt_date($x['closed_date'])) . '</td><td class="num">' . fmt_int($x['initial_population']) . '</td><td class="num">' . fmt_int($x['deaths']) . '</td><td class="num">' . fmt_pct($x['mortality_pct']) . '</td><td class="num">' . fmt_bags($x['feed_total']) . '</td><td class="act"><a class="btn btn-ghost btn-sm" href="' . e(url('houses/view.php?id=' . $hid . '&cycle=' . $x['id'])) . '">History</a>' . (is_admin() ? ' <a class="btn btn-ghost btn-sm" href="' . e(url('cycles/view.php?id=' . $x['id'])) . '">Details</a>' : '') . '</td></tr>';
  echo '</tbody></table></div>'; }
echo '</div>';
page_end();
