<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/admin-auth.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
};

$secret = random_bytes(20);
$encoded = base32_encode($secret);
$assert(base32_decode($encoded) === $secret, 'Base32 TOTP secret round-trip');
$assert(base32_decode('INVALID0') === false, 'Invalid Base32 is rejected');
$assert(verify_totp(base32_encode('12345678901234567890'), '287082', 59), 'RFC 6238 SHA-1 TOTP test vector');
$assert(!verify_totp(base32_encode('12345678901234567890'), '287083', 59), 'Incorrect TOTP is rejected');

$assert(validate_admin_password('StrongPassphrase2026'), 'Strong admin password accepted');
$assert(!validate_admin_password('weakpass123'), 'Password lacking uppercase is rejected');
$assert(!validate_admin_password('NoDigitsAtAllHere'), 'Password lacking a digit is rejected');
$assert(!validate_admin_password('Short1A'), 'Short password is rejected');
$assert(!password_verify('anything', 'invalid-hash'), 'Unknown account fallback hash never verifies');

$assert(escape_html('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', 'HTML output is escaped');
$assert(!has_permission('products.manage'), 'Anonymous users receive no permissions');

fwrite(STDOUT, "{$checks} checks passed.\n");
