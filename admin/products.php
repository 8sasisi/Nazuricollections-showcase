<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/uploads.php';
$admin = require_admin('products.manage');

$notice = '';
$noticeType = 'success';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $pdo = database();

        if ($action === 'delete') {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new RuntimeException('Select a valid product.');
            }
            $imageQuery = $pdo->prepare('SELECT stored_name FROM product_images WHERE product_id = :id');
            $imageQuery->execute(['id' => $id]);
            $oldImages = array_column($imageQuery->fetchAll(), 'stored_name');
            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $delete->execute(['id' => $id]);
            if ($delete->rowCount() !== 1) {
                throw new RuntimeException('The product no longer exists.');
            }
            audit_log((int) $admin['id'], 'product.delete', 'product', (int) $id);
            $pdo->commit();
            foreach ($oldImages as $oldImage) {
                remove_stored_image($oldImage);
            }
            $notice = 'Product deleted.';
        } elseif ($action === 'remove_image') {
            $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new RuntimeException('Select a valid product.');
            }
            $imageQuery = $pdo->prepare('SELECT id, stored_name FROM product_images WHERE product_id = :id LIMIT 1');
            $imageQuery->execute(['id' => $id]);
            $image = $imageQuery->fetch();
            if (!$image) {
                throw new RuntimeException('This product does not have a photograph.');
            }
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM product_images WHERE id = :id')->execute(['id' => $image['id']]);
            audit_log((int) $admin['id'], 'product.image_remove', 'product', (int) $id);
            $pdo->commit();
            remove_stored_image($image['stored_name']);
            $notice = 'Product photograph removed.';
        } elseif ($action === 'save') {
            $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $colors = trim((string) ($_POST['colors'] ?? ''));
            $collectionSlug = (string) ($_POST['collection'] ?? '');
            $displayOrder = filter_var($_POST['display_order'] ?? '0', FILTER_VALIDATE_INT);
            $priceInput = trim((string) ($_POST['price'] ?? ''));
            $published = isset($_POST['is_published']) ? 1 : 0;
            if ($name === '' || strlen($name) > 160 || strlen($description) > 10000 || strlen($colors) > 500
                || !in_array($collectionSlug, ['abayas', 'magauni'], true) || $displayOrder === false
                || $displayOrder < 0 || $displayOrder > 100000
                || ($id !== false && $id < 1)) {
                throw new RuntimeException('Review the product fields and try again.');
            }
            $price = null;
            if ($priceInput !== '') {
                if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $priceInput)) {
                    throw new RuntimeException('Price must be a non-negative amount with up to two decimals.');
                }
                $price = $priceInput;
            }
            $upload = store_image_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            $collectionStmt = $pdo->prepare('SELECT id FROM collections WHERE slug = :slug');
            $collectionStmt->execute(['slug' => $collectionSlug]);
            $collectionId = $collectionStmt->fetchColumn();
            if (!$collectionId) {
                if ($upload) {
                    remove_stored_image($upload['stored_name']);
                }
                throw new RuntimeException('The selected collection is unavailable.');
            }
            $categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug');
            $categoryStmt->execute(['slug' => $collectionSlug === 'abayas' ? 'abaya' : 'gown']);
            $categoryId = $categoryStmt->fetchColumn() ?: null;
            $oldImage = null;

            try {
                $pdo->beginTransaction();
                if ($id) {
                    $existing = $pdo->prepare('SELECT id FROM products WHERE id = :id');
                    $existing->execute(['id' => $id]);
                    if (!$existing->fetchColumn()) {
                        throw new RuntimeException('The product no longer exists.');
                    }
                    $save = $pdo->prepare(
                        'UPDATE products SET collection_id = :collection_id, category_id = :category_id, name = :name,
                         description = :description, colors = :colors, price = :price, is_published = :published,
                         display_order = :display_order WHERE id = :id'
                    );
                    $save->execute([
                        'collection_id' => $collectionId,
                        'category_id' => $categoryId,
                        'name' => $name,
                        'description' => $description,
                        'colors' => $colors,
                        'price' => $price,
                        'published' => $published,
                        'display_order' => $displayOrder,
                        'id' => $id,
                    ]);
                    $entityId = (int) $id;
                } else {
                    $slugBase = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
                    $slug = substr($slugBase ?: 'product', 0, 130) . '-' . bin2hex(random_bytes(5));
                    $save = $pdo->prepare(
                        'INSERT INTO products
                         (collection_id, category_id, name, slug, description, colors, price, is_published, display_order, created_by)
                         VALUES (:collection_id, :category_id, :name, :slug, :description, :colors, :price, :published, :display_order, :created_by)'
                    );
                    $save->execute([
                        'collection_id' => $collectionId,
                        'category_id' => $categoryId,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'colors' => $colors,
                        'price' => $price,
                        'published' => $published,
                        'display_order' => $displayOrder,
                        'created_by' => $admin['id'],
                    ]);
                    $entityId = (int) $pdo->lastInsertId();
                }

                if ($upload) {
                    $oldStmt = $pdo->prepare('SELECT stored_name FROM product_images WHERE product_id = :id');
                    $oldStmt->execute(['id' => $entityId]);
                    $oldImage = $oldStmt->fetchColumn() ?: null;
                    $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute(['id' => $entityId]);
                    $imageInsert = $pdo->prepare(
                        'INSERT INTO product_images (product_id, stored_name, mime_type, byte_size, width, height)
                         VALUES (:product_id, :stored_name, :mime_type, :byte_size, :width, :height)'
                    );
                    $imageInsert->execute([
                        'product_id' => $entityId,
                        'stored_name' => $upload['stored_name'],
                        'mime_type' => $upload['mime_type'],
                        'byte_size' => $upload['byte_size'],
                        'width' => $upload['width'],
                        'height' => $upload['height'],
                    ]);
                }
                audit_log((int) $admin['id'], $id ? 'product.update' : 'product.create', 'product', $entityId);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($upload) {
                    remove_stored_image($upload['stored_name']);
                }
                throw $error;
            }
            if ($oldImage) {
                remove_stored_image($oldImage);
            }
            $notice = $id ? 'Product updated.' : 'Product created.';
        } else {
            throw new RuntimeException('Unknown product action.');
        }
    }

    $products = database()->query(
        'SELECT p.id, p.name, p.description, p.colors, p.price, p.is_published, p.display_order,
            c.slug AS collection, i.id AS image_id, i.stored_name, i.width, i.height, i.byte_size
         FROM products p JOIN collections c ON c.id = p.collection_id
         LEFT JOIN product_images i ON i.product_id = p.id AND i.display_order = 0
         ORDER BY c.display_order, p.display_order, p.id DESC'
    )->fetchAll();

    $body = $notice !== '' ? '<p class="notice notice-' . escape_html($noticeType) . '">' . escape_html($notice) . '</p>' : '';
    $body .= '<section class="panel"><h2>Add a product</h2>' . product_form(null) . '</section>';
    $body .= '<section class="panel"><h2>Products</h2>';
    if (!$products) {
        $body .= '<p>No products yet. Product listings are empty until verified business information is entered.</p>';
    }
    foreach ($products as $product) {
        $body .= '<details class="product-editor"><summary>' . escape_html($product['name']) . ' <span class="badge">'
            . ((int) $product['is_published'] ? 'Published' : 'Draft') . '</span></summary>';
        if ($product['image_id'] !== null) {
            $body .= '<img class="admin-product-preview" src="/api/admin-media.php?id=' . (int) $product['image_id'] . '" alt="">';
            $body .= '<p class="muted">Image: ' . (int) $product['width'] . ' × ' . (int) $product['height'] . ' px · ' . number_format((int) $product['byte_size']) . ' bytes</p>';
            $body .= '<form method="post" action="/admin/products.php">' . csrf_field() . '<input type="hidden" name="action" value="remove_image">'
                . '<input type="hidden" name="product_id" value="' . (int) $product['id'] . '"><button class="button button-danger" type="submit">Remove photograph</button></form>';
        }
        $body .= product_form($product);
        $body .= '<form method="post" action="/admin/products.php" data-confirm="Delete this product and its image?">'
            . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="'
            . (int) $product['id'] . '"><button class="button button-danger" type="submit">Delete product</button></form></details>';
    }
    $body .= '</section>';
    admin_page('Products', $body);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $noticeType = 'error';
    $body = '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p><p><a href="/admin/products.php">Return to product management</a></p>';
    admin_page('Products', $body);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('Product management failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Products', '<p class="notice notice-error">Product management is temporarily unavailable. Check the private application log.</p>');
}

