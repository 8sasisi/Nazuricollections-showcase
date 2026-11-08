<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin-auth.php';
start_app_session();
$notice = '';
$noticeType = 'error';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $token = trim((string) ($_POST['reset_token'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        $genericError = 'This reset request is invalid or expired. Request a fresh reset and try again.';
        if (strlen($token) < 40 || strlen($token) > 100 || !validate_admin_password($password)) {
            throw new RuntimeException($genericError);
        }
        $pdo = database();
        $pdo->beginTransaction();
        $lookup = $pdo->prepare(
            'SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP
             FOR UPDATE'
        );
        $lookup->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $lookup->fetch();
        if (!$reset) {
            throw new RuntimeException($genericError);
        }
        $update = $pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, auth_version = auth_version + 1
             WHERE id = :user_id AND is_active = 1'
        );
        $update->execute([
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'user_id' => $reset['user_id'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException($genericError);
        }
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND used_at IS NULL')
            ->execute(['user_id' => $reset['user_id']]);
        audit_log((int) $reset['user_id'], 'password.reset', 'user', (int) $reset['user_id']);
        $pdo->commit();
        $notice = 'Password updated. You can now sign in.';
        $noticeType = 'success';
    }
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $notice = $error->getMessage();
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('Password reset failed: ' . $error->getMessage());
    http_response_code(503);
    $notice = 'Password reset is temporarily unavailable.';
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset password | Usimamizi — Grant Fashion</title><link rel="stylesheet" href="/assets/css/admin.css"></head>
<body class="login-page"><main class="login-card"><a class="admin-brand" href="/admin/login.php">GRANT FASHION <span>Usimamizi</span></a>
<h1>Reset administrator password</h1>
<?php if ($notice !== ''): ?><p class="notice notice-<?= escape_html($noticeType) ?>" role="status"><?= escape_html($notice) ?></p><?php endif; ?>
<form method="post" action="/admin/reset-password.php"><?= csrf_field() ?>
<label for="reset_token">One-time reset code</label><input id="reset_token" name="reset_token" autocomplete="off" required maxlength="100">
<label for="new_password">New password (at least 14 characters)</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="14" maxlength="128" required>
<button class="button button-primary button-wide" type="submit">Reset password</button></form>
<p><a href="/admin/login.php">Return to sign in</a></p></main></body></html>
