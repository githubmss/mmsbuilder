<?php
namespace Mmsbuilder\Connector\Controller\Index;

class Search extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->productModel             = $productModel;
        $this->imageHelper              = $imageHelper;
        $this->stockStateInterface      = $stockStateInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customHelper             = $customHelper;
        $this->resultJsonFactory        = $resultJsonFactory;
        $this->request                  = $context->getRequest();
        parent::__construct($context);
    }

    public function execute()
    {

        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $searchstring   = $this->request->getParam('search');
        $page        = ($this->request->getParam('page')) ? ($this->request->getParam('page')) : 1;
        $limit       = ($this->request->getParam('limit')) ? ($this->request->getParam('limit')) : 10;
        $order       = ($this->request->getParam('order')) ? ($this->request->getParam('order')) : 'position';
        $result      = $this->resultJsonFactory->create();
        $productlist = [];
        if ($searchstring) {
            $products = $this->productCollectionFactory->create();
            $products->addAttributeToSelect(['name', 'entity_id', 'status', 'visibility', 'sku'], 'inner')
                ->addAttributeToFilter([
                    ['attribute' => 'name', 'like' => '%' . $searchstring . '%'],
                    ['attribute' => 'sku', 'like' => '%' . $searchstring . '%'],
                ])
                ->addAttributeToFilter('status', 1)
                ->addAttributeToFilter('visibility', ['neq' => 1])
                ->setPageSize($limit)
                ->setCurPage($page);
            $objectData    = \Magento\Framework\App\ObjectManager::getInstance();
            $checkWishlist = $objectData->get('\Mmsbuilder\Connector\Helper\Products');
            foreach ($products as $key => $pro) {
                $objectData           = \Magento\Framework\App\ObjectManager::getInstance();
                $product              = $this->getEntity($pro->getData('entity_id'));
                $specialprice         = $product->getPriceInfo()->getPrice('special_price')->getAmount()->getValue();
                $final_price_with_tax = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
                if ($specialprice >= $final_price_with_tax) {
                    $specialprice = $final_price_with_tax;
                }
                /*special product of price ends*/
                $productlist[] = [
                    'entity_id'              => $product->getId(),
                    'product_type'           => $product->getTypeId(),
                    'sku'                    => $product->getSku(),
                    'name'                   => $product->getName(),
                    'news_from_date'         => $product->getNewsFromDate(),
                    'news_to_date'           => $product->getNewsToDate(),
                    'special_from_date'      => $product->getSpecialFromDate(),
                    'special_to_date'        => $product->getSpecialToDate(),
                    'is_in_stock'            => $product->isAvailable(),
                    'regular_price_with_tax' => number_format($product->getPrice(), 2, '.', ''),
                    'final_price_with_tax'   => number_format($product->getFinalPrice(), 2, '.', ''),
                    'weight'                 => number_format($product->getWeight(), 2, '.', ''),
                    'qty'                    => $this->stockStateInterface
                        ->getStockQty($product->getId(), $product->getStore()->getWebsiteId()),
                    'specialprice'           => number_format($specialprice, 2, '.', ''),
                    'url_key'                => $product->getProductUrl() . '?shareid=' . $product->getId(),
                    'image_url'              => $this->imageHelper
                        ->init($product, 'product_page_image_large')
                        ->setImageFile($product->getFile())
                        ->resize('500', '500')
                        ->getUrl(),
                    'wishlist'               => $checkWishlist->checkWishlist($product->getId()),
                    'symbol'                 => $this->customHelper->getCurrencysymbolByCode($this->currency),
                ];
            }
            if (!empty($productlist)) {
                $result->setData($productlist);
                return $result;
            } else {
                $result->setData([]);
                return $result;
            }
        } else {
            $result->setData(['status' => 'error', 'message' => __('Search string is required.')]);
            return $result;
        }
    }

    // Functionality to check product is in wishlist or not
    private function checkWishlist($productId)
    {
        $objectData = \Magento\Framework\App\ObjectManager::getInstance();
        $customer   = $objectData->get('Magento\Customer\Model\Session');
        if ($customer->isLoggedIn()) {
            $currentUserWishlist = $this->wishlistProvider->getWishlist();
            if ($currentUserWishlist) {
                $wishListItemCollection = $currentUserWishlist->getItemCollection();
            } else {
                return false;
            }
            $wishlist_product_id = [];
            foreach ($wishListItemCollection as $item) {
                $wishlist_product_id[] = $item->getProductId();
            }
            if (in_array($productId, $wishlist_product_id)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function getEntity($pId)
    {
        $objectData = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectData->create('Magento\Catalog\Model\Product')
            ->load($pId);
    }
}