function product_form(?array $product): string
{
    $isEdit = $product !== null;
    $value = static fn(string $key, string $fallback = ''): string => escape_html((string) ($product[$key] ?? $fallback));
    $body = '<form method="post" action="/admin/products.php" enctype="multipart/form-data">'
        . csrf_field() . '<input type="hidden" name="action" value="save">';
    if ($isEdit) {
        $body .= '<input type="hidden" name="product_id" value="' . (int) $product['id'] . '">';
    }
    $body .= '<div class="form-grid"><div><label>Product name<input name="name" maxlength="160" required value="' . $value('name') . '"></label></div>'
        . '<div><label>Collection<select name="collection" required><option value="abayas"' . (($product['collection'] ?? '') === 'abayas' ? ' selected' : '') . '>Abayas</option><option value="magauni"' . (($product['collection'] ?? '') === 'magauni' ? ' selected' : '') . '>Magauni / Gowns</option></select></label></div>'
        . '<div><label>Colors (comma separated)<input name="colors" maxlength="500" value="' . $value('colors') . '"></label></div>'
        . '<div><label>Price (optional)<input name="price" inputmode="decimal" pattern="\\d{1,8}(\\.\\d{1,2})?" value="' . $value('price') . '"></label></div>'
        . '<div><label>Display order<input name="display_order" type="number" min="0" max="100000" value="' . $value('display_order', '0') . '"></label></div>'
        . '<div><label>Product photograph (JPEG, PNG, WebP · max 5 MB)<input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label></div>'
        . '<div class="form-full"><label>Description<textarea name="description" rows="4" maxlength="10000">' . $value('description') . '</textarea></label></div>'
        . '<div class="form-full"><label class="inline-label"><input name="is_published" type="checkbox" value="1"' . ((int) ($product['is_published'] ?? 0) ? ' checked' : '') . '> Published on the public website</label></div>'
        . '<div class="form-full"><button class="button button-primary" type="submit">' . ($isEdit ? 'Save changes' : 'Create product') . '</button></div></div></form>';
    return $body;
}
