<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $u = require_admin();
$pdo = db();
try {
    switch (post('op')) {
        case 'create':
            $houseId = (int)post('house_id'); $name = post('cycle_name'); $pop = (int)post('initial_population'); $date = post('placement_date'); $days = (int)post('expected_days');
            if ($pop < 1 || $pop > 10000000) throw new UserError('Enter how many chickens were placed (1 or more).');
            if (!valid_date($date) || $date > today()) throw new UserError('Placement date must be a valid date that is not in the future.');
            if ($days < 1 || $days > 365) throw new UserError('Expected duration must be between 1 and 365 days.');
            if (mb_strlen($name) > 120) throw new UserError('Cycle name is too long.');
            $pdo->beginTransaction();
            $h = row('SELECT * FROM houses WHERE id = ? FOR UPDATE', [$houseId]);
            if (!$h) throw new UserError('Please choose a house.');
            if ($h['status'] === 'maintenance') throw new UserError(house_label($h['house_number']) . ' is under maintenance. Change its status first.');
            if (val("SELECT id FROM production_cycles WHERE house_id = ? AND status = 'active'", [$houseId])) throw new UserError(house_label($h['house_number']) . ' already has an active cycle. Close it before starting a new one.');
            $prevLast = val("SELECT MAX(COALESCE(closed_date, placement_date)) FROM production_cycles WHERE house_id = ?", [$houseId]);
            if ($prevLast && $date < $prevLast) throw new UserError('Placement date overlaps the previous cycle in this house (it ran until ' . fmt_date($prevLast) . ').');
            if ($name === '') $name = house_label($h['house_number']) . ' – Cycle ' . (1 + (int)val('SELECT COUNT(*) FROM production_cycles WHERE house_id = ?', [$houseId])) . ' (' . date('M Y', strtotime($date)) . ')';
            q('INSERT INTO production_cycles (house_id, cycle_name, initial_population, placement_date, expected_days, supplier, notes, created_by) VALUES (?,?,?,?,?,?,?,?)', [$houseId, $name, $pop, $date, $days, post('supplier') ?: null, post('notes') ?: null, $u['id']]);
            $cid = (int)$pdo->lastInsertId();
            q("UPDATE houses SET status = 'active' WHERE id = ?", [$houseId]);
            audit_log('cycle.create', 'cycle', $cid, null, ['house' => $h['house_number'], 'name' => $name, 'initial_population' => $pop, 'placement_date' => $date, 'expected_days' => $days]);
            $pdo->commit(); flash('success', "Cycle “{$name}” created."); redirect('cycles/view.php?id=' . $cid);

        case 'close':
            $id = (int)post('id'); if (post('confirm') !== '1') throw new UserError('Please tick the confirmation box to close the cycle.');
            $pdo->beginTransaction();
            $locked = row('SELECT id, status FROM production_cycles WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked) throw new UserError('Cycle #' . $id . ' could not be found.');
            if ($locked['status'] !== 'active') throw new UserError('Cycle #' . $id . ' is currently ' . $locked['status'] . '; only an active cycle can be closed.');
            $c = cycle_stat($id);
            if (!$c) throw new UserError('Cycle #' . $id . ' could not be loaded.');
            $d = post('closed_date');
            if (!valid_date($d) || $d < $c['placement_date'] || $d > today()) throw new UserError('Closing date must be between the placement date and today.');
            if ($c['last_report_date'] && $c['last_report_date'] > $d) throw new UserError('Reports exist after ' . fmt_date($d) . '. Choose a closing date on or after ' . fmt_date($c['last_report_date']) . '.');
            q("UPDATE production_cycles SET status = 'closed', closed_date = ?, close_notes = ? WHERE id = ?", [$d, post('close_notes') ?: null, $id]);
            q("UPDATE houses SET status = 'cleaning' WHERE id = ?", [$c['house_id']]);
            audit_log('cycle.close', 'cycle', $id, ['status' => 'active'], ['status' => 'closed', 'closed_date' => $d, 'living' => $c['living'], 'deaths' => $c['deaths'], 'feed_total' => $c['feed_total'], 'mortality_pct' => round($c['mortality_pct'], 2)], post('close_notes') ?: null);
            $pdo->commit(); flash('success', 'Cycle closed. Its full history is preserved.'); redirect('cycles/view.php?id=' . $id);

        case 'adjust':
            $id = (int)post('id'); $type = post('type'); $qty = (int)post('quantity'); $d = post('adjustment_date'); $reason = post('reason');
            if (!in_array($type, ['arrival', 'removal'], true)) throw new UserError('Choose a valid adjustment type.');
            if ($qty < 1 || $qty > 10000000) throw new UserError('Quantity must be 1 or more.');
            if (mb_strlen($reason) < 5) throw new UserError('Please give a clear reason (at least 5 characters).');
            $pdo->beginTransaction();
            q('SELECT id FROM production_cycles WHERE id = ? FOR UPDATE', [$id]); $c = cycle_stat($id);
            if (!$c || $c['status'] !== 'active') throw new UserError('Adjustments can only be made on an active cycle.');
            if (!valid_date($d) || $d < $c['placement_date'] || $d > today()) throw new UserError('Date must be between the placement date and today.');
            if ($type === 'removal' && $qty > $c['living']) throw new UserError('You cannot remove ' . fmt_int($qty) . ' chickens; only ' . fmt_int($c['living']) . ' are living.');
            q('INSERT INTO population_adjustments (cycle_id, adjustment_date, type, quantity, reason, created_by) VALUES (?,?,?,?,?,?)', [$id, $d, $type, $qty, mb_substr($reason, 0, 500), $u['id']]);
            $after = $c['living'] + ($type === 'arrival' ? $qty : -$qty);
            audit_log('cycle.adjust_population', 'cycle', $id, ['living' => $c['living']], ['living' => $after, 'type' => $type, 'quantity' => $qty, 'date' => $d], $reason);
            $pdo->commit(); flash('success', 'Adjustment saved. Living population is now ' . fmt_int($after) . '.'); redirect(back_target('cycles/view.php?id=' . $id));
    }
    throw new UserError('Unknown action.');
} catch (UserError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $e->getMessage());
    if (post('op') === 'create') { remember_input($_POST); open_modal_next('m-cycle'); }
    redirect(back_target(post('op') === 'close' ? 'cycles/view.php?id=' . (int)post('id') : 'cycles/index.php'));
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PFM] ' . $e->getMessage());
    flash('error', $e->getCode() === '23000' ? 'That change conflicts with existing data (for example, the house already has an active cycle).' : 'The database could not save this change. Nothing was changed.');
    redirect(back_target(post('op') === 'close' ? 'cycles/view.php?id=' . (int)post('id') : 'cycles/index.php'));
}
