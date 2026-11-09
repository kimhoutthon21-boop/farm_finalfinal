<?php
function fin_table(string $type): string { return $type === 'revenue' ? 'revenue' : 'expenses'; }
function fin_scope(string $alias, array $R, int $cycleId, int $houseId, int $catId, string $qs): array {
    $w = ["$alias.txn_date BETWEEN ? AND ?"]; $p = [$R['from'], $R['to']];
    if ($cycleId === -1) $w[] = "$alias.cycle_id IS NULL"; elseif ($cycleId) { $w[] = "$alias.cycle_id = ?"; $p[] = $cycleId; }
    if ($houseId) { $w[] = "($alias.house_id = ? OR $alias.cycle_id IN (SELECT id FROM production_cycles WHERE house_id = ?))"; array_push($p, $houseId, $houseId); }
    if ($catId) { $w[] = "$alias.category_id = ?"; $p[] = $catId; }
    if ($qs !== '') { $l = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qs) . '%'; $w[] = "($alias.description LIKE ? OR $alias.reference LIKE ? OR $alias.notes LIKE ?)"; array_push($p, $l, $l, $l); }
    return [implode(' AND ', $w), $p];
}
