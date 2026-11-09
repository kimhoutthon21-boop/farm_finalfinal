<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $u = require_login();
try {
    $row = row('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify((string)($_POST['current'] ?? ''), $row['password_hash'])) throw new UserError('Your current password is not correct.');
    $new = (string)($_POST['new'] ?? '');
    if ($new !== (string)($_POST['new2'] ?? '')) throw new UserError('The new passwords do not match.');
    if ($p = password_problem($new)) throw new UserError($p);
    if (password_verify($new, $row['password_hash'])) throw new UserError('Please choose a password different from the current one.');
    q('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    session_regenerate_id(true);
    audit_log('user.password_changed', 'user', (int)$u['id']);
    flash('success', 'Password updated.'); redirect('dashboard.php');
} catch (UserError $e) { flash('error', $e->getMessage()); redirect('profile.php'); }
