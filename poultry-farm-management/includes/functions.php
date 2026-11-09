<?php
class UserError extends Exception {}

// Fallbacks so the app still runs if PHP's mbstring extension is switched off (it is on by default in XAMPP).
if (!function_exists('mb_strlen')) { function mb_strlen($s, $e = null) { return preg_match_all('/./su', (string)$s); } }
if (!function_exists('mb_substr')) { function mb_substr($s, $start, $len = null, $e = null) { preg_match_all('/./su', (string)$s, $m); return implode('', array_slice($m[0], $start, $len)); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper($s, $e = null) { return strtoupper((string)$s); } }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void {
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}
/** Only allows local relative paths, so a form can never redirect users to another site. */
function back_target(string $fallback): string {
    $r = (string)($_POST['return'] ?? $_GET['return'] ?? '');
    if ($r !== '' && preg_match('#^[A-Za-z0-9_\-/\.]+(\?[A-Za-z0-9_\-=&%\.\[\]+]*)?$#', $r) && !str_contains($r, '..') && !str_contains($r, '//')) return $r;
    return $fallback;
}
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => t($msg)]; }
function pull_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function remember_input(array $d): void { unset($d['csrf_token'], $d['password']); $_SESSION['old'] = $d; }
function old(string $k, $default = '') { $v = $GLOBALS['OLD'][$k] ?? $default; return is_array($v) ? $v : (string)$v; }
function open_modal_next(string $id): void { $_SESSION['open_modal'] = $id; }

// ----- request helpers -----
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_array($v) ? $d : trim((string)$v); }
function get(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_array($v) ? $d : trim((string)$v); }
function int_list($v): array { return is_array($v) ? array_values(array_unique(array_filter(array_map('intval', $v), fn($x) => $x > 0))) : []; }
function valid_date(string $s): bool { $d = DateTime::createFromFormat('Y-m-d', $s); return $d && $d->format('Y-m-d') === $s; }
function today(): string { return date('Y-m-d'); }
function add_days(string $d, int $n): string { return date('Y-m-d', strtotime($d . ' ' . ($n >= 0 ? '+' : '') . $n . ' days')); }
function days_between(string $a, string $b): int { return (int)round((strtotime($b) - strtotime($a)) / 86400); }

// ----- settings -----
function settings(): array {
    static $s = null;
    if ($s === null) { $s = []; foreach (rows('SELECT setting_key, setting_value FROM farm_settings') as $r) $s[$r['setting_key']] = $r['setting_value']; }
    return $s;
}
function setting(string $k, $d = null) { $s = settings(); return (isset($s[$k]) && $s[$k] !== '') ? $s[$k] : $d; }
function save_setting(string $k, string $v): void {
    q('INSERT INTO farm_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$k, $v]);
}

// ----- formatting -----
function fmt_int($n): string { return number_format((float)$n, 0, '.', ','); }
function fmt_bags($n): string { $s = number_format((float)$n, 2, '.', ','); return rtrim(rtrim($s, '0'), '.'); }
function fmt_pct($n, int $d = 2): string { return number_format((float)$n, $d, '.', '') . '%'; }
function fmt_money($n): string {
    $dec = (int)setting('currency_decimals', 0);
    return (setting('currency_symbol', '៛')) . number_format((float)$n, $dec, '.', ',');
}
function fmt_date(?string $d): string { return $d ? date('d ', strtotime($d)) . t(date('M', strtotime($d))) . date(' Y', strtotime($d)) : '—'; }
function fmt_datetime(?string $d): string { return $d ? date('d ', strtotime($d)) . t(date('M', strtotime($d))) . date(' Y, H:i', strtotime($d)) : '—'; }
function house_label($n): string { return t('House') . ' ' . (int)$n; }
function pct_of($part, $whole): float { return ((float)$whole) > 0 ? ((float)$part / (float)$whole) * 100 : 0.0; }
function bags_label(): string { return 'bags'; }
// ----- UI helpers -----
function badge(string $text, string $type = 'muted'): string { return '<span class="badge badge-' . e($type) . '">' . e(t($text)) . '</span>'; }
function house_status_badge(string $s): string {
    $m = ['active' => 'success', 'empty' => 'muted', 'cleaning' => 'info', 'maintenance' => 'warn'];
    return badge(ucfirst($s), $m[$s] ?? 'muted');
}
function cycle_status_badge(string $s): string {
    $m = ['active' => 'success', 'closed' => 'danger'];
    return badge(ucfirst($s), $m[$s] ?? 'muted');
}
function farm_metric_card(string $symbol, string $label, string $value, string $tone, string $aside, string $detail = ''): string {
    return '<div class="farm-metric-card ' . e($tone) . '"><div class="metric-main"><div class="metric-background" aria-hidden="true"><span></span><span></span><span></span></div>'
        . '<div class="metric-primary"><div class="metric-label"><span class="metric-icon">' . icon($symbol, 20) . '</span><span>' . e($label) . '</span></div>'
        . '<div class="metric-value">' . e($value) . '</div></div><div class="metric-aside"><span class="metric-aside-label">' . e($aside) . '</span>'
        . ($detail !== '' ? '<span class="metric-indicator">' . $detail . '</span>' : '') . '</div></div></div>';
}
function report_status_badge(array $r): string {
    if ($r['status'] === 'voided') return badge('Voided', 'danger');
    if ((int)$r['revision'] > 0) return badge('Corrected', 'warn');
    return badge('Submitted', 'success');
}
function icon(string $name, int $size = 20): string {
    static $p = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'house' => '<path d="M3 21V8l9-5 9 5v13"/><path d="M3 21h18"/><path d="M9 21v-7h6v7"/>',
        'layers' => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 12 10 5 10-5"/><path d="m2 17 10 5 10-5"/>',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
        'package' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'wallet' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'settings' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'menu' => '<path d="M4 12h16M4 6h16M4 18h16"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'plus' => '<path d="M5 12h14M12 5v14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8M16 13H8M16 17H8"/>',
        'edit' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'trend' => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
        'moon' => '<path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'egg' => '<path d="M12 2C8 2 4 8 4 14a8 8 0 0 0 16 0c0-6-4-12-8-12Z"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'undo' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'archive' => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['info']) . '</svg>';
}

