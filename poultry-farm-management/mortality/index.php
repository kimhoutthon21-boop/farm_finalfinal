<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();
$R = resolve_range('30d'); $houseId = (int)get('house_id'); $cycleId = (int)get('cycle_id');
[$W, $P] = report_scope($R, $houseId, $cycleId);
$FROM = ' FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id LEFT JOIN death_causes dc ON dc.id = r.death_cause_id ';
$dailyWarn = (float)setting('daily_mortality_warn_pct', 0.2); $cycleWarn = (float)setting('cycle_mortality_warn_pct', 5);
$totDeaths = (int)val("SELECT COALESCE(SUM(r.deaths),0) $FROM WHERE $W", $P);
$cyW = '1=1'; $cyP = []; if ($houseId) { $cyW .= ' AND house_id = ?'; $cyP[] = $houseId; } if ($cycleId) { $cyW .= ' AND id = ?'; $cyP[] = $cycleId; }
$cycles = cycle_stats($cyW, $cyP, "FIELD(status,'active','closed','archived'), house_number, placement_date DESC");
$livingActive = array_sum(array_map(fn($c) => $c['status'] === 'active' ? $c['living'] : 0, $cycles));
$daily = rows("SELECT r.report_date d, h.house_number hn, SUM(r.deaths) deaths, MAX(c.initial_population) placed $FROM WHERE $W GROUP BY r.report_date, h.house_number", $P);
$chart = series_by_house($R, $daily, 'deaths'); $chart['hasData'] = (bool)$daily;
// cumulative deaths per selected cycle (full cycle history, not limited by date filter)
$cum = []; foreach (array_slice($cycles, 0, 6) as $c) { $run = 0; $pts = rows("SELECT report_date, deaths FROM daily_reports WHERE cycle_id = ? AND status = 'submitted' ORDER BY report_date", [$c['id']]); $arr = [['x' => 0, 'y' => 0]]; foreach ($pts as $p) { $run += (int)$p['deaths']; $arr[] = ['x' => days_between($c['placement_date'], $p['report_date']) + 1, 'y' => $run]; } if (count($arr) > 1) $cum[] = ['label' => house_label($c['house_number']) . ' · ' . $c['cycle_name'], 'data' => $arr]; }
$pg = paginate((int)val("SELECT COUNT(*) $FROM WHERE $W AND r.deaths > 0", $P), 15);
$hist = rows("SELECT r.id, r.report_date, r.deaths, r.standard_deaths, dc.name cause, r.death_cause_detail, c.initial_population, c.cycle_name, h.house_number $FROM WHERE $W AND r.deaths > 0 ORDER BY r.report_date DESC, h.house_number LIMIT " . $pg['per'] . ' OFFSET ' . $pg['offset'], $P);
page_start('Mortality', 'mortality');
page_head('Mortality analytics', 'Mortality figures for the selected range.');
echo range_filter_html($R, ['house_id' => $houseId ?: '', 'cycle_id' => $cycleId ?: '']) . house_cycle_filter_html($R, $houseId, $cycleId, 'mortality/index.php');
echo '<div class="grid g4 mb">'
    . farm_metric_card('alert', 'Deaths in selected range', fmt_int($totDeaths), 'metric-rose', 'DEATHS', e(fmt_date($R['from']) . ' – ' . fmt_date($R['to'])))
    . farm_metric_card('users', 'Living now (active cycles)', fmt_int($livingActive), 'metric-green', 'LIVING BIRDS')
    . farm_metric_card('activity', 'Daily warning level', $dailyWarn > 0 ? fmt_pct($dailyWarn) : 'Off', 'metric-gold', 'DAILY LIMIT', 'of placed birds in one day')
    . farm_metric_card('activity', 'Cycle mortality limit', $cycleWarn > 0 ? fmt_pct($cycleWarn) : 'Off', 'metric-violet', 'CYCLE LIMIT', '<a href="' . e(url('settings/index.php#alerts')) . '">Change limits</a>')
    . '</div>';
