<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$t = today();

// ---------- Worker home ----------
if (!is_admin()) {
    $ids = allowed_house_ids();
    page_start('Today', 'dashboard');
    page_head('Good day, ' . explode(' ', $user['full_name'])[0], fmt_date($t) . ' · your houses and today\'s reports');
    if (!$ids) { echo '<div class="card dashboard-card">' . empty_state('No houses assigned yet', 'Ask an administrator to give you access to a house.', 'house') . '</div>'; page_end(); exit; }
    echo '<div class="grid g3">';
    $houses = rows('SELECT * FROM houses WHERE id IN (' . in_placeholders($ids) . ') ORDER BY house_number', $ids);
    foreach ($houses as $h) {
        $c = active_cycle_for_house((int)$h['id']);
        $rep = $c ? row("SELECT id, deaths, feed_bags FROM daily_reports WHERE cycle_id = ? AND report_date = ? AND status = 'submitted'", [$c['id'], $t]) : null;
        echo '<div class="card dashboard-card house-card" data-spotlight><div class="row"><h3>' . e(house_label($h['house_number'])) . '</h3>' . house_status_badge($h['status']) . '</div>';
        if ($c) {
            echo '<p class="muted small" style="margin:0 0 10px">' . e($c['cycle_name']) . ' · Day ' . $c['age_days'] . ' of ' . $c['expected_days'] . '</p>' . progress_bar($c['progress_pct']);
            echo '<div class="kv mt"><div><div class="k">Living</div><div class="v">' . fmt_int($c['living']) . '</div></div><div><div class="k">Deaths total</div><div class="v">' . fmt_int($c['deaths']) . '</div></div></div><div class="mt">';
            echo $rep ? '<div class="notice ok" style="margin:0 0 10px">' . icon('check', 18) . '<span>Today\'s report is in: ' . fmt_bags($rep['feed_bags']) . ' bags, ' . fmt_int($rep['deaths']) . ' deaths.</span></div><a class="btn" style="width:100%" href="' . e(url('reports/view.php?id=' . $rep['id'])) . '">View report</a>'
                : '<a class="btn btn-primary big-btn" style="width:100%" href="' . e(url('reports/create.php?house=' . $h['id'])) . '">Submit today\'s report</a>';
            echo '</div>';
        } else echo '<p class="muted">No active flock in this house.</p>';
        echo '</div>';
    }
    echo '</div>';
    page_end(); exit;
}

// ---------- Admin dashboard ----------
$R = resolve_range('7d');
$active = active_cycles();
$houses = rows('SELECT * FROM houses ORDER BY house_number');
$yesterday = add_days($t, -1);
$agg = fn($d) => row("SELECT COALESCE(SUM(feed_bags),0) feed, COALESCE(SUM(deaths),0) deaths, COUNT(*) n FROM daily_reports WHERE report_date = ? AND status = 'submitted'", [$d]);
$todayAgg = $agg($t); $yAgg = $agg($yesterday);
$living = array_sum(array_column($active, 'living')); $placed = array_sum(array_column($active, 'initial_population')); $deathsActive = array_sum(array_column($active, 'deaths'));
$mortality = pct_of($deathsActive, $placed);
$activeHouses = count($active);

// Chart data (null = no report that day, so a missing report is never drawn as "0 deaths")
$series = rows("SELECT r.report_date d, h.house_number hn, SUM(r.deaths) deaths, SUM(r.feed_bags) feed FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id WHERE r.status = 'submitted' AND r.report_date BETWEEN ? AND ? GROUP BY r.report_date, h.house_number", [$R['from'], $R['to']]);
$labels = []; for ($d = $R['from']; $d <= $R['to']; $d = add_days($d, 1)) $labels[] = $d;
$map = []; foreach ($series as $s) $map[$s['hn']][$s['d']] = $s;
$ds = fn($key) => array_values(array_map(function ($hn) use ($map, $labels, $key) { return ['label' => house_label($hn), 'data' => array_map(fn($d) => isset($map[$hn][$d]) ? (float)$map[$hn][$d][$key] : null, $labels)]; }, array_keys($map)));
$deathsByDay = []; foreach ($series as $s) $deathsByDay[$s['d']] = ($deathsByDay[$s['d']] ?? 0) + (int)$s['deaths'];
$chart = [
    'labels' => array_map(fn($d) => date('d M', strtotime($d)), $labels),
    'deathLabels' => array_merge([date('d M', strtotime(add_days($R['from'], -1)))], array_map(fn($d) => date('d M', strtotime($d)), $labels)),
    'deaths' => [['label' => 'Total deaths', 'data' => array_merge([0], array_map(fn($d) => $deathsByDay[$d] ?? null, $labels))]],
    'feed' => $ds('feed'),
    'hasData' => (bool)$series,
];
$recent = rows("SELECT r.id, r.report_date, r.feed_bags, r.deaths, r.status, r.revision, h.house_number, w.full_name worker FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id JOIN users w ON w.id = r.worker_id ORDER BY r.report_date DESC, r.id DESC LIMIT 8");
$alerts = farm_alerts();
$todayRows = rows("SELECT c.id cycle_id, r.id rid, r.feed_bags, r.deaths, r.status, (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM daily_report_tasks x JOIN work_task_categories t ON t.id = x.task_id WHERE x.report_id = r.id) tasks FROM production_cycles c LEFT JOIN daily_reports r ON r.cycle_id = c.id AND r.report_date = ? AND r.status = 'submitted' WHERE c.status = 'active'", [$t]);
$todayBy = array_column($todayRows, null, 'cycle_id');

