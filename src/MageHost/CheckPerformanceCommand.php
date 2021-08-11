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
use MageHost\CheckPerformanceRows\MinifySettingsRow;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;
use Magento\Framework\App\ProductMetadataInterface;

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

    protected $minifySettingsRow;

    protected $productMetaData;

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
     * @param MinifySettingsRow $minifySettingsRow 
     * @param ProductMetadataInterface $productMetadata 
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
        MinifySettingsRow $minifySettingsRow,
        ProductMetadataInterface $productMetadata
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
        $this->minifySettingsRow = $minifySettingsRow;
        $this->productMetaData = $productMetadata;
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

        $magentoVersionArray = explode(
            '.',
            $this->productMetaData->getVersion()
        );

        $table = array(
            $this->phpVersionRow->setInputFormat($inputFormat)->getRow(),
            $this->phpConfigRow->setInputFormat($inputFormat)->getRow(),
            $this->appModeRow->setInputFormat($inputFormat)->getRow(),
            $this->httpVersionRow->setInputFormat($inputFormat)->getRow(),
            $this->cacheStorageRow->setInputFormat($inputFormat)->getRow(
                'Magento Cache Storage',
                'default',
                ['Cm_Cache_Backend_Redis', 'Magento\Framework\Cache\Backend\Redis']
            ),
            $this->cacheStorageRow->setInputFormat($inputFormat)->getRow(
                'Full Page Cache Storage',
                'page_cache',
                ['Cm_Cache_Backend_Redis', 'Magento\Framework\Cache\Backend\Redis']
            ),
            $this->sessionStorageRow->setInputFormat($inputFormat)->getRow(),
            $this->nonCacheableLayoutsRow->setInputFormat($inputFormat)->getRow(),
            $this->composerAutoloaderRow->setInputFormat($inputFormat)->getRow(),
            $this->fullPageCacheApplicationRow->setInputFormat($inputFormat)->getRow(),
            $this->asyncEmailRow->setInputFormat($inputFormat)->getRow(),
            $this->asyncIndexingRow->setInputFormat($inputFormat)->getRow(),
        );

        $table = array_merge($table, $this->minifySettingsRow->setInputFormat($inputFormat)->getRow());

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
}
