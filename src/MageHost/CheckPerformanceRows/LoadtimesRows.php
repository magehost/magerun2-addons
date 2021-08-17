<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Url;

/**
 * Class LoadtimesRows 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class LoadtimesRows extends AbstractRow
{
    protected $storeManager;

    protected $categoryCollection;

    protected $url;

    /**
     * @param StoreManagerInterface $storeManager 
     * 
     * @return void 
     */
    public function __construct(StoreManagerInterface $storeManager, CategoryCollectionFactory $categoryCollection, Url $url)
    {
        $this->storeManager = $storeManager;
        $this->categoryCollection = $categoryCollection;
        $this->url = $url;
    }


    /**
     * @return (string|void)[] 
     * @throws NoSuchEntityException 
     */
    public function getRow()
    {
        $this->categoryCollection
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('level', 2)
            ->addAttributeToFilter('is_active', 1)
            ->getSelect()->limit(1);
        $defaultStoreId = $this->storeManager->getDefaultStoreView()->getId();
        $category = $this->categoryCollection->getFirstItem()->setStoreId($defaultStoreId);

        $productCollection = $category
            ->getProductCollection();

        $productCollection
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', 4)
            ->getSelect()->limit(1);

        $product = $productCollection->getFirstItem();
        $pagesToCheck = array(
            'Home' => $this->storeManager->getDefaultStoreView()->getBaseUrl(),
            'Cart' => $this->storeManager->getDefaultStoreView()->getUrl('checkout/cart', ['_secure' => true]),
            'Product'  => $product->getProductUrl(),
            'Category' => $category->getUrl()
        );

        $result = array();
        foreach ($pagesToCheck as $title => $url) {
            $output = null;
            $retval = null;
            exec(dirname(__FILE__) . '/assets/phantomjs '  . dirname(__FILE__)  . '/assets/pageload.js ' .  $url . ' 2>&1', $output, $retval);

            if ($retval) {
                array_push($result, array(
                    $title . ' (' . $url . ')',
                    $this->formatStatus('STATUS_UNKNOWN'),
                    'Could not load the URL',
                    '< 2000ms'
                ));
                continue;
            }

            array_push($result, array(
                $title . ' (' . $url . ')',
                $output[0] < 2000 ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'),
                $output[0] . 'ms',
                '< 2000ms'
            ));
        }

        return $result;
    }
}
