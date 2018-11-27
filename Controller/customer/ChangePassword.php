<?php
namespace Mmsbuilder\Connector\Controller\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Reflection\DataObjectProcessor;

class ChangePassword extends \Magento\Framework\App\Action\Action
{

    const XML_PATH_RESET_PASSWORD_TEMPLATE = 'customer/password/reset_password_template';

    const XML_PATH_FORGOT_EMAIL_IDENTITY = 'customer/password/forgot_email_identity';

    const FORM_DATA_EXTRACTOR_CODE = 'customer_account_edit';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\Customer $customerModel,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepo,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        CustomerViewHelper $customerViewHelper,
        DataObjectProcessor $dataProcessor,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        CustomerExtractor $customerExtractor
    ) {
        $this->customHelper       = $customHelper;
        $this->jsonHelper         = $jsonHelper;
        $this->customerFactory    = $customerFactory;
        $this->customerModel      = $customerModel;
        $this->resultJsonFactory  = $resultJsonFactory;
        $this->request            = $context->getRequest();
        $this->customerRepo       = $customerRepo;
        $this->customerViewHelper = $customerViewHelper;
        $this->dataProcessor      = $dataProcessor;
        $this->customerRegistry   = $customerRegistry;
        $this->customerExtractor  = $customerExtractor;
        $this->transportBuilder   = $transportBuilder;
        $this->scopeConfig        = $scopeConfig;
        $this->storeManager       = $storeManager;

        parent::__construct($context);
    }
    public function execute()
    {
        $this->storeId         = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId          = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency        = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result                = $this->resultJsonFactory->create();
        $validate              = 0;
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        if ($this->customerSession->isLoggedIn()) {
            $customer    = $this->customerSession->getCustomer();
            $customerid  = $customer->getEntityId();
            $oldpassword = $this->customHelper->decode($this->request->getParam('oldpassword'));
            $newpassword = $this->customHelper->decode($this->request->getParam('newpassword'));
            $username    = $customer->getEmail();
            try {
                $login_customer_result = $this->customerModel->setWebsiteId('1')->authenticate($username, $oldpassword);
                $validate              = 1;
            } catch (\Exception $ex) {
                $validate = 0;
            }
            if ($validate == 1) {
                try {
                    $customer = $this->customerModel->load($customerid);
                    $customer->setPassword($newpassword);
                    $customer->save();

                    $currentCustomerDataObject   = $this->getCustomerDataObject($customerid);
                    $customerCandidateDataObject = $this->populateNewCustomerDataObject(
                        $this->_request,
                        $currentCustomerDataObject
                    );
                    $this->passwordReset($customerCandidateDataObject);
                    $result->setData(['status' => 'success', 'message' =>
                        'Your password has been changed successfully.']);
                    return $result;
                } catch (\Exception $ex) {
                    $result->setData(['status' => 'error', 'message' => __($ex->getMessage())]);
                    return $result;
                }
            } else {
                $result->setData(['status' => 'error', 'message' => __('Incorrect old password.')]);
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
        return ObjectManager::getInstance()->get(EmailNotificationInterface::class);
    }

    /**
     * Send email to customer when his email is changed
     *
     * @param CustomerInterface $customer
     * @param string $email
     * @return void
     */
    private function passwordReset(CustomerInterface $customer)
    {

        $storeId = $customer->getStoreId();
        if (!$storeId) {
            $storeId = $this->storeId;
        }

        $customerEmailData = $this->getFullCustomerObject($customer);

        $this->sendEmailTemplate(
            $customer,
            self::XML_PATH_RESET_PASSWORD_TEMPLATE,
            self::XML_PATH_FORGOT_EMAIL_IDENTITY,
            ['customer' => $customerEmailData, 'store' => $this->storeManager->getStore($storeId)],
            $storeId
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
}