function trend(float $now, float $prev): string {
    if ($prev == 0 && $now == 0) return '<span class="muted">same as yesterday</span>';
    if ($prev == 0) return '<span class="muted">no report yesterday</span>';
    $d = $now - $prev; $p = abs($d) / $prev * 100;
    return '<span class="' . ($d > 0 ? 'up' : 'down') . '">' . ($d > 0 ? '▲' : ($d < 0 ? '▼' : '•')) . ' ' . number_format($p, 0) . '%</span> <span class="muted">vs yesterday</span>';
}
page_start('Dashboard', 'dashboard');
page_head('Farm dashboard', 'Live figures from daily reports · ' . fmt_date($t), '<a class="btn btn-primary btn-featured" href="' . e(url('reports/create.php')) . '">' . icon('plus', 18) . ' New daily report</a>');
echo range_filter_html($R);
if ($R['cycle_missing']) echo '<div class="notice">' . icon('info', 18) . '<span>There is no active cycle, so "This cycle" shows the last 30 days.</span></div>';
$cards = [
  ['house', 'Active chicken houses', $activeHouses, 0, 'LIVE', 'metric-coral', 'houses/index.php', ''],
  ['users', 'Total living chickens', $living, 0, 'BIRDS', 'metric-blue', 'houses/index.php', ''],
  ['package', 'Feed consumed today (bags)', (float)$todayAgg['feed'], 0, 'VS YESTERDAY', 'metric-gold', 'feed/index.php?range=' . $R['range'] . '&from=' . $R['from'] . '&to=' . $R['to'], trend((float)$todayAgg['feed'], (float)$yAgg['feed'])],
  ['alert', 'Deaths today', (int)$todayAgg['deaths'], 0, 'VS YESTERDAY', 'metric-rose', 'mortality/index.php?range=' . $R['range'] . '&from=' . $R['from'] . '&to=' . $R['to'], trend((float)$todayAgg['deaths'], (float)$yAgg['deaths'])],
  ['activity', 'Mortality rate (active cycles)', $mortality, 2, 'CYCLE LIMIT', 'metric-violet', 'mortality/index.php', ''],
  ['layers', 'Current active cycles', count($active), 0, 'RUNNING', 'metric-green', 'cycles/index.php?status=active', ''],
];
echo '<div class="grid g6 mb">';
foreach ($cards as [$ic, $label, $v, $dec, $aside, $tone, $href, $indicator]) {
    echo '<a class="card farm-metric-card ' . $tone . '" href="' . e(url($href)) . '">'
        . '<div class="metric-main"><div class="metric-background" aria-hidden="true"><span></span><span></span><span></span></div>'
        . '<div class="metric-primary"><div class="metric-label"><span class="metric-icon">' . icon($ic, 20) . '</span><span>' . e($label) . '</span></div>'
        . '<div class="metric-value" data-count="' . e((string)$v) . '" data-dec="' . $dec . '">' . number_format($v, $dec) . ($ic === 'activity' ? '<span class="metric-unit">%</span>' : '') . '</div></div>'
        . '<div class="metric-aside"><span class="metric-aside-label">' . e($aside) . '</span>' . ($indicator ? '<span class="metric-indicator">' . $indicator . '</span>' : '') . '</div></div></a>';
}
echo '</div>';
?>
<div class="grid g-main mb">
  <div class="card chart-bento" data-spotlight>
    <div class="card-head"><h2>Daily activity today</h2><span class="muted small"><?= count(array_filter($todayRows, fn($r) => $r['rid'])) ?> of <?= count($active) ?> reports in</span></div>
    <?php if (!$active): echo empty_state('No active cycles', 'Add a new cycle to start tracking daily activity.', 'layers', '<a class="btn btn-primary btn-featured" href="' . e(url('cycles/index.php?new=1')) . '">Add New Cycle</a>'); else: ?>
    <div class="table-wrap"><table><thead><tr><th>House</th><th>Report</th><th class="num">Feed (bags)</th><th class="num">Deaths</th><th>Work done</th></tr></thead><tbody>
    <?php foreach ($active as $c): $tr = $todayBy[$c['id']] ?? null; ?>
      <tr><td><a href="<?= e(url('houses/view.php?id=' . $c['house_id'])) ?>"><?= e(house_label($c['house_number'])) ?></a></td>
      <td><?= ($tr && $tr['rid']) ? '<a href="' . e(url('reports/view.php?id=' . $tr['rid'])) . '">' . badge('Submitted', 'success') . '</a>' : '<a href="' . e(url('reports/create.php?house=' . $c['house_id'])) . '">' . badge('Missing', 'warn') . '</a>' ?></td>
      <td class="num"><?= ($tr && $tr['rid']) ? fmt_bags($tr['feed_bags']) : '—' ?></td><td class="num"><?= ($tr && $tr['rid']) ? fmt_int($tr['deaths']) : '—' ?></td><td class="small muted"><?= e($tr['tasks'] ?? '') ?: '—' ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
  <div class="card chart-bento" data-spotlight>
    <div class="card-head"><h2>Alerts</h2><?= $alerts ? badge(count($alerts) . '', 'warn') : '' ?></div>
    <?php if (!$alerts): echo '<div class="notice ok">' . icon('check', 18) . '<span>No alerts. Reports are up to date and mortality is within limits.</span></div>'; else: ?>
    <div class="alert-list"><?php foreach (array_slice($alerts, 0, 6) as $a): ?><a class="alert-item <?= e($a['level']) ?>" href="<?= e(url($a['url'])) ?>"><?= icon($a['level'] === 'info' ? 'info' : 'alert', 18) ?><div><strong><?= e($a['title']) ?></strong><span><?= e($a['text']) ?></span></div></a><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<div class="grid g2 mb">
  <div class="card chart-bento" data-spotlight><div class="card-head"><h2>Mortality trend</h2><span class="muted small">deaths per day, <?= e(fmt_date($R['from'])) ?> – <?= e(fmt_date($R['to'])) ?></span></div>
    <?php if ($chart['hasData']): ?><div class="chart-box"><canvas id="ch-deaths"></canvas></div><?php else: echo empty_state('No reports in this range', 'Try a wider date range, or submit a daily report.', 'activity'); endif; ?></div>
  <div class="card chart-bento" data-spotlight><div class="card-head"><h2>Feed consumption</h2><span class="muted small">bags per day</span></div>
    <?php if ($chart['hasData']): ?><div class="chart-box"><canvas id="ch-feed"></canvas></div><?php else: echo empty_state('No feed data in this range', '', 'package'); endif; ?></div>
