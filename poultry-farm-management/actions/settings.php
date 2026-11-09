<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf(); $u = require_admin();
function num_between(string $k, float $min, float $max, string $label, bool $int = false): string {
    $v = str_replace(',', '.', post($k)); if ($v === '') return '';
    if (!is_numeric($v) || (float)$v < $min || (float)$v > $max) throw new UserError("$label must be between $min and $max.");
    return $int ? (string)(int)$v : (string)(float)$v;
}
$tab = 'general';
try {
    $op = post('op'); $old = settings();
    switch ($op) {
        case 'general':
            $name = post('farm_name'); if ($name === '') throw new UserError('Farm name is required.');
            $code = strtoupper(post('currency_code')); if (!preg_match('/^[A-Z]{2,5}$/', $code)) throw new UserError('Currency code should be letters, for example KHR or USD.');
            $sym = post('currency_symbol'); if ($sym === '' || mb_strlen($sym) > 5) throw new UserError('Enter a currency symbol (up to 5 characters).');
            $new = ['farm_name' => $name, 'farm_info' => post('farm_info'), 'currency_code' => $code, 'currency_symbol' => $sym, 'currency_decimals' => post('currency_decimals') === '2' ? '2' : '0',
                'default_cycle_days' => num_between('default_cycle_days', 1, 365, 'Cycle length', true) ?: '70', 'bag_weight_kg' => num_between('bag_weight_kg', 0, 1000, 'Bag weight'), 'allow_partial_bags' => isset($_POST['allow_partial_bags']) ? '1' : '0',
                'worker_backdate_days' => num_between('worker_backdate_days', 0, 30, 'Back-date days', true) ?: '0'];
            break;
        case 'alerts': $tab = 'alerts';
            $new = ['daily_mortality_warn_pct' => num_between('daily_mortality_warn_pct', 0, 100, 'Daily deaths warning') ?: '0', 'cycle_mortality_warn_pct' => num_between('cycle_mortality_warn_pct', 0, 100, 'Cycle mortality limit') ?: '0', 'report_reminder_hour' => num_between('report_reminder_hour', 0, 23, 'Reminder hour', true) ?: '0'];
            break;
        case 'cause_add': case 'task_add': case 'cause_save': case 'task_save':
            $kind = str_starts_with($op, 'cause') ? 'cause' : 'task'; $tab = $kind . 's'; $table = $kind === 'cause' ? 'death_causes' : 'work_task_categories';
            $name = post('name'); if ($name === '' || mb_strlen($name) > 80) throw new UserError('Enter a name (up to 80 characters).');
            if (str_ends_with($op, '_add')) {
                if (val("SELECT id FROM $table WHERE name = ?", [$name])) throw new UserError('That name already exists.');
                $max = (int)val("SELECT COALESCE(MAX(sort_order),0) FROM $table");
                if ($kind === 'cause') q('INSERT INTO death_causes (name, sort_order) VALUES (?,?)', [$name, $max + 1]); else q('INSERT INTO work_task_categories (name, sort_order) VALUES (?,?)', [$name, $max + 1]);
                audit_log("settings.$kind" . '_add', 'setting', null, null, ['name' => $name]); flash('success', 'Added.');
            } else {
                $id = (int)post('id'); $cur = row("SELECT * FROM $table WHERE id = ?", [$id]); if (!$cur) throw new UserError('Item not found.');
                if (val("SELECT id FROM $table WHERE name = ? AND id <> ?", [$name, $id])) throw new UserError('Another item already has that name.');
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($kind === 'cause') q('UPDATE death_causes SET name = ?, requires_detail = ?, is_active = ? WHERE id = ?', [$name, isset($_POST['requires_detail']) ? 1 : 0, $active, $id]); else q('UPDATE work_task_categories SET name = ?, is_active = ? WHERE id = ?', [$name, $active, $id]);
                audit_log("settings.$kind" . '_update', 'setting', $id, $cur, ['name' => $name, 'is_active' => $active]); flash('success', 'Saved.');
            }
            redirect('settings/index.php#' . $tab);
        default: throw new UserError('Unknown action.');
    }
    $changed = [];
    foreach ($new as $k => $v) { if (($old[$k] ?? '') !== $v) { $changed[$k] = ['from' => $old[$k] ?? null, 'to' => $v]; save_setting($k, $v); } }
    if ($changed) audit_log('settings.update', 'setting', null, null, $changed);
    flash('success', $changed ? 'Settings saved.' : 'No changes to save.');
} catch (UserError $e) { flash('error', $e->getMessage()); }
catch (PDOException $e) { error_log('[PFM] ' . $e->getMessage()); flash('error', 'Could not save. Nothing was changed.'); }
redirect('settings/index.php#' . $tab);
