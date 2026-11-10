<?php
// Loaded first by every page. Sets up config, session, database helpers and shared functions.
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$GLOBALS['PFM_CONFIG_FILE'] = APP_ROOT . '/config/config.php';

// Hosted deployments (e.g. Render) set DB_HOST and friends as environment variables
// instead of writing config/config.php, because the server's files are reset on every deploy.
function env_config(): ?array {
    $host = getenv('DB_HOST');
    if ($host === false || $host === '') return null;
    return [
        'db' => [
            'host'    => $host,
            'port'    => (int)(getenv('DB_PORT') ?: 3306),
            'name'    => getenv('DB_NAME') ?: 'poultry_farm',
            'user'    => getenv('DB_USER') ?: 'root',
            'pass'    => (string)getenv('DB_PASS'),
            'charset' => 'utf8mb4',
            // The database provider's CA certificate (public, not a secret). config/db-ca.pem in the repo wins,
            // because pasting a certificate into a dashboard text box often mangles it.
            'ssl_ca_file' => is_file(APP_ROOT . '/config/db-ca.pem') ? APP_ROOT . '/config/db-ca.pem' : null,
            'ssl_ca'  => getenv('DB_SSL_CA') ?: null,   // fallback: PEM text in an environment variable
        ],
        'app' => [
            'timezone'     => getenv('APP_TIMEZONE') ?: 'Asia/Phnom_Penh',
            'base_url'     => getenv('APP_BASE_URL') ?: null,
            'session_name' => 'pfm_session',
            'debug'        => getenv('APP_DEBUG') === 'true',
        ],
    ];
}
$GLOBALS['PFM_ENV_CONFIG'] = env_config();

function app_config(): array {
    static $cfg = null;
    if ($cfg === null && $GLOBALS['PFM_ENV_CONFIG'] !== null) $cfg = $GLOBALS['PFM_ENV_CONFIG'];
    if ($cfg === null) {
        if (!is_file($GLOBALS['PFM_CONFIG_FILE'])) {
            // First run: send the visitor to the setup wizard.
            if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
                $base = rtrim(str_replace('\\', '/', substr(APP_ROOT, strlen(rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/')))), '/');
                header('Location: ' . $base . '/setup.php');
                exit;
            }
            return $cfg = ['db' => [], 'app' => ['timezone' => 'Asia/Phnom_Penh', 'session_name' => 'pfm_session', 'debug' => false, 'base_url' => null]];
        }
        $cfg = require $GLOBALS['PFM_CONFIG_FILE'];
    }
    return $cfg;
}

$cfg = app_config();
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Phnom_Penh');

if (!empty($cfg['app']['base_url'])) {
    define('BASE_URL', rtrim($cfg['app']['base_url'], '/'));
} else {
    $root = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $app  = str_replace('\\', '/', APP_ROOT);
    define('BASE_URL', ($root !== '' && str_starts_with($app, $root)) ? rtrim(substr($app, strlen($root)), '/') : '');
}

if (!empty($cfg['app']['debug'])) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); error_reporting(E_ALL); ini_set('log_errors', '1'); }

set_exception_handler(function (Throwable $e) {
    error_log('[PFM] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $debug = !empty(app_config()['app']['debug']);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;padding:40px;max-width:560px;margin:auto">';
    echo '<h2>Something went wrong</h2><p>The page could not be loaded. Please try again, or ask the administrator to check the server log.</p>';
    if ($debug) echo '<pre style="white-space:pre-wrap;background:#f6f6f6;padding:12px">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</pre>';
    echo '</body>';
    exit;
});

// Secure session settings
// Render (and most hosts) terminate HTTPS at a proxy and forward plain HTTP, so also trust X-Forwarded-Proto.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name($cfg['app']['session_name'] ?? 'pfm_session');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/i18n.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/population.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/report_table.php';
require_once __DIR__ . '/filters.php';

$GLOBALS['OLD'] = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
