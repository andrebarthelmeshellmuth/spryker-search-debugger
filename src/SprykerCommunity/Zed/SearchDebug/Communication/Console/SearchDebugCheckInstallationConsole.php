<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchDebug\Communication\Console;

use Elastica\Client;
use Elastica\Query;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Client\SearchDebug\SearchDebugClient;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Diagnoses a search-debug installation.
 *
 * Installing this package means wiring several independent things, and almost every one of them fails
 * SILENTLY when missed — the overlay simply does not appear, with nothing in any log to say why. That
 * turns a five-minute mistake into an afternoon of bisecting. This command checks each prerequisite it
 * can reach from the CLI and names the exact remedy for whatever is wrong.
 *
 * Deliberately honest about its own limits: it runs in Zed, so it cannot introspect the Yves DI
 * container or confirm that the storefront templates actually render the widget. It verifies that the
 * classes exist and that everything ELSE those steps depend on is in place, and says plainly which
 * checks it could not make.
 *
 * Complementary counterpart:
 * {@see \SprykerCommunity\Yves\SearchDebugWidget\Controller\CheckInstallationController} (the
 * `/search-debug/check-installation` page) closes exactly the Yves-side gap this command names above —
 * event listener, Twig function and widget route registration — by running from inside the real Yves DI
 * container. It does not re-check anything this command already covers (engine reachability, page index,
 * explain support); run both for a full picture.
 */
class SearchDebugCheckInstallationConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-debug:check-installation';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Diagnoses a search-debug installation: core namespace, plugin classes, search engine reachability, page index shape, and explain support.';

    /**
     * @var string
     */
    protected const CORE_NAMESPACE = 'SprykerCommunity';

    /**
     * @var string
     */
    protected const PAGE_SOURCE_IDENTIFIER = 'page';

    /**
     * @var array<string>
     */
    protected array $failures = [];

    /**
     * @var array<string>
     */
    protected array $warnings = [];

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $input is mandated by the Console base class.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkCoreNamespace($output);
        $this->checkPluginClasses($output);
        $this->checkSearchEngine($output);

        $output->writeln('');

        foreach ($this->warnings as $warning) {
            $output->writeln(sprintf('<comment>! %s</comment>', $warning));
        }

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $output->writeln(sprintf('<error>✗ %s</error>', $failure));
            }

            return static::CODE_ERROR;
        }

        $output->writeln('<info>Everything checkable from the CLI is in place.</info>');
        $output->writeln('Not verifiable from Zed — Zed never bootstraps the Yves DI container, so it cannot confirm:');
        $output->writeln('  - Yves plugin registration (EventDispatcher + Twig dependency providers, and the widget routes)');
        $output->writeln('  - storefront template integration and the compiled frontend assets');
        $output->writeln('  - that a customer actually holds the permission');
        $output->writeln('');
        $output->writeln('The first and third of those ARE checkable from Yves: load /search-debug/check-installation as a');
        $output->writeln('permitted customer (SprykerCommunity\Yves\SearchDebugWidget\Controller\CheckInstallationController).');
        $output->writeln('Template wiring and the frontend build remain a load-the-page check either way.');

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function checkCoreNamespace(OutputInterface $output): void
    {
        $coreNamespaces = Config::get(KernelConstants::CORE_NAMESPACES, []);

        if (in_array(static::CORE_NAMESPACE, $coreNamespaces, true)) {
            $output->writeln(sprintf('<info>✓</info> core namespace "%s" is registered', static::CORE_NAMESPACE));

            return;
        }

        $this->failures[] = sprintf(
            'Core namespace "%s" is NOT registered. Add it to KernelConstants::CORE_NAMESPACES in config/Shared/config_default.php — without it Spryker cannot resolve any of this package\'s classes.',
            static::CORE_NAMESPACE,
        );
    }

    /**
     * Class existence only — whether a project actually registered these is not visible from Zed. A
     * missing class means a broken install; a present class means "nothing is stopping you from
     * registering it".
     *
     * The Yves-layer plugins are deliberately NOT checked here: Spryker forbids a Zed file from
     * referencing the Yves namespace at all, and they ship in this same package anyway, so their
     * existence is already implied by the core-namespace check passing.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function checkPluginClasses(OutputInterface $output): void
    {
        $requiredClasses = [
            'permission plugin' => SeeSearchDebugInfoPermissionPlugin::class,
            'search debug client' => SearchDebugClient::class,
        ];

        foreach ($requiredClasses as $label => $className) {
            if (class_exists($className)) {
                $output->writeln(sprintf('<info>✓</info> %s class is loadable', $label));

                continue;
            }

            $this->failures[] = sprintf('The %s (%s) could not be autoloaded.', $label, $className);
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function checkSearchEngine(OutputInterface $output): void
    {
        try {
            $searchElasticsearchConfig = new SearchElasticsearchConfig();
            $elasticaClient = (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
            $info = $elasticaClient->request('')->getData();
        } catch (Throwable $exception) {
            $this->failures[] = sprintf('Search engine is not reachable: %s', $exception->getMessage());

            return;
        }

        $version = $info['version'] ?? [];
        $output->writeln(sprintf(
            '<info>✓</info> search engine reachable: %s %s (Lucene %s)',
            (string)($version['distribution'] ?? 'elasticsearch'),
            (string)($version['number'] ?? '?'),
            (string)($version['lucene_version'] ?? '?'),
        ));

        $this->checkPageIndex($elasticaClient, $searchElasticsearchConfig, $output);
    }

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function checkPageIndex(Client $elasticaClient, SearchElasticsearchConfig $searchElasticsearchConfig, OutputInterface $output): void
    {
        $indexPrefix = $searchElasticsearchConfig->getIndexPrefix();

        try {
            $aliases = $elasticaClient->request('_aliases')->getData();
        } catch (Throwable $exception) {
            $this->warnings[] = sprintf('Could not list indexes (%s) — skipping page index checks.', $exception->getMessage());

            return;
        }

        $pageIndexes = [];

        foreach (array_keys($aliases) as $indexName) {
            if (!str_starts_with((string)$indexName, (string)$indexPrefix) || !str_ends_with((string)$indexName, static::PAGE_SOURCE_IDENTIFIER)) {
                continue;
            }

            $pageIndexes[] = (string)$indexName;
        }

        if ($pageIndexes === []) {
            $this->failures[] = sprintf(
                'No "%s*...%s" index found. The catalog has not been exported yet — run the publish/sync pipeline before expecting debug output.',
                (string)$indexPrefix,
                static::PAGE_SOURCE_IDENTIFIER,
            );

            return;
        }

        $output->writeln(sprintf('<info>✓</info> page index found: %s', implode(', ', $pageIndexes)));

        $this->checkExplainSupport($elasticaClient, $pageIndexes[0], $output);
    }

    /**
     * The whole package rests on Elasticsearch's `explain` output being available and shaped the way the
     * parser expects, so this fires a real explained query rather than assuming.
     *
     * @param \Elastica\Client $elasticaClient
     * @param string $indexName
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function checkExplainSupport(Client $elasticaClient, string $indexName, OutputInterface $output): void
    {
        try {
            $query = new Query();
            $query->setExplain(true);
            $query->setSize(1);

            $resultSet = $elasticaClient->getIndex($indexName)->search($query);
        } catch (Throwable $exception) {
            $this->failures[] = sprintf('An explained query against "%s" failed: %s', $indexName, $exception->getMessage());

            return;
        }

        if ($resultSet->count() === 0) {
            $this->warnings[] = sprintf('Index "%s" is empty, so explain output could not be confirmed.', $indexName);

            return;
        }

        $explanation = $resultSet->getResults()[0]->getExplanation();

        if ($explanation === []) {
            $this->failures[] = sprintf('Query against "%s" returned no _explanation — the overlay would show no score breakdown.', $indexName);

            return;
        }

        $output->writeln('<info>✓</info> explain output is available and non-empty');
    }
}