</div>
<div class="grid g-main mb">
  <div class="card chart-bento" data-spotlight><div class="card-head"><h2>Recent daily reports</h2><a class="btn btn-primary btn-sm" href="<?= e(url('reports/index.php')) ?>">View all</a></div>
    <?php if (!$recent): echo empty_state('No reports yet', 'Reports appear here as soon as workers submit them.', 'file'); else: ?>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>House</th><th>Worker</th><th class="num">Feed</th><th class="num">Deaths</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?><tr><td><a href="<?= e(url('reports/view.php?id=' . $r['id'])) ?>"><?= e(fmt_date($r['report_date'])) ?></a></td><td><?= e(house_label($r['house_number'])) ?></td><td><?= e($r['worker']) ?></td><td class="num"><?= fmt_bags($r['feed_bags']) ?></td><td class="num"><?= fmt_int($r['deaths']) ?></td><td><?= report_status_badge($r) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  <div class="card chart-bento" data-spotlight><div class="card-head"><h2>Active cycles</h2></div>
    <?php if (!$active): echo empty_state('No active cycles', '', 'layers'); else: foreach ($active as $c): ?>
      <a href="<?= e(url('cycles/view.php?id=' . $c['id'])) ?>" style="display:block;color:inherit;margin-bottom:16px"><div style="display:flex;justify-content:space-between"><strong><?= e(house_label($c['house_number'])) ?></strong><span class="muted small">Day <?= $c['age_days'] ?> of <?= $c['expected_days'] ?></span></div><div class="small muted" style="margin:2px 0 6px"><?= e($c['cycle_name']) ?> · <?= fmt_int($c['living']) ?> living</div><?= progress_bar($c['progress_pct'], $c['age_days'] > $c['expected_days'] ? 'warn' : '') ?></a>
    <?php endforeach; endif; ?></div>
</div>
<script>window.__dash = <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php page_end(['assets/js/vendor/chart.umd.min.js', 'assets/js/charts.js', 'assets/js/dashboard.js']);
