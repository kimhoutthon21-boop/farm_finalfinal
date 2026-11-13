<?php
require_once __DIR__ . '/sidebar.php';

function page_start(string $title, string $active = '', array $opts = []): void {
    $u = current_user();
    $flash = pull_flash();
    $openModal = $_SESSION['open_modal'] ?? null; unset($_SESSION['open_modal']);
    $alerts = []; $alertCount = 0; $alertReadKey = '';
    if ($u) {
        $alerts = farm_alerts();
        if ($u['role'] !== 'admin') { $ids = allowed_house_ids(); $alerts = array_values(array_filter($alerts, fn($a) => isset($a['house_id']) && in_array((int)$a['house_id'], $ids, true) && !str_contains($a['url'], 'cycles/view'))); }
        $alertCount = count($alerts);
        $alertReadKey = hash('sha256', (int)$u['id'] . ':' . serialize($alerts));
    }
    $sug = is_admin() ? [
        ['Find a chicken house', 'houses/index.php', 'house'], ['Search daily reports', 'reports/index.php?focus=1', 'file'],
        ['View active cycles', 'cycles/index.php?status=active', 'layers'], ['Search workers', 'workers/index.php', 'users'], ['View feed records', 'feed/index.php', 'package'],
    ] : [['Submit today\'s report', 'reports/create.php', 'clipboard'], ['My submitted reports', 'reports/index.php?focus=1', 'file'], ['View my houses', 'houses/index.php', 'house']];
    $sug = array_map(fn($s) => ['label' => $s[0], 'url' => url($s[1]), 'icon' => icon($s[2], 18)], $sug);
    $boot = ['base' => BASE_URL, 'csrf' => csrf_token(), 'flash' => $flash, 'openModal' => $openModal, 'suggestions' => $sug];
    ?><!doctype html>
<html lang="<?= e(current_language() === 'km' ? 'km' : 'en') ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t($title)) ?> · <?= e(setting('farm_name', 'Poultry Farm')) ?></title>
<script>try { document.documentElement.dataset.theme = localStorage.getItem('pfm-theme') === 'dark' ? 'dark' : 'light'; } catch (error) { document.documentElement.dataset.theme = 'light'; console.warn('Theme preference could not be loaded.', error); }</script>
<link rel="stylesheet" href="<?= e(url('assets/css/icons.css')) ?>?v=1">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=65">
</head><body class="<?= $u ? 'app sidebar-compact' : 'auth' . ($title === 'Log in' ? ' login-page' : '') ?>">
<?php if ($u): render_sidebar($active); ?>
<div class="main-wrap">
<header class="topbar">
  <button class="icon-btn menu-btn" data-sidebar-open aria-label="Open menu"><?= icon('menu') ?></button>
  <div class="search" data-search>
    <?= icon('search', 18) ?><input type="search" placeholder="Search houses, reports, cycles…" autocomplete="off" aria-label="Search" data-search-input>
    <div class="search-pop" data-search-pop hidden></div>
  </div>
  <div class="top-actions">
    <div class="dropdown" data-dropdown>
      <button class="icon-btn" data-dropdown-toggle data-notification-toggle data-alert-user="<?= (int)$u['id'] ?>" data-alert-key="<?= e($alertReadKey) ?>" aria-label="Notifications"><?= icon('bell') ?><?php if ($alertCount): ?><span class="dot" data-alert-count><?= min(99, $alertCount) ?></span><?php endif; ?></button>
      <div class="dropdown-menu wide" hidden>
        <div class="dd-title">Alerts</div>
        <?php if (!$alerts): ?><div class="dd-empty">All clear. No alerts right now.</div><?php endif; ?>
        <?php foreach (array_slice($alerts, 0, 8) as $a): ?>
          <a class="dd-alert lvl-<?= e($a['level']) ?>" href="<?= e(url($a['url'])) ?>"><strong><?= e($a['title']) ?></strong><span><?= e($a['text']) ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="dropdown" data-dropdown>
      <button class="user-chip" data-dropdown-toggle><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['full_name'], 0, 1))) ?></span><span class="uname"><?= e($u['full_name']) ?><small><?= e(ucfirst($u['role'])) ?></small></span><?= icon('down', 16) ?></button>
      <div class="dropdown-menu" hidden>
        <a href="<?= e(url('profile.php')) ?>"><?= icon('settings', 16) ?> Settings</a>
        <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 16) ?> Log out</button></form>
      </div>
    </div>
  </div>
</header>
<main class="content" id="main">
<?php endif; ?>
<script>window.__translations = <?= json_encode(translations(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>window.__app = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php
}
function page_head(string $title, string $subtitle = '', string $actionsHtml = ''): void {
    echo '<div class="page-head"><div><h1>' . e(t($title)) . '</h1>' . ($subtitle ? '<p class="muted">' . e(t($subtitle)) . '</p>' : '') . '</div><div class="page-actions">' . $actionsHtml . '</div></div>';
}
function modal_start(string $id, string $title, string $size = ''): void {
    echo '<div class="modal" id="' . e($id) . '" hidden><div class="modal-backdrop" data-modal-close></div><div class="modal-card ' . e($size) . '" role="dialog" aria-modal="true" aria-label="' . e(t($title)) . '"><div class="modal-head"><h3 data-title>' . e(t($title)) . '</h3><button class="icon-btn" data-modal-close aria-label="Close">' . icon('x', 18) . '</button></div><div class="modal-body">';
}
function modal_end(): void { echo '</div></div></div>'; }
function page_end(array $scripts = []): void {
    $u = current_user();
    if ($u) echo '</main></div>';
    echo '<div class="toasts" id="toasts" aria-live="polite"></div>';
    echo '<div class="modal" id="confirm-modal" hidden><div class="modal-backdrop" data-modal-close></div><div class="modal-card sm"><div class="modal-head"><h3 id="confirm-title">Please confirm</h3></div><div class="modal-body"><p id="confirm-text"></p><div class="modal-actions"><button class="btn btn-ghost" data-modal-close type="button">Cancel</button><button class="btn btn-danger" id="confirm-ok" type="button">Confirm</button></div></div></div></div>';
    echo '<script src="' . e(url('assets/js/i18n.js')) . '?v=1"></script>';
    echo '<script src="' . e(url('assets/js/app.js')) . '?v=30"></script>';
    foreach ($scripts as $s) echo '<script src="' . e(url($s)) . '?v=23"></script>';
    echo '</body></html>';
}
