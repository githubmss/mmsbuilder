<?php
namespace Mmsbuilder\Connector\Controller\products;

class Getproductdetail extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Mmsbuilder\Connector\Helper\Products $productHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->customHelper  = $customHelper;
        $this->productHelper = $productHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }

    /*
     * @params productid
     * @description : get the detail of product.
     * @return Json
     */
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $product_id     = $this->request->getParam('productid');
        $result         = $this->resultJsonFactory->create();
        if (!$product_id) {
            $result->setData(['status' => 'error', 'message' => __('Product Id is required.')]);
            return $result;
        }
        $results = $this->productHelper->loadProduct($product_id, $this->currency);
        $result->setData(['status' => 'success', 'data' => $results]);
        return $result;
    }
}
