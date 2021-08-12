<?php

namespace MageHost\CheckPerformanceRows;

use InvalidArgumentException;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\PageCache\Model\Config as CacheConfig;

/**
 * Class CacheStorageRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class CacheStorageRow extends AbstractRow
{
    protected $pool;

    /**
     * @param Pool $pool 
     * 
     * @return void 
     */
    public function __construct(Pool $pool, ConfigCollection $configCollection)
    {
        $this->pool = $pool;
        $this->configCollection = $configCollection;
    }

    /**
     * @param mixed $name 
     * @param mixed $identifier 
     * @param mixed $expectedBackendClass 
     * 
     * @return array 
     * @throws InvalidArgumentException 
     */
    public function getRow($name, $identifier, $expectedBackendClasses)
    {
        $currentBackend = $this->pool->get(
            $identifier
        )->getBackend();
        $currentBackendClass = get_class($currentBackend);

        return array(
            $name,
            in_array($currentBackendClass, $expectedBackendClasses) ? $this->formatStatus('STATUS_OK')
                : $this->formatStatus('STATUS_PROBLEM'),
            $currentBackendClass,
            implode(' or ', $expectedBackendClasses),
        );
    }
}
