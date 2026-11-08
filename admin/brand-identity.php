<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/uploads.php';
$admin = require_admin('brand.manage');
$notice = '';
$noticeType = 'success';
$pdo = null;
$uploadedImage = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (($_POST['confirm_save'] ?? '') !== '1') {
            throw new RuntimeException('Confirm the brand identity changes before saving.');
        }

        $name = trim((string) ($_POST['brand_name'] ?? ''));
        $tagline = trim((string) ($_POST['brand_tagline'] ?? ''));
        $description = trim((string) ($_POST['brand_description'] ?? ''));
        if (!is_valid_brand_text($name, 120, true)
            || !is_valid_brand_text($tagline, 255, false)
            || !is_valid_brand_text($description, 1000, false)) {
            throw new RuntimeException('Check that the name is required and all brand fields are valid and within their length limits.');
        }

        $current = brand_identity();
        $uploadedImage = store_image_upload($_FILES['brand_logo'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        $removeLogo = ($_POST['remove_brand_logo'] ?? '') === '1';
        if ($removeLogo && $uploadedImage !== null) {
            throw new RuntimeException('Choose either a new logo or remove the current logo, not both.');
        }
        $pdo = database();
        $pdo->beginTransaction();
        $logoId = $current['logo_id'];
        if ($uploadedImage !== null) {
            $insertImage = $pdo->prepare(
                'INSERT INTO media (stored_name, mime_type, byte_size, width, height, alt_text, uploaded_by)
                 VALUES (:stored_name, :mime_type, :byte_size, :width, :height, :alt_text, :uploaded_by)'
            );
            $insertImage->execute($uploadedImage + [
                'alt_text' => $name . ' logo',
                'uploaded_by' => $admin['id'],
            ]);
            $logoId = (int) $pdo->lastInsertId();
        } elseif ($removeLogo) {
            $logoId = null;
        }

        $values = [
            'brand_name' => $name,
            'brand_tagline' => $tagline,
            'brand_description' => $description,
            'brand_logo' => $logoId === null ? '' : (string) $logoId,
        ];
        $previousValues = [
            'brand_name' => $current['name'],
            'brand_tagline' => $current['tagline'],
            'brand_description' => $current['description'],
            'brand_logo' => $current['logo_id'] === null ? '' : (string) $current['logo_id'],
        ];
        $changes = [];
        $save = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, updated_by)
             VALUES (:setting_key, :setting_value, :updated_by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        foreach ($values as $key => $value) {
            if ($previousValues[$key] !== $value) {
                $changes[$key] = ['from' => $previousValues[$key], 'to' => $value];
            }
            $save->execute([
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_by' => $admin['id'],
            ]);
        }
        if ($changes) {
            audit_log((int) $admin['id'], 'brand_identity.update', 'site_settings', null, ['changes' => $changes]);
        }
        $pdo->commit();
        $notice = $changes ? 'Brand identity saved.' : 'No brand identity changes to save.';
        $uploadedImage = null;
    }

    $brand = brand_identity();
    $body = $notice !== ''
        ? '<p class="notice notice-' . escape_html($noticeType) . '">' . escape_html($notice) . '</p>'
        : '';
    $logoUrl = brand_logo_url($brand['logo_id']);
    $body .= '<section class="panel"><h2>Current brand preview</h2><div class="brand-preview">'
        . '<span class="brand-logo-frame"><span class="brand-mark" data-brand-initials aria-hidden="true">' . escape_html($brand['initials']) . '</span><img class="brand-preview-image" data-brand-image data-brand-preview-logo'
        . ($logoUrl !== null ? ' src="' . escape_html($logoUrl) . '"' : '')
        . ($logoUrl !== null ? ' data-saved-src="' . escape_html($logoUrl) . '"' : '')
        . ' alt="' . escape_html($brand['name'] . ' logo') . '"'
        . ' hidden></span>'
        . '<div><strong data-brand-preview-name>' . escape_html($brand['name']) . '</strong>'
        . '<p data-brand-preview-tagline>' . escape_html($brand['tagline']) . '</p>'
        . '<p data-brand-preview-description>' . escape_html($brand['description']) . '</p></div></div></section>';
    $body .= '<section class="panel"><h2>Brand identity details</h2>'
        . '<p>Changes update the public website and administrator branding. Logo uploads use the secure image library.</p>'
        . '<form method="post" action="/admin/brand-identity.php" enctype="multipart/form-data" data-confirm="Save these brand identity changes?">'
        . csrf_field() . '<div class="form-grid">'
        . '<div class="form-full"><label for="brand_name">Brand name</label>'
        . '<input id="brand_name" name="brand_name" maxlength="120" required value="' . escape_html($brand['name']) . '" data-brand-preview-input="name"></div>'
        . '<div class="form-full"><label for="brand_tagline">Brand tagline</label>'
        . '<input id="brand_tagline" name="brand_tagline" maxlength="255" value="' . escape_html($brand['tagline']) . '" data-brand-preview-input="tagline"></div>'
        . '<div class="form-full"><label for="brand_description">Brand description</label>'
        . '<textarea id="brand_description" name="brand_description" maxlength="1000" rows="4" data-brand-preview-input="description">' . escape_html($brand['description']) . '</textarea></div>'
        . '<div class="form-full"><label for="brand_logo">Brand logo (optional)</label>'
        . '<input id="brand_logo" name="brand_logo" type="file" accept="image/jpeg,image/png,image/webp">'
        . '<span class="muted">JPEG, PNG, or WebP; maximum 5 MB and 16 megapixels.</span>'
        . ($brand['logo_id'] !== null ? '<label><input type="checkbox" name="remove_brand_logo" value="1"> Remove current logo and use generated initials</label>' : '') . '</div>'
        . '<label class="form-full brand-confirm"><input type="checkbox" name="confirm_save" value="1" required> I have reviewed and confirm these brand identity changes.</label>'
        . '</div><button class="button button-primary" type="submit">Save brand identity</button></form></section>';
    admin_page('Brand Identity', $body);
} catch (RuntimeException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploadedImage !== null) {
        remove_stored_image($uploadedImage['stored_name']);
    }
    admin_page('Brand Identity', '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p><p><a href="/admin/brand-identity.php">Return to Brand Identity</a></p>');
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploadedImage !== null) {
        remove_stored_image($uploadedImage['stored_name']);
    }
    app_log('Brand identity page failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Brand Identity', '<p class="notice notice-error">Brand identity management is temporarily unavailable. Check the private application log.</p>');
}
