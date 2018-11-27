<?php
namespace Mmsbuilder\Connector\Controller\cart;

class Getpaymentmethods extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Payment\Model\Config $paymentMethodConfig,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->paymentMethodConfig = $paymentMethodConfig;
        $this->scopeConfig         = $scopeConfig;
        $this->customHelper        = $customHelper;
        $this->resultJsonFactory   = $resultJsonFactory;
        parent::__construct($context);
    }
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $payments       = $this->paymentMethodConfig->getActiveMethods();
        $result         = $this->resultJsonFactory->create();
        $methods        = [];
        $payments       = $this->paymentMethodConfig->getActiveMethods();
        $methods        = [];
        $objectData     = \Magento\Framework\App\ObjectManager::getInstance();
        $cart           = $objectData->get('\Magento\Checkout\Model\Cart');
        $quote_id       = $cart->getQuote()->getEntityId();
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->scopeConfig->getValue('payment/' . $paymentCode . '/title');
            if ($paymentCode == 'cashondelivery') {
                $methods[] = [
                    'value'    => $paymentTitle,
                    'code'     => $paymentCode,
                    'quote_id' => $quote_id,
                ];
            } else {
                if ($paymentCode == 'paypal_express') {
                    $methods[] = [
                        'value'    => $paymentTitle,
                        'code'     => $paymentCode,
                        'quote_id' => $quote_id,
                    ];
                }
            }
        }
        //magentomobileshop_payment/cashondelivery/cod_status
        $result->setData($methods);
        return $result;
    }
}
