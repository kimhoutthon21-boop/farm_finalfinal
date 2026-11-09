<?php
function is_admin(): bool { $u = current_user(); return $u && $u['role'] === 'admin'; }
function user_id(): int { return (int)(current_user()['id'] ?? 0); }
/** Server-side gate. Hiding a button is never enough - every admin page and action calls this. */
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); render_forbidden(); }
    return $u;
}
function render_forbidden(): void {
    page_start('Access denied', '');
    echo '<div class="empty"><div class="empty-icon">' . icon('shield', 28) . '</div><h2>You do not have access to this page</h2><p>This area is only available to farm administrators.</p><a class="btn btn-primary" href="' . e(url('dashboard.php')) . '">Back to home</a></div>';
    page_end();
    exit;
}
/** House ids a worker may use. Admins may use all houses. */
function allowed_house_ids(?int $uid = null): array {
    $uid = $uid ?? user_id();
    if (is_admin()) return array_map('intval', array_column(rows('SELECT id FROM houses'), 'id'));
    return array_map('intval', array_column(rows('SELECT house_id FROM worker_houses WHERE user_id = ?', [$uid]), 'house_id'));
}
function can_access_house(int $houseId): bool { return in_array($houseId, allowed_house_ids(), true); }
function require_house_access(int $houseId): void {
    if (!can_access_house($houseId)) { http_response_code(403); render_forbidden(); }
}
