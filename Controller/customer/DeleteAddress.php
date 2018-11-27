<?php
namespace Mmsbuilder\Connector\Controller\customer;

class DeleteAddress extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->addressFactory = $addressFactory;
        $this->storeManager   = $storeManager;
        $this->customHelper   = $customHelper;
        $this->customer          = $customer;
        $this->addressRepository = $addressRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId         = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId          = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency        = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $customer              = $this->customerSession;
        $addressId             = $this->request->getParam('addressId');
        $result                = $this->resultJsonFactory->create();
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        if (!$addressId) {
            $result->setData(['status' => 'error', 'message' => __('Please select address.')]);
            return $result;
        }
        if ($customer->isLoggedIn()) {
            try {
                $this->addressRepository->deleteById($addressId);
                $result->setData(['status' => 'success', 'message' => __('Address deleted successfully.')]);
                return $result;
            } catch (\Exception $e) {
                $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
                return $result;
            }
        } else {
            $result->setData(['status' => 'error', 'message' => __('Session expired, Please login again.')]);
            return $result;
        }
    }
}
