<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
$admin = require_admin('collections.manage');
$notice = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $id = filter_var($_POST['collection_id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $order = filter_var($_POST['display_order'] ?? '', FILTER_VALIDATE_INT);
        $published = isset($_POST['is_published']) ? 1 : 0;
        if (!$id || $id < 1 || $name === '' || strlen($name) > 100 || strlen($description) > 500
            || $order === false || $order < 0 || $order > 10000) {
            throw new RuntimeException('Review the collection fields and try again.');
        }
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $save = $pdo->prepare(
                'UPDATE collections SET name = :name, description = :description,
                 display_order = :display_order, is_published = :published WHERE id = :id'
            );
            $save->execute([
                'name' => $name,
                'description' => $description,
                'display_order' => $order,
                'published' => $published,
                'id' => $id,
            ]);
            if ($save->rowCount() < 1) {
                $check = $pdo->prepare('SELECT id FROM collections WHERE id = :id');
                $check->execute(['id' => $id]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('The collection no longer exists.');
                }
            }
            audit_log((int) $admin['id'], 'collection.update', 'collection', (int) $id);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        $notice = 'Collection saved.';
    }

    $collections = database()->query(
        'SELECT c.id, c.slug, c.name, c.description, c.is_published, c.display_order,
                m.id AS media_id, m.alt_text, m.width, m.height
         FROM collections c LEFT JOIN media m ON m.id = c.media_id ORDER BY c.display_order'
    )->fetchAll();
    $body = $notice !== '' ? '<p class="notice">' . escape_html($notice) . '</p>' : '';
    $body .= '<p>Collection identifiers are fixed so product links remain stable. Published status controls public listings.</p>';
    foreach ($collections as $collection) {
        $body .= '<section class="panel"><h2>' . escape_html($collection['name']) . '</h2>';
        if ($collection['media_id'] !== null) {
            $body .= '<img class="admin-product-preview" src="/api/admin-media.php?source=library&id=' . (int) $collection['media_id'] . '" alt="' . escape_html($collection['alt_text']) . '">';
        }
        $body .= '<form method="post" action="/admin/collections.php">' . csrf_field()
            . '<input type="hidden" name="collection_id" value="' . (int) $collection['id'] . '"><div class="form-grid">'
            . '<div><label>Name<input name="name" maxlength="100" required value="' . escape_html($collection['name']) . '"></label></div>'
            . '<div><label>Display order<input name="display_order" type="number" min="0" max="10000" value="' . (int) $collection['display_order'] . '"></label></div>'
            . '<div class="form-full"><label>Description<textarea name="description" rows="3" maxlength="500">' . escape_html($collection['description']) . '</textarea></label></div>'
            . '<div class="form-full"><label class="inline-label"><input type="checkbox" name="is_published" value="1"' . ((int) $collection['is_published'] ? ' checked' : '') . '> Published</label></div>'
            . '</div><button class="button button-primary" type="submit">Save collection</button></form></section>';
    }
    admin_page('Collections', $body);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_page('Collections', '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p><p><a href="/admin/collections.php">Return to collections</a></p>');
} catch (Throwable $error) {
    app_log('Collection management failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Collections', '<p class="notice notice-error">Collections are temporarily unavailable. Check the private application log.</p>');
}
