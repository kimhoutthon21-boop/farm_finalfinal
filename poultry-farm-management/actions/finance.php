<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/finance_lib.php';
require_post_csrf(); $u = require_admin();
try {
    $type = post('type') === 'revenue' ? 'revenue' : 'expense'; $tbl = $type === 'revenue' ? 'revenue' : 'expenses';
    if (post('op') === 'delete') {
        $id = (int)post('id'); $old = row("SELECT * FROM $tbl WHERE id = ?", [$id]); if (!$old) throw new UserError('Record not found.');
        q("DELETE FROM $tbl WHERE id = ?", [$id]); audit_log("finance.$type" . '_delete', $type, $id, $old, null);
        flash('success', 'Record deleted (kept in the audit log).'); redirect(back_target('finance/index.php'));
    }
    if (post('op') !== 'save') throw new UserError('Unknown action.');
    $id = (int)post('id'); $d = post('txn_date'); $amt = str_replace(',', '', post('amount')); $desc = post('description');
    if (!valid_date($d) || $d > today()) throw new UserError('Choose a valid date that is not in the future.');
    if (!is_numeric($amt) || (float)$amt <= 0 || (float)$amt > 9999999999999) throw new UserError('Amount must be a number greater than 0.');
    if ($desc === '' || mb_strlen($desc) > 255) throw new UserError('Please enter a short description.');
    $cat = row('SELECT * FROM finance_categories WHERE id = ? AND type = ?', [(int)post('category_id'), $type]); if (!$cat) throw new UserError('Choose a valid category.');
    $cycleId = (int)post('cycle_id') ?: null; $houseId = (int)post('house_id') ?: null;
    if ($cycleId) { $c = row('SELECT house_id FROM production_cycles WHERE id = ?', [$cycleId]); if (!$c) throw new UserError('That production cycle does not exist.'); $houseId = (int)$c['house_id']; }
    elseif ($houseId && !row('SELECT id FROM houses WHERE id = ?', [$houseId])) throw new UserError('That house does not exist.');
    $vals = [$d, $cat['id'], $desc, (float)$amt, $houseId, $cycleId, post('payment_method') ?: null, post('reference') ?: null, post('notes') ?: null];
    if ($id) {
        $old = row("SELECT * FROM $tbl WHERE id = ?", [$id]); if (!$old) throw new UserError('Record not found.');
        q("UPDATE $tbl SET txn_date = ?, category_id = ?, description = ?, amount = ?, house_id = ?, cycle_id = ?, payment_method = ?, reference = ?, notes = ? WHERE id = ?", array_merge($vals, [$id]));
        audit_log("finance.$type" . '_update', $type, $id, $old, row("SELECT * FROM $tbl WHERE id = ?", [$id]));
    } else {
        q("INSERT INTO $tbl (txn_date, category_id, description, amount, house_id, cycle_id, payment_method, reference, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)", array_merge($vals, [$u['id']]));
        audit_log("finance.$type" . '_create', $type, (int)db()->lastInsertId(), null, ['date' => $d, 'amount' => (float)$amt, 'category' => $cat['name']]);
    }
    flash('success', ucfirst($type) . ' saved.');
} catch (UserError $e) { flash('error', $e->getMessage()); }
redirect(back_target('finance/index.php'));
