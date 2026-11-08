<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/uploads.php';
$admin = require_admin('media.manage');
$notice = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $pdo = database();
        if ($action === 'upload') {
            $alt = trim((string) ($_POST['alt_text'] ?? ''));
            $target = (string) ($_POST['target'] ?? '');
            if ($alt === '' || strlen($alt) > 200
                || !in_array($target, ['hero', 'brand_story', 'collection:abayas', 'collection:magauni'], true)) {
                throw new RuntimeException('Provide descriptive alternative text and choose a supported location.');
            }
            $image = store_image_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            if (!$image) {
                throw new RuntimeException('Choose an image to upload.');
            }
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    'INSERT INTO media (stored_name, mime_type, byte_size, width, height, alt_text, uploaded_by)
                     VALUES (:stored_name, :mime_type, :byte_size, :width, :height, :alt_text, :uploaded_by)'
                );
                $insert->execute($image + ['alt_text' => $alt, 'uploaded_by' => $admin['id']]);
                $mediaId = (int) $pdo->lastInsertId();
                if (str_starts_with($target, 'collection:')) {
                    $collection = substr($target, strlen('collection:'));
                    $update = $pdo->prepare('UPDATE collections SET media_id = :media_id WHERE slug = :slug');
                    $update->execute(['media_id' => $mediaId, 'slug' => $collection]);
                } else {
                    $update = $pdo->prepare('UPDATE homepage_sections SET media_id = :media_id WHERE section_key = :section_key');
                    $update->execute(['media_id' => $mediaId, 'section_key' => $target]);
                }
                if ($update->rowCount() < 1) {
                    throw new RuntimeException('The selected location is unavailable.');
                }
                audit_log((int) $admin['id'], 'media.upload', 'media', $mediaId, ['target' => $target]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                remove_stored_image($image['stored_name']);
                throw $error;
            }
            $notice = 'Image uploaded and assigned.';
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['media_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new RuntimeException('Choose a valid image.');
            }
            $lookup = $pdo->prepare(
                "SELECT m.stored_name,
                    EXISTS(SELECT 1 FROM homepage_sections h WHERE h.media_id = m.id)
                    OR EXISTS(SELECT 1 FROM collections c WHERE c.media_id = m.id)
                    OR EXISTS(SELECT 1 FROM site_settings s
                              WHERE s.setting_key = 'brand_logo' AND s.setting_value = CAST(m.id AS CHAR)) AS assigned
                 FROM media m WHERE m.id = :id"
            );
            $lookup->execute(['id' => $id]);
            $media = $lookup->fetch();
            if (!$media || (bool) $media['assigned']) {
                throw new RuntimeException('Assigned images cannot be deleted. Replace their assignment first.');
            }
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM media WHERE id = :id')->execute(['id' => $id]);
            audit_log((int) $admin['id'], 'media.delete', 'media', (int) $id);
            $pdo->commit();
            remove_stored_image($media['stored_name']);
            $notice = 'Unused image removed.';
        } else {
            throw new RuntimeException('Unknown image action.');
        }
    }

    $images = database()->query(
        "SELECT m.id, m.alt_text, m.mime_type, m.byte_size, m.width, m.height, m.stored_name,
            (SELECT h.section_key FROM homepage_sections h WHERE h.media_id = m.id LIMIT 1) AS section_key,
            (SELECT c.name FROM collections c WHERE c.media_id = m.id LIMIT 1) AS collection_name,
            (SELECT 'Brand logo' FROM site_settings s
             WHERE s.setting_key = 'brand_logo' AND s.setting_value = CAST(m.id AS CHAR) LIMIT 1) AS brand_label
         FROM media m ORDER BY m.created_at DESC LIMIT 200"
    )->fetchAll();
    $body = $notice !== '' ? '<p class="notice">' . escape_html($notice) . '</p>' : '';
    $body .= '<section class="panel"><h2>Upload a photograph</h2><p>JPEG, PNG, or WebP only; maximum 5 MB and 16 megapixels. The server decodes and re-encodes each image.</p>'
        . '<form method="post" action="/admin/media.php" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="upload">'
        . '<div class="form-grid"><div><label>Image<input name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label></div>'
        . '<div><label>Assign to<select name="target" required><option value="hero">Homepage hero</option><option value="brand_story">Homepage brand story</option>'
        . '<option value="collection:abayas">Abayas collection</option><option value="collection:magauni">Magauni / Gowns collection</option></select></label></div>'
        . '<div class="form-full"><label>Alternative text<input name="alt_text" maxlength="200" required></label></div></div>'
        . '<button class="button button-primary" type="submit">Upload and assign</button></form></section>';
    $body .= '<section class="panel"><h2>Image library</h2>';
    if (!$images) {
        $body .= '<p>No images have been uploaded.</p>';
    }
    foreach ($images as $image) {
        $location = $image['brand_label'] ?: $image['section_key'] ?: $image['collection_name'] ?: 'Unassigned';
        $body .= '<article class="product-editor"><img class="admin-product-preview" src="/api/admin-media.php?source=library&id=' . (int) $image['id'] . '" alt="' . escape_html($image['alt_text']) . '">'
            . '<p><strong>' . escape_html($image['alt_text']) . '</strong><br>' . escape_html($image['mime_type']) . ' · '
            . (int) $image['width'] . ' × ' . (int) $image['height'] . ' px · ' . number_format((int) $image['byte_size']) . ' bytes · '
            . escape_html($location) . '</p>';
        if ($location === 'Unassigned') {
            $body .= '<form method="post" action="/admin/media.php">' . csrf_field() . '<input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="media_id" value="' . (int) $image['id'] . '"><button class="button button-danger" type="submit">Delete image</button></form>';
        }
        $body .= '</article>';
    }
    $body .= '</section>';
    admin_page('Photographs', $body);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_page('Photographs', '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p><p><a href="/admin/media.php">Return to image management</a></p>');
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('Image management failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Photographs', '<p class="notice notice-error">Image management is temporarily unavailable. Check the private application log.</p>');
}
