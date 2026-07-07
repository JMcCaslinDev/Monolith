#!/usr/bin/env php
<?php

// ponytail: fail fast if registry and DB drift apart
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$registry = config('registry');
$expectedPerms = array_column($registry['permissions'], 'name');
$dbPerms = db()->query('SELECT name FROM permissions ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$missingInDb = array_diff($expectedPerms, $dbPerms);
$extraInDb = array_diff($dbPerms, $expectedPerms);

$failed = false;
if ($missingInDb !== []) {
    fwrite(STDERR, "FAIL: registry permissions missing in DB: " . implode(', ', $missingInDb) . "\n");
    $failed = true;
}
if ($extraInDb !== []) {
    fwrite(STDERR, "WARN: DB permissions not in registry: " . implode(', ', $extraInDb) . "\n");
}

$ownerPerms = db()->query(
    "SELECT p.name FROM role_permission rp
     JOIN roles r ON r.id = rp.role_id
     JOIN permissions p ON p.id = rp.permission_id
     WHERE r.name = 'owner'"
)->fetchAll(PDO::FETCH_COLUMN);
$ownerMissing = array_diff($expectedPerms, $ownerPerms);
if ($ownerMissing !== []) {
    fwrite(STDERR, "FAIL: owner missing: " . implode(', ', $ownerMissing) . "\n");
    $failed = true;
}

if ($failed) {
    exit(1);
}

echo "OK: registry synced — " . count($expectedPerms) . " permissions, "
    . count($registry['events']) . " event types documented\n";
