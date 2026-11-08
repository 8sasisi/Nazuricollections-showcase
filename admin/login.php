<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin-auth.php';
start_app_session();

if (current_admin()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$errorMessage = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254;
        $throttled = login_is_throttled($validEmail ? $email : 'invalid', $ip);
        $user = $validEmail && !$throttled ? find_admin_by_email($email) : null;
        $passwordForCheck = strlen($password) <= 128 ? $password : '';
        $validPassword = password_verify(
            $passwordForCheck,
            $user['password_hash'] ?? '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$ZUeOON/+8ZYzO6F8L2ppxbY+8NdD8o/H7AJoPSQyBA4'
        );
        $valid = $user !== null && (bool) $user['is_active'] && $validPassword;

        if ($valid && (bool) $user['totp_enabled']) {
            $verifiedCode = false;
            if ($user['totp_secret_encrypted'] !== null) {
                $verifiedCode = verify_totp(totp_secret_decrypt($user['totp_secret_encrypted']), $code);
            }
            if (!$verifiedCode) {
                $verifiedCode = verify_recovery_code((int) $user['id'], $code);
            }
            $valid = $verifiedCode;
        }

        record_login_attempt($validEmail ? $email : 'invalid', $ip, $valid);
        if ($valid && $user) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
                $rehash = database()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $rehash->execute([
                    'hash' => password_hash($password, PASSWORD_ARGON2ID),
                    'id' => $user['id'],
                ]);
            }
            audit_log((int) $user['id'], 'login.success', 'user', (int) $user['id']);
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['admin'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'auth_version' => (int) $user['auth_version'],
                'permissions' => admin_permissions((int) $user['id']),
            ];
            $_SESSION['last_activity'] = time();
            header('Location: /admin/dashboard.php');
            exit;
        }
        audit_log($user ? (int) $user['id'] : null, 'login.failure');
        $errorMessage = 'Unable to sign in with those details.';
    }
} catch (Throwable $error) {
    app_log('Administrator login failed: ' . $error->getMessage());
    http_response_code(503);
    $errorMessage = 'Sign-in is temporarily unavailable. Please try again later.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | Usimamizi — Grant Fashion</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
  <main class="login-card">
    <a class="admin-brand" href="/">GRANT FASHION <span>Usimamizi</span></a>
    <h1>Administrator sign in</h1>
    <p>Access is limited to authorized administrators.</p>
    <?php if ($errorMessage !== ''): ?>
      <p class="notice notice-error" role="alert"><?= escape_html($errorMessage) ?></p>
    <?php endif; ?>
    <form method="post" action="/admin/login.php">
      <?= csrf_field() ?>
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" required>
      <label for="totp_code">Authenticator or recovery code <span class="muted">(if enabled)</span></label>
      <input id="totp_code" name="totp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="32">
      <button class="button button-primary button-wide" type="submit">Sign in</button>
    </form>
    <p><a href="/admin/reset-password.php">Have a one-time reset code?</a></p>
  </main>
</body>
</html>
