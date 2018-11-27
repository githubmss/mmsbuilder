<?php
namespace Mmsbuilder\Connector\Controller\index;

class Getpiclists extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->productModel      = $productModel;
        $this->imageHelper       = $imageHelper;
        $this->customHelper      = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->result            = $context->getRequest();
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $productId      = $this->result->getParam('product');
        $_images        = $this->productModel->load($productId);
        $media          = $_images->getMediaGallery();
        $images         = [];
        foreach ($media as $_image) {
            foreach ($_image as $key => $value) {
                $images[] = [
                    'url'       => $this->imageHelper
                        ->init($value, 'product_page_image_large')
                        ->setImageFile($value['file'])
                        ->resize('500', '500')
                        ->getUrl(),
                    'thumbnail' => $this->imageHelper
                        ->init($value, 'product_page_image_small')
                        ->setImageFile($value['file'])
                        ->resize('100', '100')
                        ->getUrl(),
                    'position'  => $value['position'],
                ];
            }
        }
        $result->setData([$images]);
        return $result;
    }
}
