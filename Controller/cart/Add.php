<?php
namespace Mmsbuilder\Connector\Controller\Cart;

class Add extends \Magento\Framework\App\Action\Action
{

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
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Framework\Locale\ResolverInterface $resolverInterface,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutCart      = $checkoutCart;
        $this->productModel      = $productModel;
        $this->jsonHelper        = $jsonHelper;
        $this->resolverInterface = $resolverInterface;
        $this->messageManager    = $context->getMessageManager();
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        parent::__construct($context);
    }
    
    public function execute()
    {
        $final_params = [];
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $result = $this->resultJsonFactory->create();
        try {
            $params     = $this->getRequest()->getContent();
            $params     = $this->jsonHelper->jsonDecode($params, true);
            $product_id = $params['product'];
            $product    = $this->productModel->load($product_id);
            if (isset($params['qty'])) {
                $objectData  = \Magento\Framework\App\ObjectManager::getInstance();
                $filter = $objectData->create('\Zend_Filter_LocalizedToNormalized');
                $params['qty']       = $filter->filter($params['qty']);
                $final_params['qty'] = $params['qty'];
            } elseif ($product_id == '') {
                $this->messageManager->addError(__('Product not added. The SKU added %1 does not exists.', $sku));
            }

            if ($product) {
                $final_params['product'] = $params['product'];
                if (isset($params['super_attribute'])) {
                    foreach ($params['super_attribute'] as $attribute) {
                        $final_params['super_attribute'][$attribute['attribute_id']] = $attribute['option_id'];
                    }
                }
                $this->_loadOptions($params);
                if (isset($params['bundle_option'])) {
                    $final_params['bundle_option'] = $this->jsonHelper->jsonDecode($params['bundle_option']);
                }
                $this->checkoutCart->addProduct($product, $final_params);
                $this->checkoutCart->save();
            }
            $objectData    = \Magento\Framework\App\ObjectManager::getInstance();
            $this->session = $objectData->create('\Magento\Checkout\Model\Session');
            $quote         = $this->session->getQuote();
            $items         = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                $cartItemArr = $item->getId();
            }

            $items_qty = floor($quote->getItemsQty());
            $result->setData(['status' => 'success', 'items_qty' => $items_qty, "cart_item_id" => $cartItemArr]);
            return $result;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $result->setData(['status' => 'error', 'message' => str_replace("\"", "||", $e->getMessage())]);
            return $result;
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => str_replace("\"", "||", $e->getMessage())]);
            return $result;
        }
    }

    private function _loadOptions($params)
    {
        if (isset($params['options'])) {
            foreach ($params['options'] as $attributeOption) {
                return $final_params['options'][$attributeOption['attribute_id']] = $attributeOption['option_id'];
            }
        }
    }
}
