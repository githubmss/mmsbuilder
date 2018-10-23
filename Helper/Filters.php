<?php
namespace Mmsbuilder\Connector\Helper;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;

class Filters extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        CollectionFactory $factory
    ) {
        $this->collectionFactory = $factory;
        $this->_logger           = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function getFilterByCategory($categoryId)
    {
        $result               = $this->resultJsonFactory->create();
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $filterableAttributes = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Catalog\Model\Layer\Category\FilterableAttributeList::class);
            $layerResolver = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Catalog\Model\Layer\Resolver::class);
            $filterList = \Magento\Framework\App\ObjectManager::getInstance()->create(
                \Magento\Catalog\Model\Layer\FilterList::class,
                [
                    'filterableAttributes' => $filterableAttributes,
                ]
            );
            $category = $categoryId;
            $layer    = $layerResolver->get();
            $layer->setCurrentCategory($category);
            $filters = $filterList->getFilters($layer);

            $resultfilters = [];
            $k             = 0;
            foreach ($filters as $filter) {
                if ($filter->getName() == 'Price') {
                    $resultfilters[$k]['label'] = $filter->getName();
                    $resultfilters[$k]['code']  = $filter->getRequestVar();
                    $data                       = [];
                    $counter                    = !empty($filter->getItems());
                    $i                          = 0;
                    foreach ($filter->getItems() as $item) {
                        if (is_numeric(substr($item->getValue(), 0, 1))) {
                            $value = $item->getValue();
                        } else {
                            $value = '0' . $item->getValue();
                        }
                        if (!is_numeric(substr($value, -1))) {
                            $value = $item->getValue() . '0';
                        }
                        if (!$i) {
                            $minValue     = explode('-', $value);
                            $data['min']  = $minValue[0];
                            $data['step'] = $minValue[1] - $minValue[0];
                        }
                        if ($i == ($counter - 1)) {
                            $minValue    = explode('-', $value);
                            $data['max'] = $minValue[1] ?: $minValue[0];
                        }
                        $i++;
                    }
                    $myfilters['code']          = $value;
                    $myfilters['label']         = strip_tags($item->getLabel());
                    $resultfilters[$k]['value'] = $data;
                    continue;
                }
                $this->__filterItems($filter);
                foreach ($filter->getItems() as $item) {
                    $myfilters                    = [];
                    $myfilters['code']            = $item->getValue();
                    $myfilters['label']           = $item->getLabel();
                    $resultfilters[$k]['value'][] = $myfilters;
                }
                $k++;
            }
            $json = ['status' => 'success', 'category' => null, 'filters' => array_values($resultfilters)];
        } catch (\Exception $e) {
            $json = ['status' => 'error', 'message' => $e->getMessage()];
        }
        $result->setData([$json]);
        return $result;
    }

    public function getpricerange($maincategoryId)
    {
        $pricerange = [];
        $layer      = $layerResolver      = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Catalog\Model\Layer\Resolver::class)->get();
        $category = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Catalog\Model\CategoryRepository::class)->get($maincategoryId);
        if ($category->getId()) {
            $origCategory = $layer->getCurrentCategory();
            $layer->setCurrentCategory($category);
        }
        $r        = $this->Price->setLayer($layer);
        $range    = $layer->getPriceRange();
        $dbRanges = $layer->getRangeItemCounts($range);
        $data     = [];
        foreach ($dbRanges as $index => $count) {
            $data[] = [
                'label' => $this->_renderItemLabel($range, $index),
                'value' => $this->_renderItemValue($range, $index),
                'count' => $count,
            ];
        }
        return $data;
    }

    private function __filterItems($filter)
    {
        if ($filter->getItems()) {
             $resultfilters[$k]['label'] = $filter->getName();
             $resultfilters[$k]['code']  = $filter->getRequestVar();
             return $resultfilters[$k];
        }
    }
}
