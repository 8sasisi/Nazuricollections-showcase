<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

try {
    $sections = database()->query(
        'SELECT h.section_key, h.heading, h.body, h.button_label, h.button_url,
                h.media_id, m.alt_text AS image_alt
         FROM homepage_sections h LEFT JOIN media m ON m.id = h.media_id
         WHERE h.is_published = 1 ORDER BY h.display_order'
    )->fetchAll();
    $settings = database()->query(
        "SELECT setting_key, setting_value FROM site_settings
         WHERE setting_key IN ('business_phone', 'business_whatsapp', 'instagram_url', 'tiktok_url', 'currency_code')"
    )->fetchAll();
    $pages = database()->query(
        'SELECT slug, title, content FROM pages WHERE is_published = 1 AND slug IN ("about", "contact")'
    )->fetchAll();

    $settingMap = [];
    foreach ($settings as $setting) {
        $settingMap[$setting['setting_key']] = $setting['setting_value'];
    }
    $pageMap = [];
    foreach ($pages as $page) {
        $pageMap[$page['slug']] = ['title' => $page['title'], 'content' => $page['content']];
    }
    $sectionMap = [];
    foreach ($sections as $section) {
        $section['image'] = $section['media_id'] === null ? null : '/api/media.php?id=' . (int) $section['media_id'];
        $sectionMap[$section['section_key']] = $section;
    }
    $collections = database()->query(
        'SELECT c.slug, c.name, c.description, c.media_id, m.alt_text AS image_alt
         FROM collections c LEFT JOIN media m ON m.id = c.media_id
         WHERE c.is_published = 1 ORDER BY c.display_order'
    )->fetchAll();
    $collectionMap = [];
    foreach ($collections as $collection) {
        $collection['image'] = $collection['media_id'] === null ? null : '/api/media.php?id=' . (int) $collection['media_id'];
        $collectionMap[$collection['slug']] = $collection;
    }
    json_response([
        'sections' => $sectionMap,
        'collections' => $collectionMap,
        'settings' => $settingMap,
        'pages' => $pageMap,
    ]);
} catch (Throwable $error) {
    app_log('Public site content request failed: ' . $error->getMessage());
    json_response(['error' => 'Website information is temporarily unavailable.'], 503);
}
