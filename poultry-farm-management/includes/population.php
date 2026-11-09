<?php
// Every population figure in the system is read from v_cycle_stats (see schema.sql).
// Nothing stores "current population" as an editable number, so it cannot drift.
function cycle_stats(string $where = '1=1', array $params = [], string $order = 'house_number ASC, placement_date DESC'): array {
    return array_map('cast_cycle', rows("SELECT * FROM v_cycle_stats WHERE $where ORDER BY $order", $params));
}
function cast_cycle(array $c): array {
    if ($c['status'] === 'archived') $c['status'] = 'closed';
    foreach (['initial_population', 'arrivals', 'removals', 'deaths', 'living', 'report_count', 'expected_days'] as $k) $c[$k] = (int)$c[$k];
    $c['feed_total'] = (float)$c['feed_total'];
    $c['mortality_pct'] = pct_of($c['deaths'], $c['initial_population']);
    $end = ($c['status'] !== 'active' && $c['closed_date']) ? $c['closed_date'] : today();
    $c['age_days'] = max(1, days_between($c['placement_date'], $end) + 1);   // placement day = day 1
    $c['progress_pct'] = pct_of($c['age_days'], $c['expected_days']);
    return $c;
}
function cycle_stat(int $id): ?array { $r = cycle_stats('id = ?', [$id]); return $r[0] ?? null; }
function active_cycle_for_house(int $houseId): ?array { $r = cycle_stats("house_id = ? AND status = 'active'", [$houseId]); return $r[0] ?? null; }
function active_cycles(): array { return cycle_stats("status = 'active'"); }

/** Alerts used by the dashboard and the notification bell. All thresholds come from settings. */
function farm_alerts(): array {
    $out = []; $t = today(); $y = add_days($t, -1);
    $dailyWarn = (float)setting('daily_mortality_warn_pct', 0.2);
    $cycleWarn = (float)setting('cycle_mortality_warn_pct', 5);
    $hour = (int)setting('report_reminder_hour', 17);
    $cycles = active_cycles();
    if (!$cycles) return [['level' => 'info', 'title' => 'No active production cycles', 'text' => 'Start a cycle to begin recording daily reports.', 'url' => is_admin() ? 'cycles/index.php?new=1' : 'houses/index.php']];
    foreach ($cycles as $c) {
        $n0 = count($out);
        $h = house_label($c['house_number']);
        $dates = rows("SELECT report_date, deaths FROM daily_reports WHERE cycle_id = ? AND status = 'submitted' AND report_date >= ?", [$c['id'], add_days($t, -3)]);
        $have = array_column($dates, 'deaths', 'report_date');
        if ($c['placement_date'] <= $y && !isset($have[$y])) $out[] = ['level' => 'warn', 'title' => "$h: missing report for " . fmt_date($y), 'text' => 'No daily report was recorded yesterday.', 'url' => 'reports/create.php?house=' . $c['house_id'] . '&date=' . $y];
        if ($c['placement_date'] <= $t && !isset($have[$t]) && (int)date('G') >= $hour) $out[] = ['level' => 'info', 'title' => "$h: today's report is not in yet", 'text' => 'Reminder after ' . sprintf('%02d:00', $hour) . '.', 'url' => 'reports/create.php?house=' . $c['house_id']];
        foreach ($have as $d => $deaths) {
            $p = pct_of($deaths, $c['initial_population']);
            if ($dailyWarn > 0 && $p >= $dailyWarn) $out[] = ['level' => $p >= 2 * $dailyWarn ? 'danger' : 'warn', 'title' => "$h: high deaths on " . fmt_date($d), 'text' => fmt_int($deaths) . ' deaths = ' . fmt_pct($p) . ' of placed (limit ' . fmt_pct($dailyWarn) . ').', 'url' => 'cycles/view.php?id=' . $c['id']];
        }
        if ($cycleWarn > 0 && $c['mortality_pct'] >= $cycleWarn) $out[] = ['level' => 'danger', 'title' => "$h: cycle mortality above limit", 'text' => fmt_pct($c['mortality_pct']) . ' (' . fmt_int($c['deaths']) . ' / ' . fmt_int($c['initial_population']) . ') vs limit ' . fmt_pct($cycleWarn) . '.', 'url' => 'cycles/view.php?id=' . $c['id']];
        elseif ($cycleWarn > 0 && $c['mortality_pct'] >= 0.8 * $cycleWarn) $out[] = ['level' => 'warn', 'title' => "$h: cycle mortality nearing limit", 'text' => fmt_pct($c['mortality_pct']) . ' of ' . fmt_pct($cycleWarn) . ' limit.', 'url' => 'cycles/view.php?id=' . $c['id']];
        if ($c['age_days'] > $c['expected_days']) $out[] = ['level' => 'info', 'title' => "$h: past expected cycle length", 'text' => 'Day ' . $c['age_days'] . ' of ' . $c['expected_days'] . '. Close the cycle when the flock is sold.', 'url' => 'cycles/view.php?id=' . $c['id']];
        for ($i = $n0; $i < count($out); $i++) $out[$i]['house_id'] = $c['house_id'];
    }
    $order = ['danger' => 0, 'warn' => 1, 'info' => 2];
    usort($out, fn($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
    return $out;
}
