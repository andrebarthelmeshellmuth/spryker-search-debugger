<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Controller;

use Spryker\Yves\Kernel\Controller\AbstractController;
use Spryker\Yves\Kernel\PermissionAwareTrait;
use Spryker\Yves\Kernel\View\View;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reached from a "view full definition" link on the token-analysis page, only shown there when a
 * component's config was too long to display inline (see `ComponentDefinitionFormatter`'s `truncated`
 * flag) — this page re-fetches the SAME component server-side and shows its config in full, untruncated.
 *
 * @method \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory getFactory()
 */
class ComponentConfigController extends AbstractController
{
    use PermissionAwareTrait;

    /**
     * @var string
     */
    protected const PARAM_TYPE = 'type';

    /**
     * @var string
     */
    protected const PARAM_NAME = 'name';

    /**
     * @var array<string>
     */
    protected const ALLOWED_COMPONENT_KINDS = [
        IndexSchemaMapper::COMPONENT_KIND_TOKENIZER,
        IndexSchemaMapper::COMPONENT_KIND_FILTER,
        IndexSchemaMapper::COMPONENT_KIND_CHAR_FILTER,
    ];

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function indexAction(Request $request): View
    {
        if (!$this->can(SeeSearchDebugInfoPermissionPlugin::KEY)) {
            throw new AccessDeniedHttpException();
        }

        $componentKind = (string)$request->query->get(static::PARAM_TYPE, '');
        $componentName = (string)$request->query->get(static::PARAM_NAME, '');

        if (!in_array($componentKind, static::ALLOWED_COMPONENT_KINDS, true) || $componentName === '') {
            throw new BadRequestHttpException(sprintf(
                '`%s` query parameter must be one of `%s`, and `%s` is required.',
                static::PARAM_TYPE,
                implode('`, `', static::ALLOWED_COMPONENT_KINDS),
                static::PARAM_NAME,
            ));
        }

        $component = $this->getFactory()->getSearchDebugClient()->getComponentConfig($componentKind, $componentName);

        if ($component === null) {
            throw new NotFoundHttpException(sprintf(
                'No %s named "%s" was found in the live index schema.',
                $componentKind,
                $componentName,
            ));
        }

        return $this->view(
            [
                'name' => $component['name'],
                'type' => $component['type'],
                'config' => $this->getFactory()->createComponentConfigFormatter()->format($component['config']),
            ],
            [],
            '@SearchDebugWidget/views/component-config/component-config.twig',
        );
    }
}
