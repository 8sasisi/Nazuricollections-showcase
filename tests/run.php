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
$assert(is_valid_brand_text('Grant Fashion', 120, true), 'Brand name accepts valid text');
$assert(is_valid_brand_text('Mavazi ya Kisasa', 255, false), 'Brand tagline accepts valid text');
$assert(!is_valid_brand_text('  ', 120, true), 'Required brand name rejects whitespace');
$assert(!is_valid_brand_text(str_repeat('a', 121), 120, true), 'Brand name length limit is enforced');
$assert(!is_valid_brand_text("Brand\0Name", 120, true), 'Brand name rejects control characters');
$assert(brand_initials('Grant Fashion') === 'GF', 'Two-word brand initials use first letters');
$assert(brand_initials('  Royal   Abaya  ') === 'RA', 'Brand initials ignore repeated whitespace');
$assert(brand_initials('Elegant Wear Collection') === 'EW', 'Brand initials use only the first two words');
$assert(brand_initials('ZURI') === 'ZU', 'Single-word initials use first two characters');
$assert(brand_initials('Fashion') === 'FA', 'Single-word initials use first two characters');
$assert(brand_initials('A') === 'AF', 'Single-letter names use a safe second character');
$assert(brand_initials('') === 'GF', 'Empty brand names use the final fallback');
$assert(brand_initials('---') === 'GF', 'Names without usable letters use the final fallback');
$assert(brand_initials('123') === 'GF', 'Names without letters use the final fallback');
$assert(brand_initials('Élégant Wear') === 'ÉW', 'Brand initials preserve Unicode uppercase');

$assert(escape_html('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', 'HTML output is escaped');
$assert(!has_permission('products.manage'), 'Anonymous users receive no permissions');
$assert(!has_permission('brand.manage'), 'Anonymous users cannot manage brand identity');

fwrite(STDOUT, "{$checks} checks passed.\n");