function audit_log(string $action, string $entityType, ?int $entityId, $old = null, $new = null, ?string $reason = null): void {
    q('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, reason, ip_address) VALUES (?,?,?,?,?,?,?,?)', [
        current_user()['id'] ?? null, $action, $entityType, $entityId,
        $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
        $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
        $reason, client_ip(),
    ]);
}

function paginate(int $total, int $perPage = 15): array {
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)get('page', '1')), $pages);
    return ['page' => $page, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'pages' => $pages, 'total' => $total];
}
function pagination_html(array $p): string {
    if ($p['pages'] <= 1) return '<div class="pager"><span class="muted">' . fmt_int($p['total']) . ' record' . ($p['total'] == 1 ? '' : 's') . '</span></div>';
    $qs = $_GET; $link = function (int $n) use ($qs) { $qs['page'] = $n; return e('?' . http_build_query($qs)); };
    $h = '<div class="pager"><span class="muted">' . fmt_int($p['total']) . ' records · page ' . $p['page'] . ' of ' . $p['pages'] . '</span><div class="pager-links">';
    if ($p['page'] > 1) $h .= '<a class="btn btn-ghost btn-sm" href="' . $link($p['page'] - 1) . '">Previous</a>';
    $start = max(1, $p['page'] - 2); $end = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) $h .= '<a class="btn btn-sm ' . ($i === $p['page'] ? 'btn-primary' : 'btn-ghost') . '" href="' . $link($i) . '">' . $i . '</a>';
    if ($p['page'] < $p['pages']) $h .= '<a class="btn btn-ghost btn-sm" href="' . $link($p['page'] + 1) . '">Next</a>';
    return $h . '</div></div>';
}
/** Sortable header cell. $cols is a whitelist map key => SQL expression. */
function sort_state(array $cols, string $defKey, string $defDir = 'desc'): array {
    $k = get('sort', $defKey); if (!isset($cols[$k])) $k = $defKey;
    $d = strtolower(get('dir', $defDir)) === 'asc' ? 'asc' : 'desc';
    return ['key' => $k, 'dir' => $d, 'sql' => $cols[$k] . ' ' . strtoupper($d)];
}
function th_sort(string $label, string $key, array $st, string $class = ''): string {
    $qs = $_GET; $qs['sort'] = $key; $qs['dir'] = ($st['key'] === $key && $st['dir'] === 'asc') ? 'desc' : 'asc'; unset($qs['page']);
    $arrow = $st['key'] === $key ? ($st['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<th' . ($class !== '' ? ' class="' . e($class) . '"' : '') . '><a class="sort" href="' . e('?' . http_build_query($qs)) . '">' . e($label) . $arrow . '</a></th>';
}
function empty_state(string $title, string $text = '', string $iconName = 'info', string $actionHtml = ''): string {
    return '<div class="empty"><div class="empty-icon">' . icon($iconName, 26) . '</div><h3>' . e(t($title)) . '</h3>' . ($text ? '<p>' . e(t($text)) . '</p>' : '') . $actionHtml . '</div>';
}
function progress_bar(float $pct, string $cls = ''): string {
    $p = max(0, min(100, $pct));
    return '<div class="progress ' . e($cls) . '"><span style="width:' . round($p, 1) . '%"></span></div>';
}
function csv_download(string $filename, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $header);
    foreach ($rows as $r) fputcsv($o, $r);
    fclose($o);
    exit;
}
function excel_xml_download(string $filename, array $sheets): void {
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<?mso-application progid="Excel.Sheet"?>'
        . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
        . '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>Poultry Farm Management</Author></DocumentProperties>'
        . '<Styles>'
        . '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#24332A"/></Style>'
        . '<Style ss:ID="Title"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="3" ss:Color="#A3C56A"/></Borders><Font ss:FontName="Calibri" ss:Size="18" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#174A32" ss:Pattern="Solid"/></Style>'
        . '<Style ss:ID="Subtitle"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#52645A"/><Interior ss:Color="#EAF2EC" ss:Pattern="Solid"/></Style>'
        . '<Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#A3C56A"/></Borders><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#287548" ss:Pattern="Solid"/></Style>';
    foreach (['Text' => null, 'Number' => '#,##0.##', 'Decimal' => '#,##0.00', 'Date' => 'yyyy-mm-dd', 'Percent' => '0.00%', 'Currency' => '#,##0.00'] as $kind => $format) {
        foreach (['', 'Alt'] as $alt) {
            $alignment = $kind === 'Date' ? ' ss:Horizontal="Center"' : (in_array($kind, ['Number', 'Decimal', 'Percent', 'Currency'], true) ? ' ss:Horizontal="Right"' : '');
            $xml .= '<Style ss:ID="' . $kind . $alt . '"><Alignment' . $alignment . ' ss:Vertical="Top" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E1E9E3"/></Borders>';
            if ($format !== null) $xml .= '<NumberFormat ss:Format="' . $escape($format) . '"/>';
            if ($alt !== '') $xml .= '<Interior ss:Color="#F2F7F3" ss:Pattern="Solid"/>';
            $xml .= '</Style>';
        }
    }
    $xml .= '</Styles>';
    foreach ($sheets as $sheet) {
        $headers = array_values($sheet['headers']);
        $columnCount = count($headers);
        if ($columnCount < 1) continue;
        $dataRows = $sheet['rows'] ?? [];
        $columnName = preg_replace('/[\\\\\/\?\*\[\]:]/', '', (string)$sheet['name']);
        $xml .= '<Worksheet ss:Name="' . $escape(substr($columnName, 0, 31)) . '"><Table ss:ExpandedColumnCount="' . $columnCount . '" ss:ExpandedRowCount="' . (count($dataRows) + 3) . '">';
        foreach ($headers as $header) {
            $width = (int)($sheet['widths'][$header] ?? min(240, max(80, strlen((string)$header) * 8 + 24)));
            $xml .= '<Column ss:Width="' . max(45, min(360, $width)) . '"/>';
        }
        $xml .= '<Row ss:Height="32" ss:StyleID="Title"><Cell ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">' . $escape($sheet['title']) . '</Data></Cell></Row>';
        $xml .= '<Row ss:Height="28" ss:StyleID="Subtitle"><Cell ss:MergeAcross="' . ($columnCount - 1) . '"><Data ss:Type="String">' . $escape($sheet['subtitle'] ?? '') . '</Data></Cell></Row>';
        $xml .= '<Row ss:Height="30">';
        foreach ($headers as $header) $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $escape($header) . '</Data></Cell>';
        $xml .= '</Row>';
        foreach ($dataRows as $rowIndex => $row) {
            $xml .= '<Row ss:AutoFitHeight="1">';
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $kind = $sheet['types'][$header] ?? 'Text';
                $kind = in_array($kind, ['Text', 'Number', 'Decimal', 'Date', 'Percent', 'Currency'], true) ? $kind : 'Text';
                $alternate = $rowIndex % 2 ? 'Alt' : '';
                if ($value === null || $value === '') {
                    $xml .= '<Cell ss:StyleID="' . $kind . $alternate . '"><Data ss:Type="String"></Data></Cell>';
                } elseif ($kind === 'Date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
                    $xml .= '<Cell ss:StyleID="Date' . $alternate . '"><Data ss:Type="DateTime">' . $escape($value . 'T00:00:00.000') . '</Data></Cell>';
                } elseif (in_array($kind, ['Number', 'Decimal', 'Percent', 'Currency'], true) && is_numeric($value)) {
                    $number = $kind === 'Percent' ? (float)$value / 100 : $value;
                    $xml .= '<Cell ss:StyleID="' . $kind . $alternate . '"><Data ss:Type="Number">' . $escape($number) . '</Data></Cell>';
                } else {
                    $xml .= '<Cell ss:StyleID="Text' . $alternate . '"><Data ss:Type="String">' . $escape($value) . '</Data></Cell>';
                }
            }
            $xml .= '</Row>';
        }
        $lastRow = count($dataRows) + 3;
        $xml .= '</Table><AutoFilter x:Range="R3C1:R' . $lastRow . 'C' . $columnCount . '" xmlns="urn:schemas-microsoft-com:office:excel"/>'
            . '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><PageSetup><Layout x:Orientation="Landscape" x:CenterHorizontal="1"/><PageMargins x:Bottom="0.5" x:Left="0.3" x:Right="0.3" x:Top="0.5"/></PageSetup><FitToPage/><Print><FitWidth>1</FitWidth><FitHeight>0</FitHeight></Print><FreezePanes/><FrozenNoSplit/><SplitHorizontal>3</SplitHorizontal><TopRowBottomPane>3</TopRowBottomPane><ActivePane>2</ActivePane><Panes><Pane><Number>2</Number><ActiveRow>3</ActiveRow><ActiveCol>0</ActiveCol></Pane></Panes></WorksheetOptions></Worksheet>';
    }
    $xml .= '</Workbook>';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    echo $xml;
    exit;
}
/** Date-range filter shared by dashboard, feed, mortality and finance. */
function resolve_range(string $default = '30d'): array {
    $range = get('range', $default); $t = today();
    $activeStart = val("SELECT MIN(placement_date) FROM production_cycles WHERE status = 'active'");
    switch ($range) {
        case 'today': $from = $to = $t; break;
        case '7d': $from = add_days($t, -6); $to = $t; break;
        case 'cycle': $from = $activeStart ?: add_days($t, -29); $to = $t; break;
        case 'all': $from = val('SELECT MIN(placement_date) FROM production_cycles') ?: add_days($t, -29); $to = $t; break;
        case 'custom':
            $from = valid_date(get('from')) ? get('from') : add_days($t, -29);
            $to = valid_date(get('to')) ? get('to') : $t;
            if ($from > $to) [$from, $to] = [$to, $from];
            if (days_between($from, $to) > 800) $from = add_days($to, -800);
            break;
        default: $range = '30d'; $from = add_days($t, -29); $to = $t;
    }
    return ['range' => $range, 'from' => $from, 'to' => $to, 'days' => days_between($from, $to) + 1, 'cycle_missing' => ($range === 'cycle' && !$activeStart)];
}
function range_filter_html(array $r, array $extraHidden = [], ?array $presets = null): string {
    $presets = $presets ?? ['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'cycle' => 'This cycle', 'custom' => 'Custom'];
    $h = '<form method="get" class="range-bar" data-range-form>';
    foreach ($extraHidden as $k => $v) if ($v !== '' && $v !== null) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    $h .= '<div class="timeframe-control" role="group" aria-label="Timeframe" style="--timeframe-count:' . count($presets) . '"><span class="timeframe-thumb" aria-hidden="true"></span>';
    foreach ($presets as $k => $l) {
        $active = $r['range'] === $k;
        $h .= '<button type="submit" name="range" value="' . e($k) . '" class="timeframe-option' . ($active ? ' is-selected' : '') . '" aria-pressed="' . ($active ? 'true' : 'false') . '" data-range-option' . ($k === 'custom' ? ' data-custom-toggle' : '') . '>' . e($l) . '</button>';
    }
    $h .= '</div><div class="custom-dates ' . ($r['range'] === 'custom' ? 'show' : '') . '"><input type="date" name="from" value="' . e($r['from']) . '" aria-label="From"><span>to</span><input type="date" name="to" value="' . e($r['to']) . '" max="' . e(today()) . '" aria-label="To"><button class="btn btn-primary btn-sm" name="range" value="custom">Apply</button></div></form>';
    return $h;
}
