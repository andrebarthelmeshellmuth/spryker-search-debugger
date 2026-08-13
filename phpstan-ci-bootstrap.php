<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

/*
 * Standalone CI bootstrap for `composer phpstan-ci` — replaces the real host-shop
 * `phpstan-bootstrap.php` that `phpstan.neon`'s own `bootstrapFiles` points at (that file only exists
 * inside a real Spryker project, one level above `vendor/spryker-community/search-debug/`). This
 * package needs none of that file's IDE-AutoCompletion requires — see phpstan.ci.neon for how the
 * resulting gaps are handled instead.
 */

declare(strict_types = 1);

define('APPLICATION_ROOT_DIR', __DIR__);
define('APPLICATION_VENDOR_DIR', APPLICATION_ROOT_DIR . '/vendor');
define('APPLICATION_SOURCE_DIR', APPLICATION_ROOT_DIR . '/src');
define('APPLICATION', '');
define('APPLICATION_ENV', '');
define('APPLICATION_STORE', '');
define('APPLICATION_CODE_BUCKET', '');
