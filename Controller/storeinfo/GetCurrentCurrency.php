<?php
namespace Mmsbuilder\Connector\Controller\storeinfo;

class GetCurrentCurrency extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Store\Model\Website $website,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Magento\Directory\Model\Currency $currencyFactory
    ) {
        $this->_storeManager     = $storeManager;
        $this->customHelper      = $customHelper;
        $this->website           = $website;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->localeCurrency    = $localeCurrency;
        $this->currencyFactory   = $currencyFactory;
        parent::__construct($context);
    }
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result         = $this->resultJsonFactory->create();
        $website_id     = $this->_storeManager->getStore()->getWebsiteId();
        $website        = $this->_storeManager->getWebsite($this->website);
        $storeId        = $this->_storeManager->getStore()->getId();
        $store          = $this->_storeManager->getStore($storeId);

        $codes      = $this->_storeManager->getStore()->getAvailableCurrencyCodes(true);
        $currencies = [];

        if (is_array($codes) && count($codes) > 1) {
            $rates = $this->currencyFactory->getCurrencyRates(
                $this->_storeManager->getStore()->getBaseCurrencyCode(),
                $codes
            );
            foreach ($codes as $code) {
                if (isset($rates[$code])) {
                    $currencies[] = [
                        'name'   => __($this->localeCurrency->getCurrency($code, 'nametocurrency')->getName()),
                        'code'   => $code,
                        'symbol' => $this->localeCurrency->getCurrency($code)->getSymbol(),
                    ];
                }
            }
        }
        $result->setData($currencies);
        return $result;
    }
}
