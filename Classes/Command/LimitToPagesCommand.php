<?php

declare(strict_types=1);

namespace Pint\LimitToPages\Command;

use PDO;
use Pint\LimitToPages\Event\AfterLimitToPagesGeneratedEvent;
use Pint\LimitToPages\Service\ExtensionService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_unique;
use function count;
use function in_array;
use function is_file;
use function ksort;
use function unlink;

class LimitToPagesCommand extends Command
{
    protected ConnectionPool $connectionPool;
    protected EventDispatcherInterface $eventDispatcher;
    protected ExtensionService $extensionService;
    protected SiteFinder $siteFinder;

    public function __construct(
        ConnectionPool $connectionPool,
        EventDispatcherInterface $eventDispatcher,
        ExtensionService $extensionService,
        SiteFinder $siteFinder
    ) {
        parent::__construct();

        $this->connectionPool = $connectionPool;
        $this->eventDispatcher = $eventDispatcher;
        $this->extensionService = $extensionService;
        $this->siteFinder = $siteFinder;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'Defines behaviour during generation. Possible values are "hard", "merge" (default) and "soft".',
                'merge'
            )
            ->addOption(
                'skip-site',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Site identifier to skip.'
            )
            ->addOption(
                'sort',
                's',
                InputOption::VALUE_NONE,
                'Sort route enhancers by identifier.'
            )
            ->setDescription('Generate automatically "limitToPages" configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sites = $this->siteFinder->getAllSites();

        if (count($sites) === 0) {
            $io->title('No sites configured');
            $io->note('You can configure new sites in the "Sites" module.');

            return Command::SUCCESS;
        }

        $mode = $input->getOption('mode');

        if (in_array($mode, ['hard', 'merge', 'soft'], true) === false) {
            $mode = 'merge';
        }

        $skipSites = $input->getOption('skip-site');

        foreach ($sites as $siteIdentifier => $site) {
            if (in_array($siteIdentifier, $skipSites, true) === true) {
                continue;
            }

            $io->title('Generating "limitToPages" for "' . $siteIdentifier . '"');

            $enhancers = $site->getAttribute('routeEnhancers');

            if (count($enhancers) === 0) {
                continue;
            }

            if ($input->getOption('sort') === true) {
                ksort($enhancers);
            }

            $configuration = [];

            foreach ($enhancers as $enhancerIdentifier => $enhancer) {
                if ($enhancer['type'] !== 'Extbase') {
                    continue;
                }

                if ($mode === 'soft' && count((array)$enhancer['limitToPages']) > 0) {
                    continue;
                }

                $pluginSignature = $this->extensionService->getPluginSignature(
                    (string)$enhancer['extension'],
                    (string)$enhancer['plugin']
                );

                if ($pluginSignature === '') {
                    continue;
                }

                $pageIds = $mode === 'merge' ? $enhancer['limitToPages'] : [];

                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
                $queryBuilder->createNamedParameter('list', PDO::PARAM_STR, ':CType');
                $queryBuilder->createNamedParameter($pluginSignature, PDO::PARAM_STR, ':list_type');
                $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

                $result = $queryBuilder
                    ->select('pid')
                    ->from('tt_content')
                    ->where(
                        $queryBuilder->expr()->eq('CType', ':CType'),
                        $queryBuilder->expr()->eq('list_type', ':list_type')
                    )
                    ->execute();

                while ($pageId = $result->fetchOne()) {
                    $pageIds[] = $pageId;
                }

                $configuration['routeEnhancers'][$enhancerIdentifier]['limitToPages'] = array_unique($pageIds);
            }

            $configuration = $this->eventDispatcher
                ->dispatch(new AfterLimitToPagesGeneratedEvent($configuration))
                ->getConfiguration();

            $filePath = 'limit_to_pages/Configuration/Routing/' . $siteIdentifier . '.yaml';
            $file = Environment::getExtensionsPath() . '/' . $filePath;

            if (count($configuration) === 0) {
                if (is_file($file) === true) {
                    unlink($file);
                }

                $io->info('No "Extbase" enhancer configured.');

                return Command::SUCCESS;
            }

            if (GeneralUtility::writeFile($file, Yaml::dump($configuration, 99, 2)) === true) {
                $io->success('File "EXT:' . $filePath . '" has been successfully generated.');
            } else {
                $io->error('File "EXT:' . $filePath . '" could not be created.');
            }
        }

        return Command::SUCCESS;
    }
}
