<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function store_image_upload(array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('The image could not be uploaded.');
    }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Choose an image smaller than 5 MB.');
    }

    $info = getimagesize((string) $file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!$info || !isset($allowed[$mime]) || ($info['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Only valid JPEG, PNG, and WebP images are accepted.');
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || $width * $height > 16000000) {
        throw new RuntimeException('The image dimensions exceed the allowed limit.');
    }
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('Image processing support is not enabled on this server.');
    }

    $bytes = file_get_contents((string) $file['tmp_name']);
    $image = $bytes === false ? false : imagecreatefromstring($bytes);
    if ($image === false) {
        throw new RuntimeException('The image data could not be decoded.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    $directory = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        imagedestroy($image);
        throw new RuntimeException('Image storage is unavailable.');
    }
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($image, $destination, 88),
        'image/png' => imagepng($image, $destination, 7),
        'image/webp' => function_exists('imagewebp') ? imagewebp($image, $destination, 88) : false,
    };
    imagedestroy($image);
    if (!$saved) {
        if (is_file($destination) && !unlink($destination)) {
            app_log('Unable to remove failed image output: ' . $filename);
        }
        throw new RuntimeException('The server could not process this image format.');
    }
    return [
        'stored_name' => $filename,
        'mime_type' => $mime,
        'byte_size' => (int) filesize($destination),
        'width' => $width,
        'height' => $height,
    ];
}
