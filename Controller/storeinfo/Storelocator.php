<?php
namespace Mmsbuilder\Connector\Controller\storeinfo;

class Storelocator extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Store\Model\Store $storeModel,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->_storeManager     = $storeManager;
        $this->customHelper      = $customHelper;
        $this->storeModel        = $storeModel;
        $this->scopeConfig       = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result         = $this->resultJsonFactory->create();
        $Info           = ['phone', 'name', 'hours', 'address', 'longitude', 'latitude'];
        $website_id     = $this->_storeManager->getStore()->getWebsiteId();
        $website        = $this->_storeManager->getWebsite($website_id);
        $storedata      = [];
        foreach ($website->getGroups() as $key => $group) {
            $stores = $group->getStores();

            $store_Id = '';
            $view_Id  = '';
            foreach ($stores as $key => $view) {
                $store_Id = $view->getStoreId();
                if ($view->getStoreId()) {
                    $v_stores = $group->getStores();
                    foreach ($v_stores as $v_key => $v_view) {
                        $view_Id = $v_view->getStoreId();
                        break;
                    };
                    break;
                };
            };

            foreach ($Info as $detail) {
                $storedetails[$detail] = $this->scopeConfig
                    ->getValue('general/store_information/' .
                        $detail, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store_Id);
            }

            $storedetails['store_id']   = $store_Id;
            $storedetails['view_id']    = $view_Id;
            $storedetails['store_code'] = $this->_storeManager->getStore($store_Id)->getCode();
            $storedata[]                = $storedetails;
        };

        $result->setData(['status' => 'success', 'data' => $storedata]);
        return $result;
    }
}
