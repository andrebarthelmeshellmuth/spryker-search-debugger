<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\ConvertStaticToSelfRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
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
        // The bare directory pattern alone doesn't reliably skip the FILES inside it -- fnmatch() needs
        // an exact string match, and a per-file path has a filename trailing the directory this pattern
        // matches. Confirmed empirically on the sibling search-ranking package: this let
        // RemoveUselessReturnTagRector reach into a regenerated *TesterActions.php file there. Both
        // forms kept since which one actually matches depends on how the caller passes the path.
        __DIR__ . '/tests/*/_support/_generated',
        __DIR__ . '/tests/*/_support/_generated/*',
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
        // Rewrites plain `=== null` / `!== null` checks on a nullable single-class type into
        // `instanceof \Fully\Qualified\ClassName` — strictly more verbose for a simple null check
        // (no added type-safety over the null check it replaces), breaks this codebase's consistent
        // === null idiom used everywhere else, and writes an inline FQCN instead of a use import,
        // which trips Spryker.Namespaces.UseStatement.
        FlipTypeControlToUseExclusiveTypeRector::class,
        // Direct contradiction of Spryker's own SprykerPreferStaticOverSelf sniff (active, not
        // excluded): converts static:: to self::, confirmed empirically as "Please use static::
        // instead of self::" on ExplanationParser.php.
        ConvertStaticToSelfRector::class,
    ])
    // Picks up the PHP floor (>=8.3) from composer.json.
    ->withPhpSets()
    // Both at the real ceiling for the installed rector/rector version: DeadCodeLevel::RULES has 68
    // entries (max index 67) and CodeQualityLevel::RULES has 77 (max index 76) — level numbers above
    // either ceiling are silently clamped to it by Rector's own LevelRulesResolver, so writing a higher
    // number here would just be inaccurate, not more aggressive.
    ->withDeadCodeLevel(67)
    ->withCodeQualityLevel(76)
    ->withoutParallel();
