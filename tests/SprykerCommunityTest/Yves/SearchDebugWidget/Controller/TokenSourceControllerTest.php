<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Controller;

use Codeception\Test\Unit;
use ReflectionMethod;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\TokenSourceController;

/**
 * `indexAction()` itself needs a real Silex/Symfony container (`$this->can()`, `$this->getFactory()`,
 * `$this->getLocale()`, `$this->view()`) and is left to integration coverage — but `sanitizeFieldBoosts()`
 * has no framework dependency at all, so it is exercised directly here via reflection, `new`-instantiating
 * the controller (`AbstractController` has no required constructor arguments).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group TokenSourceControllerTest
 * Add your own group annotations below this line
 */
class TokenSourceControllerTest extends Unit
{
    public function testSanitizeFieldBoostsKeepsWellFormedFieldNameToIntegerBoostPairs(): void
    {
        // Arrange
        $rawFieldBoosts = ['full-text' => '1', 'full-text-boosted' => 5];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text' => 1, 'full-text-boosted' => 5], $fieldBoosts);
    }

    /**
     * A numeric query-string key (e.g. `boosts[0]=5`) parses to an int array key in PHP, not a string one
     * — such an entry is dropped rather than coerced, since a boost is only ever meaningful keyed by a
     * real field name.
     */
    public function testSanitizeFieldBoostsDropsEntriesWithANonStringFieldName(): void
    {
        // Arrange
        $rawFieldBoosts = [0 => '5', 'full-text' => '3'];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text' => 3], $fieldBoosts);
    }

    /**
     * A malformed/hand-edited `boosts[field][]=x` query string produces a nested array value where a
     * scalar boost is expected — dropped defensively rather than letting the `(int)` cast throw or coerce
     * an array into a nonsensical number.
     */
    public function testSanitizeFieldBoostsDropsEntriesWhoseBoostValueIsAnArray(): void
    {
        // Arrange
        $rawFieldBoosts = ['full-text' => ['nested', 'array'], 'full-text-boosted' => '5'];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text-boosted' => 5], $fieldBoosts);
    }

    public function testSanitizeFieldBoostsReturnsAnEmptyArrayForAnEmptyInput(): void
    {
        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts([]);

        // Assert
        $this->assertSame([], $fieldBoosts);
    }

    /**
     * A non-numeric boost string casts to `0` via PHP's `(int)` cast rather than being dropped — the
     * method only guards against structurally wrong shapes (non-string keys, array values), not against
     * nonsensical-but-scalar boost values.
     */
    public function testSanitizeFieldBoostsCastsANonNumericBoostStringToZero(): void
    {
        // Arrange
        $rawFieldBoosts = ['full-text' => 'not-a-number'];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text' => 0], $fieldBoosts);
    }

    /**
     * @param array<mixed> $rawFieldBoosts
     *
     * @return array<string, int>
     */
    protected function invokeSanitizeFieldBoosts(array $rawFieldBoosts): array
    {
        $reflectionMethod = new ReflectionMethod(TokenSourceController::class, 'sanitizeFieldBoosts');

        return $reflectionMethod->invoke(new TokenSourceController(), $rawFieldBoosts);
    }
}
