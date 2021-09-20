<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\Indexer\Model\Indexer\Collection as IndexerCollection;

/**
 * Class AsyncIndexingRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class AsyncIndexingRow extends AbstractRow
{
    protected $indexerCollection;

    /**
     * @param ConfigCollection $configCollection 
     * 
     * @return void 
     */
    public function __construct(ConfigCollection $configCollection, IndexerCollection $indexerCollection)
    {
        $this->configCollection = $configCollection;
        $this->indexerCollection = $indexerCollection;
    }

    /**
     * @return (string|void)[] 
     */
    public function getRow()
    {
        $status = $this->formatStatus('STATUS_OK');
        $cachingApplication = $this->getConfigValuesByPath('dev/grid/async_indexing');
        if (!$cachingApplication || !in_array(true, $cachingApplication)) {
            return array(
                'Asynchronous Indexing',
                $this->formatStatus('STATUS_PROBLEM'),
                'Disabled',
                'Enabled'
            );
        }

        $disabledIndexersMessage = '';
        foreach ($this->indexerCollection->getItems() as $indexer) {
            if (!$indexer->isScheduled()) {
                $disabledIndexersMessage .= $indexer->getTitle() . ' Index is set to \'Update on Save\'' . "\n";
            }
        }

        return array(
            'Asynchronous Indexing',
            ($disabledIndexersMessage == '' ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM')),
            ($disabledIndexersMessage == '' ? 'Enabled' : $disabledIndexersMessage),
            'Enabled'
        );
    }
}
