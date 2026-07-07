-- Dev Tools package (replaces Tools)

-- Rename project permissions
UPDATE permissions SET name = 'projects.devtools.view', description = 'See Dev Tools on dashboard'
WHERE name = 'projects.tools.view';
UPDATE permissions SET name = 'projects.devtools.open', description = 'Open Dev Tools project'
WHERE name = 'projects.tools.open';

DELETE FROM permissions WHERE name = 'tools.json-converter.use';

INSERT INTO permissions (name, description, category) VALUES
    ('projects.devtools.view', 'See Dev Tools on dashboard', 'projects'),
    ('projects.devtools.open', 'Open Dev Tools project', 'projects'),
    ('devtools.converters.use', 'Use all Converters tools', 'devtools.converters'),
    ('devtools.converters.cron-parser.use', 'Use Cron parser', 'devtools.converters'),
    ('devtools.converters.date.use', 'Use Date converter', 'devtools.converters'),
    ('devtools.converters.json-table.use', 'Use JSON > Table', 'devtools.converters'),
    ('devtools.converters.json-yaml.use', 'Use JSON <> YAML', 'devtools.converters'),
    ('devtools.converters.number-base.use', 'Use Number Base', 'devtools.converters'),
    ('devtools.encoders.use', 'Use all Encoders / Decoders tools', 'devtools.encoders'),
    ('devtools.encoders.base64-image.use', 'Use Base64 Image', 'devtools.encoders'),
    ('devtools.encoders.base64-text.use', 'Use Base64 Text', 'devtools.encoders'),
    ('devtools.encoders.certificate.use', 'Use Certificate', 'devtools.encoders'),
    ('devtools.encoders.gzip.use', 'Use GZip', 'devtools.encoders'),
    ('devtools.encoders.html.use', 'Use HTML encoder', 'devtools.encoders'),
    ('devtools.encoders.jwt.use', 'Use JWT decoder', 'devtools.encoders'),
    ('devtools.encoders.qr-code.use', 'Use QR Code', 'devtools.encoders'),
    ('devtools.encoders.url.use', 'Use URL encoder', 'devtools.encoders'),
    ('devtools.formatters.use', 'Use all Formatters tools', 'devtools.formatters'),
    ('devtools.formatters.json.use', 'Use JSON formatter', 'devtools.formatters'),
    ('devtools.formatters.sql.use', 'Use SQL formatter', 'devtools.formatters'),
    ('devtools.formatters.xml.use', 'Use XML formatter', 'devtools.formatters'),
    ('devtools.generators.use', 'Use all Generators tools', 'devtools.generators'),
    ('devtools.generators.hash.use', 'Use Hash / Checksum', 'devtools.generators'),
    ('devtools.generators.lorem-ipsum.use', 'Use Lorem Ipsum', 'devtools.generators'),
    ('devtools.generators.password.use', 'Use Password generator', 'devtools.generators'),
    ('devtools.generators.uuid.use', 'Use UUID generator', 'devtools.generators'),
    ('devtools.graphic.use', 'Use all Graphic tools', 'devtools.graphic'),
    ('devtools.graphic.color-blindness.use', 'Use Color Blindness Simulator', 'devtools.graphic'),
    ('devtools.graphic.image-converter.use', 'Use Image Converter', 'devtools.graphic'),
    ('devtools.testers.use', 'Use all Testers tools', 'devtools.testers'),
    ('devtools.testers.jsonpath.use', 'Use JSONPath tester', 'devtools.testers'),
    ('devtools.testers.regex.use', 'Use RegEx tester', 'devtools.testers'),
    ('devtools.testers.xml-tester.use', 'Use XML tester', 'devtools.testers'),
    ('devtools.text.use', 'Use all Text tools', 'devtools.text'),
    ('devtools.text.escape-unescape.use', 'Use Escape / Unescape', 'devtools.text'),
    ('devtools.text.list-compare.use', 'Use List Compare', 'devtools.text'),
    ('devtools.text.markdown-preview.use', 'Use Markdown Preview', 'devtools.text'),
    ('devtools.text.text-analyzer.use', 'Use Analyzer & Utilities', 'devtools.text'),
    ('devtools.text.text-compare.use', 'Use Compare', 'devtools.text')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name LIKE 'devtools.%' OR p.name IN ('projects.devtools.view', 'projects.devtools.open')
WHERE r.name IN ('owner', 'member');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name = 'projects.devtools.view'
WHERE r.name = 'viewer';

-- Navbar pins: tools → devtools
UPDATE user_settings
SET setting_value = REPLACE(setting_value, '"tools"', '"devtools"')
WHERE setting_key = 'navbar_projects' AND setting_value LIKE '%"tools"%';
