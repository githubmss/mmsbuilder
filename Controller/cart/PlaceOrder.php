<?php
namespace Mmsbuilder\Connector\Controller\cart;

class PlaceOrder extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\Data\Form\FormKey $formkey,
        \Magento\Quote\Model\QuoteFactory $quote,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Checkout\Helper\Cart $checkoutCartHelper,
        \Magento\Customer\Model\Address $customerAddress,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Event\Manager $eventManager,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Quote\Model\Quote $quotes,
        \Magento\Quote\Model\QuoteIdMaskFactory $QuoteIdMaskFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Sales\Model\Order $order
    ) {
        $this->storeManager       = $storeManager;
        $this->product            = $product;
        $this->formkey            = $formkey;
        $this->quote              = $quote;
        $this->quoteManagement    = $quoteManagement;
        $this->customerFactory    = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderService       = $orderService;
        $this->scopeConfig        = $scopeConfig;
        $this->customHelper       = $customHelper;
        $this->checkoutCart       = $checkoutCart;
        $this->checkoutCartHelper = $checkoutCartHelper;
        $this->customerAddress    = $customerAddress;
        $this->logger             = $logger;
        $this->resultJsonFactory  = $resultJsonFactory;
        $this->customer           = $customer;
        $this->quotes             = $quotes;
        $this->QuoteIdMaskFactory = $QuoteIdMaskFactory;
        $this->quoteRepository    = $quoteRepository;
        $this->_eventManager      = $eventManager;
        $this->request            = $context->getRequest();
        $this->jsonHelper         = $jsonHelper;
        $this->order              = $order;
        parent::__construct($context);
    }
    public function execute()
    {
        $this->storeId = $this->customHelper->storeConfig($this->getRequest()
                ->getHeader('storeid'));
        $this->viewId = $this->customHelper->viewConfig($this->getRequest()
                ->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()
                ->getHeader('currency'));
        $result                = $this->resultJsonFactory->create();
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        $this->checkoutSession = $objectData->create('\Magento\Checkout\Model\Session');
        if ($this->customerSession->isLoggedIn()) {
            $session    = $this->customerSession;
            $customerId = $session->getId();
            $totalItems = $this->checkoutCartHelper->getSummaryCount();
            if ($totalItems > 0) {
                $params          = $this->getRequest()->getContent();
                $finalJosn       = $this->jsonHelper->jsonDecode($params, true);
                $usershippingid  = $finalJosn['usershippingid'];
                $userbillingid   = $finalJosn['userbillingid'];
                $shipping_method = $finalJosn['shippingmethod'];
                $paymentmethod   = $finalJosn['paymentmethod'];
                $deviceType      = $finalJosn['deviceType'] ?: 0;
                $registration_id = isset($finalJosn['registration_id']) ? $finalJosn['registration_id'] : "";
                $this->validateCheck($finalJosn);
                $customers = $this->customerSession->getCustomer()->getId();
                try {
                    $usershippingidData = $this->customerAddress->load($usershippingid)->getData();
                    $userbillingidData  = $this->customerAddress->load($userbillingid)->getData();
                    $quote              = $this->checkoutSession->getQuote();
                    $quote->setMms_order_type('app');
                    $quote->setDeviceData(json_encode(['device_registraton' =>
                        $registration_id, 'device_type' => $deviceType]))->save();
                    $billingAddress  = $quote->getBillingAddress()->addData($userbillingidData);
                    $shippingAddress = $quote->getShippingAddress()->addData($usershippingidData);
                    $shippingAddress->setCollectShippingRates(true)->collectShippingRates()
                        ->setShippingMethod($shipping_method);
                    if ($paymentmethod != 'authorizenet') {
                        $shippingAddress->setPaymentMethod($paymentmethod);
                        $quote->getPayment()->importData(['method' => $paymentmethod]);
                    }
                    $quote->collectTotals()->save();
                    $order = $this->quoteManagement->submit($quote);
                    $order->setEmailSent(0);
                    if ($order->getCanSendNewEmailFlag()) {
                        $objectData  = \Magento\Framework\App\ObjectManager::getInstance();
                        $emailSender = $objectData->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
                        $emailSender->send($order);
                        $order->setEmailSent(1);
                    }
                    $itemcount  = $order->getTotalItemCount();
                    $grandTotal = $order->getData('grand_total');
                    $order->setMms_order_type('app');
                    $increment_id = $order->getRealOrderId();
                    $om           = \Magento\Framework\App\ObjectManager::getInstance();
                    $order->setDeviceData(json_encode(['device_registraton' =>
                        $registration_id, 'device_type' => $deviceType]))->save();
                    $cart = $this->checkoutCart;
                    if ($cart->getQuote()->getItemsCount()) {
                        $current_cart = $this->checkoutCart;
                        $current_cart->truncate();
                        $current_cart->save();
                    }
                    $allItems = $this->checkoutSession->getQuote()->getAllVisibleItems();
                    foreach ($allItems as $item) {
                        $itemId = $item->getItemId();
                        $this->itemSave($itemId);
                    }
                    $objectData = \Magento\Framework\App\ObjectManager::getInstance();
                    $order_data = $objectData->create('Magento\Sales\Model\Order')->loadByIncrementId($increment_id);
                    if ($paymentmethod == "paypal_express") {
                        $payment     = $order_data->getPayment();
                        $order_array = [
                            'paypal_correlation_id'         => $finalJosn['tx'],
                            'order_incremental_id'          => $order->getIncrementId(),
                            'paypal_payer_status'           => $finalJosn['st'],
                            'paypal_express_checkout_token' => "EC-981900249B799783",
                            'paypal_payer_email'            => $order->getCustomerEmail(),
                        ];
                        $payment->setAdditionalInformation($order_array);
                        $order_data->save();
                    }
                    $finalResult = ['message' => ('Order placed successfully.'),
                        'orderid'                 => $order->getRealOrderId(),
                        'items_count'             => $itemcount,
                        'grand_total'             => $grandTotal,
                        'status'                  => 'success'];
                    if ($paymentmethod == 'payu') {
                        $finalResult['checkout_request'] = $this->buildCheckoutRequest($order);
                        $finalResult['url']              = $this->getCgiUrl();
                    }
                    return $result->setData($finalResult);
                } catch (\Exception $e) {
                    $result->setData(['status' => 'error', 'message' => $e->getMessage()]);
                    return $result;
                }
            } else {
                $result->setData(['message' => 'cart is empty', 'status' => 'success']);
                return $result;
            }
        } else {
            return $this->_guestOrder();
        }
    }

    private function _guestOrder()
    {
        $params          = $this->getRequest()->getContent();
        $finalJosn       = $this->jsonHelper->jsonDecode($params, true);
        $billingJosn     = $finalJosn['data'][0];
        $shippingJosn    = $finalJosn['data'][1];
        $paymentmethod   = $finalJosn['paymentmethod'];
        $shipping_method = $finalJosn['shippingmethod'];
        if (!isset($registration_id)) {
            $registration_id = "";
        } else {
            $registration_id = $finalJosn['registration_id'] ?: null;
        }
        $deviceType = $finalJosn['deviceType'] ?: 0;
        try {
            $checkout_session = $this->checkoutSession->getQuoteId();
            $quote            = $this->quotes->load($checkout_session);
            $quote->setStoreId($this->storeManager->getStore()->getId());
            $billingAddress = [
                'firstname'            => $billingJosn['firstname'],
                'lastname'             => $billingJosn['lastname'],
                'email'                => $billingJosn['email'],
                'street'               => [
                    $billingJosn['street'],
                ],
                'city'                 => $billingJosn['city'],
                'postcode'             => $billingJosn['postcode'],
                'country_id'           => $billingJosn['country_id'],
                'telephone'            => $billingJosn['telephone'],
                'customer_password'    => '',
                'confirm_password'     => '',
                'save_in_address_book' => '0',
                'is_default_shipping'  => $billingJosn['is_default_shipping'],
                'is_default_billing'   => $billingJosn['is_default_billing'],
            ];

            $shippingAddress = [
                'firstname'            => $shippingJosn['firstname'],
                'lastname'             => $shippingJosn['lastname'],
                'email'                => $shippingJosn['email'],
                'street'               => [
                    $shippingJosn['street'],
                ],
                'city'                 => $shippingJosn['city'],
                'postcode'             => $shippingJosn['postcode'],
                'country_id'           => $shippingJosn['country_id'],
                'telephone'            => $shippingJosn['telephone'],
                'customer_password'    => '',
                'confirm_password'     => '',
                'save_in_address_book' => '0',
                'is_default_shipping'  => $shippingJosn['is_default_shipping'],
                'is_default_billing'   => $shippingJosn['is_default_billing'],
            ];

            if (isset($shippingJosn['region'])) {
                $shippingAddress['region'] = $shippingJosn['region'];
            } else {
                $shippingAddress['region_id'] = $shippingJosn['region_id'];
            }
            if (isset($billingJosn['region'])) {
                $billingAddress['region'] = $billingJosn['region'];
            } else {
                $billingAddress['region_id'] = $billingJosn['region_id'];
            }

            $result          = $this->resultJsonFactory->create();
            $customerFactory = $this->customerFactory;
            $customer        = $customerFactory->create();
            $customer->setWebsiteId($this->storeManager->getStore()->getWebsiteId());
            $customer = $customer->loadByEmail($billingJosn['email']);
            if ($customer->getEntityId()) {
                $customer_id = $customer->getEntityId();
            } else {
                $customer->setWebsiteId($this->storeManager->getStore()->getWebsiteId())
                    ->setFirstname($billingJosn['firstname'])
                    ->setLastname($billingJosn['firstname'])
                    ->setEmail($billingJosn['email'])
                    ->setPassword($billingJosn['email']);
                $customer->save();
                $customer_id = $customer->getEntityId();
            }

            $customer = $this->customerRepository->getById($customer->getEntityId());
            $quote->setCurrency();
            $quote->assignCustomer($customer);

            $quote->getBillingAddress()->addData($billingAddress);

            $quote->getShippingAddress()->addData($shippingAddress)->setShippingMethod($shipping_method);

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->collectTotals();
            if ($paymentmethod != 'authorizenet') {
                $quote->setPaymentMethod($paymentmethod);
            }
            $quote->setMms_order_type('app');
            $quote->setDeviceData(json_encode(['device_registraton' =>
                $registration_id, 'device_type' => $deviceType]));
            $quote->save();

            $quote->getPayment()->importData(['method' => $paymentmethod]);
            $quote->collectTotals()->save();
            $order = $this->quoteManagement->submit($quote);
            $order->setEmailSent(0);
            if ($order->getCanSendNewEmailFlag()) {
                $objectData  = \Magento\Framework\App\ObjectManager::getInstance();
                $emailSender = $objectData->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
                $emailSender->send($order);
                $order->setEmailSent(1);
            }
            $order->setMms_order_type('app');
            $order->setDeviceData(json_encode(['device_registraton' =>
                $registration_id, 'device_type' => $deviceType]))->save();
            $itemcount    = $order->getTotalItemCount();
            $grandTotal   = $order->getData('grand_total');
            $increment_id = $order->getRealOrderId();
            $objectData   = \Magento\Framework\App\ObjectManager::getInstance();
            $order_data   = $objectData->create('Magento\Sales\Model\Order')->loadByIncrementId($increment_id);
            $finalResult  = ['status' => 'success',
                'orderid'                 => $increment_id,
                'items_count'             => $itemcount,
                'grand_total'             => $grandTotal,
            ];

            /* this will only work if the payu is enabled for app*/
            if ($paymentmethod == 'payu') {
                $finalResult['checkout_request'] = $this->buildCheckoutRequest($order);
                $finalResult['url']              = $this->getCgiUrl();
            }
            /* this will only work if the payu is enabled for app*/
            $result->setData($finalResult);
            return $result;
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
            return $result;
        }
    }

    /**
     * Payment processing for PayUIndia_Payu
     */
    private function buildCheckoutRequest($order)
    {
        $billing_address = $order->getBillingAddress();
        $params          = [];
        $params["key"]   = $this->scopeConfig->getValue("payment/payu/merchant_key");
        if ($this->scopeConfig->getValue('payment/payu/account_type') == 'payumoney') {
            $params["payment/payu/service_provider"] = $this->scopeConfig->getValue("payment/payu/service_provider");
        }
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $urlObj                = $objectData->create('\PayUIndia\Payu\Model\Payu');
        $params["txnid"]       = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $params["amount"]      = round($order->getBaseGrandTotal(), 2);
        $params["productinfo"] = $order->getRealOrderId();
        $params["firstname"]   = $billing_address->getFirstName();
        $params["lastname"]    = $billing_address->getLastname();
        $params["city"]        = $billing_address->getCity();
        $params["state"]       = $billing_address->getRegion();
        $params["zip"]         = $billing_address->getPostcode();
        $params["country"]     = $billing_address->getCountryId();
        $params["email"]       = $order->getCustomerEmail();
        $params["phone"]       = $billing_address->getTelephone();
        $params["curl"]        = $urlObj->getCancelUrl();
        $params["furl"]        = $urlObj->getReturnUrl();
        $params["surl"]        = $urlObj->getReturnUrl();
        $params["hash"]        = $this->generatePayuHash(
            $params['txnid'],
            $params['amount'],
            $params['productinfo'],
            $params['firstname'],
            $params['email']
        );
        return $params;
    }

    /**
     * Return url according to environment
     * @return string
     */
    private function getCgiUrl()
    {
        $env = $this->scopeConfig->getValue('payment/payu/environment');
        if ($env === 'production') {
            return $this->scopeConfig->getValue('payment/payu/production_url');
        }
        return $this->scopeConfig->getValue('payment/payu/sandbox_url');
    }

    private function generatePayuHash($txnid, $amount, $productInfo, $name, $email)
    {
        $SALT   = $this->scopeConfig->getValue('payment/payu/salt');
        $posted = [
            'key'         => $this->scopeConfig->getValue("payment/payu/merchant_key"),
            'txnid'       => $txnid,
            'amount'      => $amount,
            'productinfo' => $productInfo,
            'firstname'   => $name,
            'email'       => $email,
        ];

        $hashSequence = 'key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|
        udf4|udf5|udf6|udf7|udf8|udf9|udf10';

        $hashVarsSeq = explode('|', $hashSequence);
        $hash_string = '';
        foreach ($hashVarsSeq as $hash_var) {
            $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
            $hash_string .= '|';
        }
        $hash_string .= $SALT;
        return strtolower(hash('sha512', $hash_string));
    }

    private function itemSave($itemId)
    {
        return $this->cart->removeItem($itemId)->save();
    }

    private function validateCheck($finalJosn)
    {
        $usershippingid  = $finalJosn['usershippingid'];
        $userbillingid   = $finalJosn['userbillingid'];
        $shipping_method = $finalJosn['shippingmethod'];
        $paymentmethod   = $finalJosn['paymentmethod'];

        if (!\Zend_Validate::is($usershippingid, 'NotEmpty')) {
            $result->setData(['Status' => 'error', 'message' => (__('Please select address.'))]);
            return $result;
        }
        if (!\Zend_Validate::is($userbillingid, 'NotEmpty')) {
            $result->setData(['Status' => 'error', 'message' => (__('Please select address.'))]);
            return $result;
        }
        if (!\Zend_Validate::is($shipping_method, 'NotEmpty')) {
            $result->setData(['Status' => 'error', 'message' => (__('Please select shipping method.'))]);
            return $result;
        }
        if (!\Zend_Validate::is($paymentmethod, 'NotEmpty')) {
            $result->setData(['status' => 'error', 'message' => (__('Please select payment method.'))]);
            return $result;
        }
        if ($usershippingid == '' && $userbillingid == '') {
            $result->setData(['status' => 'error', 'message' => (__('Please select address.'))]);
            return $result;
        }
    }
}
