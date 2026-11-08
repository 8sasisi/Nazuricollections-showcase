<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
$admin = require_admin('settings.manage');
$notice = '';
$noticeType = 'success';
$recoveryCodes = [];
$pendingSecret = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? 'save_settings');
        if ($action === 'save_settings') {
            $values = [
                'business_phone' => trim((string) ($_POST['business_phone'] ?? '')),
                'business_whatsapp' => preg_replace('/\D+/', '', (string) ($_POST['business_whatsapp'] ?? '')),
                'instagram_url' => trim((string) ($_POST['instagram_url'] ?? '')),
                'tiktok_url' => trim((string) ($_POST['tiktok_url'] ?? '')),
                'currency_code' => strtoupper(trim((string) ($_POST['currency_code'] ?? ''))),
            ];
            if (strlen($values['business_phone']) > 40 || strlen($values['business_whatsapp']) > 15
                || ($values['business_whatsapp'] !== '' && !preg_match('/^\d{8,15}$/', $values['business_whatsapp']))) {
                throw new RuntimeException('Review the phone number fields and try again.');
            }
            if ($values['currency_code'] !== '' && !preg_match('/^[A-Z]{3}$/', $values['currency_code'])) {
                throw new RuntimeException('Currency must be an ISO 4217 three-letter code, or left blank.');
            }
            foreach (['instagram_url' => 'instagram.com', 'tiktok_url' => 'tiktok.com'] as $key => $host) {
                if ($values[$key] !== '') {
                    $parts = parse_url($values[$key]);
                    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                        || !in_array(strtolower($parts['host'] ?? ''), [$host, 'www.' . $host], true)) {
                        throw new RuntimeException('Use a valid HTTPS ' . ucfirst(str_replace('_url', '', $key)) . ' profile URL.');
                    }
                }
            }
            $pdo = database();
            $pdo->beginTransaction();
            $save = $pdo->prepare(
                'INSERT INTO site_settings (setting_key, setting_value, updated_by)
                 VALUES (:setting_key, :setting_value, :updated_by)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
            );
            foreach ($values as $key => $value) {
                $save->execute(['setting_key' => $key, 'setting_value' => $value, 'updated_by' => $admin['id']]);
            }
            audit_log((int) $admin['id'], 'settings.update', 'site');
            $pdo->commit();
            $notice = 'Contact settings saved.';
        } elseif ($action === 'begin_totp') {
            if (!function_exists('sodium_crypto_secretbox')) {
                throw new RuntimeException('Two-factor authentication requires the PHP sodium extension.');
            }
            $secret = base32_encode(random_bytes(20));
            $encrypted = totp_secret_encrypt($secret);
            $pdo = database();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE users SET totp_secret_encrypted = :secret, totp_enabled = 0 WHERE id = :id');
            $stmt->execute(['secret' => $encrypted, 'id' => $admin['id']]);
            audit_log((int) $admin['id'], 'totp.enrollment_started', 'user', (int) $admin['id']);
            $pdo->commit();
            $_SESSION['pending_totp_secret'] = $secret;
            $notice = 'Add this secret to your authenticator app, then enter its six-digit code to finish enrollment.';
            $pendingSecret = $secret;
        } elseif ($action === 'verify_totp') {
            $secret = (string) ($_SESSION['pending_totp_secret'] ?? '');
            $code = trim((string) ($_POST['totp_code'] ?? ''));
            if ($secret === '' || !verify_totp($secret, $code)) {
                throw new RuntimeException('The authenticator code was not accepted. Try again with the current code.');
            }
            $pdo = database();
            $pdo->beginTransaction();
            $enable = $pdo->prepare('UPDATE users SET totp_enabled = 1, auth_version = auth_version + 1 WHERE id = :id');
            $enable->execute(['id' => $admin['id']]);
            $pdo->prepare('DELETE FROM recovery_codes WHERE user_id = :id')->execute(['id' => $admin['id']]);
            $insert = $pdo->prepare('INSERT INTO recovery_codes (user_id, code_hash) VALUES (:user_id, :code_hash)');
            for ($i = 0; $i < 10; $i++) {
                $recoveryCodes[] = bin2hex(random_bytes(16));
                $insert->execute(['user_id' => $admin['id'], 'code_hash' => hash('sha256', end($recoveryCodes))]);
            }
            audit_log((int) $admin['id'], 'totp.enabled', 'user', (int) $admin['id']);
            $pdo->commit();
            unset($_SESSION['pending_totp_secret']);
            $_SESSION['admin']['auth_version']++;
            $notice = 'Two-factor authentication is enabled. Save these recovery codes now; they will not be shown again.';
        } else {
            throw new RuntimeException('Unknown settings action.');
        }
    }

    $settings = [];
    foreach (database()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    $status = database()->prepare('SELECT totp_enabled FROM users WHERE id = :id');
    $status->execute(['id' => $admin['id']]);
    $totpEnabled = (bool) $status->fetchColumn();
    if ($pendingSecret === null && isset($_SESSION['pending_totp_secret'])) {
        $pendingSecret = (string) $_SESSION['pending_totp_secret'];
    }
    $body = $notice !== '' ? '<p class="notice notice-' . escape_html($noticeType) . '">' . escape_html($notice) . '</p>' : '';
    if ($recoveryCodes) {
        $body .= '<section class="panel"><h2>One-time recovery codes</h2><ul class="recovery-codes">';
        foreach ($recoveryCodes as $recoveryCode) {
            $body .= '<li>' . escape_html($recoveryCode) . '</li>';
        }
        $body .= '</ul></section>';
    }
    $body .= '<section class="panel"><h2>Business contact information</h2><p>Leave a field blank until the official Grant Fashion detail is verified.</p>'
        . '<form method="post" action="/admin/settings.php">' . csrf_field() . '<input type="hidden" name="action" value="save_settings"><div class="form-grid">'
        . setting_input('business_phone', 'Business phone', $settings['business_phone'] ?? '', 'tel')
        . setting_input('business_whatsapp', 'WhatsApp number (international digits)', $settings['business_whatsapp'] ?? '', 'tel')
        . setting_input('instagram_url', 'Instagram profile URL', $settings['instagram_url'] ?? '', 'url')
        . setting_input('tiktok_url', 'TikTok profile URL', $settings['tiktok_url'] ?? '', 'url')
        . setting_input('currency_code', 'Currency code (optional, e.g. TZS)', $settings['currency_code'] ?? '', 'text')
        . '</div><button class="button button-primary" type="submit">Save contact details</button></form></section>';
    $body .= '<section class="panel"><h2>Two-factor authentication</h2><p>Status: <strong>' . ($totpEnabled ? 'Enabled' : 'Not enabled') . '</strong></p>';
    if (!$totpEnabled && $pendingSecret === null) {
        $body .= '<form method="post" action="/admin/settings.php">' . csrf_field() . '<input type="hidden" name="action" value="begin_totp"><button class="button button-secondary" type="submit">Set up authenticator</button></form>';
    } elseif (!$totpEnabled) {
        $issuer = rawurlencode('Grant Fashion: ' . $admin['email']);
        $body .= '<p>Enter this secret in your authenticator app: <code>' . escape_html($pendingSecret) . '</code></p>'
            . '<p class="muted">Provisioning URI: <code>' . escape_html('otpauth://totp/' . $issuer . '?secret=' . $pendingSecret . '&issuer=Grant%20Fashion') . '</code></p>'
            . '<form method="post" action="/admin/settings.php">' . csrf_field() . '<input type="hidden" name="action" value="verify_totp">'
            . '<label for="totp_code">Current six-digit code</label><input id="totp_code" name="totp_code" inputmode="numeric" maxlength="6" required>'
            . '<button class="button button-primary" type="submit">Verify and enable</button></form>';
    } else {
        $body .= '<p>Authenticator verification is required at sign-in. Generate new recovery codes from the account recovery procedure if needed.</p>';
    }
    $body .= '</section>';
    admin_page('Contact settings', $body);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pendingSecret = $_SESSION['pending_totp_secret'] ?? null;
    $body = '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p>';
    if ($pendingSecret) {
        $body .= '<p><a href="/admin/settings.php">Return to two-factor setup</a></p>';
    }
    admin_page('Contact settings', $body);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('Administrator settings failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Contact settings', '<p class="notice notice-error">Settings are temporarily unavailable. Check the private application log.</p>');
}

function setting_input(string $name, string $label, string $value, string $type): string
{
    return '<div><label>' . escape_html($label) . '<input name="' . escape_html($name) . '" type="' . escape_html($type)
        . '" maxlength="255" value="' . escape_html($value) . '"></label></div>';
}
