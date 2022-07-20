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
            if (!getenv('PAGESPEED_URL') || !getenv('PAGESPEED_TOKEN')) {
                array_push($result, array(
                    $title . ' (' . $url . ')',
                    $this->formatStatus('STATUS_UNKNOWN'),
                    'Missing required PAGESPEED env variables',
                    '< 2000 ms'
                ));
                continue;
            }

            $pagespeedUrl = getenv('PAGESPEED_URL') . getenv('PAGESPEED_TOKEN');
            $postdata = json_encode(['url' => $url]);
            $ch = curl_init($pagespeedUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $output = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code != 200) {
                array_push($result, array(
                    $title . ' (' . $url . ')',
                    $this->formatStatus('STATUS_UNKNOWN'),
                    'Could not load the URL',
                    '< 2000 ms'
                ));
                continue;
            }

            $decodedOutput = json_decode($output, true);
            if (!array_key_exists('observedLoad', $decodedOutput)) {
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
                $decodedOutput['observedLoad'] < 2000 ? $this->formatStatus('STATUS_OK') : $this->formatStatus('STATUS_PROBLEM'),
                $decodedOutput['observedLoad'] . ' ms',
                '< 2000 ms'
            ));
        }

        return $result;
    }
}
