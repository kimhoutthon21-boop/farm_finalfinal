<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/report_form.php';
$user = require_admin();
$rid = (int)get('id'); $r = row('SELECT r.*, c.cycle_name, c.status cycle_status, h.house_number FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id WHERE r.id = ?', [$rid]);
if (!$r) { flash('error', 'Report not found.'); redirect('reports/index.php'); }
$fd = report_form_data();
// keep inactive causes selectable if this report already uses one
if ($r['death_cause_id'] && !in_array((int)$r['death_cause_id'], array_map('intval', array_column($fd['causes'], 'id')), true)) $fd['causes'][] = row('SELECT * FROM death_causes WHERE id = ?', [$r['death_cause_id']]);
$v = ['report_date' => old('report_date', $r['report_date']), 'worker_id' => old('worker_id', $r['worker_id']), 'feed_bags' => old('feed_bags', (float)$r['feed_bags']), 'deaths' => old('deaths', $r['deaths']), 'death_cause_id' => old('death_cause_id', $r['death_cause_id'] ?? 0), 'death_cause_detail' => old('death_cause_detail', $r['death_cause_detail'] ?? ''), 'work_description' => old('work_description', $r['work_description'] ?? ''), 'notes' => old('notes', $r['notes'] ?? ''), 'tasks' => array_map('intval', (array)old('tasks', array_column(rows('SELECT task_id FROM daily_report_tasks WHERE report_id = ?', [$rid]), 'task_id')))];
$c = cycle_stat((int)$r['cycle_id']); $avail = $c['living'] + ($r['status'] === 'submitted' ? (int)$r['deaths'] : 0);
page_start('Correct report', 'reports');
page_head('Correct report', house_label($r['house_number']) . ' · ' . $r['cycle_name'], '<a class="btn" href="' . e(url('reports/view.php?id=' . $rid)) . '">Cancel</a>');
echo '<div class="notice warn">' . icon('alert', 18) . '<span>Corrections are permanent entries in the audit log: your name, the time, the old and new values and your reason are all kept.</span></div>';
?>
<form method="post" action="<?= e(url('actions/reports.php')) ?>" class="card report-form-card"><?= csrf_field() ?><input type="hidden" name="op" value="correct"><input type="hidden" name="id" value="<?= $rid ?>">
<script>window.__edit = {living: <?= (int)$avail ?>};</script>
<?php report_fields($v, $fd['causes'], $fd['tasks'], $fd['workers'], true); ?>
<div class="field"><label for="f-reason">Reason for this correction (required)</label><textarea id="f-reason" name="reason" required minlength="5" maxlength="500"><?= e(old('reason')) ?></textarea></div>
<div class="modal-actions"><a class="btn btn-ghost" href="<?= e(url('reports/view.php?id=' . $rid)) ?>">Cancel</a><button class="btn btn-primary">Save correction</button></div></form>
<script>(function(){var d=document.getElementById('f-deaths'),c=document.getElementById('f-cause'),w=document.getElementById('cause-wrap'),dw=document.getElementById('detail-wrap');function s(){var n=parseInt(d.value||'0',10);w.hidden=!(n>0);var o=c.options[c.selectedIndex];dw.hidden=!(n>0&&o&&o.dataset.detail==='1');}
document.addEventListener('click',function(e){var b=e.target.closest('[data-step]');if(!b)return;var t=document.getElementById(b.dataset.target),v=parseFloat(t.value||'0')+parseFloat(b.dataset.step);if(v<0)v=0;t.value=Math.round(v*100)/100;t.dispatchEvent(new Event('input'));});d.addEventListener('input',s);c.addEventListener('change',s);s();})();</script>
<?php page_end();
