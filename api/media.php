<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$imageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$imageId || $imageId < 1) {
    http_response_code(404);
    exit;
}

try {
    $source = (string) ($_GET['source'] ?? 'library');
    if ($source === 'product') {
        $sql = 'SELECT i.stored_name, i.mime_type FROM product_images i
                JOIN products p ON p.id = i.product_id
                JOIN collections c ON c.id = p.collection_id
                WHERE i.id = :id AND p.is_published = 1 AND c.is_published = 1';
    } elseif ($source === 'library') {
        $sql = 'SELECT m.stored_name, m.mime_type FROM media m
                WHERE m.id = :id AND (
                    EXISTS (SELECT 1 FROM homepage_sections h WHERE h.media_id = m.id AND h.is_published = 1)
                    OR EXISTS (SELECT 1 FROM collections c WHERE c.media_id = m.id AND c.is_published = 1)
                )';
    } else {
        http_response_code(404);
        exit;
    }
    $stmt = database()->prepare($sql);
    $stmt->execute(['id' => $imageId]);
    $image = $stmt->fetch();
    if (!$image || !in_array($image['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        exit;
    }
    $path = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media'
        . DIRECTORY_SEPARATOR . $image['stored_name'];
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $image['mime_type']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
} catch (Throwable $error) {
    app_log('Public image request failed: ' . $error->getMessage());
    http_response_code(503);
}
