<?php
namespace Mmsbuilder\Connector\Controller\customer;

class GetMyOrders extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Directory\Model\Currency $currentCurrency
    ) {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->imageHelper             = $imageHelper;
        $this->customHelper            = $customHelper;
        $this->resultJsonFactory       = $resultJsonFactory;
        $this->_currency               = $currency;
        $this->currentCurrency         = $currentCurrency;
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

        if ($this->customerSession->isLoggedIn()) {
            $cust_id      = $this->customerSession->getId();
            $res          = [];
            $totorders    = $this->__getOrders($cust_id);
            $res["total"] = count($totorders);
            # start order  loop
            foreach ($totorders as $order) {
                $shippingAddress = $order->getShippingAddress();
                if (is_object($shippingAddress)) {
                    $shippadd = [];
                    $flag     = 0;
                    if (!empty($totorders)) {
                        $flag = 1;
                    }
                    $shippadd = [
                        "firstname" => $shippingAddress->getFirstname(),
                        "lastname"  => $shippingAddress->getLastname(),
                        "company"   => $shippingAddress->getCompany(),
                        "street"    => $order->getShippingAddress()->getStreet(1),
                        "region"    => $shippingAddress->getRegion(),
                        "city"      => $shippingAddress->getCity(),
                        "pincode"   => $shippingAddress->getPostcode(),
                        "countryid" => $shippingAddress->getCountry_id(),
                        "contactno" => $shippingAddress->getTelephone(),
                        "shipmyid"  => $flag,
                    ];
                }
                $billingAddress = $order->getBillingAddress();
                if (is_object($billingAddress)) {
                    $billadd = [];
                    $billadd = [
                        "firstname" => $billingAddress->getFirstname(),
                        "lastname"  => $billingAddress->getLastname(),
                        "company"   => $billingAddress->getCompany(),
                        "street"    => $order->getBillingAddress()->getStreet(1),
                        "region"    => $billingAddress->getRegion(),
                        "city"      => $billingAddress->getCity(),
                        "pincode"   => $billingAddress->getPostcode(),
                        "countryid" => $billingAddress->getCountry_id(),
                        "contactno" => $billingAddress->getTelephone(),
                    ];
                }
                $payment = [];
                $payment = $order->getPayment();
                try {
                    $payment_result = [
                        "payment_method_title" => $payment->getMethodInstance()->getTitle(),
                        "payment_method_code"  => $payment->getMethodInstance()->getCode(),
                    ];
                    if ($payment->getMethodInstance()->getCode() == "banktransfer") {
                        $payment_result["payment_method_description"] =
                        $payment->getMethodInstance()->getInstructions();
                    }
                } catch (\Exception $e) {
                    $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
                    return $result;
                }

                $items                       = $order->getAllVisibleItems();
                $itemcount                   = $this->getCount($items);
                $name                        = [];
                $unitPrice                   = [];
                $sku                         = [];
                $ids                         = [];
                $qty                         = [];
                $images                      = [];
                $test_p                      = [];
                $itemsExcludingConfigurables = [];
                $productlist                 = [];
                foreach ($items as $itemId => $item) {
                    $name       = $item->getName();
                    $unitPrice  = number_format($item->getPrice(), 2, '.', '');
                    $sku        = $item->getSku();
                    $ids        = $item->getProductId();
                    $qty        = (int) $item->getQtyOrdered();
                    $objectData = \Magento\Framework\App\ObjectManager::getInstance();
                    $products   = $this->getProduct($item->getProductId());
                    $images     = $this->imageHelper
                        ->init($products, 'product_page_image_large')
                        ->setImageFile($products->getFile())
                        ->resize('250', '250')
                        ->getUrl();

                    $productlist[] = [
                        "name"             => $name,
                        "sku"              => $sku,
                        "id"               => $ids,
                        "quantity"         => (int) $qty,
                        "unitprice"        => $unitPrice,
                        "image"            => $images,
                        "total_item_count" => $itemcount,
                        "price_org"        => $test_p,
                        "price_based_curr" => 1,
                    ];
                } # item foreach close
                $order_date = $order->getCreatedAt() . '';
                $orderData = [
                    "id"                    => $order->getId(),
                    "order_id"              => $order->getRealOrderId(),
                    "status"                => str_replace('-', ' ', $order->getStatus()),
                    "order_date"            => $order_date,
                    "grand_total"           => number_format($order->getGrandTotal(), 2, '.', ''),
                    "shipping_address"      => isset($shippadd)?$shippadd:"",
                    "billing_address"       => $billadd ? $billadd : "",
                    "shipping_message"      => $order->getShippingDescription(),
                    "shipping_amount"       => number_format($order->getShippingAmount(), 2, '.', ''),
                    "payment_method"        => $payment_result,
                    "tax_amount"            => number_format($order->getTaxAmount(), 2, '.', ''),
                    "products"              => $productlist,
                    "order_currency"        => $this->customHelper->loadCurrency($this->currency),
                    "order_currency_symbol" => $this->customHelper->loadCurrency($this->currency),
                    "currency"              => $this->customHelper->loadCurrency($this->currency),
                    "couponUsed"            => 0,
                ];
                $couponCode = $order->getCouponCode();
                if ($couponCode != "") {
                    $orderData["couponUsed"]      = 1;
                    $orderData["couponCode"]      = $couponCode;
                    $orderData["discount_amount"] =
                        floatval(number_format($order->getDiscountAmount(), 2, '.', '')) * -1;
                }
                $orderData['reward_amount'] = $order->getRewardAmount() ? $order->getRewardAmount() : "";
                $res["data"][]              = $orderData;
            } # end foreach
            $result->setData($res);
            return $result;
        } else {
            $result->setData(['status' => 'error', 'message' => __('Please Login to see the orders.')]);
            return $result;
        }
    }

    private function __getOrders($customerId)
    {

        $this->orders = $this->_orderCollectionFactory->create()->addFieldToSelect(
            '*'
        )->addFieldToFilter(
            'customer_id',
            $customerId
        )->setOrder(
            'created_at',
            'desc'
        );
        return $this->orders;
    }

    private function getProduct($pId)
    {
        $objectData = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectData->create('Magento\Catalog\Model\Product')->load($pId);
    }

    private function getCount($items)
    {
        return count($items);
    }
}
