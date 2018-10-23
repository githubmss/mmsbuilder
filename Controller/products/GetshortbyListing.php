<?php
namespace Mmsbuilder\Connector\Controller\products;

class GetshortbyListing extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }

    /**
     * @param productid
     * @description : get sort of products.
     * @return Json
     */
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $category_id    = $this->request->getParam('category_id');
        $result         = $this->resultJsonFactory->create();
        if (!$category_id) {
            $result->setData(['status' => false, 'message' => __('Category Id is required.')]);
            return $result;
        }
        $_objectData = \Magento\Framework\App\ObjectManager::getInstance();
        $object_manager = $_objectData->create('Magento\Catalog\Model\Category')->load($category_id);
        $results        = [];
        foreach ($object_manager->getAvailableSortByOptions() as $key => $value) {
            if ($key == 'position') {
                $results[] = ['code' => $key, 'label' => $value->getText()];
            } else {
                $results[] = ['code' => $key, 'label' => $value];
            }
        }
        $result->setData($results);
        return $result;
    }
}
