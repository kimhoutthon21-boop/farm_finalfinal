<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin(); $t = today();
$c = cycle_stat((int)get('id'));
if (!$c) { http_response_code(404); page_start('Not found', 'cycles'); echo '<div class="card">' . empty_state('Cycle not found', '', 'layers', '<a class="btn" href="' . e(url('cycles/index.php')) . '">Back to cycles</a>') . '</div>'; page_end(); exit; }
$id = (int)$c['id']; $self = 'cycles/view.php?id=' . $id;
$adj = rows('SELECT a.*, u.full_name FROM population_adjustments a JOIN users u ON u.id = a.created_by WHERE a.cycle_id = ? ORDER BY a.adjustment_date DESC, a.id DESC', [$id]);
$fin = row('SELECT (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE cycle_id = ?) AS exp, (SELECT COALESCE(SUM(amount),0) FROM revenue WHERE cycle_id = ?) AS rev', [$id, $id]);
$audit = rows("SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'cycle' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 10", [$id]);
$f = ['cycle_id' => $id]; $st = sort_state(['date' => 'r.report_date', 'house' => 'h.house_number', 'worker' => 'w.full_name', 'feed' => 'r.feed_bags', 'deaths' => 'r.deaths'], 'date');
$pg = paginate(count_reports($f), 15);
page_start($c['cycle_name'], 'cycles');
$act = '<a class="btn" href="' . e(url('cycles/index.php')) . '">All cycles</a>';
if ($c['status'] !== 'active') $act .= '<a class="btn btn-primary btn-featured" href="' . e(url('cycles/export.php?id=' . $id)) . '" title="Download a formatted Excel workbook">' . icon('download', 18) . ' Export for Excel</a>';
if ($c['status'] === 'active') $act .= '<button class="btn" data-modal-open="m-adj">Population adjustment</button><button class="btn btn-danger" data-modal-open="m-close">Close cycle</button>';
page_head($c['cycle_name'], house_label($c['house_number']) . ' · placed ' . fmt_date($c['placement_date']) . ($c['supplier'] ? ' · ' . $c['supplier'] : ''), $act);
echo '<div class="card mb" data-spotlight><div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">' . cycle_status_badge($c['status']) . '<span class="muted">Day ' . $c['age_days'] . ' of ' . $c['expected_days'] . ($c['closed_date'] ? ' · closed ' . fmt_date($c['closed_date']) : '') . '</span></div>' . progress_bar($c['progress_pct'], $c['status'] === 'active' && $c['age_days'] > $c['expected_days'] ? 'warn' : '') . '<div class="kv mt">';
$kv = [['Initially placed', fmt_int($c['initial_population'])], ['Arrivals added', fmt_int($c['arrivals'])], ['Other removals', fmt_int($c['removals'])], ['Total deaths', fmt_int($c['deaths'])], ['Living now', fmt_int($c['living'])], ['Mortality', fmt_pct($c['mortality_pct'])], ['Feed used', fmt_bags($c['feed_total']) . ' bags'], ['Reports', fmt_int($c['report_count'])]];
foreach ($kv as [$k, $v]) echo '<div><div class="k">' . e($k) . '</div><div class="v">' . e($v) . '</div></div>';
echo '</div>';
if ($c['notes']) echo '<p class="mt" style="margin-bottom:0"><strong>Notes:</strong> ' . nl2br(e($c['notes'])) . '</p>'; if ($c['close_notes']) echo '<p><strong>Closing notes:</strong> ' . nl2br(e($c['close_notes'])) . '</p>';
echo '</div>';
$nr = (float)$fin['rev']; $ne = (float)$fin['exp'];
echo '<div class="grid g3 mb"><div class="card"><div class="stat-label">Expenses linked to this cycle</div><div class="stat-value">' . e(fmt_money($ne)) . '</div></div><div class="card"><div class="stat-label">Revenue linked to this cycle</div><div class="stat-value">' . e(fmt_money($nr)) . '</div></div><div class="card"><div class="stat-label">Net of recorded entries</div><div class="stat-value">' . e(fmt_money($nr - $ne)) . '</div></div></div>';
echo '<div class="card mb"><div class="card-head"><h2>Daily reports</h2>' . (is_admin() ? '<a class="btn btn-ghost btn-sm" href="' . e(url('reports/index.php?cycle_id=' . $id)) . '">Open in history</a>' : '') . '</div>'; reports_table(fetch_reports($f, $st['sql'] . ', r.id DESC', $pg['per'], $pg['offset']), false, $st); echo pagination_html($pg) . '</div>';
echo '<div class="grid g2"><div class="card"><div class="card-head"><h2>Population adjustments</h2></div>';
if (!$adj) echo '<p class="muted">None. Exceptional arrivals or removals appear here with a reason.</p>';
else { echo '<ul class="timeline">'; foreach ($adj as $a) echo '<li><strong>' . ($a['type'] === 'arrival' ? '+' : '−') . fmt_int($a['quantity']) . '</strong> ' . ($a['type'] === 'arrival' ? 'arrival' : 'removal') . ' · ' . e(fmt_date($a['adjustment_date'])) . '<div class="small muted">' . e($a['reason']) . ' — ' . e($a['full_name']) . '</div></li>'; echo '</ul>'; }
echo '</div><div class="card"><div class="card-head"><h2>Recent changes to this cycle</h2></div>';
if (!$audit) echo '<p class="muted">No audit entries yet.</p>'; else { echo '<ul class="timeline">'; foreach ($audit as $a) echo '<li><strong>' . e($a['action']) . '</strong> <span class="muted small">' . e(fmt_datetime($a['created_at'])) . ' · ' . e($a['full_name'] ?? 'system') . '</span>' . ($a['reason'] ? '<div class="small muted">' . e($a['reason']) . '</div>' : '') . '</li>'; echo '</ul>'; }
echo '</div></div>';
if ($c['status'] === 'active') {
    modal_start('m-adj', 'Population adjustment', 'sm');
    echo '<div class="notice warn">' . icon('alert', 18) . '<span>Use this only for exceptional cases. Routine deaths belong in the daily report. Every adjustment needs a reason and is recorded in the audit log.</span></div><form method="post" action="' . e(url('actions/cycles.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="adjust"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="return" value="' . e($self) . '">';
    echo '<div class="field"><label>Type</label><select name="type"><option value="arrival">Additional chickens received</option><option value="removal">Other removal (culled, sold early, stolen…)</option></select></div><div class="field"><label>Quantity</label><input type="number" name="quantity" min="1" required></div><div class="field"><label>Date</label><input type="date" name="adjustment_date" min="' . e($c['placement_date']) . '" max="' . $t . '" value="' . $t . '" required></div><div class="field"><label>Reason (required)</label><textarea name="reason" required maxlength="500"></textarea></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary">Save adjustment</button></div></form>';
    modal_end();
    modal_start('m-close', 'Close this cycle', 'sm');
    echo '<p>Closing ends the cycle. All reports and figures are kept permanently. The house will be set to <strong>Cleaning</strong>.</p><form method="post" action="' . e(url('actions/cycles.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="close"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="return" value="' . e($self) . '">';
    echo '<div class="field"><label>Closing date</label><input type="date" name="closed_date" min="' . e($c['placement_date']) . '" max="' . $t . '" value="' . $t . '" required></div><div class="field"><label>Closing notes (optional)</label><textarea name="close_notes" maxlength="1000" placeholder="e.g. flock sold, final weight, issues"></textarea></div><label class="check" style="margin-bottom:16px"><input type="checkbox" name="confirm" value="1" required> I confirm this production cycle has ended.</label><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-danger">Close cycle</button></div></form>';
    modal_end();
}
page_end();
