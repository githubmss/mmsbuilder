<?php
namespace Magentomobileshop\Connector\Controller\index;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * Product Collection
     *
     * @var array
     */
    private $productCollectionFactory;

    /**
     * category Collection
     *
     * @var array
     */
    private $categoryFactory;

    /**
     * Product Id
     *
     * @var int
     */
    private $page;

    /**
     * order
     *
     * @var desc, asc
     */
    private $order;

    /**
     * Collection
     *
     * @var null
     */
    private $productFinalCollection;

    /**
     * limt
     *
     * @var int
     */
    private $limit;

    /**
     * Sort
     *
     * @var int
     */
    private $dir;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Review\Model\ReviewFactory $reviewFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magentomobileshop\Connector\Helper\Products $productHelper,
        \Magentomobileshop\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Directory\Helper\Data $directoryHelper
    ) {
        $this->_stockItemRepository     = $stockItemRepository;
        $this->categoryFactory          = $categoryFactory;
        $this->imageHelper              = $imageHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->_cacheTypeList           = $cacheTypeList;
        $this->_reviewFactory           = $reviewFactory;
        $this->_storeManager            = $storeManager;
        $this->customHelper             = $customHelper;
        $this->productHelper            = $productHelper;
        $this->resultJsonFactory        = $resultJsonFactory;
        $this->jsonHelper               = $jsonHelper;
        $this->request                  = $context->getRequest();
        $this->directoryHelper          = $directoryHelper;

        parent::__construct($context);
    }

    /**
     * @param cmd,categoryid,page,order,limit,filters,dir
     * @description : get Product listing
     * @return Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $this->currency = $this->getRequest()->getHeader('currency');
        $params         = $this->getRequest()->getContent();
        $params         = $this->jsonHelper->jsonDecode($params, true);
        $cmd            = $params['cmd'];
        if (!isset($params['cmd'])) {
            $result->setData(['status' => 'error', 'message' => 'Required parameter is missing.']);
            return $result;
        }
        switch ($cmd) {
            case 'catalog':
                $categoryid = $params['categoryid'];
                if (!isset($categoryid)) {
                    $result->setData(['status' => 'error', 'message' => 'Category Id is required.']);
                    return $result;
                }
                $this->page   = isset($params['page']) ? $params['page'] : 1;
                $this->limit  = isset($params['limit']) ? $params['limit'] : 10;
                $this->order  = isset($params['order']) ? $params['order'] : 'entity_id';
                $this->dir    = isset($params['dir']) ? $params['dir'] : 'desc';
                $collection   = $this->getProductCollectionFromCatId($categoryid);
                $price_filter = [];
                /*filter added*/
                if (isset($params['filter'])) {
                    $filters = $params['filter'];
                    foreach ($filters as $key => $filter) {
                        if (!empty($filter)) {
                            if ($filter['code'] == 'price') {
                                $price        = $filter['value'];
                                $price_filter = ['gt' => $price['minPrice'], 'lt' => $price['maxPrice']];
                                $collection   = $collection
                                    ->addAttributeToFilter('price', ['gteq' => $price['minPrice']]);
                                $collection = $collection
                                    ->addAttributeToFilter('price', ['lteq' => $price['maxPrice']]);
                                return $collection->addFinalPrice()
                                    ->getSelect()
                                    ->where('price_index.final_price >= ' . $price['minPrice']);
                            } else {
                                $this->__addfilters($filter, $collection);
                            }
                        }
                    }
                }
                /*filter added*/

                if (isset($params['min'])) {
                    $collection = $collection->addAttributeToFilter('price', ['gt' => $params['min']]);
                }
                if (isset($params['min'])) {
                    $collection = $collection->addAttributeToFilter('price', ['lt' => $params['max']]);
                }
                $collection = $collection->setOrder($this->order, $this->dir);
                $pages      = $collection->setPageSize($this->limit)->getLastPageNumber();

                if ($this->page <= $pages) {
                    $collection->setPageSize($this->limit)->setCurPage($this->page);
                    $this->getProductlist($collection, 'catalog', $price_filter);
                }
                return $this->_checkConditions($collection, $result);
                break;
        }
    }

    /**
     * @param categoryId
     * @description : get Product Collection form cat id
     * @return array
     */
    private function getProductCollectionFromCatId($categoryId)
    {

        $category = $this->categoryFactory->create()->load($categoryId);
        if ($category->getdata()) {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('*');
            $collection->addCategoryFilter($category);
            $collection->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
            $collection->addAttributeToFilter(
                'status',
                \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
            );
            return $collection;
        } else {
            $result = $this->resultJsonFactory->create();
            $result->setData(['status' => 'error', 'message' => 'Category Id not found.']);
            return $result;
        }
    }

    private function __applyFilters($collection)
    {
        $collection->setOrder($this->order, $this->dir);
        return $collection;
    }

    private function getProductlist($products, $mod = 'product')
    {

        $productlist = [];
        foreach ($products as $product) {
            if ($mod == 'catalog') {
                $this->_reviewFactory->create()->getEntitySummary($product, $this->_storeManager->getStore()->getId());
                $rating_final = (int) $product->getRatingSummary()->getRatingSummary() / 20;
            }
            if ($product->getTypeId() == "virtual") {
                $qty = true;
            } elseif ($product->getTypeId() == "configurable") {
                $qty = $this->_stockItemRepository->get($product->getId())->getIsInStock();
            } else {
                try {
                    $qty = (int) $this->_stockItemRepository->get($product->getId())->getQty();
                } catch (\Exception $e) {
                    continue;
                }
            }
            $productlist[] = $this->__getListProduct($product, $qty, $rating_final);
        }

        $this->productFinalCollection = $productlist;
    }

    private function __getListProduct($product, $qty, $rating_final)
    {
        $specialprice         = $product->getPriceInfo()->getPrice('special_price')->getAmount()->getValue();
        $final_price_with_tax = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
        if ($specialprice >= $final_price_with_tax) {
            $specialprice = $final_price_with_tax;
        }
        $result = [
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
            'qty'                    => $qty,
            'product_type'           => $product->getTypeId(),
            'rating'                 => $rating_final,
            'wishlist'               => $this->productHelper->checkWishlist($product->getId()),

        ];
        return $result;
    }

    private function __addfilters($filter, $collection)
    {
        $tableAlias     = $filter['code'] . '_idx';
        $objectData     = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource       = $objectData->create('Magento\Framework\App\ResourceConnection');
        $connection     = $resource->getConnection();
        $attributeModel = $objectData->create('Magento\Eav\Model\Entity\Attribute')
            ->getCollection()->addFieldToFilter('attribute_code', $filter['code']);
        if ($attributeModel) {
            $attributeId = $attributeModel->load(1)->getData();
            $attributeId = $attributeId[0]['attribute_id'];
        } else {
            //continue;
        }
        $conditions = [
            "{$tableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$tableAlias}.attribute_id = ?", $attributeId),
            $connection->quoteInto("{$tableAlias}.store_id = ?", $collection->getStoreId()),
        ];
        $filterCode = [];
        if (count($filter['value']) > 1) {
            foreach ($filter['value'] as $filterCodeValues) {
                $filterCode[] = $connection->quoteInto("{$tableAlias}.value = ?", $filterCodeValues['code']);
            }
        } else {
            $filterCode[] = $connection->quoteInto("{$tableAlias}.value = ?", $filter['value'][0]['code']);
        }
        $filterArray = array_merge($conditions, $filterCode);
        return $collection->getSelect()->join(
            [$tableAlias => 'catalog_product_index_eav'],
            implode(' AND ', $filterArray),
            []
        )->group('e.entity_id');
    }

    private function _checkConditions($collection, $result)
    {
        $count = $collection->getSize();
        if (!$count) {
            $result->setData([]);
            return $result;
        }
        if (!empty($this->productFinalCollection)) {
            $result->setData($this->productFinalCollection);
            return $result;
        } else {
            $result->setData([]);
            return $result;
        }
    }
}
