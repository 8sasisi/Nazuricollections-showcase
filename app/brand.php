<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function brand_identity(): array
{
    $identity = [
        'name' => 'Grant Fashion',
        'initials' => 'GF',
        'tagline' => '',
        'description' => '',
        'logo_id' => null,
    ];
    $stmt = database()->query(
        "SELECT setting_key, setting_value FROM site_settings
         WHERE setting_key IN ('brand_name', 'brand_tagline', 'brand_description', 'brand_logo')"
    );
    foreach ($stmt->fetchAll() as $setting) {
        if ($setting['setting_key'] === 'brand_name' && trim($setting['setting_value']) !== '') {
            $identity['name'] = $setting['setting_value'];
        } elseif ($setting['setting_key'] === 'brand_tagline') {
            $identity['tagline'] = $setting['setting_value'];
        } elseif ($setting['setting_key'] === 'brand_description') {
            $identity['description'] = $setting['setting_value'];
        } elseif ($setting['setting_key'] === 'brand_logo') {
            $logoId = filter_var($setting['setting_value'], FILTER_VALIDATE_INT);
            $identity['logo_id'] = $logoId !== false && $logoId > 0 ? $logoId : null;
        }
    }
    $identity['initials'] = brand_initials($identity['name']);
    return $identity;
}

function brand_initials(?string $name): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim((string) $name));
    if ($normalized === null || $normalized === '') {
        return 'GF';
    }

    preg_match_all('/\p{L}+/u', $normalized, $words);
    $words = $words[0] ?? [];
    if (!$words) {
        return 'GF';
    }

    $clusters = static function (string $word): array {
        preg_match_all('/\X/u', $word, $matches);
        return $matches[0] ?? [];
    };
    if (count($words) > 1) {
        $letters = [$clusters($words[0])[0] ?? '', $clusters($words[1])[0] ?? ''];
    } else {
        $characters = $clusters($words[0]);
        $letters = [$characters[0] ?? '', $characters[1] ?? ''];
    }

    $initials = '';
    foreach ($letters as $letter) {
        $initials .= $letter === ''
            ? 'F'
            : (function_exists('mb_strtoupper') ? mb_strtoupper($letter, 'UTF-8') : strtoupper($letter));
    }
    preg_match_all('/\X/u', $initials, $result);
    $initials = implode('', array_slice($result[0] ?? [], 0, 2));
    if ($initials === '') {
        return 'GF';
    }
    if (count($result[0] ?? []) < 2) {
        $initials .= 'F';
    }
    return $initials;
}

function brand_logo_url(?int $logoId): ?string
{
    return $logoId === null ? null : '/api/media.php?source=library&id=' . $logoId;
}

function is_valid_brand_text(string $value, int $maxLength, bool $required): bool
{
    $length = preg_match_all('/./us', $value);
    return $length !== false
        && (!$required || trim($value) !== '')
        && $length <= $maxLength
        && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $value);
}
