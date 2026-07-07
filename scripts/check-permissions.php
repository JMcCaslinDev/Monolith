#!/usr/bin/env php
<?php
// ponytail: smallest check — owner role must include every permission in config
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$expected = config('permissions');
$stmt = db()->query(
    "SELECT p.name FROM role_permission rp
     JOIN roles r ON r.id = rp.role_id
     JOIN permissions p ON p.id = rp.permission_id
     WHERE r.name = 'owner'"
);
$ownerPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($expected, $ownerPerms);

if ($missing !== []) {
    fwrite(STDERR, "FAIL: owner missing permissions: " . implode(', ', $missing) . "\n");
    exit(1);
}

echo "OK: owner has all " . count($expected) . " configured permissions\n";
