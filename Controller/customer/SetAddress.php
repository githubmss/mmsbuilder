<?php
namespace Mmsbuilder\Connector\Controller\customer;

class SetAddress extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        $this->addressFactory    = $addressFactory;
        $this->storeManager      = $storeManager;
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        $this->jsonHelper        = $jsonHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result         = $this->resultJsonFactory->create();
        try {
            $params     = $this->getRequest()->getContent();
            $finalJosn  = $this->jsonHelper->jsonDecode($params, true);
            $customerId = $finalJosn['userid'];
            if ($customerId) {
                foreach ($finalJosn['address'] as $address) {
                    $this->checkValidations($address);
                    $addresssObj = $this->addressFactory->create();
                    $addresssObj->setCustomerId($customerId);
                    $addresssObj->setFirstname($address['firstname']);
                    $addresssObj->setLastname($address['lastname']);
                    $addresssObj->setCountryId($address['country_id']);
                    $addresssObj->setPostcode($address['postcode']);
                    $addresssObj->setCity($address['city']);
                    $addresssObj->setTelephone($address['telephone']);
                    if (isset($address['region'])) {
                        $addresssObj->setRegion($address['region']);
                    } else {
                        $addresssObj->setRegionId($address['region_id']);
                    }
                    $addresssObj->setStreet($address['street']);
                    $addresssObj->setIsDefaultBilling($address['is_default_billing']);
                    $addresssObj->setIsDefaultShipping($address['is_default_shipping']);
                    $addresssObj->setSaveInAddressBook('1');
                    try {
                        $this->getLoad($addresssObj);
                        $new_array[] = $addresssObj->getId();
                    } catch (\Exception $e) {
                        $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
                        return $result;
                    }
                }
                if ($new_array) {
                    $result->setData(['status' => 'success', 'message' => __('Address added successfully.')]);
                    return $result;
                }
            } else {
                $result->setData(['status' => 'error', 'message' => __('No record found.')]);
                return $result;
            }
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
            return $result;
        }
    }

    private function checkValidations($address)
    {

        if (!\Zend_Validate::is($address['firstname'], 'NotEmpty')) {
            $result->setData(['status' => 'error', 'message' => __('Firstname is required.')]);
            return $result;
        }
        if (!\Zend_Validate::is($address['lastname'], 'NotEmpty')) {
            $result->setData(['status' => 'error', 'message' => __('Lastname is required.')]);
            return $result;
        }
        if (!\Zend_Validate::is($address['street'], 'NotEmpty')) {
            $result->setData(['status' => 'error', 'message' => __('Street is required.')]);
            return $result;
        }
        if (!\Zend_Validate::is($address['city'], 'NotEmpty')) {
            $result->setData(['status' => 'error', 'message' => __('City is required.')]);
            return $result;
        }
        if (!\Zend_Validate::is($address['country_id'], 'NotEmpty')
            || $address['country_id'] == 'undefined') {
            $result->setData(['status' => 'error', 'message' =>
                __('Country is required.')]);
            return $result;
        }
        if (!\Zend_Validate::is($address['telephone'], 'NotEmpty')) {
            $result->setData(['status' => 'error', 'message' => __('Telephone number is required.')]);
            return $result;
        }

        if ($address['firstname'] == null) {
            $result->setData(['status' => 'error', 'message' => __('Firstname is required.')]);
            return $result;
        }
    }
 
    private function getLoad($addresssObj)
    {
        $addresssObj->save();
    }
}
