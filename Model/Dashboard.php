<?php

namespace Mmsbuilder\Connector\Model;

class Dashboard extends \Magento\Framework\Model\AbstractModel
{
        const STATUS_ENABLED = 1;
        const STATUS_DISABLED = 0;
       
    public function _construct()
    {
        $this->_init('Mmsbuilder\Connector\Model\ResourceModel\Dashboard');
    }
    public function getAvailableStatuses()
    {
        $availableOptions = ['1' => 'Enable',
                           '0' => 'Disable'];
        return $availableOptions;
    }
}
