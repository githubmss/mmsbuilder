<?php
namespace Mmsbuilder\Connector\Controller\customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Reflection\DataObjectProcessor;

class SetUserinfo extends \Magento\Framework\App\Action\Action
{

    const XML_PATH_CHANGE_EMAIL_TEMPLATE = 'customer/account_information/change_email_template';

    const XML_PATH_FORGOT_EMAIL_IDENTITY = 'customer/password/forgot_email_identity';

    const FORM_DATA_EXTRACTOR_CODE = 'customer_account_edit';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Customer\Model\Authentication $authentication,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepo,
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        CustomerViewHelper $customerViewHelper,
        DataObjectProcessor $dataProcessor,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        CustomerExtractor $customerExtractor
    ) {
        $this->resultPageFactory  = $resultPageFactory;
        $this->customerFactory    = $customerFactory;
        $this->storeManager       = $storeManager;
        $this->customerRepo       = $customerRepo;
        $this->customHelper       = $customHelper;
        $this->authenticate       = $authentication;
        $this->addressFactory     = $addressFactory;
        $this->resultJsonFactory  = $resultJsonFactory;
        $this->request            = $context->getRequest();
        $this->customerViewHelper = $customerViewHelper;
        $this->dataProcessor      = $dataProcessor;
        $this->customerRegistry   = $customerRegistry;
        $this->customerExtractor  = $customerExtractor;
        $this->transportBuilder   = $transportBuilder;
        $this->scopeConfig        = $scopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId         = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId          = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency        = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result                = $this->resultJsonFactory->create();
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerFactory->create();
            $customer->load($this->customerSession->getId());
            $data = $this->request->getParams();
            if (isset($data)) {
                $customer->setFirstname($data['firstname']);
                $customer->setLastname($data['lastname']);
                $address = $customer->getPrimaryBillingAddress();
                if (!$address) {
                    $address = $this->addressFactory->create();
                    $address->setCustomerId($customer->getId());
                    $address->setIsDefaultBilling(true);
                }
                if (isset($data['email']) && $this->customerSession->getCustomer()->getId()) {
                    if (isset($data['password'])) {
                        try {
                            $this->authenticate->authenticate($this->customerSession
                                    ->getCustomer()->getId(), $this->getValue($data['password']));
                            $currentCustomerDataObject = $this
                                ->getCustomerDataObject($this->customerSession->getId());
                            $customerCandidateDataObject = $this->populateNewCustomerDataObject(
                                $this->_request,
                                $currentCustomerDataObject
                            );
                            $this->emailChanged($customerCandidateDataObject, $customer->getEmail());
                            $this->emailChanged($customerCandidateDataObject, $data['email']);

                            $customer->setEmail($data['email']);
                        } catch (\Exception $e) {
                            $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
                            return $result;
                        }
                    } else {
                        $result->setData(['status' => 'error', 'message' =>
                            __('Please enter password to change email.')]);
                        return $result;
                    }
                }
                try {
                    //Customer address save.
                    $customer->save();
                    $result->setData(['status' => 'success', 'message' => __('Data updated successfully.')]);
                    return $result;
                } catch (\Exception $e) {
                    $result->setData(['status' => 'error', 'message' => __('Data not updated.')]);
                    return $result;
                }
            } else {
                $result->setData(['status' => 'error', 'message' => __('Data not updated.')]);
                return $result;
            }
        } else {
            $result->setData(['status' => 'error', 'message' => __('Session expired, Please login again.')]);
            return $result;
        }
    }

    /**
     * Get email notification
     *
     * @return EmailNotificationInterface
     * @deprecated
     */
    private function getEmailNotification()
    {
        return ObjectManager::getInstance()->get(
            EmailNotificationInterface::class
        );
    }

    /**
     * Send email to customer when his email is changed
     *
     * @param CustomerInterface $customer
     * @param string $email
     * @return void
     */
    private function emailChanged(CustomerInterface $customer, $email)
    {

        $storeId = $customer->getStoreId();
        if (!$storeId) {
            $storeId = $this->storeId;
        }

        $customerEmailData = $this->getFullCustomerObject($customer);

        $this->sendEmailTemplate(
            $customer,
            self::XML_PATH_CHANGE_EMAIL_TEMPLATE,
            self::XML_PATH_FORGOT_EMAIL_IDENTITY,
            ['customer' => $customerEmailData, 'store' => $this->storeManager->getStore($storeId)],
            $storeId,
            $email
        );
    }

    private function sendEmailTemplate(
        $customer,
        $template,
        $sender,
        $templateParams = [],
        $storeId = null,
        $email = null
    ) {
        $templateId = $this->scopeConfig->getValue($template, 'store', $storeId);
        if ($email === null) {
            $email = $customer->getEmail();
        }
        $transport = $this->transportBuilder->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
            ->setTemplateVars($templateParams)
            ->setFrom($this->scopeConfig->getValue($sender, 'store', $storeId))
            ->addTo($email, $this->customerViewHelper->getCustomerName($customer))
            ->getTransport();

        $transport->sendMessage();
    }

    /**
     * Create an object with data merged from Customer and CustomerSecure
     *
     * @param CustomerInterface $customer
     * @return \Magento\Customer\Model\Data\CustomerSecure
     */
    private function getFullCustomerObject($customer)
    {
        // No need to flatten the custom attributes or nested objects since the only usage is for email templates and
        // object passed for events
        $mergedCustomerData = $this->customerRegistry->retrieveSecureData($customer->getId());
        $customerData       = $this->dataProcessor
            ->buildOutputDataArray($customer, \Magento\Customer\Api\Data\CustomerInterface::class);
        $mergedCustomerData->addData($customerData);
        $mergedCustomerData->setData('name', $this->customerViewHelper->getCustomerName($customer));
        return $mergedCustomerData;
    }

    /**
     * Create Data Transfer Object of customer candidate
     *
     * @param \Magento\Framework\App\RequestInterface $inputData
     * @param \Magento\Customer\Api\Data\CustomerInterface $currentCustomerData
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    private function populateNewCustomerDataObject(
        \Magento\Framework\App\RequestInterface $inputData,
        \Magento\Customer\Api\Data\CustomerInterface $currentCustomerData
    ) {
        $attributeValues = $this->getCustomerMapper()->toFlatArray($currentCustomerData);
        $customerDto     = $this->customerExtractor->extract(
            self::FORM_DATA_EXTRACTOR_CODE,
            $inputData,
            $attributeValues
        );
        $customerDto->setId($currentCustomerData->getId());
        if (!$customerDto->getAddresses()) {
            $customerDto->setAddresses($currentCustomerData->getAddresses());
        }
        if (!$inputData->getParam('email')) {
            $customerDto->setEmail($currentCustomerData->getEmail());
        }

        return $customerDto;
    }

    /**
     * Get Customer Mapper instance
     *
     * @return Mapper
     *
     * @deprecated
     */
    private function getCustomerMapper()
    {
        return ObjectManager::getInstance()->get('Magento\Customer\Model\Customer\Mapper');
    }

    /**
     * Get customer data object
     *
     * @param int $customerId
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    private function getCustomerDataObject($customerId)
    {
        return $this->customerRepo->getById($customerId);
    }

    private function checkData($imageResult)
    {
        if ($imageResult['status'] == 'error') {
            $result->setData(['status' => 'error', 'message' => __('Unable to upload image.')]);
            return $result;
        }
    }
}
