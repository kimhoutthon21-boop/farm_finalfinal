<?php
function current_user(): ?array {
    static $loaded = false, $user = null;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['uid'])) return null;
    $u = row('SELECT id, username, full_name, role, is_active, must_change_password FROM users WHERE id = ?', [(int)$_SESSION['uid']]);
    if (!$u || !(int)$u['is_active'] || (time() - (int)($_SESSION['last_seen'] ?? 0)) > 43200) {
        $_SESSION = [];
        return null;
    }
    $_SESSION['last_seen'] = time();
    return $user = $u;
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }

/** Returns [ok, message]. Throttles repeated failures per username and per IP. */
function attempt_login(string $username, string $password): array {
    $ip = client_ip();
    $uFail = (int)val("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)", [$username]);
    $iFail = (int)val("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)", [$ip]);
    if ($uFail >= 5 || $iFail >= 20) return [false, 'Too many failed attempts. Please wait 15 minutes and try again.'];
    $u = row('SELECT * FROM users WHERE username = ?', [$username]);
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsalt.invalidinvalidinvalidinvalidinva';
    $ok = password_verify($password, $hash) && $u && (int)$u['is_active'] === 1;
    q('INSERT INTO login_attempts (username, ip_address, success) VALUES (?,?,?)', [substr($username, 0, 50), $ip, $ok ? 1 : 0]);
    if (!$ok) return [false, 'Incorrect username or password.'];
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['last_seen'] = time();
    unset($_SESSION['csrf']);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
    q("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)");
    return [true, ''];
}
function do_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function require_login(): array {
    $u = current_user();
    if (!$u) {
        $next = ltrim(substr($_SERVER['REQUEST_URI'] ?? '', strlen(BASE_URL)), '/');
        redirect('login.php' . ($next && $_SERVER['REQUEST_METHOD'] === 'GET' ? '?next=' . urlencode($next) : ''));
    }
    if ((int)$u['must_change_password'] && basename($_SERVER['SCRIPT_NAME']) !== 'profile.php' && !str_contains($_SERVER['SCRIPT_NAME'], '/actions/') && basename($_SERVER['SCRIPT_NAME']) !== 'logout.php') {
        flash('info', 'Please choose a new password before continuing.');
        redirect('profile.php');
    }
    return $u;
}
function password_problem(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}
