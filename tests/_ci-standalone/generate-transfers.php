<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

/*
 * Standalone transfer generation — no Zed Console app, no Locator. Direct instantiation of
 * TransferBusinessFactory bypasses AbstractFacade's project-override resolution entirely, since nothing
 * here needs a project to be able to override the generator; it is invoked exactly once, right before the
 * Portable suite runs.
 */

declare(strict_types = 1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Psr\Log\NullLogger;
use Spryker\Zed\Transfer\Business\TransferBusinessFactory;

(new TransferBusinessFactory())->createTransferGenerator(new NullLogger())->execute();

echo 'Transfers generated into ' . APPLICATION_SOURCE_DIR . "/Generated/Shared/Transfer/\n";
