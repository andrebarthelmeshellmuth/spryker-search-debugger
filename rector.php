<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveMixedDocblockOverruledByNativeTypeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
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
        // Spryker.Commenting.DocBlockParam (active in this project's phpcs.xml) requires exactly one
        // @param tag per method parameter, typed or not. This rule strips @param tags for natively
        // typed params, which produced 140 "Doc Block params do not match method signature" errors
        // across 58 files when tried — a direct, systemic contradiction of that convention.
        RemoveUselessParamTagRector::class,
        // Same DocBlockParam contradiction as above, narrower trigger: strips a single @param mixed
        // when the parameter is natively typed mixed, still breaking the required 1:1 count.
        RemoveMixedDocblockOverruledByNativeTypeRector::class,
    ])
    // Picks up the PHP floor (>=8.3) from composer.json.
    ->withPhpSets()
    // Gradual levels (0 = safest rules only). Raising in batches; stop at the first hit that
    // conflicts with established Spryker style rather than applying it automatically.
    ->withDeadCodeLevel(50)
    ->withCodeQualityLevel(50)
    ->withoutParallel();
