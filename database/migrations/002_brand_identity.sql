INSERT IGNORE INTO permissions (slug, name)
VALUES ('brand.manage', 'Manage brand identity');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'administrator'
  AND p.slug = 'brand.manage'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
    ('brand_name', 'Grant Fashion'),
    ('brand_tagline', ''),
    ('brand_description', ''),
    ('brand_logo', '');
