#!/usr/bin/env php
<?php

declare(strict_types=1);

// Deletes audit events older than EVENTS_RETENTION_DAYS (default 90). Run daily from cron or a scheduled task.
require __DIR__ . '/../app/bootstrap.php';

$days = (int) ($_ENV['EVENTS_RETENTION_DAYS'] ?? 90);
$removed = events()->prune($days);
echo "Pruned {$removed} audit events older than {$days} days\n";
