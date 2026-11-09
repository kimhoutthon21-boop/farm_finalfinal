<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();
$id = (int)get('id');
$cycle = $id > 0 ? cycle_stat($id) : null;
if (!$cycle) {
    http_response_code(404);
    page_start('Not found', 'cycles');
    echo '<div class="card">' . empty_state('Cycle not found', '', 'layers', '<a class="btn" href="' . e(url('cycles/index.php')) . '">Back to cycles</a>') . '</div>';
    page_end();
    exit;
}
if ($cycle['status'] === 'active') {
    flash('error', 'Close the cycle before exporting its full history.');
    redirect('cycles/view.php?id=' . $id);
}

$cycleLabel = $cycle['cycle_name'] . ' · ' . house_label($cycle['house_number']);
$summary = [
    ['Cycle', $cycle['cycle_name']],
    ['House', house_label($cycle['house_number'])],
    ['Status', ucfirst($cycle['status'])],
    ['Placed on', $cycle['placement_date']],
    ['Closed on', $cycle['closed_date'] ?: '—'],
    ['Supplier', $cycle['supplier'] ?: '—'],
    ['Initial chickens', fmt_int($cycle['initial_population'])],
    ['Additional arrivals', fmt_int($cycle['arrivals'])],
    ['Other removals', fmt_int($cycle['removals'])],
    ['Total deaths', fmt_int($cycle['deaths'])],
    ['Living chickens', fmt_int($cycle['living'])],
    ['Mortality', fmt_pct($cycle['mortality_pct'])],
    ['Feed used (bags)', fmt_bags($cycle['feed_total'])],
    ['Number of reports', fmt_int($cycle['report_count'])],
    ['Expected duration (days)', fmt_int($cycle['expected_days'])],
    ['Cycle notes', $cycle['notes'] ?: '—'],
    ['Closing notes', $cycle['close_notes'] ?: '—'],
];

$reports = rows(
    "SELECT r.report_date, r.feed_bags, r.deaths, r.death_cause_detail, r.work_description, r.notes,
            r.status, r.revision, u.full_name AS worker, dc.name AS cause,
            (SELECT GROUP_CONCAT(wt.name ORDER BY wt.name SEPARATOR ', ')
             FROM daily_report_tasks drt
             JOIN work_task_categories wt ON wt.id = drt.task_id
             WHERE drt.report_id = r.id) AS tasks
     FROM daily_reports r
     JOIN users u ON u.id = r.worker_id
     LEFT JOIN death_causes dc ON dc.id = r.death_cause_id
     WHERE r.cycle_id = ?
     ORDER BY r.report_date, r.id",
    [$id]
);
$reportRows = [];
foreach ($reports as $report) {
    $reportRows[] = [
        'Date' => $report['report_date'],
        'Status' => ucfirst($report['status']) . ((int)$report['revision'] > 0 ? ' (corrected)' : ''),
        'Recorded by' => $report['worker'],
        'Feed (bags)' => $report['feed_bags'],
        'Deaths' => $report['deaths'],
        'Cause' => $report['cause'] ?? '',
        'Cause details' => $report['death_cause_detail'] ?? '',
        'Work tasks' => $report['tasks'] ?? '',
        'Work description' => $report['work_description'] ?? '',
        'Notes' => $report['notes'] ?? '',
    ];
}

$transactionSheets = [];
foreach (['expenses' => ['Expenses', 'Expense'], 'revenue' => ['Revenue', 'Revenue']] as $table => [$sheetName, $type]) {
    $transactions = rows(
        "SELECT t.txn_date, t.description, t.amount, t.payment_method, t.reference, t.notes,
                fc.name AS category, u.full_name AS worker
         FROM $table t
         JOIN finance_categories fc ON fc.id = t.category_id
         JOIN users u ON u.id = t.created_by
         WHERE t.cycle_id = ? ORDER BY t.txn_date, t.id",
        [$id]
    );
    $transactionRows = [];
    foreach ($transactions as $transaction) {
        $transactionRows[] = [
            'Date' => $transaction['txn_date'],
            'Category' => $transaction['category'],
            'Description' => $transaction['description'],
            'Amount' => $transaction['amount'],
            'Currency' => setting('currency_code', 'KHR'),
            'Payment method' => $transaction['payment_method'] ?? '',
            'Reference' => $transaction['reference'] ?? '',
            'Notes' => $transaction['notes'] ?? '',
            'Recorded by' => $transaction['worker'],
        ];
    }
    $transactionSheets[] = [
        'name' => $sheetName,
        'title' => $sheetName . ' · ' . $cycleLabel,
        'subtitle' => 'Linked ' . strtolower($type) . ' records for this cycle. Amounts are shown in the currency column.',
        'headers' => ['Date', 'Category', 'Description', 'Amount', 'Currency', 'Payment method', 'Reference', 'Notes', 'Recorded by'],
        'types' => ['Date' => 'Date', 'Amount' => 'Currency'],
        'widths' => ['Description' => 260, 'Notes' => 260, 'Category' => 150, 'Recorded by' => 160, 'Payment method' => 140],
        'rows' => $transactionRows,
    ];
}

$sheets = [
    [
        'name' => 'Cycle Summary',
        'title' => 'Flock cycle summary',
        'subtitle' => $cycleLabel . ' · Key figures and cycle information',
        'headers' => ['Item', 'Details'],
        'widths' => ['Item' => 210, 'Details' => 420],
        'rows' => array_map(static fn($item) => ['Item' => $item[0], 'Details' => $item[1]], $summary),
    ],
    [
        'name' => 'Daily Reports',
        'title' => 'Daily reports',
        'subtitle' => $cycleLabel . ' · One row per report, sorted by date',
        'headers' => ['Date', 'Status', 'Recorded by', 'Feed (bags)', 'Deaths', 'Cause', 'Cause details', 'Work tasks', 'Work description', 'Notes'],
        'types' => ['Date' => 'Date', 'Feed (bags)' => 'Decimal', 'Deaths' => 'Number'],
        'widths' => ['Recorded by' => 160, 'Cause details' => 220, 'Work tasks' => 200, 'Work description' => 280, 'Notes' => 280],
        'rows' => $reportRows,
    ],
    ...$transactionSheets,
];

$recordCount = count($reports);
foreach ($transactionSheets as $sheet) $recordCount += count($sheet['rows']);
audit_log('cycle.export', 'cycle', $id, null, ['rows' => $recordCount]);
excel_xml_download('cycle-' . $id . '-excel.xls', $sheets);
