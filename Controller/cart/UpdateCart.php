<?php
namespace Mmsbuilder\Connector\Controller\Cart;

class UpdateCart extends \Magento\Framework\App\Action\Action
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
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Checkout\Helper\Cart $cartHelper,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        $this->checkoutCart      = $checkoutCart;
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
        $this->cartHelper        = $cartHelper;
        $this->jsonHelper        = $jsonHelper;
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
        $this->checkoutSession = $objectData->create('\Magento\Checkout\Model\Session');
        $params                = $this->getRequest()->getContent();
        $josn                  = $this->jsonHelper->jsonDecode($params, true);
        $josn                  = $josn['cart_data'];
        if ($josn['id']) {
            try {
                $items = $this->cartHelper->getCart()->getItems();
                foreach ($items as $item) {
                    if (urldecode($item->getProduct()->getId()) == $josn['id']
                        || urldecode($item->getProduct()->getSku()) == $josn['sku']) {
                        $itemId = $item->getItemId();
                        $this->saveItem($itemId);
                        break;
                    }
                }
                $result->setData(['status' => 'success']);
                return $result;
            } catch (\Exception $e) {
                $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
                return $result;
            } catch (\Exception $e) {
                $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
                return $result;
            }
        } else {
            $result->setData(['status' => 'error', 'message' => __('Cart is not valid.')]);
            return $result;
        }
    }

    private function saveItem($itemId)
    {
        return $this->cartHelper->getCart()->removeItem($itemId)->save();
    }
}
