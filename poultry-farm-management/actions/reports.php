<?php
// Create / correct / void / restore daily reports. All population effects are derived from
// saved rows (see v_cycle_stats), so a report can only ever count once.
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $u = require_login();
$pdo = db(); $admin = is_admin();

function parse_report_input(array $c, ?array $existing, bool $admin): array {
    $d = post('report_date');
    if (!valid_date($d)) throw new UserError('Please choose a valid report date.');
    if ($d > today()) throw new UserError('The report date cannot be in the future.');
    if ($d < $c['placement_date']) throw new UserError('The report date is before this cycle was placed (' . fmt_date($c['placement_date']) . ').');
    if ($c['closed_date'] && $d > $c['closed_date']) throw new UserError('The report date is after this cycle was closed.');
    if (!$admin) { $min = add_days(today(), -(int)setting('worker_backdate_days', 2)); if ($d < $min) throw new UserError('Workers can only submit reports from the last ' . (int)setting('worker_backdate_days', 2) . ' days. Ask an administrator to add older reports.'); }

    $feedRaw = str_replace(',', '.', post('feed_bags'));
    if ($feedRaw === '' || !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $feedRaw)) throw new UserError('Feed consumed must be a number of bags, 0 or more (for example 12 or 12.5).');
    $feed = (float)$feedRaw;
    if (!(int)setting('allow_partial_bags', 1) && floor($feed) != $feed) throw new UserError('Partial bags are turned off in settings. Enter whole bags.');
    if ($feed > 99999) throw new UserError('Feed amount looks too large. Please check it.');

    $deathsRaw = post('deaths');
    if (!preg_match('/^\d{1,7}$/', $deathsRaw)) throw new UserError('Deaths must be a whole number, 0 or more.');
    $deaths = (int)$deathsRaw;

    $causeId = null; $detail = null;
    if ($deaths > 0) {
        $causeId = (int)post('death_cause_id');
        $cause = row('SELECT * FROM death_causes WHERE id = ?', [$causeId]);
        if (!$cause || (!(int)$cause['is_active'] && (!$existing || (int)$existing['death_cause_id'] !== $causeId))) throw new UserError('Please choose a cause of death.');
        $detail = post('death_cause_detail');
        if ((int)$cause['requires_detail'] && mb_strlen($detail) < 3) throw new UserError('Please describe the cause of death when you choose “' . $cause['name'] . '”.');
        if (mb_strlen($detail) > 500) throw new UserError('Cause details are too long (500 characters max).');
        $detail = $detail === '' ? null : $detail;
    }
    $taskIds = int_list($_POST['tasks'] ?? []);
    if ($taskIds) {
        $valid = array_column(rows('SELECT id FROM work_task_categories WHERE id IN (' . in_placeholders($taskIds) . ')', $taskIds), 'id');
        if (count($valid) !== count($taskIds)) throw new UserError('One of the selected work tasks is not valid.');
    }
    $desc = post('work_description'); $notes = post('notes');
    if (!$taskIds && $desc === '') throw new UserError('Please select at least one task or describe the work performed today.');
    if (mb_strlen($desc) > 2000 || mb_strlen($notes) > 2000) throw new UserError('Work description and notes are limited to 2000 characters.');

    $workerId = $admin ? (int)post('worker_id', (string)user_id()) : user_id();
    if (!val('SELECT id FROM users WHERE id = ? AND is_active = 1', [$workerId])) throw new UserError('Please choose who recorded this report.');
    return ['date' => $d, 'feed' => $feed, 'deaths' => $deaths, 'cause' => $causeId, 'detail' => $detail, 'tasks' => $taskIds, 'desc' => $desc === '' ? null : $desc, 'notes' => $notes === '' ? null : $notes, 'worker' => $workerId];
}
function report_snapshot(array $r, array $taskIds): array {
    $names = $taskIds ? array_column(rows('SELECT name FROM work_task_categories WHERE id IN (' . in_placeholders($taskIds) . ') ORDER BY name', $taskIds), 'name') : [];
    $cause = $r['death_cause_id'] ? val('SELECT name FROM death_causes WHERE id = ?', [$r['death_cause_id']]) : null;
    return ['report_date' => $r['report_date'], 'worker' => val('SELECT full_name FROM users WHERE id = ?', [$r['worker_id']]), 'feed_bags' => (float)$r['feed_bags'], 'deaths' => (int)$r['deaths'], 'cause' => $cause, 'cause_detail' => $r['death_cause_detail'], 'tasks' => $names, 'work_description' => $r['work_description'], 'notes' => $r['notes'], 'status' => $r['status']];
}
function task_ids(int $reportId): array { return array_map('intval', array_column(rows('SELECT task_id FROM daily_report_tasks WHERE report_id = ?', [$reportId]), 'task_id')); }
function save_tasks(int $reportId, array $ids): void { q('DELETE FROM daily_report_tasks WHERE report_id = ?', [$reportId]); foreach ($ids as $t) q('INSERT INTO daily_report_tasks (report_id, task_id) VALUES (?,?)', [$reportId, $t]); }

