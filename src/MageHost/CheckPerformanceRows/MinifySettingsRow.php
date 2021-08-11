<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class MinifySettingsRow 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class MinifySettingsRow extends AbstractRow
{
    protected $storeManager;

    /**
     * @param ConfigCollection $configCollection 
     * @param StoreManagerInterface $storeManager 
     * 
     * @return void 
     */
    public function __construct(ConfigCollection $configCollection, StoreManagerInterface $storeManager)
    {
        $this->configCollection = $configCollection;
        $this->storeManager = $storeManager;
    }

    /**
     * @return array 
     */
    public function getRow()
    {
        $configs = array(
            'js' => array('title' => 'Minify JavaScript Files', 'path' => 'dev/js/minify_files'),
            'css' => array('title' => 'Minify CSS Files', 'path' => 'dev/css/minify_files'),
            'html' => array('title' => 'Minify HTML', 'path' => 'dev/template/minify_html')
        );

        $result = array();
        $stores = $this->storeManager->getStores();


        foreach ($configs as $key => $config) {
            $ok = true;
            $currentResult = array();
            $this->configCollection->clear()->getSelect()->reset(\Zend_Db_Select::WHERE);
            $configValues = $this->configCollection->addFieldToFilter('path', $config['path'])->addFieldToSelect(['value', 'scope', 'scope_id'])->toArray();
            $configPerScope = array();
            foreach ($configValues['items'] as $item) {
                $configPerScope[$item['scope']][$item['scope_id']] = $item['value'];
            }
            $counter = 0;
            $firstValue = null;
            $allStoresHaveSameValue = true;
            foreach ($stores as $store) {
                $value = null;
                if (array_key_exists('stores', $configPerScope) && array_key_exists($store->getId(), $configPerScope['stores'])) {
                    $value = $configPerScope['stores'][$store->getId()];
                    if (!$value) {
                        $ok = false;
                    }

                    array_push($currentResult, 'Store ' . $store->getName() . ' has value ' . $configPerScope['stores'][$store->getId()] . ', scope: store');
                }

                if (array_key_exists('websites', $configPerScope) && array_key_exists($store->getWebsiteId(), $configPerScope['websites']) && !isset($value)) {
                    $value = $configPerScope['websites'][$store->getWebsiteId()];
                    if (!$value) {
                        $ok = false;
                    }
                    array_push($currentResult, 'Store ' . $store->getName() . ' has value ' . $configPerScope['websites'][$store->getWebsiteId()] . ', scope: website');
                }

                if (array_key_exists('default', $configPerScope) && array_key_exists(0, $configPerScope['default']) && !isset($value)) {
                    $value = $configPerScope['default'][0];
                    if (!$value) {
                        $ok = false;
                    }
                    array_push($currentResult, 'Store ' . $store->getName() . ' has value ' . $configPerScope['default'][0] . ', scope: default');
                }

                if (!isset($value)) {
                    $value = 0;
                    $ok = false;
                    array_push($currentResult, 'Store ' . $store->getName() . ' has value 0, scope: none');
                }

                if ($counter == 0) {
                    $firstValue = $value;
                }

                if ($counter !== 0 && $firstValue !== $value) {
                    $allStoresHaveSameValue = false;
                }
            }

            if ($allStoresHaveSameValue) {
                $currentResult = array('All stores have value ' . $firstValue);
            }

            array_push($result, array($config['title'], $ok ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'), implode("\n", $currentResult), 'Enabled'));
        }

        return $result;
    }
}
