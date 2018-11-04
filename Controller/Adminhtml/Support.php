<?php
namespace Mmsbuilder\Connector\Controller\Adminhtml;

use Magento\Backend\App\Action;

class Support extends Action
{

    public function execute()
    {
        return $this->getResponse()->setBody('This is magento admin panel after redirect');
    }
}
