<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/security.php';
require_admin('audit.view');

try {
    $entries = database()->query(
        'SELECT a.action, a.entity_type, a.entity_id, a.ip_address, a.metadata_json, a.created_at, u.name
         FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC LIMIT 200'
    )->fetchAll();
    $body = '<section class="panel"><h2>Latest 200 events (UTC)</h2><div class="table-wrap"><table><thead><tr><th>When</th><th>Administrator</th><th>Action</th><th>Object</th><th>IP address</th><th>Details</th></tr></thead><tbody>';
    foreach ($entries as $entry) {
        $metadata = $entry['metadata_json'] ? json_decode($entry['metadata_json'], true) : [];
        $body .= '<tr><td>' . escape_html($entry['created_at']) . '</td><td>' . escape_html($entry['name'] ?? 'System / unknown') . '</td><td>'
            . escape_html($entry['action']) . '</td><td>' . escape_html(trim(($entry['entity_type'] ?? '') . ' ' . ($entry['entity_id'] ?? '')))
            . '</td><td>' . escape_html($entry['ip_address']) . '</td><td>' . escape_html(is_array($metadata) ? json_encode($metadata) : '') . '</td></tr>';
    }
    $body .= '</tbody></table></div></section>';
    admin_page('Activity logs', $body);
} catch (Throwable $error) {
    app_log('Activity log page failed: ' . $error->getMessage());
    http_response_code(503);
    admin_page('Activity logs', '<p class="notice notice-error">Activity logs are temporarily unavailable. Check the private application log.</p>');
}
