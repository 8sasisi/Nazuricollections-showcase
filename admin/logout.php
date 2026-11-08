<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_method('POST');
verify_csrf();
$admin = isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? $_SESSION['admin'] : null;
$auditFailed = false;
if (isset($admin['id']) && filter_var($admin['id'], FILTER_VALIDATE_INT) !== false) {
    try {
        audit_log((int) $admin['id'], 'logout', 'user', (int) $admin['id']);
    } catch (Throwable $error) {
        app_log('Logout audit failed: ' . $error->getMessage());
        $auditFailed = true;
    }
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
if ($auditFailed) {
    http_response_code(503);
    exit('You have been signed out, but the activity could not be recorded. Notify a system administrator.');
}
header('Location: /admin/login.php');
exit;
