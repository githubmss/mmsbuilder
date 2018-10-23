<?php
namespace Mmsbuilder\Connector\Controller\staticpages;

class GetPages extends \Magento\Framework\App\Action\Action
{
    const MSS_APP_PAGE_LIST = 'configuration/app_pages/cms_page_list';
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Cms\Model\ResourceModel\Page\CollectionFactory $pageCollectionFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
    ) {
        $this->scopeConfig           = $scopeConfig;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->customHelper          = $customHelper;
        $this->resultJsonFactory     = $resultJsonFactory;
        $this->filterProvider        = $filterProvider;
        $this->storeManagerInterface = $storeManagerInterface;
        parent::__construct($context);
    }
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result         = $this->resultJsonFactory->create();
        /*check cache for dashboard API*/
        $objectData = \Magento\Framework\App\objectData::getInstance();
        $cacheObj   = $objectData->get('Magento\Framework\App\Cache');
        $cacheKey   = "mss_staticpages_store_" . $this->storeId;
        $cacheTag   = "mss";
        if ($cacheObj->load($cacheKey)) {
            $resultArray = json_decode($cacheObj->load($cacheKey), true);
            $result->setData(['status' => 'success', 'count' => COUNT($resultArray), 'data' => $resultArray]);
            return $result;
        }
        /*check cache for dashboard API*/
        try {
            $identifier = $this->scopeConfig
                ->getValue(self::MSS_APP_PAGE_LIST, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);

            $data  = [];
            $pages = explode(',', $identifier);
            foreach ($pages as $page) {
                if ($page) {
                    $finalIdentifier = explode('|', $page);
                    $page_model      = $this->pageCollectionFactory->create()
                        ->addFieldToFilter('identifier', ['eq' => $finalIdentifier[0]])
                        ->addFieldToFilter('is_active', ['eq' => 1]);
                    foreach ($page_model as $key => $value) {
                        $data[] = [
                            'page_title'   => $value->getTitle(),
                            'identifier'   => $value->getIdentifier(),
                            'page_content' => $this->filterProvider
                                ->getBlockFilter()->setStoreId($this->storeId)->filter($value->getContent()),
                        ];
                    }
                }
            }
            if (!empty($data)) {
                $cacheObj->save(json_encode($data), $cacheKey, [$cacheTag], 300);
                $result->setData(['status' => 'success', 'count' => COUNT($data), 'data' => $data]);
                return $result;
            } else {
                $result->setData(['status' => 'error', 'message' =>
                    __('No page configured, please configure page first')]);
                return $result;
            }
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => __('Problem in loading data.')]);
            return $result;
        }
    }
}
