<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

/*
 * Standalone PageIndexMap generation -- mirrors generate-transfers.php's own direct-instantiation bypass.
 * Regenerates Generated\Shared\Search\PageIndexMap from this package's real dependency
 * (spryker/search-elasticsearch)'s own default `page` mapping -- into src/Generated/, gitignored exactly
 * like transfer output already is, never committed. Replaces a copy of Spryker's own generated (and
 * Spryker-copyrighted) output that used to be checked into tests/_ci-standalone/Generated/ by mistake.
 */

declare(strict_types = 1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Spryker\Zed\SearchElasticsearch\Business\Installer\IndexMap\Generator\IndexMapGenerator;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$config = new SearchElasticsearchConfig();

$coreSchema = json_decode(
    (string)file_get_contents(APPLICATION_VENDOR_DIR . '/spryker/search-elasticsearch/src/Spryker/Shared/SearchElasticsearch/Schema/page.json'),
    true,
);

$indexDefinitionTransfer = (new \Generated\Shared\Transfer\IndexDefinitionTransfer())
    ->setIndexName('page')
    ->setMappings($coreSchema['mappings']);

$twig = new Environment(new FilesystemLoader($config->getIndexMapClassTemplateDirectory()));

(new IndexMapGenerator($config, $twig))->generate($indexDefinitionTransfer);

echo 'Index map generated into ' . $config->getClassTargetDirectory() . "\n";
