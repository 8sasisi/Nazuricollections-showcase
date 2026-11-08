<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
$admin = require_admin('content.manage');
$notice = '';
$noticeType = 'success';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $pdo = database();
        $pdo->beginTransaction();
        $sections = [
            'hero' => ['heading' => 180, 'body' => 3000, 'button_label' => 80, 'button_url' => 255],
            'brand_story' => ['heading' => 180, 'body' => 3000, 'button_label' => 80, 'button_url' => 255],
        ];
        foreach ($sections as $key => $limits) {
            $values = [];
            foreach ($limits as $field => $limit) {
                $values[$field] = trim((string) ($_POST[$key . '_' . $field] ?? ''));
                if (strlen($values[$field]) > $limit) {
                    throw new RuntimeException('One or more content fields are too long.');
                }
            }
            if ($values['button_url'] !== '' && !preg_match('/^(?:pages\/[a-z0-9-]+\.html|#[a-z0-9_-]+)$/i', $values['button_url'])) {
                throw new RuntimeException('Buttons must link to a local page or an in-page section.');
            }
            $stmt = $pdo->prepare(
                'UPDATE homepage_sections SET heading = :heading, body = :body,
                 button_label = :button_label, button_url = :button_url WHERE section_key = :section_key'
            );
            $stmt->execute($values + ['section_key' => $key]);
        }

        foreach (['about', 'contact'] as $slug) {
            $title = trim((string) ($_POST[$slug . '_title'] ?? ''));
            $content = trim((string) ($_POST[$slug . '_content'] ?? ''));
            if ($title === '' || strlen($title) > 180 || strlen($content) > 10000) {
                throw new RuntimeException('Page titles are required and page text must be under 10,000 characters.');
            }
            $stmt = $pdo->prepare(
                'UPDATE pages SET title = :title, content = :content, updated_by = :updated_by WHERE slug = :slug'
            );
            $stmt->execute(['title' => $title, 'content' => $content, 'updated_by' => $admin['id'], 'slug' => $slug]);
        }
        audit_log((int) $admin['id'], 'content.update', 'site');
        $pdo->commit();
        $notice = 'Website content saved.';
    }

    $sections = [];
    foreach (database()->query('SELECT * FROM homepage_sections')->fetchAll() as $section) {
        $sections[$section['section_key']] = $section;
    }
    $pages = [];
    foreach (database()->query('SELECT slug, title, content FROM pages WHERE slug IN ("about", "contact")')->fetchAll() as $page) {
        $pages[$page['slug']] = $page;
    }
    $body = $notice !== '' ? '<p class="notice notice-' . escape_html($noticeType) . '">' . escape_html($notice) . '</p>' : '';
    $body .= '<form method="post" action="/admin/content.php">' . csrf_field();
    foreach (['hero' => 'Homepage hero', 'brand_story' => 'Homepage brand story'] as $key => $label) {
        $section = $sections[$key] ?? [];
        $body .= '<section class="panel"><h2>' . escape_html($label) . '</h2><div class="form-grid">'
            . content_input($key . '_heading', 'Heading', $section['heading'] ?? '')
            . '<div class="form-full"><label>Supporting text<textarea name="' . escape_html($key) . '_body" rows="4">' . escape_html($section['body'] ?? '') . '</textarea></label></div>'
            . content_input($key . '_button_label', 'Button label', $section['button_label'] ?? '')
            . content_input($key . '_button_url', 'Local button link', $section['button_url'] ?? '')
            . '</div></section>';
    }
    foreach (['about' => 'About Us', 'contact' => 'Contact Us'] as $slug => $label) {
        $page = $pages[$slug] ?? ['title' => $label, 'content' => ''];
        $body .= '<section class="panel"><h2>' . escape_html($label) . '</h2><div class="form-grid">'
            . content_input($slug . '_title', 'Page title', $page['title'])
            . '<div class="form-full"><label>Page text (plain text; no HTML)<textarea name="' . $slug . '_content" rows="7">' . escape_html($page['content']) . '</textarea></label></div>'
            . '</div></section>';
    }
    $body .= '<button class="button button-primary" type="submit">Save website content</button></form>';
    admin_page('Website content', $body);
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_page('Website content', '<p class="notice notice-error">' . escape_html($error->getMessage()) . '</p><p><a href="/admin/content.php">Return to website content</a></p>');
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('Website content page failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Website content', '<p class="notice notice-error">Website content is temporarily unavailable. Check the private application log.</p>');
}

function content_input(string $name, string $label, string $value): string
{
    return '<div><label>' . escape_html($label) . '<input name="' . escape_html($name) . '" maxlength="255" value="' . escape_html($value) . '"></label></div>';
}
