<?php
namespace Mmsbuilder\Connector\Controller\customer;

class GetAddress extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Directory\Model\Config\Source\Country $countryHelper,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->addressFactory    = $addressFactory;
        $this->storeManager      = $storeManager;
        $this->customHelper      = $customHelper;
        $this->customer          = $customer;
        $this->_resource         = $resource;
        $this->countryHelper     = $countryHelper;
        $this->countryFactory    = $countryFactory;
        $this->regionFactory     = $regionFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $connection     = $this->_resource
            ->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $tbl_directory_region  = $connection->getTableName('directory_country_region');
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        try {
            $result  = $this->resultJsonFactory->create();
            $session = $this->customerSession;
            $userid  = $this->request->getParam('userid');
            if ($userid) {
                $customerId = $session->getId();
                $addresss   = $this->customer->load($userid);
                #create customer address array
                $currentAddress = [];
                $alladdress     = [];
                #loop to create the array
                $default_shipping = $addresss->getDefaultShippingAddress();
                $default_billing  = $addresss->getDefaultBillingAddress();
                $countryName      = $this->countryFactory->create();
                foreach ($addresss->getAddresses() as $address) {
                    $address_array = [
                        'id'                  => $address->getId(),
                        'firstname'           => $address->getFirstname(),
                        'lastname'            => $address->getLastname(),
                        'street'              => $address->getStreet()[0],
                        'city'                => $address->getCity(),
                        'country_name'        => $this->_loadCountryId($countryName, $address),
                        'country_id'          => $address->getCountryId(),
                        'postcode'            => $address->getPostcode(),
                        'telephone'           => $address->getTelephone(),
                        'fax'                 => $address->getFax(),
                        'email'               => $addresss->getEmail(),
                        'is_default_billing'  => ($default_billing) ?
                        (($address->getId() == $default_billing->getId() &&
                            $default_billing) ? 1 : 0) : 0,
                        'is_default_shipping' => ($default_shipping) ?
                        (($address->getId() == $default_shipping->getId()
                            && $default_shipping) ? 1 : 0) : 0,
                    ];

                    if ($address->getRegionId()) {
                        $address_array['region_id'] = $address->getRegionId();
                        $address_array['region']    = $connection
                            ->fetchOne('SELECT default_name FROM `' . $tbl_directory_region .
                                '` WHERE region_id =' . $address->getRegionId());
                    } else {
                        $address_array['region'] = $address->getRegion();
                    }
                    if ($default_billing) {
                        if ($address->getId() == $default_billing->getId()) {
                            $currentAddress[] = $address_array;
                            continue;
                        }
                    }
                    if ($default_shipping) {
                        if ($address->getId() == $default_shipping->getId()) {
                            $currentAddress[] = $address_array;
                            continue;
                        }
                    }

                    $alladdress[] = $address_array;
                }
                $result->setData(['alladdress' => $alladdress, 'currentAddress' => $currentAddress]);
                return $result;
            } else {
                $result->setData(['status' => 'error', 'message' => __('No address found.')]);
                $session->logout();
                return $result;
            }
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
            return $result;
        }
    }

    private function _loadCountryId($countryName, $address)
    {
        return $countryName->load($address->getCountryId())->getName();
    }
}
