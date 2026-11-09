<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_valid(): bool {
    $t = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}
/** Call at the top of every state-changing request handler. */
function require_post_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Method not allowed'); }
    if (!csrf_valid()) {
        flash('error', 'Your session expired or the form was invalid. Please try again.');
        redirect(back_target('dashboard.php'));
    }
}
