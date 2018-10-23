<?php
namespace Mmsbuilder\Connector\Controller\products;

class GetFilters extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Mmsbuilder\Connector\Helper\Filters $filters,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->customHelper      = $customHelper;
        $this->filters           = $filters;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }

    /**
     * @param categoryid
     * @description : get Category Navigation Filters
     * @return Json
     */
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $categoryId     = $this->request->getParam('categoryid');
        $result         = $this->resultJsonFactory->create();
        if (empty($categoryId)) {
            $result->setData(['status' => 'error', 'message' => __('Category Id is required.')]);
            return $result;
        } else {
            $resultData = $this->filters->getFilterByCategory($categoryId);
        }
        $result->setData($resultData);
        return $result;
    }
}
