<?php
// PDO connection (prepared statements only) plus tiny query helpers.
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = app_config()['db'];
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "'");
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement { $st = db()->prepare($sql); $st->execute($params); return $st; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function in_placeholders(array $items): string { return implode(',', array_fill(0, count($items), '?')); }
