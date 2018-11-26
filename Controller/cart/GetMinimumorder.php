<?php
namespace Mmsbuilder\Connector\Controller\cart;

class GetMinimumorder extends \Magento\Framework\App\Action\Action
{

    /**
     * @var Session
     */
    /**
     * @var \Magento\Checkout\Model\Cart
     */
    private $checkoutCart;
    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $product;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Helper\Cart $checkoutHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        $this->checkoutCart      = $checkoutCart;
        $this->checkoutHelper    = $checkoutHelper;
        $this->productModel      = $productModel;
        $this->jsonHelper        = $jsonHelper;
        $this->scopeConfig       = $scopeConfig;
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }
    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $result         = $this->resultJsonFactory->create();
        $params         = $this->getRequest()->getContent();
        $finalJosn      = $this->jsonHelper->jsonDecode($params, true);
        $cart_data      = $finalJosn['cart_data'];

        if (empty($cart_data)) {
            $result->setData(['status' => 'error', 'message' => __('Cart is empty.')]);
            return $result;
        }
        $carts = $this->checkoutHelper->getCart();
        $carts->truncate();
        $cart = $this->checkoutCart;
        $cart->setQuote($carts->getQuote());
        $this->_saveCartItems($cart_data);

        try {
            $cart->save();
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => $e->getMessage()]);
            return $result;
        }

        if ($this->scopeConfig->getValue('sales/minimum_order/active')) {
            $check_grand_total = $this->checkoutHelper->getQuote()->getBaseSubtotalWithDiscount();

            $amount = $this->scopeConfig->getValue('sales/minimum_order/amount');
            if ($check_grand_total < $amount) {
                $message = $this->scopeConfig->getValue('sales/minimum_order/error_message');
                if (!$message) {
                    $message = 'Minimum Order limit is. ' . $amount;
                }

                $result->setData(['status' => 'error', 'message' => $this->$message]);
                return $result;
            }
        }
        $result->setData(['status' => 'success', 'message' => 'true']);
        return $result;
    }

    private function loadProduct($pId)
    {
        $objectData = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectData->create('Magento\Catalog\Model\Product')->load($pId);
    }

    private function _saveCartItems($cart_data)
    {
        $carts = $this->checkoutHelper->getCart();
        $carts->truncate();

        $cart = $this->checkoutCart;
        $cart->setQuote($carts->getQuote());
        foreach ($cart_data['items'] as $params) {
            try {
                $final_params  = [];
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $product       = $this->loadProduct($params['product']);

                if ($product) {
                    if (isset($params['qty'])) {
                        $final_params['qty'] = $params['qty'];
                    }
                    $final_params['product'] = $params['product'];
                    if (isset($params['super_attribute'])) {
                        $subject = ($params['super_attribute']);
                        foreach ($params['super_attribute'] as $attribute) {
                            $final_params['super_attribute'][$attribute['attribute_id']] = $attribute['option_id'];
                        }
                    }
                    if (isset($params['options'])) {
                        foreach ($params['options'] as $attributeOption) {
                            $final_params['options'][$attributeOption['attribute_id']] = $attributeOption['option_id'];
                        }
                    }

                    if (isset($params['bundle_option'])) {
                        $final_params['bundle_option'] = $this->jsonHelper->jsonDecode($params['bundle_option']);
                    }
                    $final_params['product'] = $params['product'];
                    $objectData  = \Magento\Framework\App\ObjectManager::getInstance();
                    $params_data = $objectData->create('\Magento\Framework\DataObject');
                    $request     = $params_data->setData($final_params);
                    $cart->addProduct($product, $request);
                }
            } catch (\Exception $e) {
                $result->setData(['status' => 'error', 'message' => $e->getMessage()]);
                return $result;
            }
        }
    }
}
