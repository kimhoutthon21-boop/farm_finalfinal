<?php
require __DIR__ . '/includes/bootstrap.php';
try { if ((int)val("SELECT COUNT(*) FROM users WHERE role = 'admin'") === 0) redirect('setup.php'); } catch (PDOException $ex) { redirect('setup.php'); }
if (current_user()) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) { $error = 'Your session expired. Please try again.'; }
    else {
        [$ok, $m] = attempt_login(post('username'), (string)($_POST['password'] ?? ''));
        if ($ok) { $next = get('next') ?: post('next'); redirect(($next && preg_match('#^[A-Za-z0-9_\-/\.]+(\?[A-Za-z0-9_\-=&%\.]*)?$#', $next) && !str_contains($next, '..') && !str_contains($next, '//')) ? $next : 'dashboard.php'); }
        $error = $m;
    }
}
page_start('Log in');
?>
<div class="login-layout">
  <?= language_switcher() ?>
  <div class="auth-card login-card">
    <form method="post" class="login-form" aria-labelledby="login-heading"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e(get('next')) ?>">
      <h1 class="login-heading" id="login-heading">Welcome!!</h1>
      <?php if ($error): ?><div class="notice danger login-error"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div><?php endif; ?>
      <label class="login-field" for="u"><?= icon('user', 18) ?><span class="sr-only">Username</span><input id="u" name="username" autocomplete="username" autofocus required value="<?= e(post('username')) ?>" placeholder="Username"></label>
      <label class="login-field" for="p"><?= icon('lock', 18) ?><span class="sr-only">Password</span><input id="p" type="password" name="password" autocomplete="current-password" required placeholder="Password"></label>
      <button class="btn btn-primary big-btn login-submit" type="submit">Login</button>
    </form>
  </div>
</div>
<?php page_end();
