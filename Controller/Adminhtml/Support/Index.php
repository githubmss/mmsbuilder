<?php

namespace Mmsbuilder\Connector\Controller\Adminhtml\Support;

class Index extends \Magento\Backend\App\Action
{

    public function execute()
    {

        return $this->getResponse()->setBody('hello Support');
    }
}