$op = post('op'); $backForm = 'reports/create.php';
try {
    switch ($op) {
        case 'create':
            $token = post('token');
            if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new UserError('This form is not valid. Please reload it.');
            if ($dup = row('SELECT id FROM daily_reports WHERE submission_token = ?', [$token])) { flash('info', 'This report was already saved. It was not counted again.'); redirect('reports/view.php?id=' . $dup['id']); }
            if (!isset($_SESSION['report_tokens'][$token])) throw new UserError('This form was already used or has expired. Please open the Daily Report form again.');
            $houseId = (int)post('house_id');
            if (!can_access_house($houseId)) throw new UserError('You do not have access to that house.');
            $backForm = 'reports/create.php?house=' . $houseId;
            $pdo->beginTransaction();
            $act = row("SELECT id FROM production_cycles WHERE house_id = ? AND status = 'active' FOR UPDATE", [$houseId]);
            if (!$act) throw new UserError('That house has no active production cycle.');
            if ((int)post('cycle_id') !== (int)$act['id']) throw new UserError('The selected cycle is not the active cycle of this house. Please reload the form.');
            $c = cycle_stat((int)$act['id']); $in = parse_report_input($c, null, $admin);
            if ($ex = row('SELECT id, status FROM daily_reports WHERE cycle_id = ? AND report_date = ?', [$c['id'], $in['date']])) throw new UserError('A report for ' . house_label($c['house_number']) . ' on ' . fmt_date($in['date']) . ' already exists' . ($ex['status'] === 'voided' ? ' (voided). Ask an administrator to restore or correct it.' : '. Ask an administrator to correct it if something was wrong.'));
            if ($in['deaths'] > $c['living']) throw new UserError('Deaths (' . fmt_int($in['deaths']) . ') are more than the chickens still living (' . fmt_int($c['living']) . ').');
            q('INSERT INTO daily_reports (cycle_id, report_date, worker_id, feed_bags, deaths, death_cause_id, death_cause_detail, work_description, notes, submission_token, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$c['id'], $in['date'], $in['worker'], $in['feed'], $in['deaths'], $in['cause'], $in['detail'], $in['desc'], $in['notes'], $token, $u['id']]);
            $rid = (int)$pdo->lastInsertId(); save_tasks($rid, $in['tasks']);
            $new = report_snapshot(row('SELECT * FROM daily_reports WHERE id = ?', [$rid]), $in['tasks']); $new['living_before'] = $c['living']; $new['living_after'] = $c['living'] - $in['deaths'];
            audit_log('report.create', 'report', $rid, null, $new);
            $pdo->commit(); unset($_SESSION['report_tokens'][$token]);
            flash('success', 'Report saved. Living chickens in ' . house_label($c['house_number']) . ': ' . fmt_int($c['living'] - $in['deaths']) . '.');
            redirect('reports/view.php?id=' . $rid . '&saved=1');

        case 'correct':
            if (!$admin) throw new UserError('Only administrators can correct reports.');
            $rid = (int)post('id'); $reason = post('reason'); $backForm = 'reports/edit.php?id=' . $rid;
            if (mb_strlen($reason) < 5) throw new UserError('Please explain the reason for the correction (at least 5 characters).');
            $pdo->beginTransaction();
            $cid = val('SELECT cycle_id FROM daily_reports WHERE id = ?', [$rid]); if (!$cid) throw new UserError('Report not found.');
            q('SELECT id FROM production_cycles WHERE id = ? FOR UPDATE', [$cid]);
            $old = row('SELECT * FROM daily_reports WHERE id = ? FOR UPDATE', [$rid]); $c = cycle_stat((int)$cid);
            $in = parse_report_input($c, $old, true);
            if ($in['date'] !== $old['report_date'] && val('SELECT id FROM daily_reports WHERE cycle_id = ? AND report_date = ? AND id <> ?', [$cid, $in['date'], $rid])) throw new UserError('Another report already exists for that date in this cycle.');
            $available = $c['living'] + ($old['status'] === 'submitted' ? (int)$old['deaths'] : 0);
            if ($old['status'] === 'submitted' && $in['deaths'] > $available) throw new UserError('Deaths (' . fmt_int($in['deaths']) . ') would exceed the chickens available (' . fmt_int($available) . ').');
            $oldTasks = task_ids($rid); $oldSnap = report_snapshot($old, $oldTasks);
            q('UPDATE daily_reports SET report_date = ?, worker_id = ?, feed_bags = ?, deaths = ?, death_cause_id = ?, death_cause_detail = ?, work_description = ?, notes = ?, revision = revision + 1 WHERE id = ?', [$in['date'], $in['worker'], $in['feed'], $in['deaths'], $in['cause'], $in['detail'], $in['desc'], $in['notes'], $rid]);
            save_tasks($rid, $in['tasks']);
            $newSnap = report_snapshot(row('SELECT * FROM daily_reports WHERE id = ?', [$rid]), $in['tasks']);
            if ($oldSnap == $newSnap) throw new UserError('Nothing was changed.');
            $after = cycle_stat((int)$cid);
            $oldSnap['living'] = $c['living']; $newSnap['living'] = $after['living'];
            audit_log('report.correct', 'report', $rid, $oldSnap, $newSnap, $reason);
            $pdo->commit(); flash('success', 'Correction saved and recorded. Living chickens: ' . fmt_int($after['living']) . '.'); redirect('reports/view.php?id=' . $rid);

        case 'void': case 'restore':
            if (!$admin) throw new UserError('Only administrators can do this.');
            $rid = (int)post('id'); $reason = post('reason'); $backForm = 'reports/view.php?id=' . $rid;
            if (mb_strlen($reason) < 5) throw new UserError('Please give a reason (at least 5 characters).');
            $pdo->beginTransaction();
            $cid = val('SELECT cycle_id FROM daily_reports WHERE id = ?', [$rid]); if (!$cid) throw new UserError('Report not found.');
            q('SELECT id FROM production_cycles WHERE id = ? FOR UPDATE', [$cid]); $r = row('SELECT * FROM daily_reports WHERE id = ? FOR UPDATE', [$rid]); $c = cycle_stat((int)$cid);
            $to = $op === 'void' ? 'voided' : 'submitted';
            if ($r['status'] === $to) throw new UserError('The report is already ' . $to . '.');
            if ($op === 'restore' && (int)$r['deaths'] > $c['living']) throw new UserError('Restoring would exceed the living population. Correct the deaths first.');
            q('UPDATE daily_reports SET status = ?, revision = revision + 1 WHERE id = ?', [$to, $rid]);
            $after = cycle_stat((int)$cid);
            audit_log('report.' . $op, 'report', $rid, ['status' => $r['status'], 'deaths' => (int)$r['deaths'], 'feed_bags' => (float)$r['feed_bags'], 'living' => $c['living']], ['status' => $to, 'living' => $after['living']], $reason);
            $pdo->commit(); flash('success', $op === 'void' ? 'Report voided. Its deaths and feed no longer count.' : 'Report restored and counted again.'); redirect('reports/view.php?id=' . $rid);
    }
    throw new UserError('Unknown action.');
} catch (UserError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $e->getMessage()); remember_input($_POST);
    redirect(back_target($backForm));
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PFM] ' . $e->getMessage());
    flash('error', $e->getCode() === '23000' ? 'A report for that house and date already exists, or this form was already submitted. Nothing was counted twice.' : 'The database could not save the report. Nothing was changed.');
    redirect(back_target($backForm));
}
