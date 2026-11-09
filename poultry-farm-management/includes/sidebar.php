<?php
function nav_items(): array {
    $items = [];
    if (is_admin()) {
        $items[] = ['dashboard', t('Dashboard'), 'dashboard.php', 'dashboard'];
        $items[] = ['clipboard', t('Daily Report'), 'reports/create.php', 'report-new', 'cta'];
        $items[] = ['file', t('Report History'), 'reports/index.php', 'reports'];
        $items[] = ['house', t('Houses'), 'houses/index.php', 'houses'];
        $items[] = ['layers', t('Flock Cycles'), 'cycles/index.php', 'cycles'];
        $items[] = ['package', t('Feed'), 'feed/index.php', 'feed'];
        $items[] = ['activity', t('Mortality'), 'mortality/index.php', 'mortality'];
        $items[] = ['wallet', t('Finance'), 'finance/index.php', 'finance'];
        $items[] = ['users', t('Workers'), 'workers/index.php', 'workers'];
        $items[] = ['settings', t('Settings'), 'settings/index.php', 'settings'];
        $items[] = ['shield', t('Audit Log'), 'settings/audit.php', 'audit'];
    } else {
        $items[] = ['clipboard', t('Daily Report'), 'reports/create.php', 'report-new', 'cta'];
        $items[] = ['dashboard', t('Today'), 'dashboard.php', 'dashboard'];
        $items[] = ['file', t('My Reports'), 'reports/index.php', 'reports'];
        $items[] = ['house', t('My houses'), 'houses/index.php', 'houses'];
    }
    return $items;
}
function render_sidebar(string $active): void {
    $u = current_user();
    echo '<aside class="sidebar" id="sidebar"><a class="brand" href="' . e(url('dashboard.php')) . '" aria-label="' . e(t('Go to dashboard')) . '"><span class="brand-mark logo-mark"><img src="' . e(url('assets/images/farm-logo.png')) . '" alt=""></span><div class="brand-copy"><strong>' . e(setting('farm_name', 'Poultry Farm')) . '</strong><small>' . e(t('Farm management')) . '</small></div></a><nav>';
    foreach (nav_items() as $it) {
        $isActive = $active === $it[3];
        echo '<a class="nav-item ' . ($isActive ? 'active' : '') . (isset($it[4]) ? ' nav-cta' : '') . '" href="' . e(url($it[2])) . '" aria-label="' . e($it[1]) . '"' . ($isActive ? ' aria-current="page"' : '') . '><span class="nav-icon">' . icon($it[0], 19) . '</span><span class="nav-label">' . e($it[1]) . '</span></a>';
    }
    echo '</nav><div class="sidebar-foot"><a class="nav-item" href="' . e(url('profile.php')) . '" aria-label="' . e($u['full_name']) . '"><span class="nav-icon">' . icon('user', 19) . '</span><span class="nav-label">' . e($u['full_name']) . '</span></a>';
    echo '<form method="post" action="' . e(url('logout.php')) . '">' . csrf_field() . '<button class="nav-item nav-btn" type="submit" aria-label="' . e(t('Log out')) . '"><span class="nav-icon">' . icon('logout', 19) . '</span><span class="nav-label">' . e(t('Log out')) . '</span></button></form></div></aside><div class="scrim" data-sidebar-close></div>';
}
