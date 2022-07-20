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

        $html = file_get_contents($url, false, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
        if (!$html) {
            return array(
                'RocketLoader (Cloudflare)',
                $this->formatStatus('STATUS_UNKNOWN'),
                'Something went wrong while fetching the page',
                'Enabled'
            );
        }

        if (strpos('rocket-loader.min.js', $html) === false) {
            return array(
                'RocketLoader (Cloudflare)',
                $this->formatStatus('STATUS_PROBLEM'),
                'No rocketloader injection found.',
                'Enabled'
            );
        }



        return array(
            'RocketLoader (Cloudflare)',
            $this->formatStatus('STATUS_OK'),
            'Enabled',
            'Enabled'
        );
    }
}
