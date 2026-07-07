<?php

declare(strict_types=1);

$registry = require __DIR__ . '/registry.php';

return array_column($registry['permissions'], 'name');
