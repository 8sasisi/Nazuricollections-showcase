<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

try {
    $collection = $_GET['collection'] ?? null;
    if ($collection !== null && (!is_string($collection) || !preg_match('/^[a-z0-9-]{1,60}$/', $collection))) {
        json_response(['error' => 'Invalid collection.'], 400);
    }

    $sql = 'SELECT p.id, p.name, p.description, p.colors, p.price, p.display_order,
           c.slug AS collection, c.name AS collection_name, i.id AS image_id,
           currency.setting_value AS currency_code
    FROM products p
    JOIN collections c ON c.id = p.collection_id AND c.is_published = 1
    LEFT JOIN product_images i ON i.product_id = p.id AND i.display_order = 0
    LEFT JOIN site_settings currency ON currency.setting_key = \'currency_code\'
            WHERE p.is_published = 1';
    $params = [];
    if ($collection !== null) {
        $sql .= ' AND c.slug = :collection';
        $params['collection'] = $collection;
    }
    $sql .= ' ORDER BY c.display_order, p.display_order, p.id LIMIT 100';

    $stmt = database()->prepare($sql);
    $stmt->execute($params);
    $products = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'colors' => $row['colors'] === '' ? [] : array_map('trim', explode(',', $row['colors'])),
            'price' => $row['price'] === null || !preg_match('/^[A-Z]{3}$/', (string) $row['currency_code']) ? null : (float) $row['price'],
            'currency' => preg_match('/^[A-Z]{3}$/', (string) $row['currency_code']) ? $row['currency_code'] : null,
            'category' => $row['collection_name'],
            'collection' => $row['collection'],
            'image' => $row['image_id'] === null ? null : '/api/media.php?source=product&id=' . (int) $row['image_id'],
        ];
    }, $stmt->fetchAll());
    json_response(['products' => $products]);
} catch (Throwable $error) {
    app_log('Public products request failed: ' . $error->getMessage());
    json_response(['error' => 'Product information is temporarily unavailable.'], 503);
}
