<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;

/**
 * Class AsyncEmailRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class AsyncEmailRow extends AbstractRow
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
     * @return (string|void)[] 
     */
    public function getRow()
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
}
