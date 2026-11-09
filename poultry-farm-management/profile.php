<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
page_start('My account', '');
page_head('My account', 'Signed in as ' . $u['username'] . ' (' . ucfirst($u['role']) . ')');
?>
<div class="card chart-bento" data-spotlight style="max-width:520px">
  <h2>Change password</h2>
  <form method="post" action="<?= e(url('actions/profile.php')) ?>"><?= csrf_field() ?>
    <div class="field"><label>Current password</label><input type="password" name="current" autocomplete="current-password" required></div>
    <div class="field"><label>New password</label><input type="password" name="new" autocomplete="new-password" required><div class="hint">At least 8 characters, with letters and numbers.</div></div>
    <div class="field"><label>Repeat new password</label><input type="password" name="new2" autocomplete="new-password" required></div>
    <button class="btn btn-primary">Update password</button>
  </form>
</div>
<?php page_end();
