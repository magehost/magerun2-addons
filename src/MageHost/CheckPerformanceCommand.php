<?php

namespace MageHost;

use N98\Magento\Command\AbstractMagentoCommand;

use MageHost\CheckPerformanceRows\PHPVersionRow;
use MageHost\CheckPerformanceRows\PHPConfigRow;
use MageHost\CheckPerformanceRows\AppModeRow;
use MageHost\CheckPerformanceRows\HttpVersionRow;
use MageHost\CheckPerformanceRows\CacheStorageRow;
use MageHost\CheckPerformanceRows\ComposerAutoloaderRow;
use MageHost\CheckPerformanceRows\SessionStorageRow;
use MageHost\CheckPerformanceRows\NonCacheableLayoutsRow;
use MageHost\CheckPerformanceRows\FullPageCacheApplicationRow;
use MageHost\CheckPerformanceRows\AsyncEmailRow;
use MageHost\CheckPerformanceRows\AsyncIndexingRow;
use MageHost\CheckPerformanceRows\MinifySettingsRows;
use MageHost\CheckPerformanceRows\VarnishHitrateRow;
use MageHost\CheckPerformanceRows\MoveScriptRow;
use MageHost\CheckPerformanceRows\LoadtimesRows;
use MageHost\CheckPerformanceRows\MySQLTableSizeRows;
use MageHost\CheckPerformanceRows\RocketLoaderRow;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\PageCache\Model\Config as CacheConfig;

/**
 * Class CheckPerformanceCommand
 *
 * @package MageHost
 */
class CheckPerformanceCommand extends AbstractMagentoCommand
{

    protected $phpVersionRow;

    protected $phpConfigRow;

    protected $appModeRow;

    protected $httpVersionRow;

    protected $cacheStorageRow;

    protected $sessionStorageRow;

    protected $nonCacheableLayoutsRow;

    protected $composerAutoloaderRow;

    protected $fullPageCacheApplicationRow;

    protected $asyncEmailRow;

    protected $asyncIndexingRow;

    protected $minifySettingsRows;

    protected $productMetaData;

    protected $varnishHitrateRow;

    protected $moveScriptRow;

    protected $loadtimesRows;

    protected $rocketLoaderRow;

    protected $configCollection;

    protected $mySQLTableSizeRows;

    /**
     * @param PHPVersionRow $phpVersionRow 
     * @param PHPConfigRow $phpConfigRow 
     * @param AppModeRow $appModeRow 
     * @param HttpVersionRow $httpVersionRow 
     * @param CacheStorageRow $cacheStorageRow 
     * @param SessionStorageRow $sessionStorageRow 
     * @param NonCacheableLayoutsRow $nonCacheableLayoutsRow 
     * @param ComposerAutoloaderRow $composerAutoloaderRow 
     * @param FullPageCacheApplicationRow $fullPageCacheApplicationRow 
     * @param AsyncEmailRow $asyncEmailRow 
     * @param AsyncIndexingRow $asyncIndexingRow 
     * @param MinifySettingsRows $minifySettingsRows 
     * @param VarnishHitrateRow $varnishHitrateRow 
     * @param MoveScriptRow $moveScriptRow 
     * @param ConfigCollection $configCollection 
     * @param LoadtimesRows $loadtimesRows 
     * 
     * @return void 
     */
    public function inject(
        PHPVersionRow $phpVersionRow,
        PHPConfigRow $phpConfigRow,
        AppModeRow $appModeRow,
        HttpVersionRow $httpVersionRow,
        CacheStorageRow $cacheStorageRow,
        SessionStorageRow $sessionStorageRow,
        NonCacheableLayoutsRow $nonCacheableLayoutsRow,
        ComposerAutoloaderRow $composerAutoloaderRow,
        FullPageCacheApplicationRow $fullPageCacheApplicationRow,
        AsyncEmailRow $asyncEmailRow,
        AsyncIndexingRow $asyncIndexingRow,
        MinifySettingsRows $minifySettingsRows,
        VarnishHitrateRow $varnishHitrateRow,
        MoveScriptRow $moveScriptRow,
        ConfigCollection $configCollection,
        LoadtimesRows $loadtimesRows,
        MySQLTableSizeRows $mySQLTableSizeRows,
        RocketLoaderRow $rocketLoaderRow
    ) {
        $this->phpVersionRow = $phpVersionRow;
        $this->phpConfigRow = $phpConfigRow;
        $this->appModeRow = $appModeRow;
        $this->httpVersionRow = $httpVersionRow;
        $this->cacheStorageRow = $cacheStorageRow;
        $this->sessionStorageRow = $sessionStorageRow;
        $this->nonCacheableLayoutsRow = $nonCacheableLayoutsRow;
        $this->composerAutoloaderRow = $composerAutoloaderRow;
        $this->fullPageCacheApplicationRow = $fullPageCacheApplicationRow;
        $this->asyncEmailRow = $asyncEmailRow;
        $this->asyncIndexingRow = $asyncIndexingRow;
        $this->minifySettingsRows = $minifySettingsRows;
        $this->varnishHitrateRow = $varnishHitrateRow;
        $this->configCollection = $configCollection;
        $this->moveScriptRow = $moveScriptRow;
        $this->loadtimesRows = $loadtimesRows;
        $this->mySQLTableSizeRows = $mySQLTableSizeRows;
        $this->rocketLoaderRow = $rocketLoaderRow;
    }


