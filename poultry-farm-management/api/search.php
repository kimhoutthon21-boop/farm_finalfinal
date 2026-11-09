<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = current_user(); header('Content-Type: application/json; charset=utf-8');
if (!$u) { http_response_code(401); echo json_encode(['groups' => []]); exit; }
$term = mb_substr(get('q'), 0, 60); $groups = [];
if ($term === '') { echo json_encode(['groups' => []]); exit; }
$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
$ids = allowed_house_ids(); $admin = is_admin();
$ph = $ids ? in_placeholders($ids) : '0';
$digits = preg_replace('/\D/', '', $term);

if ($ids && ($digits !== '' || stripos('house', $term) === 0 || $admin)) {
    $hs = rows("SELECT id, house_number, status FROM houses WHERE id IN ($ph) AND (CAST(house_number AS CHAR) = ? OR ? LIKE 'hou%') ORDER BY house_number LIMIT 6", array_merge($ids, [$digits === '' ? '-1' : $digits, strtolower($term)]));
    if ($hs) $groups[] = ['title' => 'Houses', 'items' => array_map(fn($h) => ['label' => house_label($h['house_number']), 'meta' => ucfirst($h['status']), 'url' => url('houses/view.php?id=' . $h['id'])], $hs)];
}
if ($ids) {
    $cs = rows("SELECT c.id, c.cycle_name, c.status, h.house_number FROM production_cycles c JOIN houses h ON h.id = c.house_id WHERE c.house_id IN ($ph) AND (c.cycle_name LIKE ? OR CAST(h.house_number AS CHAR) = ?) ORDER BY c.placement_date DESC LIMIT 6", array_merge($ids, [$like, $digits === '' ? '-1' : $digits]));
    if ($cs) $groups[] = ['title' => 'Production cycles', 'items' => array_map(fn($c) => ['label' => $c['cycle_name'], 'meta' => house_label($c['house_number']) . ' · ' . ucfirst($c['status']), 'url' => url($admin ? 'cycles/view.php?id=' . $c['id'] : 'houses/view.php?id=' . $c['house_id'])], $cs)];
    $w = $admin ? '' : ' AND r.worker_id = ' . (int)$u['id'];
    $rs = rows("SELECT r.id, r.report_date, h.house_number, w.full_name FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id JOIN users w ON w.id = r.worker_id WHERE c.house_id IN ($ph) $w AND (r.report_date LIKE ? OR w.full_name LIKE ? OR r.notes LIKE ? OR c.cycle_name LIKE ?) ORDER BY r.report_date DESC LIMIT 6", array_merge($ids, [$like, $like, $like, $like]));
    if ($rs) $groups[] = ['title' => 'Daily reports', 'items' => array_map(fn($r) => ['label' => fmt_date($r['report_date']) . ' · ' . house_label($r['house_number']), 'meta' => $r['full_name'], 'url' => url('reports/view.php?id=' . $r['id'])], $rs)];
}
if ($admin) {
    $ws = rows("SELECT id, full_name, username, role FROM users WHERE full_name LIKE ? OR username LIKE ? ORDER BY full_name LIMIT 5", [$like, $like]);
    if ($ws) $groups[] = ['title' => 'People', 'items' => array_map(fn($x) => ['label' => $x['full_name'], 'meta' => '@' . $x['username'] . ' · ' . ucfirst($x['role']), 'url' => url('workers/index.php?q=' . urlencode($x['username']))], $ws)];
}
$pages = $admin ? ['Feed records' => 'feed/index.php', 'Mortality' => 'mortality/index.php', 'Finance' => 'finance/index.php', 'Settings' => 'settings/index.php', 'Audit log' => 'settings/audit.php', 'Report history' => 'reports/index.php', 'Flock cycles' => 'cycles/index.php', 'Workers' => 'workers/index.php'] : ['My reports' => 'reports/index.php', 'Daily report' => 'reports/create.php'];
$pm = []; foreach ($pages as $l => $p) if (stripos($l, $term) !== false) $pm[] = ['label' => $l, 'meta' => 'Page', 'url' => url($p)];
if ($pm) $groups[] = ['title' => 'Pages', 'items' => $pm];
echo json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE);
