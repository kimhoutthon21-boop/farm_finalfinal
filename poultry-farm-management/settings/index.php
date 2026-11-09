<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();
$causes = rows('SELECT * FROM death_causes ORDER BY sort_order, name'); $tasks = rows('SELECT * FROM work_task_categories ORDER BY sort_order, name');
$S = fn($k, $d = '') => e(setting($k, $d));
page_start('Settings', 'settings');
page_head('Farm settings', 'Everything the system assumes about your farm can be changed here.');
echo '<div class="tabs" data-tabs><button class="tab" data-tab="general">General</button><button class="tab" data-tab="alerts">Alerts</button><button class="tab" data-tab="causes">Causes of death</button><button class="tab" data-tab="tasks">Work tasks</button></div><div>';
echo '<div class="tab-panel" data-panel="general"><form method="post" action="' . e(url('actions/settings.php')) . '" class="card chart-bento" data-spotlight style="max-width:760px">' . csrf_field() . '<input type="hidden" name="op" value="general"><div class="form-grid">
<div class="field"><label>Farm name</label><input name="farm_name" value="' . $S('farm_name') . '" required maxlength="100"></div>
<div class="field"><label>Default cycle length (days)</label><input type="number" name="default_cycle_days" min="1" max="365" value="' . $S('default_cycle_days', 70) . '" required></div>
<div class="field full"><label>Farm information</label><textarea name="farm_info" maxlength="1000">' . $S('farm_info') . '</textarea></div>
<div class="field"><label>Currency code</label><input name="currency_code" value="' . $S('currency_code', 'KHR') . '" maxlength="5" required></div>
<div class="field"><label>Currency symbol</label><input name="currency_symbol" value="' . $S('currency_symbol', '៛') . '" maxlength="5" required></div>
<div class="field"><label>Decimal places for money</label><select name="currency_decimals">';
foreach ([0, 2] as $d) echo '<option value="' . $d . '"' . ((int)setting('currency_decimals', 0) === $d ? ' selected' : '') . '>' . $d . '</option>';
echo '</select></div>
<div class="field"><label>Weight of one feed bag (kg, optional)</label><input type="number" name="bag_weight_kg" min="0" step="0.01" value="' . $S('bag_weight_kg') . '"></div>
<div class="field"><label>Allow partial bags (for example 12.5)</label><label class="switch"><input type="checkbox" name="allow_partial_bags" value="1"' . ((int)setting('allow_partial_bags', 1) ? ' checked' : '') . '><i></i></label></div>
<div class="field"><label>Days workers may back-date a report</label><input type="number" name="worker_backdate_days" min="0" max="30" value="' . $S('worker_backdate_days', 2) . '"></div></div><button class="btn btn-primary btn-featured">Save settings</button></form></div>';
echo '<div class="tab-panel" data-panel="alerts" hidden><form method="post" action="' . e(url('actions/settings.php')) . '" class="card chart-bento" data-spotlight style="max-width:760px">' . csrf_field() . '<input type="hidden" name="op" value="alerts"><div class="form-grid">
<div class="field"><label>Daily deaths warning (%)</label><input type="number" name="daily_mortality_warn_pct" min="0" max="100" step="0.01" value="' . $S('daily_mortality_warn_pct', 0.2) . '"></div>
<div class="field"><label>Cycle mortality limit (%)</label><input type="number" name="cycle_mortality_warn_pct" min="0" max="100" step="0.01" value="' . $S('cycle_mortality_warn_pct', 5) . '"></div>
<div class="field"><label>Remind about missing reports after (hour, 0-23)</label><input type="number" name="report_reminder_hour" min="0" max="23" value="' . $S('report_reminder_hour', 17) . '"></div></div><div class="notice">' . icon('info', 18) . '<span>These numbers are only starting points. Set them to what is normal for your farm; the system does not guess.</span></div><button class="btn btn-primary btn-featured">Save alert limits</button></form></div>';
function list_editor(string $kind, string $title, array $items, bool $withDetail): void {
    echo '<div class="tab-panel" data-panel="' . $kind . 's" hidden><div class="card chart-bento" data-spotlight style="max-width:760px"><h2>' . e($title) . '</h2><div class="table-wrap"><table><thead><tr><th>Name</th>' . ($withDetail ? '<th>Ask for details</th>' : '') . '<th>Active</th><th></th></tr></thead><tbody>';
    foreach ($items as $i) {
        echo '<tr><form method="post" action="' . e(url('actions/settings.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="' . $kind . '_save"><input type="hidden" name="id" value="' . $i['id'] . '"><td><input name="name" value="' . e($i['name']) . '" maxlength="80" required></td>' . ($withDetail ? '<td><label class="switch"><input type="checkbox" name="requires_detail" value="1"' . ((int)$i['requires_detail'] ? ' checked' : '') . '><i></i></label></td>' : '') . '<td><label class="switch"><input type="checkbox" name="is_active" value="1"' . ((int)$i['is_active'] ? ' checked' : '') . '><i></i></label></td><td class="act"><button class="btn btn-sm">Save</button></td></form></tr>';
    }
    echo '</tbody></table></div><form method="post" action="' . e(url('actions/settings.php')) . '" class="filters mt" style="margin-bottom:0">' . csrf_field() . '<input type="hidden" name="op" value="' . $kind . '_add"><div style="flex:3"><label>Add new</label><input name="name" maxlength="80" required></div><button class="btn btn-primary">Add</button></form><p class="muted small mt" style="margin-bottom:0">Items are never deleted, because old reports refer to them. Turn one off to hide it from new reports.</p></div></div>';
}
list_editor('cause', 'Causes of death', $causes, true); list_editor('task', 'Work-task categories', $tasks, false);
echo '</div>';
page_end();
