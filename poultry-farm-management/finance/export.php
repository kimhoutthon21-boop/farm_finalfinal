<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/finance_lib.php';
$user = require_admin();
$R = resolve_range('all'); $cp = get('cycle_id'); $cycleId = $cp === 'none' ? -1 : (int)$cp;
$tbl = get('type') === 'revenue' ? 'revenue' : 'expenses';
[$W, $P] = fin_scope('e', $R, $cycleId, (int)get('house_id'), (int)get('category_id'), get('q'));
$list = rows("SELECT e.*, fc.name cat, c.cycle_name, h.house_number FROM $tbl e JOIN finance_categories fc ON fc.id = e.category_id LEFT JOIN production_cycles c ON c.id = e.cycle_id LEFT JOIN houses h ON h.id = COALESCE(e.house_id, c.house_id) WHERE $W ORDER BY e.txn_date DESC, e.id DESC LIMIT 50000", $P);
$out = array_map(static fn($x) => [
    'Date' => $x['txn_date'],
    'Category' => $x['cat'],
    'Description' => $x['description'],
    'Amount' => $x['amount'],
    'Currency' => setting('currency_code', 'KHR'),
    'Cycle' => $x['cycle_name'] ?? 'Unallocated',
    'House' => $x['house_number'] ? 'House ' . $x['house_number'] : '',
    'Payment method' => $x['payment_method'] ?? '',
    'Reference' => $x['reference'] ?? '',
    'Notes' => $x['notes'] ?? '',
], $list);
audit_log("finance.export_$tbl", 'finance', null, null, ['rows' => count($out)]);
$title = $tbl === 'revenue' ? 'Revenue' : 'Expenses';
excel_xml_download($tbl . '-' . today() . '-excel.xls', [[
    'name' => $title,
    'title' => $title . ' records',
    'subtitle' => 'Exported ' . today() . ' · ' . count($out) . ' record(s), newest first',
    'headers' => ['Date', 'Category', 'Description', 'Amount', 'Currency', 'Cycle', 'House', 'Payment method', 'Reference', 'Notes'],
    'types' => ['Date' => 'Date', 'Amount' => 'Currency'],
    'widths' => ['Category' => 150, 'Description' => 280, 'Cycle' => 200, 'Payment method' => 150, 'Reference' => 150, 'Notes' => 280],
    'rows' => $out,
]]);
