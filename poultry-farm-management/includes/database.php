<?php
// PDO connection (prepared statements only) plus tiny query helpers.

// Hosting dashboards often flatten a pasted certificate onto one line (newlines become spaces or "\n").
// Rebuild a valid PEM: take the base64 body between BEGIN/END, strip all whitespace, rewrap at 64 chars.
function normalize_pem(string $raw): string {
    $raw = str_replace(['\r', '\n'], ' ', $raw);
    if (!preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $raw, $m)) return $raw;
    $body = preg_replace('/[^A-Za-z0-9+\/=]/', '', $m[1]);
    return "-----BEGIN CERTIFICATE-----\n" . chunk_split($body, 64, "\n") . "-----END CERTIFICATE-----\n";
}

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = app_config()['db'];
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    // Cloud MySQL (e.g. Aiven) requires TLS: the CA certificate is passed as PEM text and written to a temp file.
    $caFile = $c['ssl_ca_file'] ?? null;
    if (!$caFile && !empty($c['ssl_ca'])) {
        $pem = normalize_pem((string)$c['ssl_ca']);
        $caFile = sys_get_temp_dir() . '/pfm-db-ca-' . md5($pem) . '.pem';
        if (!is_file($caFile)) file_put_contents($caFile, $pem);
    }
    if ($caFile) {
        $opts[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    // PDO only says "Cannot connect to MySQL using SSL"; the real reason (e.g. "certificate verify failed")
    // arrives as a PHP warning, so collect warnings during connect and add them to the error.
    $warnings = [];
    set_error_handler(function (int $no, string $msg) use (&$warnings) { $warnings[] = $msg; return true; });
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage() . ($warnings ? ' | ' . implode(' | ', $warnings) : ''), 0, $e);
    } finally {
        restore_error_handler();
    }
    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "'");
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement { $st = db()->prepare($sql); $st->execute($params); return $st; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function in_placeholders(array $items): string { return implode(',', array_fill(0, count($items), '?')); }
