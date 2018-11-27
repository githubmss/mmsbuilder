<?php
namespace Mmsbuilder\Connector\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    private $storeManager;
    private $codes             = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    const XML_SECURE_TOKEN_EXP = 'secure/token/exp';
    public function __construct(
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Tax\Helper\Data $helperData,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Currency $currentCurrency,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\Data\OrderInterface $_order,
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
        \Magento\Checkout\Helper\Cart $cartHelper,
        \Magento\Customer\Model\Address $customerAddress,
        \Magento\Wishlist\Model\WishlistFactory $wishlistRepository,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Tax\Api\TaxCalculationInterface $taxCalculation
    ) {
        $this->checkoutCart              = $checkoutCart;
        $this->helperData                = $helperData;
        $this->productModel              = $productModel;
        $this->imageHelper               = $imageHelper;
        $this->storeManager              = $storeManager;
        $this->checkoutHelper            = $checkoutHelper;
        $this->couponFactory             = $couponFactory;
        $this->currentCurrency           = $currentCurrency;
        $this->date                      = $date;
        $this->_order                    = $_order;
        $this->_productRepositoryFactory = $productRepositoryFactory;
        $this->scopeConfig               = $scopeConfig;
        $this->cartHelper                = $cartHelper;
        $this->customerAddress           = $customerAddress;
        $this->wishlistRepository        = $wishlistRepository;
        $this->productRepository         = $productRepository;
        $this->taxCalculation            = $taxCalculation;
        $this->resultJsonFactory         = $resultJsonFactory;
        $this->_currency                 = $currency;
    }

    public function _getCartInformation($addressId, $countryId, $setRegionId, $shipping_method, $zipcode, $currencyCode)
    {
        $shipping_amount = '0.00';
        if ($shipping_method) {
            $shipping_amount = $this
                ->_getShippingTotal($addressId, $countryId, $setRegionId, $shipping_method, $zipcode);
        }
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->checkoutSession = $objectData->create('\Magento\Checkout\Model\Session');
        $quote                 = $this->checkoutSession->getQuote();
        $discountTotal         = 0;
        foreach ($quote->getAllItems() as $item) {
            $discountTotal += $item->getDiscountAmount();
        }
        $cart = $this->checkoutCart;
        if ($cart->getQuote()->getItemsCount()) {
            $cart->save();
        }
        $cart->getQuote()->collectTotals()->save();
        $cartInfo                         = [];
        $cartInfo['is_virtual']           = (string) $cart->getIsVirtualQuote();
        $cartInfo['cart_items']           = $this->_getCartItems($currencyCode);
        $cartInfo['cart_items_count']     = $cart->getItemsCount();
        $cartInfo['grand_total']          = number_format($cart->getQuote()->getGrandTotal(), 2, '.', '');
        $cartInfo['sub_total']            = number_format($cart->getQuote()->getSubtotal(), 2, '.', '');
        $cartInfo['discount']             = $discountTotal;
        $cartInfo['coupon_code']          = (string) $cart->getQuote()->getCouponCode();
        $cartInfo['allow_guest_checkout'] = $this->checkoutHelper
            ->isAllowedGuestCheckout($this->checkoutSession->getQuote());
        $cartInfo['shipping_amount']       = (float) $shipping_amount;
        $cartInfo['reward_amount_applied'] = $cart->getQuote()
            ->getRewardAmount() ? $cart->getQuote()->getRewardAmount() : "";
        $cartInfo['currency'] = $this->currentCurrency->load($currencyCode)->getCurrencySymbol() ?
        $this->currentCurrency->load($currencyCode)->getCurrencySymbol() : $currencyCode;
        return $cartInfo;
    }

    public function getCurrencysymbolByCode($currencyCode)
    {
        return $this->currentCurrency->load($currencyCode)->getCurrencySymbol()
        ? $this->currentCurrency->load($currencyCode)->getCurrencySymbol() : $currencyCode;
    }

    public function getPriceInclAndExclTax($productId)
    {
        $product = $this->productRepository->getById($productId);

        if ($taxAttribute = $product->getCustomAttribute('tax_class_id')) {
            // First get base price (=price excluding tax)
            $productRateId = $taxAttribute->getValue();
            $rate          = $this->taxCalculation->getCalculatedRate($productRateId);

            if ((int) $this->scopeConfig->getValue(
                'tax/calculation/price_includes_tax',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) === 1
            ) {
                // Product price in catalog is including tax.
                $priceExcludingTax = $product->getPrice() / (1 + ($rate / 100));
            } else {
                // Product price in catalog is excluding tax.
                $priceExcludingTax = $product->getPrice();
            }

            $priceIncludingTax = $priceExcludingTax + ($priceExcludingTax * ($rate / 100));
            return [
                'incl' => $priceIncludingTax,
                'excl' => $priceExcludingTax,
            ];
        }

        throw new \LocalizedException(__('Tax Attribute not found'));
    }

    private function _getCartItems($currencyCode)
    {
        $objectData              = \Magento\Framework\App\ObjectManager::getInstance();
        $this->checkoutSession   = $objectData->create('\Magento\Checkout\Model\Session');
        $cartItemsArr            = [];
        $cart                    = $this->checkoutSession;
        $quote                   = $cart->getQuote();
        $displayCartPriceInclTax = $this->helperData->displayCartPriceInclTax();
        $displayCartPriceExclTax = $this->helperData->displayCartPriceExclTax();
        $displayCartBothPrices   = $this->helperData->displayCartBothPrices();
        $items                   = $quote->getAllVisibleItems();
        $baseCurrency            = $this->storeManager->getStore()->getBaseCurrencyCode();
        $currentCurrencys        = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        $code                    = $this->currentCurrency->load($currencyCode)->getCurrencySymbol() ?
        $this->currentCurrency->load($currencyCode)->getCurrencySymbol() : $currencyCode;
        $product_model = $this->productModel;
        foreach ($items as $item) {
            $this->getPriceInclAndExclTax($item->getProduct()->getId());
            $product            = $this->loadProductModel($item->getProduct()->getId());
            $objectManager      = \Magento\Framework\App\ObjectManager::getInstance();
            $hotPrd             = $objectManager->get('Magento\Catalog\Model\Product')->load($product->getId());
            $store              = $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore();
            $has_real_image_set = ($hotPrd->getThumbnail() != null && $hotPrd->getThumbnail() != "no_selection");
            $image_url          = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $hotPrd->getThumbnail();

            $product                          = $this->loadProductModel($item->getProduct()->getId());
            $cartItemArr                      = [];
            $cartItemArr['cart_item_id']      = $item->getId();
            $cartItemArr['currency']          = $code;
            $cartItemArr['entity_type']       = $item->getProductType();
            $cartItemArr['item_id']           = $item->getProduct()->getId();
            $cartItemArr['item_title']        = strip_tags($item->getProduct()->getName());
            $cartItemArr['qty']               = $item->getQty();
            $cartItemArr['thumbnail_pic_url'] = $image_url;
            $cartItemArr['custom_option']     = $this->_getCustomOptions($item);
            $cartItemArr['supper_attribute']  = $this->_getAdditionalInfo($item);
            $cartItemArr['item_price']        = number_format($item->getPrice(), 2, '.', '');
            $cartItemArr['item_subtotal']     = number_format($item->getPrice() * $item->getQty(), 2, '.', '');
            array_push($cartItemsArr, $cartItemArr);
        }

        return $cartItemsArr;
    }

    private function _getCustomOptions($item)
    {
        $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        $result  = [];
        if ($options) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
        }

        return $result;
    }

    private function _getAdditionalInfo($item)
    {
        $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

        $result = [];
        if ($options) {
            if (isset($options['options'])) {
                unset($options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (!empty($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }

        return $result;
    }

    private function _getMessage()
    {
        $cart = $this->checkoutCart;
        if (!$this->checkoutCart->getQuote()->getAllItems()) {
            $this->errors[] = $this->__('Cart is empty!');
            return $this->errors;
        }
        if (!$cart->getQuote()->validateMinimumAmount()) {
            $warning        = "minimum order";
            $this->errors[] = $warning;
        }

        if (($messages = $cart->getQuote()->getErrors())) {
            foreach ($messages as $message) {
                if ($message) {
                    $message        = str_replace("\"", "||", $message);
                    $this->errors[] = $this->__($message->getText());
                }
            }
        }

        return $this->errors;
    }

    public function _getShippingTotal($addressId, $countryId, $setRegionId, $shipping_method, $zipcode)
    {
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->checkoutSession = $objectData->create('\Magento\Checkout\Model\Session');
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        $session               = $this->checkoutSession;
        $address               = $session->getQuote()->getShippingAddress();
        $address->setCountryId($countryId)
            ->setPostcode($zipcode)
            ->setSameAsBilling(1);

        $rates = $address
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->getGroupedAllShippingRates();
        $shipMethods = [];
        foreach ($rates as $carrier) {
            foreach ($carrier as $rate) {
                $shipMethods[] = [
                    'code'  => $rate->getData('code'),
                    'price' => $rate->getData('price'),
                ];
            }
        }
        foreach ($shipMethods as $method) {
            if ($method == $shipping_method) {
                return $shipping_amount = $method['price'];
            }
        }
        $quote = $this->checkoutCart->getQuote();
        if (isset($addressId)) {
            $customer      = $this->customerAddress->load($addressId);
            $countryId     = $customer['country_id'];
            $setRegionId   = $customer['region_id'];
            $regionName    = $customer['region'];
            $shippingCheck = $quote->getShippingAddress()->getData();

            if ($shippingCheck['shipping_method'] != $shipping_method) {
                if (isset($setRegionId)) {
                    $quote->getShippingAddress()
                        ->setCountryId($countryId)
                        ->setRegionId($setRegionId)
                        ->setPostcode($zipcode)
                        ->setCollectShippingRates(true);
                } else {
                    $quote->getShippingAddress()
                        ->setCountryId($countryId)
                        ->setRegion($regionName)
                        ->setPostcode($zipcode)
                        ->setCollectShippingRates(true);
                }
                $quote->save();
                $quote->getShippingAddress()->setShippingMethod($shipping_method)->save();
            }

            $quote->collectTotals()->save();
            $amount          = $quote->getShippingAddress()->getData();
            $shipping_amount = $amount['shipping_incl_tax'];
            return $shipping_amount;
        } else {
            $shippingCheck = $quote->getShippingAddress()->getData();

            if (isset($shippingCheck['shipping_method']) && $shippingCheck['shipping_method'] != $shipping_method) {
                if (isset($setRegionId)) {
                    $quote->getShippingAddress()
                        ->setCountryId($countryId)
                        ->setRegionId($setRegionId)
                        ->setPostcode($zipcode)
                        ->setCollectShippingRates(true);
                } else {
                    $quote->getShippingAddress()
                        ->setCountryId($countryId)
                        ->setPostcode($zipcode)
                        ->setCollectShippingRates(true);
                }
                $quote->save();
                $quote->getShippingAddress()->setShippingMethod($shipping_method)->save();
            }
            $quote->collectTotals()->save();
            $amount          = $quote->getShippingAddress();
            $shipping_amount = $amount['shipping_incl_tax'];
            return $shipping_amount;
        }
    }

    public function _getCartTotal()
    {
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->checkoutSession = $objectData->create('\Magento\Checkout\Model\Session');
        $cart                  = $this->checkoutCart;
        $totalItemsInCart      = $cart->getQuote()->getItemsCount(); // total items in cart
        $totals                = $this->checkoutSession->getQuote()->getTotals(); // Total object
        $oldCouponCode         = $cart->getQuote()->getCouponCode();

        $oCoupon = $this->couponFactory->create();
        $oCoupon->load($oldCouponCode, 'code');
        $oRule = $oCoupon->getRuleId();

        $subtotal      = round($totals["subtotal"]->getValue()); // Subtotal value
        $grandtotal    = round($totals["grand_total"]->getValue()); // Grandtotal value
        $quote         = $this->checkoutSession->getQuote();
        $discountTotal = 0;
        foreach ($quote->getAllItems() as $item) {
            $discountTotal += $item->getDiscountAmount();
        }
        if (isset($totals['tax'])) {
            $tax = round($totals['tax']->getValue()); // Tax value if present
        } else {
            $tax = '';
        }
        return [
            'subtotal'    => $subtotal,
            'grandtotal'  => $grandtotal,
            'discount'    => str_replace('-', '', $discount),
            'tax'         => $tax,
            'coupon_code' => $oldCouponCode,
            'coupon_rule' => $oRule,
        ];
    }

    public function compareExp()
    {
        $saved_session   = strtotime($this->scopeConfig->getValue('secure/token/exp'));
        $current_session = strtotime($this->date->gmtDate());
        return round(($current_session - $saved_session) / 3600);
    }
    public function loadParent($helper)
    {
        $result = $this->resultJsonFactory->create();

        if ($this->compareExp() > 4800) {
            $result->setData(['status' => 'error', 'code' => '001']);
            return true;
        }

        if ($this->scopeConfig->getValue('magentomobileshop/secure/token') != $helper) {
            $result->setData(['status' => 'error', 'code' => '002']);
            return true;
        }
        if (!$this->scopeConfig->getValue('magentomobileshop/key/status')) {
            $result->setData(['status' => 'error', 'code' => '003']);
            return true;
        }
        if ($this->compareExp() > 4800 ||
            $this->scopeConfig->getValue('magentomobileshop/secure/token') != isset($helper)
            || !$this->scopeConfig->getValue('magentomobileshop/key/status') || !$helper) {
            $result->setData(['status' => 'error', 'code' => '004']);
            return true;
        }
    }
    public function storeConfig($storeid)
    {
        if ($this->storeManager->getStore()->getStoreId() == $storeid) {
            return $this->storeManager->getStore()->getStoreId();
        } else {
            if ($storeid) {
                $this->storeManager->setCurrentStore($storeid);
            }
            return $storeid;
        }
    }
    public function viewConfig($viewid)
    {
        return $viewid;
    }
    public function currencyConfig($currency)
    {
        return $currency;
    }

    public function getOrderDetails($_orderId, $currencyCode)
    {
        if ($_orderId) {
            $_order     = $this->_order->loadByIncrementId($_orderId);
            $data_order = $_order->getData();
            if ($data_order) {
                $_items      = $_order->getAllItems();
                $_orderItems = [];
                foreach ($_items as $_item) {
                    $product      = $this->_productRepositoryFactory->create()->getById($_item->getProductId());
                    $productImage = $this->imageHelper->init($product, 'product_base_image')
                        ->constrainOnly(true)
                        ->keepAspectRatio(true)
                        ->keepTransparency(true)
                        ->keepFrame(false)
                        ->resize('75')->getUrl();
                    $_orderItems[] = [
                        'sku'             => $_item->getSku(),
                        'item_id'         => $_item->getId(),
                        'product_image'   => $productImage,
                        'price'           => $_item->getPrice(),
                        'discount_amount' => $_item->getDiscountAmount(),
                        'qty_ordered'     => $_item->getQtyOrdered(),
                    ];
                }
                $data_order['symbol'] = $this->currentCurrency
                    ->load($currencyCode)->getCurrencySymbol() ?
                $this->currentCurrency->load($currencyCode)
                    ->getCurrencySymbol() : $currencyCode;
                $result                  = [];
                $result['order_details'] = $data_order;
                $result['order_items']   = $_orderItems;
                $result['status']        = 'success';
            } else {
                $result['status']  = 'error';
                $result['message'] = 'Order Not Found.';
            }
        } else {
            $result['status']  = 'error';
            $result['message'] = 'Please Provide Order Id.';
        }

        return $result;
    }

    public function getBaseCurrencyCode()
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    public function getSpecialPriceProduct($product)
    {

        $specialprice = $this->getSpecialPriceByProductId($product);

        return $specialprice;
    }
    public function getSpecialPriceByProductId($product)
    {
        $specialprice         = $product->getData('special_price');
        $specialPriceFromDate = $product->getData('special_from_date');
        $specialPriceToDate   = $product->getData('special_to_date');
        $today                = time();
        if ($specialprice) {
            if ($today >= strtotime($specialPriceFromDate) &&
                $today <= strtotime($specialPriceToDate) ||
                $today >= strtotime($specialPriceFromDate) &&
                ($specialPriceToDate === "NULL")) {
                return $specialprice;
            } else {
                return '0.00';
            }
        } else {
            return '0.00';
        }
    }

    public function checkWishlist($productId)
    {
        $objectData            = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $objectData->create('\Magento\Customer\Model\Session');
        $customer              = $this->customerSession;
        if ($customer->isLoggedIn()) {
            $wishlist               = $this->wishlistRepository->create()->loadByCustomerId($customer->getId(), true);
            $wishListItemCollection = $wishlist->getItemCollection();
            $wishlist_product_id    = [];
            foreach ($wishListItemCollection as $item) {
                $wishlist_product_id[] = $item->getProductId();
            }
            if (in_array($productId, $wishlist_product_id)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function loadCurrency($code)
    {
        return $this->currentCurrency->load($code)->getCurrencySymbol() ?
        $this->currentCurrency->load($code)->getCurrencySymbol() : $this->currency;
    }

    private function loadProductModel($pid)
    {
        return $this->productModel->load($pid);
    }

    public function decode($input)
    {
        $codes                   = $this->codes;
        $objectData              = \Magento\Framework\App\ObjectManager::getInstance();
        $this->resultJsonFactory = $objectData->get('\Magento\Framework\Controller\Result\JsonFactory');
        $result                  = $this->resultJsonFactory->create();
        try {
            if ($input == null) {
                $message = "INPUT IS NULL";
                return $message;
            }
        } catch (\Exception $e) {
            if (isset($e->xdebug_message)) {
                $message = $e->xdebug_message;
            } else {
                $message = $e->getMessage();
            }
            return $message;
        }
        $decoded[] = ((strlen($input) * 3) / 4) - (strrpos($input, '=') > 0 ?
            (strlen($input) - strrpos($input, '=')) : 0);
        $inChars = str_split($input);
        $count   = count($inChars);
        $j       = 0;
        $b       = [];
        for ($i = 0; $i < $count; $i += 4) {
            $b[0]          = strpos($codes, $inChars[$i]);
            $b[1]          = strpos($codes, $inChars[$i + 1]);
            $b[2]          = strpos($codes, $inChars[$i + 2]);
            $b[3]          = strpos($codes, $inChars[$i + 3]);
            $decoded[$j++] = (($b[0] << 2) | ($b[1] >> 4));
            if ($b[2] < 64) {
                $decoded[$j++] = (($b[1] << 4) | ($b[2] >> 2));
                if ($b[3] < 64) {
                    $decoded[$j++] = (($b[2] << 6) | $b[3]);
                }
            }
        }
        $decodedstr   = '';
        $count_decode = count($decoded);
        for ($i = 0; $i < $count_decode; $i++) {
            $decodedstr .= pack("C*", $decoded[$i]);
        }

        return $decodedstr;
    }
}
