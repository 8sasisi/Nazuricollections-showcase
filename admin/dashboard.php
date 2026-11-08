<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_admin();

try {
    $counts = database()->query(
        'SELECT
            SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published,
            SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) AS drafts
         FROM products'
    )->fetch();
    $collections = (int) database()->query('SELECT COUNT(*) FROM collections WHERE is_published = 1')->fetchColumn();
    $activities = database()->query(
        'SELECT a.action, a.entity_type, a.created_at, u.name
         FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC LIMIT 8'
    )->fetchAll();
    $body = '<section class="stat-grid">'
        . '<article class="stat-card"><span>Published products</span><strong>' . (int) ($counts['published'] ?? 0) . '</strong></article>'
        . '<article class="stat-card"><span>Draft products</span><strong>' . (int) ($counts['drafts'] ?? 0) . '</strong></article>'
        . '<article class="stat-card"><span>Collections</span><strong>' . $collections . '</strong></article></section>'
        . '<section class="panel"><h2>Recent activity</h2>';
    if (!$activities) {
        $body .= '<p>No administrative activity has been recorded yet.</p>';
    } else {
        $body .= '<div class="table-wrap"><table><thead><tr><th>Activity</th><th>Administrator</th><th>Date (UTC)</th></tr></thead><tbody>';
        foreach ($activities as $activity) {
            $body .= '<tr><td>' . escape_html($activity['action']) . '</td><td>' . escape_html($activity['name'] ?? 'System') . '</td><td>' . escape_html($activity['created_at']) . '</td></tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</section>';
    admin_page('Overview', $body);
} catch (Throwable $error) {
    app_log('Dashboard unavailable: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Overview', '<p class="notice notice-error">Dashboard information is temporarily unavailable. Check the private application log.</p>');
}
