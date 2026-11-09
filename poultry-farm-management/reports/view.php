<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$rid = (int)get('id');
$r = row('SELECT r.*, c.cycle_name, c.status AS cycle_status, c.house_id, h.house_number, w.full_name AS worker_name, cb.full_name AS created_name, dc.name AS cause_name FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id JOIN users w ON w.id = r.worker_id JOIN users cb ON cb.id = r.created_by LEFT JOIN death_causes dc ON dc.id = r.death_cause_id WHERE r.id = ?', [$rid]);
if (!$r || (!is_admin() && ((int)$r['worker_id'] !== (int)$user['id'] || !can_access_house((int)$r['house_id'])))) { http_response_code(404); page_start('Not found', 'reports'); echo '<div class="card">' . empty_state('Report not found', 'It may not exist, or you may not have access to it.', 'file', '<a class="btn" href="' . e(url('reports/index.php')) . '">Back to reports</a>') . '</div>'; page_end(); exit; }
$tasks = array_column(rows('SELECT t.name FROM daily_report_tasks x JOIN work_task_categories t ON t.id = x.task_id WHERE x.report_id = ? ORDER BY t.name', [$rid]), 'name');
$hist = rows("SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.entity_type = 'report' AND a.entity_id = ? ORDER BY a.id DESC", [$rid]);
$c = cycle_stat((int)$r['cycle_id']);
page_start('Report · ' . fmt_date($r['report_date']), 'reports');
$act = '<a class="btn" href="' . e(url('reports/index.php')) . '">All reports</a>';
if (is_admin()) {
    $act .= '<a class="btn" href="' . e(url('reports/edit.php?id=' . $rid)) . '">' . icon('edit', 18) . ' Correct</a>';
    $act .= '<button class="btn ' . ($r['status'] === 'voided' ? '' : 'btn-danger') . '" data-modal-open="m-void">' . ($r['status'] === 'voided' ? icon('undo', 18) . ' Restore' : 'Void report') . '</button>';
}
page_head(house_label($r['house_number']) . ' · ' . fmt_date($r['report_date']), $r['cycle_name'], $act);
if (get('saved')) echo '<div class="notice ok">' . icon('check', 20) . '<div><strong>Report saved.</strong> Living chickens in this house are now <strong>' . fmt_int($c['living']) . '</strong>.</div></div>';
if ($r['status'] === 'voided') echo '<div class="notice danger">' . icon('alert', 18) . '<span>This report is voided. Its deaths and feed are not counted in any total.</span></div>';
echo '<div class="grid g-main"><div class="card" data-spotlight><div class="kv">';
$kv = [['Status', null], ['Feed consumed', fmt_bags($r['feed_bags']) . ' bags'], ['Deaths', fmt_int($r['deaths'])], ['Cause of death', ($r['cause_name'] ?? '—') . ($r['death_cause_detail'] ? ': ' . $r['death_cause_detail'] : '')], ['Recorded by', $r['worker_name']], ['Submitted by', $r['created_name']], ['Submitted at', fmt_datetime($r['created_at'])], ['Last updated', fmt_datetime($r['updated_at'])]];
foreach ($kv as [$k, $v]) echo '<div><div class="k">' . e($k) . '</div><div class="v" style="font-size:1rem">' . ($v === null ? report_status_badge($r) : e($v)) . '</div></div>';
echo '</div><hr style="border:0;border-top:1px solid var(--line);margin:18px 0"><div class="k muted small">WORK PERFORMED</div><p style="margin:4px 0 12px">' . ($tasks ? e(implode(', ', $tasks)) : '—') . ($r['work_description'] ? '<br>' . nl2br(e($r['work_description'])) : '') . '</p><div class="k muted small">NOTES</div><p style="margin:4px 0 0">' . ($r['notes'] ? nl2br(e($r['notes'])) : '—') . '</p></div>';
echo '<div class="card"><div class="card-head"><h2>Cycle position</h2></div><div class="kv" style="grid-template-columns:1fr 1fr"><div><div class="k">Living now</div><div class="v">' . fmt_int($c['living']) . '</div></div><div><div class="k">Placed</div><div class="v">' . fmt_int($c['initial_population']) . '</div></div><div><div class="k">Total deaths</div><div class="v">' . fmt_int($c['deaths']) . '</div></div><div><div class="k">Mortality</div><div class="v">' . fmt_pct($c['mortality_pct']) . '</div></div></div></div></div>';
echo '<div class="card mt"><div class="card-head"><h2>Change history</h2></div>';
if (!$hist) echo '<p class="muted">No changes recorded.</p>'; else { echo '<ul class="timeline">';
    foreach ($hist as $a) { echo '<li><strong>' . e(str_replace('report.', '', $a['action'])) . '</strong> <span class="muted small">' . e(fmt_datetime($a['created_at'])) . ' · ' . e($a['full_name'] ?? 'system') . '</span>' . ($a['reason'] ? '<div class="small">Reason: ' . e($a['reason']) . '</div>' : '');
        if (is_admin() && $a['old_values']) { echo '<details><summary class="small muted" style="cursor:pointer">Before and after</summary><div class="grid g2"><pre class="json">' . e(json_encode(json_decode($a['old_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre><pre class="json">' . e(json_encode(json_decode($a['new_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></div></details>'; }
        echo '</li>'; }
    echo '</ul>'; }
echo '</div>';
if (is_admin()) {
    $void = $r['status'] !== 'voided';
    modal_start('m-void', $void ? 'Void this report' : 'Restore this report', 'sm');
    echo '<p>' . ($void ? 'Voiding removes this report\'s deaths and feed from all totals. The report itself is kept and can be restored.' : 'Restoring counts this report\'s deaths and feed again.') . '</p><form method="post" action="' . e(url('actions/reports.php')) . '" data-confirm="' . ($void ? 'Void this report?' : 'Restore this report?') . '">' . csrf_field() . '<input type="hidden" name="op" value="' . ($void ? 'void' : 'restore') . '"><input type="hidden" name="id" value="' . $rid . '"><div class="field"><label>Reason (required)</label><textarea name="reason" required minlength="5" maxlength="500"></textarea></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn ' . ($void ? 'btn-danger' : 'btn-primary') . '">' . ($void ? 'Void report' : 'Restore report') . '</button></div></form>';
    modal_end();
}
page_end();
