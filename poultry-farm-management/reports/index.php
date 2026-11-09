<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login(); $admin = is_admin();
$f = ['q' => get('q'), 'from' => valid_date(get('from')) ? get('from') : '', 'to' => valid_date(get('to')) ? get('to') : '', 'house_id' => (int)get('house_id'), 'cycle_id' => (int)get('cycle_id'), 'worker_id' => (int)get('worker_id'), 'status' => get('status')];
$st = sort_state(['date' => 'r.report_date', 'house' => 'h.house_number', 'worker' => 'w.full_name', 'feed' => 'r.feed_bags', 'deaths' => 'r.deaths'], 'date');
$pg = paginate(count_reports($f), 20);
$list = fetch_reports($f, $st['sql'] . ', r.id DESC', $pg['per'], $pg['offset']);
$ids = allowed_house_ids();
$houses = $ids ? rows('SELECT id, house_number FROM houses WHERE id IN (' . in_placeholders($ids) . ') ORDER BY house_number', $ids) : [];
$cyc = $ids ? rows('SELECT c.id, c.cycle_name FROM production_cycles c WHERE c.house_id IN (' . in_placeholders($ids) . ') ORDER BY c.placement_date DESC', $ids) : [];
$workers = $admin ? rows('SELECT id, full_name FROM users ORDER BY full_name') : [];
page_start($admin ? 'Report history' : 'My reports', 'reports');
page_head($admin ? 'Daily report history' : 'My reports', $admin ? 'Search, filter and review every daily report.' : 'Reports you submitted and their status.',
  ($admin ? '<a class="btn btn-featured" href="' . e(url('reports/export.php?' . http_build_query($_GET))) . '">' . icon('download', 18) . ' Export for Excel</a>' : '') . '<a class="btn btn-primary btn-featured" href="' . e(url('reports/create.php')) . '">' . icon('plus', 18) . ' New report</a>');
echo '<div class="card chart-bento" data-spotlight><form method="get" class="filters"><div style="flex:2"><label>Search</label><input type="search" name="q" value="' . e($f['q']) . '" placeholder="Worker, cycle, notes…"' . (get('focus') ? ' autofocus' : '') . '></div><div><label>From</label><input type="date" name="from" value="' . e($f['from']) . '"></div><div><label>To</label><input type="date" name="to" value="' . e($f['to']) . '"></div>';
echo '<div><label>House</label><select name="house_id"><option value="">All</option>'; foreach ($houses as $h) echo '<option value="' . $h['id'] . '"' . ($f['house_id'] == $h['id'] ? ' selected' : '') . '>' . e(house_label($h['house_number'])) . '</option>'; echo '</select></div>';
echo '<div><label>Cycle</label><select name="cycle_id"><option value="">All</option>'; foreach ($cyc as $c) echo '<option value="' . $c['id'] . '"' . ($f['cycle_id'] == $c['id'] ? ' selected' : '') . '>' . e($c['cycle_name']) . '</option>'; echo '</select></div>';
if ($admin) { echo '<div><label>Worker</label><select name="worker_id"><option value="">All</option>'; foreach ($workers as $w) echo '<option value="' . $w['id'] . '"' . ($f['worker_id'] == $w['id'] ? ' selected' : '') . '>' . e($w['full_name']) . '</option>'; echo '</select></div>'; }
echo '<div><label>Status</label><select name="status"><option value="">All</option>'; foreach (['submitted' => 'Submitted', 'corrected' => 'Corrected', 'voided' => 'Voided'] as $k => $l) echo '<option value="' . $k . '"' . ($f['status'] === $k ? ' selected' : '') . '>' . $l . '</option>'; echo '</select></div>';
echo '<button class="btn btn-primary">Filter</button><a class="btn btn-primary" href="?">Reset</a></form>';
reports_table($list, true, $st); echo pagination_html($pg) . '</div>';
page_end();
