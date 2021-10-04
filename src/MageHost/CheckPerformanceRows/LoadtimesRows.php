<?php

namespace MageHost\CheckPerformanceRows;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Url;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;

/**
 * Class LoadtimesRows 
 * 
 * @package MageHost\CheckPerformanceRows
 */
class LoadtimesRows extends AbstractRow
{
    protected $storeManager;

    protected $categoryCollection;

    protected $productCollection;

    protected $productStatus;

    protected $productVisibility;

    protected $url;

    /**
     * @param StoreManagerInterface $storeManager 
     * 
     * @return void 
     */
    public function __construct(StoreManagerInterface $storeManager, CategoryCollection $categoryCollection, Url $url, ProductCollection $productCollection, ProductStatus $productStatus, ProductVisibility $productVisibility)
    {
        $this->storeManager = $storeManager;
        $this->categoryCollection = $categoryCollection;
        $this->productCollection = $productCollection;
        $this->url = $url;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
    }


    /**
     * @return (string|void)[] 
     * @throws NoSuchEntityException 
     */
    public function getRow()
    {
        $selectedStoreId = $this->storeManager->getDefaultStoreView()->getId();
        $stores = $this->storeManager->getStores();
        // if the count is 0, we are probably dealing with single store mode
        if (count($stores) > 0) {
            foreach ($stores as $store) {
                if ($store->isActive()) {
                    $selectedStoreId = $store->getId();
                    break;
                }
            }
        }

        $this->storeManager->setCurrentStore($selectedStoreId);
        $store = $this->storeManager->getStore($selectedStoreId);

        $this->categoryCollection
            ->addAttributeToSelect('*')
            ->setStoreId($selectedStoreId)
            ->addAttributeToFilter('level', 2)
            ->addAttributeToFilter('is_active', 1);

        $this->productCollection->setStoreId($selectedStoreId)->addCountToCategories($this->categoryCollection);
        $this->categoryCollection->getSelect()->where('product_count > 5')->limit(1);

        $category = $this->categoryCollection->getFirstItem();

        $this->productCollection
            ->setStoreId($selectedStoreId)
            ->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()])
            ->setVisibility($this->productVisibility->getVisibleInSiteIds())
            ->getSelect()->limit(1);

        $product = $this->productCollection->getFirstItem();
        $pagesToCheck = array(
            'Home' => $store->getBaseUrl(),
            'Cart' => $store->getUrl('checkout/cart', ['_secure' => true]),
            'Product'  => $product->getProductUrl(),
            'Category' => $category->setStoreId($selectedStoreId)->getUrl()
        );

        $result = array();
        foreach ($pagesToCheck as $title => $url) {
            $output = null;
            $retval = null;
            exec(dirname(__FILE__) . '/assets/phantomjs --ignore-ssl-errors=yes '  . dirname(__FILE__)  . '/assets/pageload.js ' .  $url . ' 2>&1', $output, $retval);

            if ($retval) {
                array_push($result, array(
                    $title . ' (' . $url . ')',
                    $this->formatStatus('STATUS_UNKNOWN'),
                    'Could not load the URL',
                    '< 2000 ms'
                ));
                continue;
            }

            array_push($result, array(
                $title . ' (' . $url . ')',
                $output[0] < 2000 ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'),
                $output[0] . ' ms',
                '< 2000 ms'
            ));
        }

        return $result;
    }
}
