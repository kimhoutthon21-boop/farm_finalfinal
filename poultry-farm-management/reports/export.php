<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();
$f = ['q' => get('q'), 'from' => valid_date(get('from')) ? get('from') : '', 'to' => valid_date(get('to')) ? get('to') : '', 'house_id' => (int)get('house_id'), 'cycle_id' => (int)get('cycle_id'), 'worker_id' => (int)get('worker_id'), 'status' => get('status')];
$list = fetch_reports($f, 'r.report_date DESC, r.id DESC', 50000, 0); $out = [];
foreach ($list as $r) $out[] = [
    'Date' => $r['report_date'],
    'House' => 'House ' . $r['house_number'],
    'Cycle' => $r['cycle_name'],
    'Worker' => $r['worker_name'],
    'Feed (bags)' => $r['feed_bags'],
    'Deaths' => $r['deaths'],
    'Standard deaths' => $r['standard_deaths'] ?? '',
    'Cause' => $r['cause_name'] ?? '',
    'Cause details' => $r['death_cause_detail'] ?? '',
    'Work description' => $r['work_description'] ?? '',
    'Notes' => $r['notes'] ?? '',
    'Status' => $r['status'] === 'voided' ? 'Voided' : ($r['revision'] > 0 ? 'Corrected' : 'Submitted'),
];
audit_log('report.export', 'report', null, null, ['rows' => count($out)]);
excel_xml_download('daily-reports-' . today() . '-excel.xls', [[
    'name' => 'Daily Reports',
    'title' => 'Daily report history',
    'subtitle' => 'Exported ' . today() . ' · ' . count($out) . ' report(s), newest first',
    'headers' => ['Date', 'House', 'Cycle', 'Worker', 'Feed (bags)', 'Deaths', 'Cause', 'Cause details', 'Work description', 'Notes', 'Status'],
    'types' => ['Date' => 'Date', 'Feed (bags)' => 'Decimal', 'Deaths' => 'Number'],
    'widths' => ['Cycle' => 190, 'Worker' => 160, 'Cause details' => 220, 'Work description' => 280, 'Notes' => 280],
    'rows' => $out,
]]);
