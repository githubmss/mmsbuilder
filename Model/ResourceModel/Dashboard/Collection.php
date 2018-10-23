<?php
namespace Mmsbuilder\Connector\Model\ResourceModel\Dashboard;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    private $idFieldName = 'id';
    
    public function _construct()
    {
        $this->_init(

            'Mmsbuilder\Connector\Model\Dashboard',
            'Mmsbuilder\Connector\Model\ResourceModel\Dashboard'
        );
    }
}
