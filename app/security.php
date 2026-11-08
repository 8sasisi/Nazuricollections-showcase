<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/brand.php';

function csrf_token(): string
{
    start_app_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape_html(csrf_token()) . '">';
}

function verify_csrf(): void
{
    start_app_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('Your session expired. Please reload the page and try again.');
    }
}

function current_admin(): ?array
{
    start_app_session();
    if (!isset($_SESSION['admin']['id'])) {
        return null;
    }
    try {
        $stmt = database()->prepare(
            'SELECT u.id, u.name, u.email, u.auth_version, r.slug AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.is_active = 1'
        );
        $stmt->execute(['id' => $_SESSION['admin']['id']]);
        $user = $stmt->fetch();
        if (!$user || (int) $user['auth_version'] !== (int) ($_SESSION['admin']['auth_version'] ?? 0)) {
            $_SESSION = [];
            session_regenerate_id(true);
            return null;
        }
        $permissionStmt = database()->prepare(
            'SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = (SELECT role_id FROM users WHERE id = :id)'
        );
        $permissionStmt->execute(['id' => $user['id']]);
        $_SESSION['admin'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'auth_version' => (int) $user['auth_version'],
            'permissions' => array_column($permissionStmt->fetchAll(), 'slug'),
        ];
        return $_SESSION['admin'];
    } catch (Throwable $error) {
        app_log('Administrator session validation failed: ' . $error->getMessage());
        http_response_code(503);
        exit('Administrator access is temporarily unavailable. Please try again later.');
    }
}

function require_admin(string $permission = 'dashboard.view'): array
{
    $admin = current_admin();
    if (!$admin) {
        header('Location: /admin/login.php');
        exit;
    }
    if (!has_permission($permission)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $admin;
}

function has_permission(string $permission): bool
{
    $admin = current_admin();
    if (!$admin) {
        return false;
    }
    if (($admin['role'] ?? '') === 'super_admin') {
        return true;
    }
    return in_array($permission, $admin['permissions'] ?? [], true);
}

function audit_log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
{
    try {
        $stmt = database()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, metadata_json)
             VALUES (:user_id, :action, :entity_type, :entity_id, :ip, :metadata)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'metadata' => json_encode($metadata, JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
    } catch (Throwable $error) {
        app_log('Audit write failed: ' . $error->getMessage());
        throw new RuntimeException('The security audit record could not be saved.', 0, $error);
    }
}

function admin_page(string $title, string $content): void
{
    $admin = current_admin();
    $brand = brand_identity();
    $safeTitle = escape_html($title);
    $safeBrandName = escape_html($brand['name']);
    $adminLabel = mb_strtolower($brand['name'] . ' Administrator', 'UTF-8');
    $adminLabel = mb_strtoupper(mb_substr($adminLabel, 0, 1, 'UTF-8'), 'UTF-8')
        . mb_substr($adminLabel, 1, null, 'UTF-8');
    $safeAdminLabel = escape_html($adminLabel);
    $logoUrl = brand_logo_url($brand['logo_id']);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
        . $safeTitle . ' | Usimamizi — ' . $safeBrandName . '</title><link rel="stylesheet" href="/assets/css/admin.css"><script src="/assets/js/brand.js" defer></script><script src="/assets/js/admin.js" defer></script></head><body>';
    echo '<header class="admin-header"><a href="/admin/dashboard.php" class="admin-brand">'
        . '<span class="admin-brand-mark" data-brand-initials aria-hidden="true">' . escape_html($brand['initials']) . '</span>'
        . ($logoUrl !== null ? '<img class="admin-brand-logo" data-brand-image src="' . escape_html($logoUrl) . '" alt="' . $safeBrandName . ' logo" hidden>' : '')
        . '<span class="admin-brand-name">' . $safeBrandName . '</span><span class="admin-brand-subtitle">Usimamizi</span></a><div class="admin-user">'
        . $safeAdminLabel . '<form action="/admin/logout.php" method="post">' . csrf_field()
        . '<button class="button button-quiet" type="submit">Sign out</button></form></div></header>';
    echo '<div class="admin-shell"><nav class="admin-nav" aria-label="Administration">';
    $links = [
        'dashboard.view' => ['/admin/dashboard.php', 'Overview'],
        'products.manage' => ['/admin/products.php', 'Products'],
        'collections.manage' => ['/admin/collections.php', 'Collections'],
        'media.manage' => ['/admin/media.php', 'Photographs'],
        'content.manage' => ['/admin/content.php', 'Website content'],
        'brand.manage' => ['/admin/brand-identity.php', 'Brand Identity'],
        'settings.manage' => ['/admin/settings.php', 'Contact settings'],
        'users.manage' => ['/admin/users.php', 'Administrators'],
        'audit.view' => ['/admin/activity-logs.php', 'Activity logs'],
    ];
    foreach ($links as $permission => [$url, $label]) {
        if (has_permission($permission)) {
            echo '<a href="' . escape_html($url) . '">' . escape_html($label) . '</a>';
        }
    }
    echo '</nav><main class="admin-main"><h1>' . $safeTitle . '</h1>' . $content . '</main></div></body></html>';
}

function totp_secret_encrypt(string $secret): string
{
    $key = base64_decode((string) env_value('TOTP_ENCRYPTION_KEY', ''), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Set TOTP_ENCRYPTION_KEY to a base64-encoded 32-byte key before enabling two-factor authentication.');
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, $key));
}

function totp_secret_decrypt(string $encrypted): string
{
    $key = base64_decode((string) env_value('TOTP_ENCRYPTION_KEY', ''), true);
    $packed = base64_decode($encrypted, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || $packed === false
        || strlen($packed) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Two-factor authentication configuration is invalid.');
    }
    $nonce = substr($packed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($packed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    if ($plain === false) {
        throw new RuntimeException('Unable to decrypt two-factor authentication secret.');
    }
    return $plain;
}

function base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 5) as $chunk) {
        $result .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $result;
}

function base32_decode(string $value): string|false
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(rtrim($value, '='))) as $char) {
        $position = strpos($alphabet, $char);
        if ($position === false) {
            return false;
        }
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $result .= chr(bindec($byte));
        }
    }
    return $result;
}

function verify_totp(string $secret, string $code, ?int $timestamp = null): bool
{
    $key = base32_decode($secret);
    if ($key === false || !preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $counter = (int) floor(($timestamp ?? time()) / 30);
    for ($offset = -1; $offset <= 1; $offset++) {
        $binaryCounter = pack('N2', 0, $counter + $offset);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $index = ord($hash[19]) & 0x0f;
        $number = (unpack('N', substr($hash, $index, 4))[1] & 0x7fffffff) % 1000000;
        if (hash_equals(str_pad((string) $number, 6, '0', STR_PAD_LEFT), $code)) {
            return true;
        }
    }
    return false;
}
