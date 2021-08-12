<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Sales\Model\ResourceModel\Sale\Collection as SalesCollection;

/**
 * Class AsyncEmailRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class AsyncEmailRow extends AbstractRow
{
    protected $productMetadata;

    protected $salesCollection;

    /**
     * @param ConfigCollection $configCollection 
     * @param ProductMetadataInterface $productMetadata 
     * @param SalesCollection $salesCollection 
     * 
     * @return void 
     */
    public function __construct(ConfigCollection $configCollection, ProductMetadataInterface $productMetadata, SalesCollection $salesCollection)
    {
        $this->configCollection = $configCollection;
        $this->productMetadata = $productMetadata;
        $this->salesCollection = $salesCollection;
    }

    /**
     * @return (string|void)[] 
     */
    public function getRow()
    {
        $magentoVersion = $this->productMetadata->getVersion();
        $status = $this->formatStatus('STATUS_OK');
        $message = 'Enabled';
        $cachingApplication = $this->getConfigValuesByPath('sales_email/general/async_sending');
        if (!$cachingApplication || !in_array(true, $cachingApplication)) {
            $status = $this->formatStatus('STATUS_PROBLEM');
            $message = 'Disabled';

            if (version_compare($magentoVersion, '2.4.2', '<')) {
                $this->salesCollection->addFieldToFilter('send_email', ['eq' => 1]);
                $this->salesCollection->addFieldToFilter('email_sent', ['null' => true]);
                if ($this->salesCollection->count() > 0) {
                    $message .= ' Warning: found ' . $this->salesCollection->count() . ' old emails. Do not enable before cleanup (MC-39521).';
                }
            }
        }

        return array(
            'Asynchronous sending of sales emails',
            $status,
            $message,
            'Enabled',
        );
    }
}
