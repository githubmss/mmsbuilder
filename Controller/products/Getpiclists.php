<?php
namespace Mmsbuilder\Connector\Controller\products;

class Getpiclists extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Mmsbuilder\Connector\Helper\Data $customHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->productModel = $productModel;
        $this->imageHelper  = $imageHelper;
        $this->customHelper = $customHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->customHelper->loadParent($this->getRequest()->getHeader('token'));
        $this->storeId  = $this->customHelper->storeConfig($this->getRequest()->getHeader('storeid'));
        $this->viewId   = $this->customHelper->viewConfig($this->getRequest()->getHeader('viewid'));
        $this->currency = $this->customHelper->currencyConfig($this->getRequest()->getHeader('currency'));
        $productId      = $this->getRequest()->getParam('product');
        $_images        = $this->productModel->load($productId);
        $mediaList          = $_images->getMediaGalleryImages();
        $imagesGallery = [];
        foreach ($mediaList as $media) {
            $imagesGallery[] = [
                'url'       => $this->imageHelper
                    ->init($_images, 'product_page_image_large')
                    ->setImageFile($media->getFile())
                    ->resize('500', '500')
                    ->getUrl(),
                'thumbnail' => $this->imageHelper
                    ->init($_images, 'product_page_image_small')
                    ->setImageFile($media->getFile())
                    ->resize('100', '100')
                    ->getUrl(),
                'position'  => $media->getPosition(),
            ];
        }
        $result         = $this->resultJsonFactory->create();
        $result->setData($imagesGallery);
        return $result;
    }
}
