<?php
namespace Mmsbuilder\Connector\Model\ResourceModel;

class Dashboard extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    private $idFieldName = 'id';

    public function _construct()
    {
        $this->_init('mmsbuilder_dashboard', 'id');
    }
}
