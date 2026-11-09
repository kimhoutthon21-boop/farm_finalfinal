<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();
$w = ['1=1']; $p = [];
$ent = get('entity'); $act = get('action'); $uid = (int)get('user_id'); $from = valid_date(get('from')) ? get('from') : ''; $to = valid_date(get('to')) ? get('to') : '';
if ($ent !== '') { $w[] = 'a.entity_type = ?'; $p[] = $ent; } if ($act !== '') { $w[] = 'a.action LIKE ?'; $p[] = $act . '%'; } if ($uid) { $w[] = 'a.user_id = ?'; $p[] = $uid; }
if ($from) { $w[] = 'a.created_at >= ?'; $p[] = $from . ' 00:00:00'; } if ($to) { $w[] = 'a.created_at <= ?'; $p[] = $to . ' 23:59:59'; }
$W = implode(' AND ', $w);
$pg = paginate((int)val("SELECT COUNT(*) FROM audit_logs a WHERE $W", $p), 25);
$logs = rows("SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $W ORDER BY a.id DESC LIMIT " . $pg['per'] . ' OFFSET ' . $pg['offset'], $p);
$ents = array_column(rows('SELECT DISTINCT entity_type FROM audit_logs ORDER BY 1'), 'entity_type'); $users = rows('SELECT id, full_name FROM users ORDER BY full_name');
page_start('Audit log', 'audit'); page_head('Audit log', 'Every sensitive change: who, when, what it was before and after, and why.');
echo '<div class="card chart-bento" data-spotlight><form method="get" class="filters"><div><label>Area</label><select name="entity"><option value="">All</option>'; foreach ($ents as $x) echo '<option' . ($ent === $x ? ' selected' : '') . '>' . e($x) . '</option>'; echo '</select></div><div><label>Action starts with</label><input name="action" value="' . e($act) . '" placeholder="report.correct"></div><div><label>User</label><select name="user_id"><option value="">Anyone</option>'; foreach ($users as $x) echo '<option value="' . $x['id'] . '"' . ($uid == $x['id'] ? ' selected' : '') . '>' . e($x['full_name']) . '</option>'; echo '</select></div><div><label>From</label><input type="date" name="from" value="' . e($from) . '"></div><div><label>To</label><input type="date" name="to" value="' . e($to) . '"></div><button class="btn btn-primary">Filter</button><a class="btn btn-primary" href="?">Reset</a></form>';
if (!$logs) echo empty_state('No audit entries', 'Changes will be listed here as they happen.', 'shield');
else { echo '<div class="table-wrap"><table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Item</th><th>Reason</th><th>Details</th></tr></thead><tbody>';
    foreach ($logs as $a) { echo '<tr><td class="nowrap small">' . e(fmt_datetime($a['created_at'])) . '</td><td>' . e($a['full_name'] ?? 'system') . '</td><td><code>' . e($a['action']) . '</code></td><td class="small">' . e($a['entity_type'] . ($a['entity_id'] ? ' #' . $a['entity_id'] : '')) . '</td><td class="small">' . e($a['reason'] ?? '—') . '</td><td>';
        if ($a['old_values'] || $a['new_values']) echo '<details><summary class="small" style="cursor:pointer">View</summary>' . ($a['old_values'] ? '<div class="small muted">Before</div><pre class="json">' . e(json_encode(json_decode($a['old_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>' : '') . ($a['new_values'] ? '<div class="small muted">After</div><pre class="json">' . e(json_encode(json_decode($a['new_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>' : '') . '</details>'; else echo '<span class="muted">—</span>';
        echo '</td></tr>'; }
    echo '</tbody></table></div>'; }
echo pagination_html($pg) . '</div>'; page_end();
