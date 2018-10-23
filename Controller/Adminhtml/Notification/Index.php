<?php

namespace Mmsbuilder\Connector\Controller\Adminhtml\Notification;

class Index extends \Magento\Backend\App\Action
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
