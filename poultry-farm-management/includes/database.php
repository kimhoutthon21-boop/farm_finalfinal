<?php
// PDO connection (prepared statements only) plus tiny query helpers.
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
    if (!empty($c['ssl_ca'])) {
        $caFile = sys_get_temp_dir() . '/pfm-db-ca-' . md5($c['ssl_ca']) . '.pem';
        if (!is_file($caFile)) file_put_contents($caFile, str_replace('\n', "\n", $c['ssl_ca']));
        $opts[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "'");
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement { $st = db()->prepare($sql); $st->execute($params); return $st; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function in_placeholders(array $items): string { return implode(',', array_fill(0, count($items), '?')); }
