<?php
// First-run wizard: (1) connect + create database, (2) create the first administrator.
// Locks itself once an administrator exists. No default password is ever created.
require __DIR__ . '/includes/bootstrap.php';

function run_sql_file(PDO $pdo, string $file): void {
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (preg_split('/;\s*[\r\n]+/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) continue;
        $pdo->exec($stmt);
    }
}
function setup_shell(string $title, string $body): void {
    echo '<!doctype html><html lang="' . e(current_language()) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e(t('Setup')) . '</title><link rel="stylesheet" href="' . e(url('assets/css/icons.css')) . '?v=1"><link rel="stylesheet" href="' . e(url('assets/css/app.css')) . '?v=34"></head><body class="auth"><div class="auth-card">' . language_switcher() . '<span class="brand-mark logo-mark"><img src="' . e(url('assets/images/farm-logo.png')) . '" alt=""></span><h1>' . e(t($title)) . '</h1>' . $body . '</div><script>window.__translations = ' . json_encode(translations(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script><script src="' . e(url('assets/js/i18n.js')) . '?v=1"></script><script src="' . e(url('assets/js/app.js')) . '"></script></body></html>';
    exit;
}
$configExists = $GLOBALS['PFM_ENV_CONFIG'] !== null || is_file($GLOBALS['PFM_CONFIG_FILE']);
$msg = '';

if (!$configExists) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!csrf_valid()) throw new UserError('Form expired. Please reload the page.');
            $db = ['host' => post('host', '127.0.0.1'), 'port' => (int)post('port', '3306'), 'name' => post('name', 'poultry_farm'), 'user' => post('user', 'root'), 'pass' => (string)($_POST['pass'] ?? ''), 'charset' => 'utf8mb4'];
            if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db['name'])) throw new UserError('Database name may only contain letters, numbers and underscores.');
            try { $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
            catch (PDOException $ex) { throw new UserError('Could not connect to MySQL. Is MySQL running in XAMPP, and are the username and password right?'); }
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $db['name'] . '`');
            run_sql_file($pdo, __DIR__ . '/database/schema.sql');
            run_sql_file($pdo, __DIR__ . '/database/seed.sql');
            $cfg = ['db' => $db, 'app' => ['timezone' => 'Asia/Phnom_Penh', 'base_url' => null, 'session_name' => 'pfm_session', 'debug' => false]];
            if (@file_put_contents($GLOBALS['PFM_CONFIG_FILE'], "<?php\nreturn " . var_export($cfg, true) . ";\n") === false) throw new UserError('Database was created, but config/config.php could not be written. Copy config/config.example.php to config/config.php manually.');
            redirect('setup.php');
        } catch (UserError $ex) { $msg = $ex->getMessage(); }
        catch (Throwable $ex) { error_log('[PFM setup] ' . $ex->getMessage()); $msg = 'Setup failed: ' . $ex->getMessage(); }
    }
    $f = fn($k, $d) => e($_POST[$k] ?? $d);
    setup_shell('Set up your farm system', '<p class="muted">Step 1 of 2 · Connect to MySQL. In XAMPP the defaults below normally work (user <b>root</b>, empty password).</p>' . ($msg ? '<div class="notice danger">' . e($msg) . '</div>' : '') .
        '<form method="post">' . csrf_field() . '<div class="field"><label>MySQL host</label><input name="host" value="' . $f('host', '127.0.0.1') . '" required></div><div class="form-grid"><div class="field"><label>Port</label><input name="port" value="' . $f('port', '3306') . '" required></div><div class="field"><label>Database name</label><input name="name" value="' . $f('name', 'poultry_farm') . '" required></div></div><div class="form-grid"><div class="field"><label>MySQL user</label><input name="user" value="' . $f('user', 'root') . '" required></div><div class="field"><label>MySQL password</label><input type="password" name="pass" autocomplete="off"></div></div><button class="btn btn-primary" style="width:100%">Create database &amp; continue</button></form>');
}

// Config exists: make sure tables exist, then create the first admin.
try { $hasUsers = (bool)val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"); }
catch (Throwable $ex) {
    error_log('[PFM setup] database connection failed: ' . $ex->getMessage());
    $where = $GLOBALS['PFM_ENV_CONFIG'] !== null
        ? 'The database connection failed. Check the DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME and DB_SSL_CA environment variables on your hosting dashboard.'
        : 'config/config.php exists but the database connection failed. Check the credentials in that file and that MySQL is running.';
    // PDO messages contain the host and user name but never the password.
    setup_shell('Database not reachable', '<div class="notice danger">' . e($where) . '</div><p class="muted" style="word-break:break-word">Reason: ' . e($ex->getMessage()) . '</p>');
}
if (!$hasUsers) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) { run_sql_file(db(), __DIR__ . '/database/schema.sql'); run_sql_file(db(), __DIR__ . '/database/seed.sql'); redirect('setup.php'); }
    setup_shell('Install database tables', '<p class="muted">The connection works but the tables are missing.</p><form method="post">' . csrf_field() . '<button class="btn btn-primary" style="width:100%">Install tables</button></form>');
}
if ((int)val("SELECT COUNT(*) FROM users WHERE role = 'admin'") > 0) { flash('info', 'Setup is already complete. Please log in.'); redirect('login.php'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_valid()) throw new UserError('Form expired. Please reload the page.');
        $name = post('full_name'); $user = strtolower(post('username')); $pw = (string)($_POST['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 120) throw new UserError('Please enter the administrator\'s full name.');
        if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $user)) throw new UserError('Username must be 3-50 letters, numbers, dots, dashes or underscores.');
        if ($pw !== (string)($_POST['password2'] ?? '')) throw new UserError('The two passwords do not match.');
        if ($p = password_problem($pw)) throw new UserError($p);
        q("INSERT INTO users (username, full_name, password_hash, role) VALUES (?,?,?, 'admin')", [$user, $name, password_hash($pw, PASSWORD_DEFAULT)]);
        $id = (int)db()->lastInsertId();
        $_SESSION['uid'] = $id; $_SESSION['last_seen'] = time(); session_regenerate_id(true);
        audit_log('setup.admin_created', 'user', $id, null, ['username' => $user]);
        flash('success', 'Setup complete. Welcome! Add your first production cycle to get started.');
        redirect('dashboard.php');
    } catch (UserError $ex) { $err = $ex->getMessage(); }
}
$f = fn($k) => e($_POST[$k] ?? '');
setup_shell('Create the administrator', '<p class="muted">Step 2 of 2 · Choose your own login. This page locks itself after the first administrator is created.</p>' . ($err ? '<div class="notice danger">' . e($err) . '</div>' : '') .
    '<form method="post">' . csrf_field() . '<div class="field"><label>Full name</label><input name="full_name" value="' . $f('full_name') . '" required></div><div class="field"><label>Username</label><input name="username" value="' . $f('username') . '" autocomplete="username" required></div><div class="field"><label>Password</label><input type="password" name="password" autocomplete="new-password" required><div class="hint">At least 8 characters, with letters and numbers.</div></div><div class="field"><label>Repeat password</label><input type="password" name="password2" autocomplete="new-password" required></div><button class="btn btn-primary" style="width:100%">Create administrator</button></form>');
