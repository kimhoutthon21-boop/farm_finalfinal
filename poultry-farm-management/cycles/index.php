<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin(); $t = today();
$status = get('status', 'all'); if (!in_array($status, ['active', 'closed', 'all'], true)) $status = 'all';
$where = $status === 'all' ? '1=1' : ($status === 'closed' ? "status IN ('closed','archived')" : 'status = ?');
$cycles = cycle_stats($where, $status === 'active' ? ['active'] : [], 'FIELD(status,\'active\',\'closed\',\'archived\'), placement_date DESC');
$eligible = rows("SELECT h.* FROM houses h WHERE h.status <> 'maintenance' AND NOT EXISTS (SELECT 1 FROM production_cycles c WHERE c.house_id = h.id AND c.status = 'active') ORDER BY h.house_number");
$maint = (int)val("SELECT COUNT(*) FROM houses WHERE status = 'maintenance'");
$newHouse = (int)get('new');
page_start('Flock cycles', 'cycles');
page_head('Flock cycles', 'Each cycle keeps its own population, reports and history. Closed cycles are never deleted.', '<button class="btn btn-primary btn-featured" data-modal-open="m-cycle">' . icon('plus', 18) . ' Add New Cycle</button>');
echo '<div class="tabs">'; foreach (['all' => 'All', 'active' => 'Active', 'closed' => 'Closed'] as $k => $l) echo '<a class="tab ' . ($status === $k ? 'on' : '') . '" href="?status=' . $k . '">' . $l . '</a>'; echo '</div>';
echo '<div class="card chart-bento" data-spotlight>';
if (!$cycles) echo empty_state('No cycles here yet', 'Use “Add New Cycle” to place a new flock in an empty house.', 'layers');
else {
    echo '<div class="table-wrap"><table><thead><tr><th>Cycle</th><th>House</th><th>Status</th><th>Placed</th><th style="min-width:150px">Progress</th><th class="num">Placed birds</th><th class="num">Living</th><th class="num">Mortality</th><th></th></tr></thead><tbody>';
    foreach ($cycles as $c) echo '<tr><td><strong>' . e($c['cycle_name']) . '</strong></td><td>' . e(house_label($c['house_number'])) . '</td><td>' . cycle_status_badge($c['status']) . '</td><td class="nowrap">' . e(fmt_date($c['placement_date'])) . '</td><td>' . progress_bar($c['progress_pct'], $c['status'] === 'active' && $c['age_days'] > $c['expected_days'] ? 'warn' : '') . '<div class="small muted">Day ' . $c['age_days'] . ' of ' . $c['expected_days'] . ($c['status'] !== 'active' ? ' (ended)' : '') . '</div></td><td class="num">' . fmt_int($c['initial_population']) . '</td><td class="num">' . fmt_int($c['living']) . '</td><td class="num">' . fmt_pct($c['mortality_pct']) . '</td><td class="act"><a class="btn btn-ghost btn-sm" href="' . e(url('cycles/view.php?id=' . $c['id'])) . '">Open</a></td></tr>';
    echo '</tbody></table></div>';
}
echo '</div>';
modal_start('m-cycle', 'Add New Cycle');
if (!$eligible) echo '<div class="notice warn">' . icon('alert', 18) . '<span>Every house already has an active cycle' . ($maint ? ' or is under maintenance' : '') . '. Close a cycle (or change a house status) first.</span></div><div class="modal-actions"><button class="btn" data-modal-close type="button">OK</button></div>';
else {
    echo '<form method="post" action="' . e(url('actions/cycles.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="create"><input type="hidden" name="return" value="cycles/index.php"><div class="form-grid">';
    echo '<div class="field"><label>House number</label><select name="house_id" required>';
    foreach ($eligible as $h) echo '<option value="' . $h['id'] . '"' . ((int)old('house_id', $newHouse) === (int)$h['id'] ? ' selected' : '') . '>' . e(house_label($h['house_number'])) . ' (' . $h['status'] . ')</option>';
    echo '</select></div><div class="field"><label>Cycle name / reference</label><input type="text" name="cycle_name" maxlength="120" value="' . e(old('cycle_name')) . '" placeholder="Leave blank to auto-name"></div>';
    echo '<div class="field"><label>Chickens initially placed</label><input type="number" name="initial_population" min="1" max="10000000" required value="' . e(old('initial_population')) . '"></div><div class="field"><label>Placement date</label><input type="date" name="placement_date" max="' . $t . '" required value="' . e(old('placement_date', $t)) . '"></div>';
    echo '<div class="field"><label>Expected cycle duration (days)</label><input type="number" name="expected_days" min="1" max="365" required value="' . e(old('expected_days', setting('default_cycle_days', 70))) . '"></div><div class="field"><label>Supplier / source (optional)</label><input type="text" name="supplier" maxlength="160" value="' . e(old('supplier')) . '"></div>';
    echo '<div class="field full"><label>Notes (optional)</label><textarea name="notes" maxlength="1000">' . e(old('notes')) . '</textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary">Create cycle</button></div></form>';
}
modal_end();
if ($newHouse) echo '<script>document.addEventListener("DOMContentLoaded",function(){openModal("m-cycle")});</script>';
page_end();
