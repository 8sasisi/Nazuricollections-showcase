<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_admin('products.manage');

$imageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$imageId || $imageId < 1) {
    http_response_code(404);
    exit;
}

try {
    $library = ($_GET['source'] ?? '') === 'library';
    $stmt = database()->prepare($library
        ? 'SELECT stored_name, mime_type FROM media WHERE id = :id'
        : 'SELECT stored_name, mime_type FROM product_images WHERE id = :id');
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
    header('Cache-Control: no-store');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
} catch (Throwable $error) {
    app_log('Administrator image request failed: ' . $error->getMessage());
    http_response_code(503);
}
