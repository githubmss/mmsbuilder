<?php
namespace Mmsbuilder\Connector\Controller\storeinfo;

class Getstoredata extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Store\Model\Website $website,
        \Magento\Store\Model\Store $storeModel,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerFactory   = $customerFactory;
        $this->_storeManager     = $storeManager;
        $this->customHelper      = $customHelper;
        $this->website           = $website;
        $this->storeModel        = $storeModel;
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
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        $website_id = $this->_storeManager->getStore()->getWebsiteId();
        $websiteGroups = $this->_storeManager->getWebsite()->getGroups();
        foreach ($websiteGroups as $group) {
            $stores         = $group->getStores();
            $store_view     = [];
            $new_array      = [];
            $currencyInfo   = [];
            $store_currency = '';
            foreach ($stores as $key => $view) {
                $store_view['name']            = $view->getName();
                $store_view['view_id']         = $view->getStoreId();
                $store_view['store_url']       = $view->getUrl();
                $store_view['store_code']      = $view->getCode();
                $store_view['store_name']      = $view->getName();
                $store_view['sort_order']      = $view->getSortOrder();
                $store_view['is_active']       = $view->getIsActive();
                $store_view['currency']        = $view->getCurrentCurrencyCode();
                $store_view['currency_symbol'] = $view->getCurrentCurrencySymbol() ?: $view->getCurrentCurrencyCode();
                if ($store_currency != $view->getCurrentCurrencyCode()) {
                    $currencyInfo[] = ['code' => $view->getCurrentCurrencyCode(),
                        'symbol'                  => $view->getCurrentCurrencySymbol() ?:
                        $view->getCurrentCurrencyCode(),
                        'view_id'                 => $view->getId(),
                    ];
                }

                $store_currency = $view->getCurrentCurrencyCode();
                array_push($new_array, $store_view);
            }
            $basicinfo[] = [
                'store'            => $group->getName(),
                'store_id'         => $group->getGroupId(),
                'root_category_id' => $group->getRootCategoryId(),
                'view'             => $new_array,
                'currency_data'    => $currencyInfo,
            ];

            $store_view = '';
        }
        $result->setData($basicinfo);
        return $result;
    }
}
