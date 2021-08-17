<?php

namespace MageHost\CheckPerformanceRows;

use MageHost\CheckPerformanceRows\AbstractRow;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class RocketLoaderRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class RocketLoaderRow extends AbstractRow
{
    protected $storeManager;

    /**
     * @param StoreManagerInterface $storeManager 
     * 
     * @return void 
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    public function getRow()
    {
        $url = $this->storeManager->getDefaultStoreView()->getBaseUrl();

        exec(dirname(__FILE__) . '/assets/phantomjs '  . dirname(__FILE__)  . '/assets/rocketloader.js ' .  $url . ' 2>&1', $output, $retval);

        if ($retval == 2) {
            return array(
                'RocketLoader',
                $this->formatStatus('STATUS_PROBLEM'),
                'No rocketloader injection found.',
                'Enabled'
            );
        }

        if ($retval) {
            return array(
                'RocketLoader',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Something went wrong while fetching the page.',
                'Enabled'
            );
        }

        return array(
            'RocketLoader',
            $this->formatStatus('STATUS_OK'),
            'Enabled',
            'Enabled'
        );
    }
}
