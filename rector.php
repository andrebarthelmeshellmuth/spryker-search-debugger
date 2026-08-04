<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Concat\RemoveConcatAutocastRector;
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
        // SearchStringAnalyzer casts three structurally identical `$x['name'] ?? '?'` values to
        // string (tokenizer/filter/analyzer) — two feed a native `string $name` parameter, so this
        // rule can't touch those; letting it strip the cast on the third (plain concatenation) would
        // silently break that established three-way consistency for no readability gain.
        RemoveConcatAutocastRector::class => [
            __DIR__ . '/src/SprykerCommunity/Client/SearchDebug/Analyzer/SearchStringAnalyzer.php',
        ],
    ])
    // Picks up the PHP floor (>=8.3) from composer.json.
    ->withPhpSets()
    // Gradual levels (0 = safest rules only). Raising in batches; stop at the first hit that
    // conflicts with established Spryker style rather than applying it automatically.
    ->withDeadCodeLevel(22)
    ->withCodeQualityLevel(22)
    ->withoutParallel();
