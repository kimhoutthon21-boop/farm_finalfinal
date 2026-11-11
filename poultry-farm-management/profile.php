<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
page_start('My account', '');
page_head('My account', 'Signed in as ' . $u['username'] . ' (' . ucfirst($u['role']) . ')');
?>
<div class="card chart-bento mb" data-spotlight style="max-width:520px">
  <h2>Preferences</h2>
  <div class="pref-row"><div><strong>Language</strong><div class="small muted">English or Khmer</div></div><?= language_switcher() ?></div>
  <div class="pref-row"><div><strong>Dark mode</strong><div class="small muted">Easier on the eyes at night</div></div><div class="theme-switch-wrap"><input class="theme-switch-input" id="theme-switch" type="checkbox" data-theme-toggle aria-label="Enable dark mode"><label class="theme-switch-button" for="theme-switch" data-theme-toggle-label title="Switch to dark mode"><span class="theme-switch-icon theme-switch-moon"><?= icon('moon', 20) ?></span><span class="theme-switch-icon theme-switch-sun"><?= icon('sun', 20) ?></span></label></div></div>
</div>
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
