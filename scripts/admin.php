<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin-auth.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$command = $argv[1] ?? '';
$email = strtolower(trim((string) ($argv[2] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    fwrite(STDERR, "Usage:\n  php scripts/admin.php create <email> <name> [super_admin|administrator|content_manager]\n  php scripts/admin.php issue-reset <email>\n");
    exit(2);
}

try {
    $pdo = database();
    if ($command === 'create') {
        $name = trim((string) ($argv[3] ?? ''));
        $role = (string) ($argv[4] ?? 'super_admin');
        $password = (string) env_value('ADMIN_PASSWORD', '');
        if ($name === '' || strlen($name) > 120 || !in_array($role, ['super_admin', 'administrator', 'content_manager'], true)
            || !validate_admin_password($password)) {
            throw new RuntimeException('Set ADMIN_PASSWORD to a strong password (14+ characters with uppercase, lowercase, and a number), and provide a valid name and role.');
        }
        $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug');
        $roleQuery->execute(['slug' => $role]);
        $roleId = $roleQuery->fetchColumn();
        if (!$roleId) {
            throw new RuntimeException('The requested role is unavailable. Apply the database migration first.');
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0 && $role !== 'super_admin') {
            throw new RuntimeException('The first account must be a super administrator.');
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (role_id, name, email, password_hash) VALUES (:role_id, :name, :email, :password_hash)'
            );
            $stmt->execute([
                'role_id' => $roleId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            ]);
            $userId = (int) $pdo->lastInsertId();
            audit_log($userId, 'user.create', 'user', $userId, ['role' => $role, 'method' => 'cli']);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        fwrite(STDOUT, "Administrator account created.\n");
    } elseif ($command === 'issue-reset') {
        $userQuery = $pdo->prepare('SELECT id FROM users WHERE email = :email AND is_active = 1');
        $userQuery->execute(['email' => $email]);
        $userId = $userQuery->fetchColumn();
        if (!$userId) {
            throw new RuntimeException('No active account exists for that email.');
        }
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND used_at IS NULL')
                ->execute(['user_id' => $userId]);
            $insert = $pdo->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
            );
            $insert->execute([
                'user_id' => $userId,
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800),
            ]);
            audit_log((int) $userId, 'password.reset_issued', 'user', (int) $userId, ['method' => 'cli']);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        fwrite(STDOUT, "One-time reset code (expires in 30 minutes; deliver securely, do not place it in a URL):\n" . $token . "\n");
    } else {
        throw new RuntimeException('Unknown command.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
