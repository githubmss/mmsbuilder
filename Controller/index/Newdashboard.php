<?php

namespace Mmsbuilder\Connector\Controller\index;

use Magento\Framework\Event;

class Newdashboard extends \Magento\Framework\App\Action\Action
{
    const XML_CATEGORY_SECTION = 'configuration/dashboard/manage_category_dashboard';

    const XML_PRODUCT_SECTION = 'configuration/dashboard/manage_product_dashboard';

    /**
     * @var \Magento\Framework\Event\Manager
     */
    private $eventManager;

    public function __construct(
        Event\Manager $eventManager,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Framework\Pricing\PriceCurrencyInterface $PriceCurrencyInterface,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Mmsbuilder\Connector\Helper\Products $productHelper
    ) {
        $this->imageHelper              = $imageHelper;
        $this->date                     = $date;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockStateInterface      = $stockStateInterface;
        $this->storeManager             = $storeManager;
        $this->customHelper             = $customHelper;
        $this->cache                    = $cache;
        $this->resultJsonFactory        = $resultJsonFactory;
        $this->eventManager             = $eventManager;
        $this->scopeConfig              = $scopeConfig;
        $this->productStatus            = $productStatus;
        $this->productVisibility        = $productVisibility;
        $this->PriceCurrencyInterface   = $PriceCurrencyInterface;
        $this->priceHelper              = $priceHelper;
        $this->directoryHelper          = $directoryHelper;
        $this->productHelper            = $productHelper;
        parent::__construct($context);
    }

