<?php

namespace MageHost\CheckPerformanceRows;

use InvalidArgumentException;
use Magento\Framework\App\Cache\Frontend\Pool;

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
    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
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
