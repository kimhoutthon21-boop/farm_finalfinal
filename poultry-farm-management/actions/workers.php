<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $me = require_admin(); $pdo = db();
function other_active_admins(int $exceptId): int { return (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$exceptId]); }
function set_houses(int $uid, array $ids): void {
    q('DELETE FROM worker_houses WHERE user_id = ?', [$uid]);
    foreach ($ids as $h) if (val('SELECT id FROM houses WHERE id = ?', [$h])) q('INSERT INTO worker_houses (user_id, house_id) VALUES (?,?)', [$uid, $h]);
}
try {
    switch (post('op')) {
        case 'create':
            $name = post('full_name'); $user = strtolower(post('username')); $role = post('role') === 'admin' ? 'admin' : 'worker'; $pw = (string)($_POST['password'] ?? '');
            if ($name === '' || mb_strlen($name) > 120) throw new UserError('Enter the person\'s full name.');
            if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $user)) throw new UserError('Username must be 3-50 letters, numbers, dots, dashes or underscores.');
            if (val('SELECT id FROM users WHERE username = ?', [$user])) throw new UserError('That username is already taken.');
            if ($p = password_problem($pw)) throw new UserError($p);
            $pdo->beginTransaction();
            q('INSERT INTO users (username, full_name, phone, password_hash, role, must_change_password) VALUES (?,?,?,?,?,1)', [$user, $name, post('phone') ?: null, password_hash($pw, PASSWORD_DEFAULT), $role]);
            $id = (int)$pdo->lastInsertId(); if ($role === 'worker') set_houses($id, int_list($_POST['houses'] ?? []));
            audit_log('user.create', 'user', $id, null, ['username' => $user, 'role' => $role, 'houses' => int_list($_POST['houses'] ?? [])]);
            $pdo->commit(); flash('success', "Account “{$user}” created. They must set a new password at first login."); break;
        case 'update':
            $id = (int)post('id'); $x = row('SELECT * FROM users WHERE id = ?', [$id]); if (!$x) throw new UserError('Account not found.');
            $role = post('role') === 'admin' ? 'admin' : 'worker'; $active = isset($_POST['is_active']) ? 1 : 0; $name = post('full_name');
            if ($name === '') throw new UserError('Name is required.');
            if ($x['role'] === 'admin' && (int)$x['is_active'] === 1 && ($role !== 'admin' || !$active) && other_active_admins($id) === 0) throw new UserError('This is the last active administrator. Make another administrator first.');
            $pdo->beginTransaction();
            q('UPDATE users SET full_name = ?, phone = ?, role = ?, is_active = ? WHERE id = ?', [$name, post('phone') ?: null, $role, $active, $id]);
            $hs = int_list($_POST['houses'] ?? []); set_houses($id, $role === 'worker' ? $hs : []);
            audit_log('user.update', 'user', $id, ['full_name' => $x['full_name'], 'role' => $x['role'], 'is_active' => (int)$x['is_active']], ['full_name' => $name, 'role' => $role, 'is_active' => $active, 'houses' => $hs]);
            $pdo->commit(); flash('success', 'Account updated.'); break;
        case 'reset':
            $id = (int)post('id'); $pw = (string)($_POST['password'] ?? ''); if (!row('SELECT id FROM users WHERE id = ?', [$id])) throw new UserError('Account not found.');
            if ($p = password_problem($pw)) throw new UserError($p);
            q('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            q("DELETE FROM login_attempts WHERE username = (SELECT username FROM users WHERE id = ?)", [$id]);
            audit_log('user.password_reset', 'user', $id); flash('success', 'Password reset. Share the temporary password privately.'); break;
        default: throw new UserError('Unknown action.');
    }
} catch (UserError $e) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', $e->getMessage()); }
catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('[PFM] ' . $e->getMessage()); flash('error', 'Could not save the account.'); }
redirect('workers/index.php');