    /**
     * @return void 
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
                'Output Format. One of [' . implode(',', RendererFactory::getFormats()) . ']'
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
        $inputFormat = $input->getOption('format');
        if ($inputFormat === null) {
            $section->writeln(
                [
                    '',
                    $this->fromatInfoMessage('Loading...'),
                    '',
                ]
            );
        }

        $cachingApplication = $this->getConfigValuesByPath(
            'system/full_page_cache/caching_application'
        );

        $table = array();
        array_push($table, $this->phpVersionRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->phpConfigRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->appModeRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->httpVersionRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->cacheStorageRow->setInputFormat($inputFormat)->getRow(
            'Magento Cache Storage',
            'default',
            ['Cm_Cache_Backend_Redis', 'Magento\Framework\Cache\Backend\Redis']
        ));

        if (!in_array(CacheConfig::VARNISH, $cachingApplication)) {
            array_push($table, $this->cacheStorageRow->setInputFormat($inputFormat)->getRow(
                'Full Page Cache Storage',
                'page_cache',
                ['Cm_Cache_Backend_Redis', 'Magento\Framework\Cache\Backend\Redis']
            ));
        }
        array_push($table, $this->sessionStorageRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->nonCacheableLayoutsRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->composerAutoloaderRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->fullPageCacheApplicationRow->setInputFormat($inputFormat)->getRow());
        if (in_array(CacheConfig::VARNISH, $cachingApplication)) {
            array_push($table, $this->varnishHitrateRow->setInputFormat($inputFormat)->getRow());
        }
        array_push($table, $this->asyncEmailRow->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->asyncIndexingRow->setInputFormat($inputFormat)->getRow());

        $table = array_merge($table, $this->minifySettingsRows->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->moveScriptRow->setInputFormat($inputFormat)->getRow());
        $table = array_merge($table, $this->loadtimesRows->setInputFormat($inputFormat)->getRow());
        $table = array_merge($table, $this->mySQLTableSizeRows->setInputFormat($inputFormat)->getRow());
        array_push($table, $this->rocketLoaderRow->setInputFormat($inputFormat)->getRow());

        if ($input->getOption('format') === null) {
            $section->overwrite(
                [
                    '',
                    $this->fromatInfoMessage('MageHost Performance Dashboard'),
                    '',
                ]
            );
        }

        return $this->getHelper('table')
            ->setHeaders(array('optimization', 'status', 'current', 'recommended'))
            ->renderByFormat($output, $table, $input->getOption('format'));
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
