<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!csrf_valid()) {
    http_response_code(403);
    exit(e(t('Your session expired. Please try again.')));
}

$language = post('language');
if (!in_array($language, ['en', 'km'], true)) {
    http_response_code(400);
    exit(e(t('Invalid language selection.')));
}

$_SESSION['pfm_language'] = $language;
$cookiePath = BASE_URL !== '' ? BASE_URL : '/';
setcookie('pfm_language', $language, [
    'expires' => time() + 31536000,
    'path' => $cookiePath,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
redirect(back_target('dashboard.php'));
