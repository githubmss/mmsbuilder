<?php
namespace Mmsbuilder\Connector\Controller\cart;

class Getshippingmethods extends \Magento\Framework\App\Action\Action
{
    private $checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Directory\Model\Currency $currency,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->checkoutCart      = $checkoutCart;
        $this->currency          = $currency;
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    private function getCheckOutSession()
    {
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->checkoutSession = $objectData->create('\Magento\Checkout\Model\Session');
        return $this->checkoutSession;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId   = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId    = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency  = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $countryId       = $this->getRequest()->getParam('country_id');
        $postCode        = $this->getRequest()->getParam('zipcode');
        $currentCurrency = $this->currency;
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        $session = $this->getCheckOutSession();
        $address = $session->getQuote()->getShippingAddress();
        $address->setCountryId($countryId)
            ->setPostcode($postCode)
            ->setSameAsBilling(1);

        $rates = $address
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->getGroupedAllShippingRates();
        $shipMethods = [];
        foreach ($rates as $carrier) {
            foreach ($carrier as $rate) {
                $shipMethods[] = [
                    'code'  => $rate->getData('code'),
                    'value' => $rate->getData('carrier_title'),
                    'price' => $rate->getData('price'),
                ];
            }
        }
        
        $result->setData($shipMethods);
        return $result;
    }
}
