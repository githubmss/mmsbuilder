<?php
namespace Mmsbuilder\Connector\Controller\wishlist;

class GetWishlist extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Wishlist\Model\WishlistFactory $wishlistRepository,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Directory\Model\Currency $currentCurrency,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Mmsbuilder\Connector\Helper\Products $productHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface
    ) {
        $this->wishlistRepository  = $wishlistRepository;
        $this->currentCurrency     = $currentCurrency;
        $this->productModel        = $productModel;
        $this->productHelper       = $productHelper;
        $this->_storeManager       = $storeManager;
        $this->coreRegistry        = $coreRegistry;
        $this->customHelper        = $customHelper;
        $this->imageHelper         = $imageHelper;
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->directoryHelper     = $directoryHelper;
        $this->stockStateInterface = $stockStateInterface;
        parent::__construct($context);
    }
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId         = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId          = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency        = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $wishlist              = $this->coreRegistry->registry('wishlist');
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        $result                = $this->resultJsonFactory->create();
        $customer              = $this->customerSession->getCustomer();
        $customerId            = $customer->getId();
        $wishlist              = $this->wishlistRepository->create()->loadByCustomerId($customerId, true);
        $this->coreRegistry->registry('wishlist', $wishlist);
        $items = [];
        foreach ($wishlist->getItemCollection() as $item) {
            $product              = $this->loadItemId($item);
            $specialprice         = $product->getPriceInfo()->getPrice('special_price')->getAmount()->getValue();
            $final_price_with_tax = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
            if ($specialprice >= $final_price_with_tax) {
                $specialprice = $final_price_with_tax;
            }
            if ($product->getId()) {
                $items[] = [
                    'entity_id'              => $product->getId(),
                    'sku'                    => $product->getSku(),
                    'name'                   => $product->getName(),
                    'news_from_date'         => $product->getNewsFromDate(),
                    'news_to_date'           => $product->getNewsToDate(),
                    'special_from_date'      => $product->getSpecialFromDate(),
                    'special_to_date'        => $product->getSpecialToDate(),
                    'image_url'              => $this->imageHelper
                        ->init($product, 'product_page_image_large')
                        ->setImageFile($product->getFile())
                        ->resize('300', '300')
                        ->getUrl(),
                    'url_key'                => $product->getProductUrl(),
                    'regular_price_with_tax' => number_format($product->getPrice(), 2, '.', ''),
                    'final_price_with_tax'   => number_format($product->getFinalPrice(), 2, '.', ''),
                    'specialprice'           => number_format($specialprice, 2, '.', ''),
                    'symbol'                 => $this->customHelper->getCurrencysymbolByCode($this->currency),
                    'qty'                    => $this->stockStateInterface
                        ->getStockQty($product->getId(), $product->getStore()->getWebsiteId()),
                    'product_type'           => $product->getTypeId(),
                    'wishlist'               => $this->productHelper->checkWishlist($product->getId()),

                ];
            }
        }
        $result->setData(['wishlist' => (object) $wishlist->getData(), 'items' => $items]);
        return $result;
    }

    private function loadItemId($item)
    {
        return $this->productModel->setStoreId($item->getStoreId())->load($item->getProductId());
    }
}
