<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/report_form.php';
$user = require_login(); $t = today();
$ids = allowed_house_ids();
$cycles = $ids ? cycle_stats("status = 'active' AND house_id IN (" . in_placeholders($ids) . ')', $ids) : [];
$fd = report_form_data();
$_SESSION['report_tokens'] = array_slice($_SESSION['report_tokens'] ?? [], -15, 15, true);
$token = bin2hex(random_bytes(32)); $_SESSION['report_tokens'][$token] = time();
$sel = (int)old('house_id', get('house', $cycles[0]['house_id'] ?? 0));
$date = old('report_date', valid_date(get('date')) ? get('date') : $t);
$v = ['report_date' => $date, 'worker_id' => old('worker_id', $user['id']), 'feed_bags' => old('feed_bags', ''), 'deaths' => old('deaths', '0'), 'death_cause_id' => old('death_cause_id', 0), 'death_cause_detail' => old('death_cause_detail'), 'work_description' => old('work_description'), 'notes' => old('notes'), 'tasks' => array_map('intval', (array)old('tasks', []))];
page_start('Daily report', 'report-new');
page_head('Daily report', 'Record today\'s feed, deaths and work for a house. It takes about a minute.');
if (!$cycles) {
    echo '<div class="card">' . empty_state('No house is ready for a report', is_admin() ? 'Reports need an active production cycle. Add a new cycle first.' : (!$ids ? 'You have not been given access to any house yet. Ask an administrator.' : 'None of your houses has an active cycle right now.'), 'clipboard', is_admin() ? '<a class="btn btn-primary btn-featured" href="' . e(url('cycles/index.php?new=1')) . '">Add New Cycle</a>' : '') . '</div>';
    page_end(); exit;
}
?>
<form method="post" action="<?= e(url('actions/reports.php')) ?>" id="report-form" data-no-lock data-backdays="<?= (int)(is_admin() ? 3650 : setting('worker_backdate_days', 2)) ?>" data-today="<?= e($t) ?>" novalidate>
<?= csrf_field() ?><input type="hidden" name="op" value="create"><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="cycle_id" id="f-cycle-id" value="">
<div id="step-form" class="card report-form-card" data-spotlight>
  <div class="form-grid">
    <div class="field"><label for="f-house">House number</label>
      <select id="f-house" name="house_id" required>
        <?php foreach ($cycles as $c): ?><option value="<?= $c['house_id'] ?>" data-cycle-id="<?= $c['id'] ?>" data-cycle="<?= e($c['cycle_name']) ?>" data-living="<?= $c['living'] ?>" data-start="<?= e($c['placement_date']) ?>" <?= $sel === (int)$c['house_id'] ? 'selected' : '' ?>><?= e(house_label($c['house_number'])) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label>Active production cycle</label><input type="text" id="f-cycle-name" readonly></div>
  </div>
  <div id="dup-warning" class="notice warn" hidden><?= icon('alert', 18) ?><span></span></div>
  <?php report_fields($v, $fd['causes'], $fd['tasks'], $fd['workers'], is_admin()); ?>
  <div class="modal-actions"><button type="button" class="btn btn-primary btn-featured big-btn" id="btn-review">Review report</button></div>
</div>
<div id="step-review" class="card" hidden>
  <h2>Please check your report</h2><p class="muted">Nothing is saved until you press “Confirm and save”.</p>
  <div class="review"><dl id="review-list"></dl></div>
  <div class="modal-actions mt"><button type="button" class="btn big-btn" id="btn-edit">Go back and edit</button><button type="submit" class="btn btn-primary big-btn" id="btn-save">Confirm and save</button></div>
</div>
</form>
<?php page_end(['assets/js/report.js']);
