<?php

namespace Mmsbuilder\Connector\Controller\Adminhtml\Support;

use Magento\Backend\App\Action;

class Index extends Action
{

    public function execute()
    {

        return $this->getResponse()->setBody('hello Support');
    }
}
