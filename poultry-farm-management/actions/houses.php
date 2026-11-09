<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $u = require_admin();
try {
    switch (post('op')) {
        case 'create':
            $n = (int)post('house_number');
            if ($n < 1 || $n > 999) throw new UserError('Enter a house number between 1 and 999.');
            if (val('SELECT id FROM houses WHERE house_number = ?', [$n])) throw new UserError(house_label($n) . ' already exists.');
            q('INSERT INTO houses (house_number) VALUES (?)', [$n]); $id = (int)db()->lastInsertId();
            audit_log('house.create', 'house', $id, null, ['house_number' => $n]);
            flash('success', house_label($n) . ' added.'); redirect('houses/view.php?id=' . $id);
        case 'status':
            $id = (int)post('id'); $s = post('status'); $h = row('SELECT * FROM houses WHERE id = ?', [$id]);
            if (!$h || !in_array($s, ['active', 'empty', 'cleaning', 'maintenance'], true)) throw new UserError('Invalid house or status.');
            if (active_cycle_for_house($id)) throw new UserError('This house has an active cycle. Close the cycle before changing its status.');
            if ($s === 'active') throw new UserError('A house becomes Active automatically when you add a cycle to it.');
            q('UPDATE houses SET status = ? WHERE id = ?', [$s, $id]);
            audit_log('house.status', 'house', $id, ['status' => $h['status']], ['status' => $s]);
            flash('success', house_label($h['house_number']) . ' is now ' . $s . '.'); redirect('houses/view.php?id=' . $id);
    }
    throw new UserError('Unknown action.');
} catch (UserError $e) { flash('error', $e->getMessage()); redirect(back_target('houses/index.php')); }
