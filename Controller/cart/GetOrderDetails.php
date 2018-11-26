<?php
namespace Mmsbuilder\Connector\Controller\cart;

//Connector Magento Mobile Shop get Order details api.
class GetOrderDetails extends \Magento\Framework\App\Action\Action
{

    private $order;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {

        $this->customHelper = $customHelper;
        $this->jsonHelper   = $jsonHelper;
        $this->resultJsonFactory  = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result->setData($this->customHelper->getOrderDetails($this->getRequest()
                ->getParam('order_id'), $this->currency));
        return $result;
    }
}
