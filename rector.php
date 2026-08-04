<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->withSkip([
        __DIR__ . '/tests/*/_support/_generated',
        __DIR__ . '/tests/*/_output',
        __DIR__ . '/tests/*/_data',
        // Typed class constants (PHP 8.3) aren't understood by the installed phpcs 3.7.1
        // (Generic.NamingConventions.UpperCaseConstantName misreads the type as the constant name).
        AddTypeToConstRector::class,
    ])
    // Picks up the PHP floor (>=8.3) from composer.json.
    ->withPhpSets()
    // Gradual levels (0 = safest rules only). Raising in batches; stop at the first hit that
    // conflicts with established Spryker style rather than applying it automatically.
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withoutParallel();
