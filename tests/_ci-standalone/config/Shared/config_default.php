<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

/*
 * Minimal config for standalone transfer generation and for the `@group Portable` tests that construct
 * (but never connect) a search client config object. Not a real project's config_default.php — just the
 * handful of keys the transfer generator's own class-resolver and the search client config classes read
 * before anything ever tries to reach a network.
 */

declare(strict_types = 1);

use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Shared\SearchElasticsearch\SearchElasticsearchConstants;

$config[KernelConstants::PROJECT_NAMESPACES] = [];
$config[KernelConstants::CORE_NAMESPACES] = ['SprykerCommunity', 'Spryker'];

// Placeholder values only — nothing in the Portable subset makes a live Elasticsearch/OpenSearch call.
$config[SearchElasticsearchConstants::TRANSPORT] = 'http';
$config[SearchElasticsearchConstants::HOST] = 'localhost';
$config[SearchElasticsearchConstants::PORT] = 9200;
