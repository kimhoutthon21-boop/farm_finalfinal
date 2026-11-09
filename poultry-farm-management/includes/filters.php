<?php
/** House / cycle selectors shared by feed and mortality pages. */
function house_cycle_filter_html(array $R, int $houseId, int $cycleId, string $page): string {
    $houses = rows('SELECT id, house_number FROM houses ORDER BY house_number'); $cycles = rows('SELECT id, cycle_name FROM production_cycles ORDER BY placement_date DESC');
    $h = '<form method="get" class="filters"><input type="hidden" name="range" value="' . e($R['range']) . '"><input type="hidden" name="from" value="' . e($R['from']) . '"><input type="hidden" name="to" value="' . e($R['to']) . '"><div><label>House</label><select name="house_id" onchange="this.form.submit()"><option value="">All houses</option>';
    foreach ($houses as $x) $h .= '<option value="' . $x['id'] . '"' . ($houseId == $x['id'] ? ' selected' : '') . '>' . e(house_label($x['house_number'])) . '</option>';
    $h .= '</select></div><div><label>Production cycle</label><select name="cycle_id" onchange="this.form.submit()"><option value="">All cycles</option>';
    foreach ($cycles as $x) $h .= '<option value="' . $x['id'] . '"' . ($cycleId == $x['id'] ? ' selected' : '') . '>' . e($x['cycle_name']) . '</option>';
    return $h . '</select></div><noscript><button class="btn">Apply</button></noscript></form>';
}
/** Builds WHERE for reports within range + optional house/cycle. Voided reports never count. */
function report_scope(array $R, int $houseId, int $cycleId): array {
    $w = "r.status = 'submitted' AND r.report_date BETWEEN ? AND ?"; $p = [$R['from'], $R['to']];
    if ($houseId) { $w .= ' AND c.house_id = ?'; $p[] = $houseId; } if ($cycleId) { $w .= ' AND r.cycle_id = ?'; $p[] = $cycleId; }
    return [$w, $p];
}
function series_by_house(array $R, array $rowsData, string $key): array {
    $labels = []; for ($d = $R['from']; $d <= $R['to']; $d = add_days($d, 1)) $labels[] = $d;
    $map = []; foreach ($rowsData as $s) $map[$s['hn']][$s['d']] = (float)$s[$key];
    $sets = []; foreach ($map as $hn => $m) $sets[] = ['label' => house_label($hn), 'data' => array_map(fn($d) => $m[$d] ?? null, $labels)];
    return ['labels' => array_map(fn($d) => date('d M', strtotime($d)), $labels), 'sets' => $sets];
}
