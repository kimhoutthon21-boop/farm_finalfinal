<?php
/** Shared report queries and table used by history, house and cycle pages. */
function report_filters_sql(array $f): array {
    $w = ['1=1']; $p = [];
    if (!is_admin()) { $w[] = 'r.worker_id = ?'; $p[] = user_id(); }
    if (!empty($f['q'])) { $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['q']) . '%'; $w[] = '(w.full_name LIKE ? OR c.cycle_name LIKE ? OR r.notes LIKE ? OR r.work_description LIKE ? OR r.report_date LIKE ?)'; array_push($p, $like, $like, $like, $like, $like); }
    if (!empty($f['from'])) { $w[] = 'r.report_date >= ?'; $p[] = $f['from']; }
    if (!empty($f['to'])) { $w[] = 'r.report_date <= ?'; $p[] = $f['to']; }
    if (!empty($f['house_id'])) { $w[] = 'c.house_id = ?'; $p[] = (int)$f['house_id']; }
    if (!empty($f['cycle_id'])) { $w[] = 'r.cycle_id = ?'; $p[] = (int)$f['cycle_id']; }
    if (!empty($f['worker_id']) && is_admin()) { $w[] = 'r.worker_id = ?'; $p[] = (int)$f['worker_id']; }
    if (!empty($f['status']) && in_array($f['status'], ['submitted', 'voided', 'corrected'], true)) { $w[] = $f['status'] === 'corrected' ? "(r.status = 'submitted' AND r.revision > 0)" : 'r.status = ?'; if ($f['status'] !== 'corrected') $p[] = $f['status']; }
    if (!is_admin()) { $ids = allowed_house_ids(); $w[] = $ids ? 'c.house_id IN (' . in_placeholders($ids) . ')' : '1=0'; $p = array_merge($p, $ids); }
    return [implode(' AND ', $w), $p];
}
const REPORT_FROM = ' FROM daily_reports r JOIN production_cycles c ON c.id = r.cycle_id JOIN houses h ON h.id = c.house_id JOIN users w ON w.id = r.worker_id LEFT JOIN death_causes dc ON dc.id = r.death_cause_id ';
function fetch_reports(array $f, string $orderSql, int $limit, int $offset): array {
    [$w, $p] = report_filters_sql($f);
    return rows('SELECT r.*, c.cycle_name, c.initial_population, h.house_number, c.house_id, w.full_name AS worker_name, dc.name AS cause_name' . REPORT_FROM . "WHERE $w ORDER BY $orderSql LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset, $p);
}
function count_reports(array $f): int { [$w, $p] = report_filters_sql($f); return (int)val('SELECT COUNT(*)' . REPORT_FROM . "WHERE $w", $p); }
function reports_table(array $rows, bool $showHouse = true, ?array $st = null): void {
    if (!$rows) { echo empty_state('No reports found', 'Try changing the filters, or submit a new daily report.', 'file'); return; }
    $th = fn($l, $k, $class = '') => $st ? th_sort($l, $k, $st, $class) : '<th' . ($class !== '' ? ' class="' . e($class) . '"' : '') . '>' . e($l) . '</th>';
    echo '<div class="table-wrap"><table><thead><tr>' . $th('Date', 'date') . ($showHouse ? $th('House', 'house') : '') . '<th>Cycle</th>' . $th('Worker', 'worker') . $th('Feed (bags)', 'feed', 'num') . $th('Deaths', 'deaths', 'num') . '<th class="num">Standard</th><th>Cause</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td class="nowrap">' . e(fmt_date($r['report_date'])) . '</td>' . ($showHouse ? '<td>' . e(house_label($r['house_number'])) . '</td>' : '') . '<td class="small">' . e($r['cycle_name']) . '</td><td>' . e($r['worker_name']) . '</td><td class="num">' . fmt_bags($r['feed_bags']) . '</td><td class="num">' . fmt_int($r['deaths']) . '</td><td class="num muted">' . ($r['standard_deaths'] === null ? '—' : fmt_int($r['standard_deaths'])) . '</td><td class="small">' . e($r['cause_name'] ?? '—') . '</td><td>' . report_status_badge($r) . '</td><td class="act"><a class="btn btn-ghost btn-sm" href="' . e(url('reports/view.php?id=' . $r['id'])) . '">Details</a></td></tr>';
    }
    echo '</tbody></table></div>';
}
