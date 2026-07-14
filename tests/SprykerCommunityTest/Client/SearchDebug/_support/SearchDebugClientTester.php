<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug;

use Codeception\Actor;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;

/**
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class SearchDebugClientTester extends Actor
{
    use _generated\SearchDebugClientTesterActions;

    /**
     * @return \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface
     */
    public function getSearchDebugClient(): SearchDebugClientInterface
    {
        return $this->getLocator()->searchDebug()->client();
    }
}
