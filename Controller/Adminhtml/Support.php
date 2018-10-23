<?php
namespace Mmsbuilder\Connector\Controller\Adminhtml;

abstract class Support extends \Magento\Backend\App\Action
{

    public function execute()
    {
        return $this->getResponse()->setBody('This is magento admin panel after redirect');
    }
}
