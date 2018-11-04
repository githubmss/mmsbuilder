<?php
namespace Mmsbuilder\Connector\Controller\Adminhtml\Banner;

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
        return $this->getResponse()->setBody('Banner Hello Support');
    }
}
