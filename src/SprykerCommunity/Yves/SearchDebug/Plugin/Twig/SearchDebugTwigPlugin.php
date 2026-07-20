<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebug\Plugin\Twig;

use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\TwigExtension\Dependency\Plugin\TwigPluginInterface;
use Spryker\Yves\Kernel\AbstractPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * Registers `searchDebugTokenColors(tokens)` for the storefront templates.
 *
 * Assigning a colour class per query token is pure presentation derived from data the template already
 * has, so it does not need a controller. It previously lived in the shop's own `CatalogController` as
 * part of the package's install instructions, which meant every adopter copied the same helper method
 * into their codebase — and any shop already overriding that controller had to merge it by hand. Moving
 * it here removes that step entirely.
 *
 * Colours cycle with `%` so a query with more tokens than {@see SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT}
 * reuses classes rather than emitting a class name no stylesheet defines.
 */
class SearchDebugTwigPlugin extends AbstractPlugin implements TwigPluginInterface
{
    /**
     * @var string
     */
    protected const FUNCTION_NAME_TOKEN_COLORS = 'searchDebugTokenColors';

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $container is mandated by TwigPluginInterface.
     *
     * @param \Twig\Environment $twig
     * @param \Spryker\Service\Container\ContainerInterface $container
     *
     * @return \Twig\Environment
     */
    public function extend(Environment $twig, ContainerInterface $container): Environment
    {
        $twig->addFunction(new TwigFunction(
            static::FUNCTION_NAME_TOKEN_COLORS,
            function (array $queryTokens): array {
                return $this->getTokenColorClasses($queryTokens);
            },
        ));

        return $twig;
    }

    /**
     * @param array<string> $queryTokens
     *
     * @return array<string, string>
     */
    protected function getTokenColorClasses(array $queryTokens): array
    {
        $colorClassByToken = [];

        foreach (array_values($queryTokens) as $index => $token) {
            $colorClassByToken[$token] = sprintf(
                SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN,
                ($index % SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT) + 1,
            );
        }

        return $colorClassByToken;
    }
}
