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

            foreach ($stores as $store) {
                $valueFound = false;
                if (array_key_exists('stores', $configPerScope) && array_key_exists($store->getId(), $configPerScope['stores'])) {
                    if (!$configPerScope['stores'][$store->getId()]) {
                        $ok = false;
                    }

                    array_push($currentResult, 'Store ' . $store->getName() . ' has value ' . $configPerScope['stores'][$store->getId()] . ', scope: store');
                    $valueFound = true;
                }

                if (array_key_exists('websites', $configPerScope) && array_key_exists($store->getWebsiteId(), $configPerScope['websites']) && !$valueFound) {
                    if (!$configPerScope['websites'][$store->getWebsiteId()]) {
                        $ok = false;
                    }
                    array_push($currentResult, 'Store ' . $store->getName() . ' has value ' . $configPerScope['websites'][$store->getWebsiteId()] . ', scope: website');
                    $valueFound = true;
                }

                if (array_key_exists('default', $configPerScope) && array_key_exists(0, $configPerScope['default']) && !$valueFound) {
                    if (!$configPerScope['default'][0]) {
                        $ok = false;
                    }
                    array_push($currentResult, 'Store ' . $store->getName() . ' has value ' . $configPerScope['default'][0] . ', scope: default');
                    $valueFound = true;
                }

                if (!$valueFound) {
                    $ok = false;
                    array_push($currentResult, 'Store ' . $store->getName() . ' has value 0, scope: none');
                }
            }

            array_push($result, array($config['title'], $ok ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'), implode("\n", $currentResult), 'Enabled'));
        }

        return $result;
    }
}
