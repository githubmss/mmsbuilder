<?php

namespace Mmsbuilder\Connector\Controller\Adminhtml\Notification;

use Magento\Backend\App\Action;

class Index extends Action
{
    /**
     * Index action
     *
     * @return void
     */

    public function execute()
    {
        return $this->getResponse()->setBody('Notification hello world');
    }
}