    private function getBaseCurrencyCode()
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->viewConfig($this->getRequest()->getHeader('currency'));
        $store          = $this->storeManager->getStore()->getStoreId();
        $result         = $this->resultJsonFactory->create();
        $_objectData    = \Magento\Framework\App\ObjectManager::getInstance();
        $cacheObj       = $_objectData->get('Magento\Framework\App\Cache');
        $cacheKey       = "mss_newdashboard_store_" . $this->storeId;
        $cacheTag       = "mss";
        if ($cacheObj->load($cacheKey)) {
            $resultArray = json_decode($cacheObj->load($cacheKey), true);
            $result->setData($resultArray);
            return $result;
        }
        $customerFactory     = $_objectData->get('\Mmsbuilder\Connector\Model\Dashboard');
        $collectionDashboard = $customerFactory->getCollection()
            ->addFieldToFilter('status', '1')
            ->setOrder('position', 'ASC');
        $resultArray = [];
        if (!empty($collectionDashboard->getData())) {
            $i = 0;
            foreach ($collectionDashboard as $dashKey => $dashValue) {
                $resultArray[$i]['id']                 = $dashValue->getId();
                $resultArray[$i]['title']              = $dashValue->getTileTittle();
                $resultArray[$i]['banner_description'] = $dashValue->getBannerDescription();
                $resultArray[$i]['tile_type']          = $dashValue->getTileType();
                if ($dashValue->getTileType() == 1) {
                    $resultArray[$i]['category_id']     = $dashValue->getCategoryDisplayId();
                    $resultArray[$i]['display_product'] = $dashValue->getCategoryDisplay();
                    $resultArray[$i]['products_array']  = $this->getCategoryProduct($dashValue->getCategoryDisplayId());
                } elseif ($dashValue->getTileType() == 2) {
                    $imageMedia = $_objectData->get('Magento\Store\Model\StoreManagerInterface')
                        ->getStore()
                        ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                    $resultArray[$i]['banner_type'] = $dashValue->getBannerType();
                    $resultArray[$i]['category_id'] = $dashValue->getCategoryDisplayId();
                    if ($dashValue->getBannerName()) {
                        $resultArray[$i]['banner_image'] = $imageMedia . 'images/' . $dashValue->getBannerName();
                    } else {
                        $resultArray[$i]['banner_image'] = '';
                    }
                } else {
                    $resultArray[$i]['display_product'] = $dashValue->getPromotionDisplay();
                    $resultArray[$i]['products_array']  =
                    $this->getPermotionalProdcts($dashValue->getPromotionDisplayId());
                }
                $i++;
            }
            $cacheObj->save(json_encode($resultArray), $cacheKey, [$cacheTag], 300);
            $objectData  = \Magento\Framework\App\ObjectManager::getInstance();
            $filter = $objectData->create('\Magento\Framework\DataObject');
            $resultArray = $filter->setData($resultArray);
            $this->eventManager->dispatch('magento_mobile_shop_newdashboard', ["mss_new_dashboard" => $resultArray]);
            $result->setData($resultArray);
            return $result;
        } else {
            $result->setData(['status' => 'false', 'message' => 'No products selected.']);
            return $result;
        }
    }

    /**
     * Get the products according to category id
     * @param  [type] $catId [description]
     * @return Array
     */
    private function getCategoryProduct($catId)
    {
        $_objectData = \Magento\Framework\App\ObjectManager::getInstance();
        $category    = $_objectData->create('Magento\Catalog\Model\Category')->load($catId);
        $collection  = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addCategoryFilter($category);
        $collection->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        $collection->addAttributeToFilter(
            'status',
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
        );
        $collection->setPageSize(5);

        $new_productlist = $this->getproductCollection($collection);
        return ['title' => $category->getName(),
            'count'         => count($new_productlist), 'products' => $new_productlist];
    }

    private function getnewproducts()
    {
        $storeId    = $this->storeId;
        $collection = $this->productCollectionFactory->create();
        $todayDate  = date('Y-m-d', time());
        $collection->addAttributeToSelect('*')
            ->setPageSize(5)
            ->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()])
            ->setVisibility($this->productVisibility->getVisibleInSiteIds())
            ->addAttributeToFilter('news_from_date', ['date' => true, 'to' => $todayDate]);
        $collection->getSelect()->order('RAND()');
        $new_productlist = $this->getproductCollection($collection);
        return $new_productlist;
    }

    private function getBestsellerProducts()
    {
        $storeId    = $this->storeId;
        $collection = $this->productCollectionFactory->create()->addAttributeToSelect('*');
        $collection->addStoreFilter()
            ->joinField(
                'qty_ordered',
                'sales_bestsellers_aggregated_monthly',
                'qty_ordered',
                'product_id=entity_id',
                'at_qty_ordered.store_id=' . $storeId,
                'at_qty_ordered.qty_ordered > 0',
                'left'
            )->setPageSize(5);
        $collection->getSelect()->order('RAND()');
       
        $new_productlist = $this->getproductCollection($collection);
        return $new_productlist;
    }

    private function getsaleproducts()
    {
        $order = ($this->getRequest()->getParam('order')) ?
        ($this->getRequest()->getParam('order')) : 'entity_id';
        $dir          = ($this->getRequest()->getParam('dir')) ? ($this->getRequest()->getParam('dir')) : 'desc';
        $page         = ($this->getRequest()->getParam('page')) ? ($this->getRequest()->getParam('page')) : 1;
        $limit        = ($this->getRequest()->getParam('limit')) ? ($this->getRequest()->getParam('limit')) : 5;
        $todayDate    = $this->date->gmtDate();
        $tomorrow     = mktime(0, 0, 0, date('m'), date('d') + 1, date('y'));
        $dateTomorrow = date('m/d/y', $tomorrow);
        $storeId      = $this->storeId;
        $collection   = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*')->addAttributeToFilter('visibility', [
            'neq' => 1,
        ])->addAttributeToFilter('status', 1)->addAttributeToFilter('special_price', [
            'neq' => "0",
        ])->addAttributeToFilter('special_from_date', [
            'date' => true,
            'to'   => $todayDate,
        ])->addAttributeToFilter([
            [
                'attribute' => 'special_to_date',
                'date'      => true,
                'from'      => $dateTomorrow,
            ],
            [
                'attribute' => 'special_to_date',
                'null'      => 1,
            ],
        ])
            ->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->getSelect()->order('RAND()');
        $new_productlist = $this->getproductCollection($collection);
        return $new_productlist;
    }

    /*api to get product collection with category filter start*/
    private function getproductCollection($collection)
    {
        $new_productlist = [];
        foreach ($collection as $product) {
            $specialprice         = $product->getPriceInfo()->getPrice('special_price')->getAmount()->getValue();
            $final_price_with_tax = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
            if ($specialprice >= $final_price_with_tax) {
                $specialprice = $final_price_with_tax;
            }
            $new_productlist[] = [
                'entity_id'              => $product->getId(),
                'sku'                    => $product->getSku(),
                'name'                   => $product->getName(),
                'news_from_date'         => $product->getNewsFromDate() ?: '',
                'news_to_date'           => $product->getNewsToDate() ?: '',
                'special_from_date'      => $product->getSpecialFromDate() ?: '',
                'special_to_date'        => $product->getSpecialToDate() ?: '',
                'image_url'              => $this->imageHelper
                    ->init($product, 'product_page_image_large')
                    ->setImageFile($product->getFile())
                    ->resize('300', '300')
                    ->getUrl(),
                'url_key'                => $product->getProductUrl(),
                'qty'                    => $this->stockStateInterface
                    ->getStockQty($product->getId(), $product->getStore()->getWebsiteId()),
                'review'                 => [],
                'symbol'                 => $this->customHelper->getCurrencysymbolByCode($this->currency),
                'currency_rate'          => $this->storeManager->getStore()->getCurrentCurrencyRate(),

                'regular_price_with_tax' => number_format($product->getPrice(), 2, '.', ''),
                'final_price_with_tax'   => number_format($product->getFinalPrice(), 2, '.', ''),
                'specialprice'           => number_format($specialprice, 2, '.', ''),
                'wishlist'               => $this->productHelper->checkWishlist($product->getId()),
            ];
        }
        return $new_productlist;
    }
/*api to get product collection with category filter end*/

    private function createNewcache($key, $data, $lifeTime = 300)
    {
        try {
            $odata     = \Magento\Framework\App\ObjectManager::getInstance();
            $cache     = $odata->get('Magento\Framework\App\CacheInterface');
            $cache_key = "mss_" . $key . "_store";
            $cache->save($data, $cache_key, ["mss"], $lifeTime);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the Products according to User need
     */
    private function getPermotionalProdcts($key)
    {

        switch ($key) {
            case '1':
                $newproducts = $this->getnewproducts();

                $getnewproducts = [
                    'title'    => __('New Products'),
                    'count'    => count($newproducts),
                    'type'     => 'slider',
                    'products' => $newproducts,
                ];

                $array = $getnewproducts;
                return $array;
            case '2':
                $newproductssale = $this->getsaleproducts();

                $getnewproductssale = [
                    'title'    => __('Sale Products'),
                    'count'    => count($newproductssale),
                    'type'     => 'slider',
                    'products' => $newproductssale,
                ];

                $array = $getnewproductssale;
                return $array;
            case '0':
                $getBestseller = $this->getBestsellerProducts();

                $getBestsellerProducts = [
                    'title'    => __('Top Products'),
                    'count'    => count($getBestseller),
                    'type'     => 'slider',
                    'products' => $getBestseller,
                ];

                $array = $getBestsellerProducts;
                return $array;
        }
    }
}
