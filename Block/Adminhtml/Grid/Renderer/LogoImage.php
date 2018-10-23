<?php
namespace Mmsbuilder\Connector\Block\Adminhtml\Grid\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;

class LogoImage extends AbstractRenderer
{
    private $storeManager;
    public function __construct(
        \Magento\Backend\Block\Context $context,
        StoreManagerInterface $storemanager,
        array $data = []
    ) {
        $this->storeManager = $storemanager;
        parent::__construct($context, $data);
        $this->_authorization = $context->getAuthorization();
    }
    public function render(DataObject $row)
    {
        $imageName = $row->getRbBannerImage();
        if ($imageName == "") {
            return "";
        }
        $mediaDirectory = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );
    }
}