echo '<div class="grid g2 mb"><div class="card chart-bento" data-spotlight><div class="card-head"><h2>Daily deaths by house</h2></div>' . ($chart['hasData'] ? '<div class="chart-box"><canvas id="ch-daily"></canvas></div>' : empty_state('No reports in this range', '', 'activity')) . '</div><div class="card chart-bento" data-spotlight><div class="card-head"><h2>Cumulative deaths by cycle day</h2></div>' . ($cum ? '<div class="chart-box"><canvas id="ch-cum"></canvas></div>' : empty_state('No deaths recorded yet', '', 'activity')) . '</div></div>';
echo '<div class="card chart-bento mb" data-spotlight><div class="card-head"><h2>By production cycle</h2></div>';
if (!$cycles) echo empty_state('No cycles', '', 'layers'); else {
    echo '<div class="table-wrap"><table><thead><tr><th>Cycle</th><th>House</th><th class="num">Placed</th><th class="num">Deaths</th><th class="num">Living</th><th class="num">Mortality</th><th>Status</th></tr></thead><tbody>';
    foreach ($cycles as $c) { $over = $cycleWarn > 0 && $c['mortality_pct'] >= $cycleWarn; echo '<tr><td><a href="' . e(url('cycles/view.php?id=' . $c['id'])) . '">' . e($c['cycle_name']) . '</a></td><td>' . e(house_label($c['house_number'])) . '</td><td class="num">' . fmt_int($c['initial_population']) . '</td><td class="num">' . fmt_int($c['deaths']) . '</td><td class="num">' . fmt_int($c['living']) . '</td><td class="num"><strong>' . fmt_pct($c['mortality_pct']) . '</strong></td><td>' . ($over ? badge('Above limit', 'danger') : cycle_status_badge($c['status'])) . '</td></tr>'; }
    echo '</tbody></table></div>'; }
echo '</div><div class="card chart-bento" data-spotlight><div class="card-head"><h2>Days with deaths</h2></div>';
if (!$hist) echo empty_state('No deaths recorded in this range', 'Good news, or no reports yet.', 'check'); else {
    echo '<div class="table-wrap"><table><thead><tr><th>Date</th><th>House</th><th class="num">Deaths</th><th class="num">Standard</th><th class="num">Daily mortality</th><th>Cause</th><th></th></tr></thead><tbody>';
    foreach ($hist as $x) { $p = pct_of($x['deaths'], $x['initial_population']); echo '<tr><td>' . e(fmt_date($x['report_date'])) . '</td><td>' . e(house_label($x['house_number'])) . '</td><td class="num">' . fmt_int($x['deaths']) . ($x['standard_deaths'] !== null && $x['deaths'] > $x['standard_deaths'] ? ' ' . badge('Above', 'danger') : '') . '</td><td class="num muted">' . ($x['standard_deaths'] === null ? '—' : fmt_int($x['standard_deaths'])) . '</td><td class="num">' . fmt_pct($p) . ($dailyWarn > 0 && $p >= $dailyWarn ? ' ' . badge('High', 'warn') : '') . '</td><td class="small">' . e(($x['cause'] ?? '—') . ($x['death_cause_detail'] ? ': ' . $x['death_cause_detail'] : '')) . '</td><td class="act"><a class="btn btn-ghost btn-sm" href="' . e(url('reports/view.php?id=' . $x['id'])) . '">Report</a></td></tr>'; }
    echo '</tbody></table></div>' . pagination_html($pg); }
echo '</div>';
$cdata = ['daily' => $chart, 'cum' => $cum];
echo '<script>window.__mort = ' . json_encode($cdata, JSON_HEX_TAG) . ';</script>';
page_end(['assets/js/vendor/chart.umd.min.js', 'assets/js/charts.js', 'assets/js/mortality.js']);
