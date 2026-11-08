<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function find_admin_by_email(string $email): ?array
{
    $stmt = database()->prepare(
        'SELECT u.id, u.name, u.email, u.password_hash, u.is_active, u.auth_version, u.totp_secret_encrypted,
                u.totp_enabled, r.slug AS role
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function admin_permissions(int $userId): array
{
    $stmt = database()->prepare(
        'SELECT p.slug FROM users u
         JOIN role_permissions rp ON rp.role_id = u.role_id
         JOIN permissions p ON p.id = rp.permission_id
         WHERE u.id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    return array_column($stmt->fetchAll(), 'slug');
}

function login_is_throttled(string $email, string $ip): bool
{
    $stmt = database()->prepare(
        'SELECT COUNT(*) AS failures, MAX(attempted_at) AS latest
         FROM login_attempts
         WHERE succeeded = 0 AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)
           AND (ip_address = :ip OR email_hash = :email_hash)'
    );
    $stmt->execute([
        'ip' => substr($ip, 0, 45),
        'email_hash' => hash('sha256', strtolower($email)),
    ]);
    $result = $stmt->fetch();
    $failures = (int) ($result['failures'] ?? 0);
    if ($failures < 5) {
        return false;
    }
    $delay = min(900, 2 ** min(10, $failures - 4));
    return $result['latest'] !== null && strtotime($result['latest'] . ' UTC') + $delay > time();
}

function record_login_attempt(string $email, string $ip, bool $succeeded): void
{
    $stmt = database()->prepare(
        'INSERT INTO login_attempts (email_hash, ip_address, succeeded)
         VALUES (:email_hash, :ip, :succeeded)'
    );
    $stmt->execute([
        'email_hash' => hash('sha256', strtolower($email)),
        'ip' => substr($ip, 0, 45),
        'succeeded' => $succeeded ? 1 : 0,
    ]);
}

function verify_recovery_code(int $userId, string $code): bool
{
    $stmt = database()->prepare(
        'SELECT id, code_hash FROM recovery_codes WHERE user_id = :user_id AND used_at IS NULL'
    );
    $stmt->execute(['user_id' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        if (hash_equals($row['code_hash'], hash('sha256', $code))) {
            $update = database()->prepare(
                'UPDATE recovery_codes SET used_at = CURRENT_TIMESTAMP WHERE id = :id AND used_at IS NULL'
            );
            $update->execute(['id' => $row['id']]);
            return $update->rowCount() === 1;
        }
    }
    return false;
}

function flash_set(string $message, string $type = 'success'): void
{
    start_app_session();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    start_app_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function validate_admin_password(string $password): bool
{
    return strlen($password) >= 14 && strlen($password) <= 128
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password);
}
