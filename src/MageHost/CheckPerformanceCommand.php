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
use Magento\Framework\Session\Config;
use Magento\Framework\App\Utility\Files;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\PageCache\Model\Config as CacheConfig;

/**
 * Class CheckPerformanceCommand
 *
 * @package MageHost
 */
class CheckPerformanceCommand extends AbstractMagentoCommand
{
    /**
     *
     */
    const STATUS_OK = '<info>ok</info>';
    /**
     *
     */
    const STATUS_PROBLEM = '<error>problem</error>';
    /**
     *
     */
    const STATUS_UNKNOWN = '<warning>unknown</warning>';

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
        $section = $output->section();
        if ($input->getOption('format') === null) {
            $section->writeln(
                [
                    '',
                    $this->getHelper('formatter')->formatBlock(
                        'Loading...',
                        'bg=blue;fg=white',
                        true
                    ),
                    '',
                ]
            );
        }

        $magentoVersionArray = explode(
            '.',
            $this->productMetaData->getVersion()
        );

        $table = array();

        array_push($table, $this->getPHPVersionRow());
        array_push($table, $this->getPHPConfigRow());
        array_push($table, $this->getAppModeRow());
        array_push($table, $this->getHttpVersionRow());
        array_push(
            $table,
            $this->getCacheStorageRow(
                'Magento Cache Storage',
                'default',
                'Cm_Cache_Backend_Redis',
                '/config-guide/redis/redis-pg-cache.html'
            )
        );
        array_push(
            $table,
            $this->getCacheStorageRow(
                'Full Page Cache Storage',
                'page_cache',
                'Cm_Cache_Backend_Redis',
                '/config-guide/redis/redis-pg-cache.html'
            )
        );
        array_push($table, $this->getSessionStorageRow());
        array_push($table, $this->getNonCacheableLayoutsRow());
        array_push($table, $this->getComposerAutoloaderRow());
        array_push($table, $this->getFullPageCacheApplicationRow());
        array_push($table, $this->getAsyncEmailRow());
        array_push($table, $this->getAsyncIndexingRow());

        if ($input->getOption('format') === null) {
            $section->overwrite(
                [
                    '',
                    $this->getHelper('formatter')->formatBlock(
                        'Magehost Performance Dashboard',
                        'bg=blue;fg=white',
                        true
                    ),
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
            $versionCompare ? self::STATUS_OK : self::STATUS_PROBLEM,
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
        $ok = true;

        foreach ($values as $key => $value) {
            $curValue = ini_get($key);
            if (false === $curValue || (!in_array($key, $minimalValues) && ini_get($key) != $value)
                || (in_array($key, $minimalValues) && ini_get($key) < $value)
            ) {
                $ok = false;
            }

            $current .= $key.' = '.$curValue."\n";
        }

        $recommended = '';
        foreach ($values as $key => $value) {
            if (in_array($key, $minimalValues)) {
                $recommended .= $key.' > '.$value."\n";
            } else {
                $recommended .= $key.' = '.$value."\n";
            }
        }

        return array(
            'PHP configuration',
            $ok ? self::STATUS_OK : self::STATUS_PROBLEM,
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
            $appMode == State::MODE_PRODUCTION ? self::STATUS_OK : self::STATUS_PROBLEM,
            $appMode,
            State::MODE_PRODUCTION,
        );
    }

    /**
     * @return array
     */
    public function getHttpVersionRow()
    {
        $status = self::STATUS_OK;
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
                $status = self::STATUS_UNKNOWN;
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
                $status = self::STATUS_PROBLEM;
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
            $currentBackendClass == $expectedBackendClass ? self::STATUS_OK : self::STATUS_PROBLEM,
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
            Config::PARAM_SESSION_SAVE_METHOD,
            $defaultSaveHandler
        );
        $recommended = array('redis', 'memcache', 'memcached');

        return array(
            'Session Storage',
            in_array($saveHandler, $recommended) ? self::STATUS_OK : self::STATUS_PROBLEM,
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
            count($badNonCacheAbleElements) > 0 ? self::STATUS_PROBLEM : self::STATUS_OK,
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
        $status = self::STATUS_OK;
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
            $status = self::STATUS_UNKNOWN;
        }
        if (array_key_exists(
            \Magento\Config\Model\Config::class,
            $classLoader->getClassMap()
        )
        ) {
            $current = 'Composer\'s autoloader is optimized';
        } else {
            $status = self::STATUS_PROBLEM;
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
        $status = in_array(CacheConfig::BUILT_IN, $cachingApplication) ? self::STATUS_PROBLEM
            : self::STATUS_OK;

        return array(
            'Full Page Cache',
            $status,
            in_array(CacheConfig::BUILT_IN, $cachingApplication) ? 'Built in' : 'Varnish Cache',
            'Varnish Cache',
        );
    }

    /**
     * @return string[]
     */
    protected function getAsyncEmailRow()
    {
        $status = self::STATUS_OK;
        $cachingApplication = $this->getConfigValuesByPath('sales_email/general/async_sending');
        if (!$cachingApplication || !in_array(true, $cachingApplication)) {
            $status = self::STATUS_PROBLEM;
        }

        return array(
            'Asynchronous sending of sales emails',
            $status,
            $status === self::STATUS_OK ? 'Enabled' : 'Disabled',
            'Enabled',
        );
    }

    /**
     * @return string[]
     */
    protected function getAsyncIndexingRow()
    {
        $status = self::STATUS_OK;
        $cachingApplication = $this->getConfigValuesByPath('dev/grid/async_indexing');
        if (!$cachingApplication || !in_array(true, $cachingApplication)) {
            $status = self::STATUS_PROBLEM;
        }

        return array(
            'Asynchronous Indexing',
            $status,
            $status === self::STATUS_OK ? 'Enabled' : 'Disabled',
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
}
