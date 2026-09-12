<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withDeadCodeLevel(level: 100)
    ->withCodeQualityLevel(100)
    ->withTypeCoverageLevel(100)
     ->withPhpSets(php82: true);
