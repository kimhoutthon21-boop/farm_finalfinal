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
// Icons are Flaticon UIcons (regular rounded), served from a small subset font: assets/css/icons.css.
// Call sites keep the old short names; this maps them to UIcons glyphs.
function icon(string $name, int $size = 20): string {
    static $map = [
        'dashboard' => 'apps', 'house' => 'farm', 'layers' => 'layers', 'clipboard' => 'clipboard-check',
        'package' => 'wheat', 'activity' => 'heart-rate', 'wallet' => 'wallet', 'users' => 'users-alt', 'user' => 'user',
        'settings' => 'settings-sliders', 'search' => 'search', 'bell' => 'bell', 'logout' => 'sign-out-alt',
        'menu' => 'menu-burger', 'x' => 'cross', 'plus' => 'plus', 'minus' => 'minus', 'alert' => 'triangle-warning',
        'check' => 'check', 'file' => 'document', 'edit' => 'edit', 'download' => 'download', 'shield' => 'shield',
        'trend' => 'arrow-trend-up', 'chevron' => 'angle-small-right', 'down' => 'angle-small-down', 'sun' => 'sun',
        'moon' => 'moon', 'calendar' => 'calendar', 'egg' => 'egg', 'eye' => 'eye', 'undo' => 'undo', 'info' => 'info',
        'trash' => 'trash', 'archive' => 'archive', 'lock' => 'lock',
    ];
    $px = max(10, (int)round($size * 0.9)); // UIcons glyphs run slightly larger than the old 24px SVG grid
    return '<i class="fi fi-rr-' . ($map[$name] ?? 'info') . ' icon" style="font-size:' . $px . 'px;width:' . $size . 'px;height:' . $size . 'px" aria-hidden="true"></i>';
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
