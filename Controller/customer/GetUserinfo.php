<?php
namespace Mmsbuilder\Connector\Controller\Customer;

class GetUserinfo extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->resultPageFactory  = $resultPageFactory;
        $this->customerFactory    = $customerFactory;
        $this->storeManager       = $storeManager;
        $this->customHelper       = $customHelper;
        $this->customerRepository = $customerRepository;
        $this->resultJsonFactory  = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));

        $result = $this->resultJsonFactory->create();
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        if ($this->customerSession->isLoggedIn()) {
            $info          = [];
            $customer      = $this->customerSession->getCustomer();
            $customerData  = $this->customerRepository->getById($customer->getId());
            $customerImage = $customerData->getCustomAttribute('mss_profile_image_customer');

            $info['firstname'] = $customer->getFirstname();
            $info['lastname']  = $customer->getLastname();
            $info['email']     = $customer->getEmail();
            if ($customerImage) {
                $info['profile_image'] = $customerImage->getValue();
            }
            $result->setData(['status' => 'success', 'data' => $info]);
            return $result;
        } else {
            $result->setData(['status' => 'error', 'message' => __('Session expired , Please login again.')]);
            return $result;
        }
    }
}
