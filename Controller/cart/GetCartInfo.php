<?php
namespace Mmsbuilder\Connector\Controller\Cart;

class GetCartInfo extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->checkoutCart      = $checkoutCart;
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId   = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId    = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency  = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $addressId       = $this->request->getParam('address_id');
        $countryId       = $this->request->getParam('country_id');
        $setRegionId     = $this->request->getParam('region_id');
        $shipping_method = $this->request->getParam('shippingmethod');
        $zipcode         = $this->request->getParam('zipcode');
        $result          = $this->resultJsonFactory->create();
        $result->setData($this->customHelper->
            _getCartInformation($addressId, $countryId, $setRegionId, $shipping_method, $zipcode, $this->currency));
        return $result;
    }
}
