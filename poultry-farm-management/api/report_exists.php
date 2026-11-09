<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = current_user(); header('Content-Type: application/json; charset=utf-8');
if (!$u) { http_response_code(401); echo '{}'; exit; }
$c = row('SELECT house_id FROM production_cycles WHERE id = ?', [(int)get('cycle_id')]);
if (!$c || !can_access_house((int)$c['house_id']) || !valid_date(get('date'))) { echo json_encode(['exists' => false]); exit; }
$r = row('SELECT id, status FROM daily_reports WHERE cycle_id = ? AND report_date = ?', [(int)get('cycle_id'), get('date')]);
echo json_encode($r ? ['exists' => true, 'status' => $r['status'], 'url' => url('reports/view.php?id=' . $r['id'])] : ['exists' => false]);
