<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin-auth.php';
$admin = require_admin('users.manage');
$notice = '';
$noticeType = 'success';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $pdo = database();
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? '');
            if ($name === '' || strlen($name) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || strlen($email) > 254 || !validate_admin_password($password)
                || !in_array($role, ['administrator', 'content_manager'], true)) {
                throw new RuntimeException('Provide a valid name, email, role, and a password of at least 14 characters including uppercase, lowercase, and a number.');
            }
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug');
            $roleStmt->execute(['slug' => $role]);
            $roleId = $roleStmt->fetchColumn();
            $pdo->beginTransaction();
            try {
                $create = $pdo->prepare(
                    'INSERT INTO users (role_id, name, email, password_hash) VALUES (:role_id, :name, :email, :password_hash)'
                );
                $create->execute([
                    'role_id' => $roleId,
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                ]);
                $newId = (int) $pdo->lastInsertId();
                audit_log((int) $admin['id'], 'user.create', 'user', $newId, ['role' => $role]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
            $notice = 'Administrator account created. Share the temporary password through a separate secure channel.';
        } elseif ($action === 'update') {
            $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
            $role = (string) ($_POST['role'] ?? '');
            $active = isset($_POST['is_active']) ? 1 : 0;
            if (!$userId || $userId < 1 || $userId === (int) $admin['id']
                || !in_array($role, ['administrator', 'content_manager', 'super_admin'], true)) {
                throw new RuntimeException('Select a valid account. Your own account cannot be changed here.');
            }
            if ($role === 'super_admin' && ($admin['role'] ?? '') !== 'super_admin') {
                throw new RuntimeException('Only a super administrator can grant the super administrator role.');
            }
            $existing = $pdo->prepare(
                'SELECT u.id, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id'
            );
            $existing->execute(['id' => $userId]);
            $target = $existing->fetch();
            if (!$target) {
                throw new RuntimeException('That administrator account no longer exists.');
            }
            if ($target['role'] === 'super_admin' && $role !== 'super_admin') {
                throw new RuntimeException('A super administrator cannot be demoted through this screen.');
            }
            if ($target['role'] === 'super_admin' && !$active) {
                $count = (int) $pdo->query(
                    "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super_admin' AND u.is_active = 1"
                )->fetchColumn();
                if ($count <= 1) {
                    throw new RuntimeException('At least one active super administrator must remain.');
                }
            }
            $pdo->beginTransaction();
            try {
                $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug');
                $roleStmt->execute(['slug' => $role]);
                $update = $pdo->prepare(
                    'UPDATE users SET role_id = :role_id, is_active = :active, auth_version = auth_version + 1 WHERE id = :id'
                );
                $update->execute(['role_id' => $roleStmt->fetchColumn(), 'active' => $active, 'id' => $userId]);
                audit_log((int) $admin['id'], 'user.update', 'user', (int) $userId, ['role' => $role, 'active' => $active]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
            $notice = 'Administrator account updated. Any existing sessions for that account were invalidated.';
        } else {
            throw new RuntimeException('Unknown account action.');
        }
    }

    $users = database()->query(
        'SELECT u.id, u.name, u.email, u.is_active, u.totp_enabled, u.created_at, r.slug AS role, r.name AS role_name
         FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at'
    )->fetchAll();
    $body = $notice !== '' ? '<p class="notice notice-' . escape_html($noticeType) . '">' . escape_html($notice) . '</p>' : '';
    $body .= '<section class="panel"><h2>Create administrator</h2><form method="post" action="/admin/users.php">' . csrf_field()
        . '<input type="hidden" name="action" value="create"><div class="form-grid">'
        . '<div><label>Name<input name="name" maxlength="120" required></label></div>'
        . '<div><label>Email<input name="email" type="email" maxlength="254" required></label></div>'
        . '<div><label>Role<select name="role"><option value="administrator">Administrator</option><option value="content_manager">Content Manager</option></select></label></div>'
        . '<div><label>Temporary password<input name="password" type="password" autocomplete="new-password" minlength="14" maxlength="128" required></label></div>'
        . '</div><button class="button button-primary" type="submit">Create account</button></form></section>';
    $body .= '<section class="panel"><h2>Administrator accounts</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role / status</th><th>Two-factor</th><th>Update</th></tr></thead><tbody>';
    foreach ($users as $user) {
        $body .= '<tr><td>' . escape_html($user['name']) . '</td><td>' . escape_html($user['email']) . '</td><td>'
            . escape_html($user['role_name']) . ' · ' . ((int) $user['is_active'] ? 'Active' : 'Disabled') . '</td><td>'
            . ((int) $user['totp_enabled'] ? 'Enabled' : 'Not enabled') . '</td><td>';
        if ((int) $user['id'] === (int) $admin['id']) {
            $body .= 'Current account';
        } else {
            $body .= '<form method="post" action="/admin/users.php" class="user-update-form">' . csrf_field()
                . '<input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="' . (int) $user['id'] . '">'
                . '<label class="sr-only">Role<select name="role"><option value="administrator"' . ($user['role'] === 'administrator' ? ' selected' : '') . '>Administrator</option>'
                . '<option value="content_manager"' . ($user['role'] === 'content_manager' ? ' selected' : '') . '>Content Manager</option>'
                . '<option value="super_admin"' . ($user['role'] === 'super_admin' ? ' selected' : '') . '>Super Administrator</option></select></label>'
                . '<label class="inline-label"><input type="checkbox" name="is_active" value="1"' . ((int) $user['is_active'] ? ' checked' : '') . '> Active</label>'
                . '<button class="button button-secondary" type="submit">Save</button></form>';
        }
        $body .= '</td></tr>';
    }
    $body .= '</tbody></table></div></section>';
    admin_page('Administrators', $body);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_page('Administrators', '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p><p><a href="/admin/users.php">Return to administrator accounts</a></p>');
} catch (Throwable $error) {
    app_log('Administrator accounts page failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Administrators', '<p class="notice notice-error">Administrator accounts are temporarily unavailable. Check the private application log.</p>');
}
