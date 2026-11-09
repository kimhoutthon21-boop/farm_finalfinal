<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();
$qs = get('q'); $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qs) . '%';
$users = rows("SELECT u.*, (SELECT GROUP_CONCAT(h.house_number ORDER BY h.house_number) FROM worker_houses wh JOIN houses h ON h.id = wh.house_id WHERE wh.user_id = u.id) houses, (SELECT COUNT(*) FROM daily_reports r WHERE r.worker_id = u.id) reports FROM users u WHERE (? = '' OR u.full_name LIKE ? OR u.username LIKE ?) ORDER BY u.is_active DESC, u.role, u.full_name", [$qs, $like, $like]);
$houses = rows('SELECT * FROM houses ORDER BY house_number');
page_start('Workers', 'workers');
page_head('Workers & accounts', 'Create accounts, choose which houses each person can use, and reset passwords.', '<button class="btn btn-primary btn-featured" data-modal-open="m-new">' . icon('plus', 18) . ' Add account</button>');
echo '<div class="card chart-bento" data-spotlight><form method="get" class="filters"><div style="flex:3"><label>Search</label><input type="search" name="q" value="' . e($qs) . '" placeholder="Name or username"></div><button class="btn btn-primary">Search</button></form>';
if (!$users) echo empty_state('No accounts match', '', 'users');
else {
    echo '<div class="table-wrap"><table><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Houses</th><th class="num">Reports</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($users as $x) {
        $hv = []; $assigned = array_map('intval', array_column(rows('SELECT house_id FROM worker_houses WHERE user_id = ?', [$x['id']]), 'house_id'));
        echo '<tr><td><strong>' . e($x['full_name']) . '</strong>' . ($x['phone'] ? '<div class="small muted">' . e($x['phone']) . '</div>' : '') . '</td><td>' . e($x['username']) . '</td><td>' . badge(ucfirst($x['role']), $x['role'] === 'admin' ? 'info' : 'muted') . '</td><td class="small">' . ($x['role'] === 'admin' ? 'All houses' : ($x['houses'] ? 'House ' . e(str_replace(',', ', ', $x['houses'])) : '<span class="muted">None</span>')) . '</td><td class="num">' . fmt_int($x['reports']) . '</td><td class="small">' . e($x['last_login_at'] ? fmt_datetime($x['last_login_at']) : 'Never') . '</td><td>' . ((int)$x['is_active'] ? badge('Active', 'success') : badge('Deactivated', 'danger')) . '</td>';
        $vals = e(json_encode(['id' => $x['id'], 'full_name' => $x['full_name'], 'phone' => $x['phone'], 'role' => $x['role'], 'is_active' => (int)$x['is_active']]));
        echo '<td class="act"><button class="btn btn-primary btn-sm" data-fill="m-edit" data-values=\'' . $vals . '\' data-houses=\'' . e(json_encode($assigned)) . '\' data-edit-user>Edit</button> <button class="btn btn-primary btn-sm" data-fill="m-reset" data-values=\'' . e(json_encode(['id' => $x['id']])) . '\' data-title="Reset password for ' . e($x['full_name']) . '">Reset password</button></td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';
function house_checks(array $houses, string $prefix): string {
    $h = '<div class="field full"><label>Houses this worker may use</label><div class="pills">';
    foreach ($houses as $x) $h .= '<label class="pill"><input type="checkbox" name="houses[]" value="' . $x['id'] . '" data-house="' . $x['id'] . '"><span>' . e(house_label($x['house_number'])) . '</span></label>';
    return $h . '</div><div class="hint">Administrators can always use every house.</div></div>';
}
modal_start('m-new', 'Add account');
echo '<form method="post" action="' . e(url('actions/workers.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="create"><div class="form-grid"><div class="field"><label>Full name</label><input name="full_name" required maxlength="120"></div><div class="field"><label>Username</label><input name="username" required pattern="[A-Za-z0-9_.\-]{3,50}" autocomplete="off"><div class="hint">3-50 letters or numbers, no spaces.</div></div><div class="field"><label>Phone (optional)</label><input name="phone" maxlength="30"></div><div class="field"><label>Role</label><select name="role"><option value="worker">Farm worker</option><option value="admin">Administrator</option></select></div><div class="field full"><label>Temporary password</label><div class="stepper"><input type="text" id="np" name="password" required minlength="8" autocomplete="off"><button type="button" class="btn" data-genpass="np">Generate</button></div><div class="hint">Share it privately. The person must choose a new password at first login.</div></div>' . house_checks($houses, 'n') . '</div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary">Create account</button></div></form>';
modal_end();
modal_start('m-edit', 'Edit account');
echo '<form method="post" action="' . e(url('actions/workers.php')) . '">' . csrf_field() . '<input type="hidden" name="op" value="update"><input type="hidden" name="id"><div class="form-grid"><div class="field"><label>Full name</label><input name="full_name" required maxlength="120"></div><div class="field"><label>Phone</label><input name="phone" maxlength="30"></div><div class="field"><label>Role</label><select name="role"><option value="worker">Farm worker</option><option value="admin">Administrator</option></select></div><div class="field"><label>Account active</label><label class="switch"><input type="checkbox" name="is_active" value="1"><i></i></label></div>' . house_checks($houses, 'e') . '</div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary">Save changes</button></div></form>';
modal_end();
modal_start('m-reset', 'Reset password', 'sm');
echo '<form method="post" action="' . e(url('actions/workers.php')) . '" data-confirm="Reset this password? The person will be signed out and must choose a new one." data-confirm-ok="Reset">' . csrf_field() . '<input type="hidden" name="op" value="reset"><input type="hidden" name="id"><div class="field"><label>New temporary password</label><div class="stepper"><input type="text" id="rp" name="password" required minlength="8" autocomplete="off"><button type="button" class="btn" data-genpass="rp">Generate</button></div><div class="hint">They must change it at next login.</div></div><div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button class="btn btn-primary">Reset password</button></div></form>';
modal_end();
echo '<script>document.querySelectorAll("[data-edit-user]").forEach(function(b){b.addEventListener("click",function(){var ids=JSON.parse(b.getAttribute("data-houses")||"[]").map(String);document.querySelectorAll("#m-edit [data-house]").forEach(function(c){c.checked=ids.indexOf(c.value)>-1;});});});</script>';
page_end();
