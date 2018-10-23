<?php
namespace Mmsbuilder\Connector\Controller\Products;

class Search extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Mmsbuilder\Connector\Helper\Products $productHelper
    ) {
        $this->productModel             = $productModel;
        $this->imageHelper              = $imageHelper;
        $this->stockStateInterface      = $stockStateInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customHelper             = $customHelper;
        $this->resultJsonFactory        = $resultJsonFactory;
        $this->request                  = $context->getRequest();
        $this->storeManager             = $storeManager;
        $this->directoryHelper          = $directoryHelper;
        $this->productHelper            = $productHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result         = $this->resultJsonFactory->create();
        $searchString   = $this->request->getParam('search');
        $searchType     = $this->request->getParam('type'); //name or sku depend on search type
        if (!$searchString || !$searchType) {
            $result->setData(['status' => 'error', 'message' => __('Search string is required.')]);
            return $result;
        }
        $objectData      = \Magento\Framework\App\objectData::getInstance();
        $search_criteria = $objectData
            ->create('Magento\Framework\Api\SearchCriteriaBuilder')
            ->addFilter($searchType, $searchString, 'like')->create();
        $productRepository = $objectData->get('Magento\Catalog\Model\ProductRepository');
        $finalData         = $productRepository->getList($search_criteria);
        $products          = $finalData->getItems();
        $finalResult       = [];
        foreach ($products as $product) {
            $product = $this->_loadData($product);
            if ($product->getTypeId() == 'simple') {
                $regular_price = number_format($this->directoryHelper
                        ->currencyConvert($product->getPrice(), $this->storeManager
                                ->getStore()->getBaseCurrencyCode(), $this->currency), 2, '.', '');
            } else {
                $regular_price = number_format($this->directoryHelper
                        ->currencyConvert($product->getFinalPrice(), $this->storeManager
                                ->getStore()->getBaseCurrencyCode(), $this->currency), 2, '.', '');
            }
            $finalResult[] = [
                'entity_id'              => $product->getId(),
                'product_type'           => $product->getTypeId(),
                'sku'                    => $product->getSku(),
                'name'                   => $product->getName(),
                'news_from_date'         => $product->getNewsFromDate(),
                'symbol'                 => $this->customHelper->getCurrencysymbolByCode($this->currency),
                'news_to_date'           => $product->getNewsToDate(),
                'special_from_date'      => $product->getSpecialFromDate(),
                'special_to_date'        => $product->getSpecialToDate(),
                'description'            => $product->getDescription(),
                'short_description'      => $product->getShortDescription(),
                'is_in_stock'            => $product->isAvailable(),
                'final_price_with_tax'   => number_format(
                    $this->directoryHelper->currencyConvert(
                        $product->getFinalPrice(),
                        $this->storeManager->getStore()->getBaseCurrencyCode(),
                        $this->currency
                    ),
                    2,
                    '.',
                    ''
                ),
                'regular_price_with_tax' => $regular_price,
                'specialprice'           => number_format(
                    $this->customHelper->getSpecialPriceProduct(
                        $product->getId()
                    ),
                    2,
                    '.',
                    ''
                ),
                'weight'                 => number_format($product->getWeight(), 2, '.', ''),
                'qty'                    => $this->stockStateInterface
                    ->getStockQty($product->getId(), $product->getStore()->getWebsiteId()),
                'specialprice'           => number_format($product->getSpecialPrice(), 2, '.', ''),
                'url_key'                => $product->getProductUrl() . '?shareid=' . $product->getId(),
                'image_url'              => $this->imageHelper
                    ->init($product, 'product_page_image_small')
                    ->setImageFile($product->getFile())
                    ->resize('250', '250')
                    ->getUrl(),
                'image_url_large'        => $this->imageHelper
                    ->init($product, 'product_page_image_large')
                    ->setImageFile($product->getFile())
                    ->resize('500', '500')
                    ->getUrl(),
                'image_url_medium'       => $this->imageHelper
                    ->init($product, 'product_page_image_medium')
                    ->setImageFile($product->getFile())
                    ->getUrl(),
                'wishlist'               => $this->productHelper->checkWishlist($product->getId()),

            ];

            $finalResult[] = $product->getData();
        }
        if ($products) {
            $result->setData(['status' => 'success', 'data' => $finalResult]);
        } else {
            $result->setData(['status' => 'error', 'message' => __('There are no products matching the selection.')]);
        }
        return $result;
    }

    private function _loadData($product)
    {
        return $this->productModel->load($product->getData('entity_id'));
    }
}
