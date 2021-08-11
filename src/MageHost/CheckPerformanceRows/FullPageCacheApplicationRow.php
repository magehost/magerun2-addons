<?php

namespace MageHost\CheckPerformanceRows;

use Magento\PageCache\Model\Config as CacheConfig;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;

/**
 * Class FullPageCacheApplicationRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class FullPageCacheApplicationRow extends AbstractRow
{
    /**
     * @param ConfigCollection $configCollection 
     * 
     * @return void 
     */
    public function __construct(ConfigCollection $configCollection)
    {
        $this->configCollection = $configCollection;
    }

    /**
     * 
     * @return (string|void)[] 
     */
    public function getRow()
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
}
