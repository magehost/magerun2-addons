<?php

namespace MageHost;

use Composer\Autoload\ClassLoader;

use N98\Magento\Command\AbstractMagentoCommand;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\State;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\Framework\App\Utility\Files;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\PageCache\Model\Config as CacheConfig;
use Magento\Config\Model\Config;

/**
 * Class CheckPerformanceCommand
 *
 * @package MageHost
 */
class CheckPerformanceCommand extends AbstractMagentoCommand
{
    /**
     * @var
     */
    protected $appState;
    /**
     * @var
     */
    protected $productMetaData;
    /**
     * @var
     */
    protected $appRequest;
    /**
     * @var
     */
    protected $storeManager;
    /**
     * @var
     */
    protected $cacheFrontendPool;
    /**
     * @var
     */
    protected $deploymentConfig;
    /**
     * @var
     */
    protected $files;
    /**
     * @var
     */
    protected $themeCollection;
    /**
     * @var
     */
    protected $configCollection;

    /**
     * @var
     */
    protected $input;

    /**
     * @param  State                     $appState
     * @param  ProductMetadataInterface  $productMetaData
     * @param  RequestInterface          $appRequest
     * @param  StoreManagerInterface     $storeManager
     * @param  Pool                      $cacheFrontendPool
     * @param  DeploymentConfig          $deploymentConfig
     * @param  Files                     $files
     * @param  ThemeCollection           $themeCollection
     * @param  ConfigCollection          $configCollection
     */
    public function inject(
        State $appState,
        ProductMetadataInterface $productMetaData,
        RequestInterface $appRequest,
        StoreManagerInterface $storeManager,
        Pool $cacheFrontendPool,
        DeploymentConfig $deploymentConfig,
        Files $files,
        ThemeCollection $themeCollection,
        ConfigCollection $configCollection
    ) {
        $this->appState = $appState;
        $this->productMetaData = $productMetaData;
        $this->request = $appRequest;
        $this->storeManager = $storeManager;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->deploymentConfig = $deploymentConfig;
        $this->files = $files;
        $this->themeCollection = $themeCollection;
        $this->configCollection = $configCollection;
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('magehost:performance:check')
            ->setDescription('Check several parameters to analyse performance')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output Format. One of ['.implode(',', RendererFactory::getFormats()).']'
            );
    }

    /**
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            return 0;
        }

        $this->input = $input;
        $section = $output->section();

        if ($input->getOption('format') === null) {
            $section->writeln(
                [
                    '',
                    $this->fromatInfoMessage('Loading...'),
                    '',
                ]
            );
        }

        $magentoVersionArray = explode(
            '.',
            $this->productMetaData->getVersion()
        );

        $table = array(
            $this->getPHPVersionRow(),
            $this->getPHPConfigRow(),
            $this->getAppModeRow(),
            $this->getHttpVersionRow(),
            $this->getCacheStorageRow(
                'Magento Cache Storage',
                'default',
                'Cm_Cache_Backend_Redis',
                '/config-guide/redis/redis-pg-cache.html'
            ),
            $this->getCacheStorageRow(
                'Full Page Cache Storage',
                'page_cache',
                'Cm_Cache_Backend_Redis',
                '/config-guide/redis/redis-pg-cache.html'
            ),
            $this->getSessionStorageRow(),
            $this->getNonCacheableLayoutsRow(),
            $this->getComposerAutoloaderRow(),
            $this->getFullPageCacheApplicationRow(),
            $this->getAsyncEmailRow(),
            $this->getAsyncIndexingRow(),
        );

        if ($input->getOption('format') === null) {
            $section->overwrite(
                [
                    '',
                    $this->fromatInfoMessage('Magehost Performance Dashboard'),
                    '',
                ]
            );
        }

        return $this->getHelper('table')
            ->setHeaders(array('optimization', 'status', 'current', 'recommended'))
            ->renderByFormat($output, $table, $input->getOption('format'));
    }

    /**
     * @return array
     */
    protected function getPHPVersionRow()
    {
        $phpVersionSplit = explode('-', PHP_VERSION, 2);
        $versionCompare = version_compare(PHP_VERSION, '7.0.0', '>=');
        $showVersion = reset($phpVersionSplit);

        return array(
            'PHP version',
            $versionCompare
                ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus(
                'STATUS_PROBLEM'
            ),
            $showVersion,
            '>= 7.0.0',
        );
    }

    /**
     * @return array
     */
    protected function getPHPConfigRow()
    {
        $values = array(
            'opcache.enable_cli'            => 1,
            'opcache.save_comments'         => 1,
            'opcache.consistency_checks'    => 0,
            'opcache.memory_consumption'    => 512,
            'opcache.max_accelerated_files' => 100000,
        );

        $minimalValues = array('opcache.memory_consumption', 'opcache.max_accelerated_files');

        $problems = '';
        $current = '';
        $status = $this->formatStatus('STATUS_OK');

        foreach ($values as $key => $value) {
            $curValue = ini_get($key);
            if (false === $curValue) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            if (!in_array($key, $minimalValues) && ini_get($key) != $value) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            if (in_array($key, $minimalValues) && ini_get($key) < $value) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }

            $current .= $key.' = '.$curValue."\n";
        }

        $recommended = '';
        foreach ($values as $key => $value) {
            $recommendedRow = $key.' > '.$value."\n";
            if (!in_array($key, $minimalValues)) {
                $recommendedRow = $key.' = '.$value."\n";
            }

            $recommended .= $recommendedRow;
        }

        return array(
            'PHP configuration',
            $status,
            trim($current),
            trim($recommended),
        );
    }

    /**
     * @return array
     */
    protected function getAppModeRow()
    {
        $appMode = $this->appState->getMode();

        return array(
            'Magento mode',
            $appMode == State::MODE_PRODUCTION ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus('STATUS_PROBLEM'),
            $appMode,
            State::MODE_PRODUCTION,
        );
    }

    /**
     * @return array
     */
    public function getHttpVersionRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $finalVersion = null;
        $serverProtocol = $this->request->getServerValue('SERVER_PROTOCOL');
        if (!empty($serverProtocol)) {
            $versionSplit = explode('/', $serverProtocol);
            $version = $versionSplit[1];
            if (floatval($version) >= 2) {
                $finalVersion = $version;
            }
        }

        if (!$finalVersion) {
            $frontUrl = $this->storeManager->getStore()->getBaseUrl();

            try {
                if (!defined('CURL_HTTP_VERSION_2_0')) {
                    define('CURL_HTTP_VERSION_2_0', 3);
                }

                $curl = curl_init();
                curl_setopt_array(
                    $curl,
                    [
                        CURLOPT_URL            => $frontUrl,
                        CURLOPT_NOBODY         => true,
                        CURLOPT_HEADER         => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_CONNECTTIMEOUT => 2,
                        CURLOPT_TIMEOUT        => 10,
                    ]
                );
                $httpResponse = curl_exec($curl);
                curl_close($curl);
            } catch (Exception $e) {
                $finalVersion = sprintf(
                    "%s: Error fetching '%s': %s",
                    __CLASS__,
                    $frontUrl,
                    $e->getMessage()
                );
                $status = $this->formatStatus('STATUS_UNKNOWN');
            }

            if (!empty($httpResponse)) {
                $responseHeaders = explode("\r\n", $httpResponse);
                foreach ($responseHeaders as $header) {
                    if (preg_match('|^HTTP/([\d\.]+)|', $header, $matches)) {
                        $finalVersion = $matches[1];
                        break;
                    }
                }
                if (empty($finalVersion) || floatval($finalVersion) < 2) {
                    foreach ($responseHeaders as $header) {
                        if (preg_match('|^Upgrade: h([\d\.]+)|', $header, $matches)) {
                            $finalVersion = $matches[1];
                            break;
                        }
                    }
                }
            }

            if (!$finalVersion) {
                $status = $this->formatStatus('STATUS_PROBLEM');
            }
        }

        return array('HTTP version', $status, $finalVersion, '> 2');
    }

    /**
     * @param $name
     * @param $identifier
     * @param $expectedBackendClass
     *
     * @return array
     */
    protected function getCacheStorageRow($name, $identifier, $expectedBackendClass)
    {
        $currentBackend = $this->cacheFrontendPool->get(
            $identifier
        )->getBackend();
        $currentBackendClass = get_class($currentBackend);

        return array(
            $name,
            $currentBackendClass == $expectedBackendClass ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus('STATUS_PROBLEM'),
            $currentBackendClass,
            $expectedBackendClass,
        );
    }

    /**
     * @return array
     */
    protected function getSessionStorageRow()
    {
        $defaultSaveHandler = ini_get('session.save_handler')
            ?:
            SaveHandlerInterface::DEFAULT_HANDLER;
        $saveHandler = $this->deploymentConfig->get(
            SessionConfig::PARAM_SESSION_SAVE_METHOD,
            $defaultSaveHandler
        );
        $recommended = array('redis', 'memcache', 'memcached');

        return array(
            'Session Storage',
            in_array($saveHandler, $recommended) ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus('STATUS_PROBLEM'),
            $saveHandler,
            implode(' or ', $recommended),
        );
    }

    /**
     * @return array
     */
    protected function getNonCacheableLayoutsRow()
    {
        $elementsToInclude = array('catalog', 'cms');
        $usedThemes = $this->getConfigValuesByPath('design/theme/theme_id');

        $usedThemePaths = [];
        foreach ($this->themeCollection as $theme) {
            if (in_array($theme->getId(), $usedThemes)) {
                array_push($usedThemePaths, $theme->getThemePath());
                $currentParent = $theme->getParentTheme();
                while ($currentParent) {
                    array_push($usedThemePaths, $currentParent->getThemePath());
                    $currentParent = $currentParent->getParentTheme();
                }
            }
        }

        $files = array();
        foreach (array_unique($usedThemePaths) as $usedThemePath) {
            $files = array_merge(
                $files,
                $this->files->getLayoutFiles(
                    array('area' => 'frontend', 'theme_path' => $usedThemePath),
                    false
                )
            );
        }

        $badNonCacheAbleElements = array();
        foreach ($files as $file) {
            $xml = simplexml_load_file($file);
            $elements = $xml->xpath('//*[@cacheable="false"]');
            foreach ($elements as $element) {
                $needsLogging = false;

                if (preg_match('('.implode('|', $elementsToInclude).')', $file) === 1
                    || preg_match(
                        '('.implode('|', $elementsToInclude).')',
                        $element['name']
                    ) === 1
                ) {
                    $needsLogging = true;
                }

                if ($needsLogging && strpos($element['name'], 'compare') === false) {
                    array_push($badNonCacheAbleElements, $element['name']);
                }
            }
        }

        return array(
            'Non Cacheable Layouts',
            count($badNonCacheAbleElements) > 0 ? $this->formatStatus('STATUS_PROBLEM')
                : $this->formatStatus('STATUS_OK'),
            implode("\n", array_unique($badNonCacheAbleElements)),
            'none',
        );
    }

    /**
     * @return string[]
     */
    protected function getComposerAutoloaderRow()
    {
        $title = 'Composer autoloader';
        $recommended = 'Optimized autoloader (composer dump-autoload -o --apcu)';
        $status = $this->formatStatus('STATUS_OK');
        $current = 'Composer\'s autoloader is optimized';
        $classLoader = null;
        foreach (spl_autoload_functions() as $function) {
            if (is_array($function)
                && $function[0] instanceof ClassLoader
            ) {
                $classLoader = $function[0];
                break;
            }
        }

        if (empty($classLoader)) {
            $current = 'Could not find Composer AutoLoader';
            $status = $this->formatStatus('STATUS_UNKNOWN');
        }

        if (!array_key_exists(
            Config::class,
            $classLoader->getClassMap()
        )
        ) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $current = 'Composer\'s autoloader is not optimized.';
        }

        return array($title, $status, $current, $recommended);
    }

    /**
     * @return string[]
     */
    protected function getFullPageCacheApplicationRow()
    {
        $cachingApplication = $this->getConfigValuesByPath(
            'system/full_page_cache/caching_application'
        );

        $status = $this->formatStatus('STATUS_OK');
        $message = 'Varnish Cache';

        if (!in_array(CacheConfig::BUILT_IN, $cachingApplication)) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $message = 'Built in';
        }

        return array(
            'Full Page Cache',
            $status,
            $message,
            'Varnish Cache',
        );
    }

    /**
     * @return string[]
     */
    protected function getAsyncEmailRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $message = 'Enabled';
        $cachingApplication = $this->getConfigValuesByPath('sales_email/general/async_sending');
        if (!$cachingApplication || !in_array(true, $cachingApplication)) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $message = 'Disabled';
        }

        return array(
            'Asynchronous sending of sales emails',
            $status,
            $message,
            'Enabled',
        );
    }

    /**
     * @return string[]
     */
    protected function getAsyncIndexingRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $message = 'Enabled';
        $cachingApplication = $this->getConfigValuesByPath('dev/grid/async_indexing');
        if (!$cachingApplication || !in_array(true, $cachingApplication)) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $message = 'Disabled';
        }

        return array(
            'Asynchronous Indexing',
            $status,
            $message,
            'Enabled',
        );
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    protected function getConfigValuesByPath($path)
    {
        $this->configCollection->clear()->getSelect()->reset(\Zend_Db_Select::WHERE);

        return $this->configCollection->addFieldToFilter('path', $path)->addFieldToSelect('value')
            ->getColumnValues('value');
    }

    /**
     * @param $message
     *
     * @return mixed
     */
    protected function fromatInfoMessage($message)
    {
        return $this->getHelper('formatter')->formatBlock(
            $message,
            'bg=blue;fg=white',
            true
        );
    }

    /**
     * @param $status
     *
     * @return string
     */
    protected function formatStatus($status)
    {
        $input = $this->input;
        if ($status === 'STATUS_OK') {
            if ($input->getOption('format') !== null) {
                return 'ok';
            }

            return '<info>ok</info>';
        }

        if ($status === 'STATUS_PROBLEM') {
            if ($input->getOption('format') !== null) {
                return 'problem';
            }

            return '<error>problem</error>';
        }

        if ($status === 'STATUS_UNKNOWN') {
            if ($input->getOption('format') !== null) {
                return 'unknown';
            }

            return '<warning>unknown</warning>';
        }
    }
}
