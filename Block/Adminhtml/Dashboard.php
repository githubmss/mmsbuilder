<?php
namespace Mmsbuilder\Connector\Block\Adminhtml;

class Dashboard extends \Magento\Backend\Block\Widget\Form\Container
{
    private $coreRegistry = null;
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    public function _construct()
    {
        $this->_objectId   = 'row_id';
        $this->_blockGroup = 'Mmsbuilder_Connector';
        $this->_controller = 'adminhtml_dashboard';
        parent::_construct();

    }

    public function getHeaderText()
    {
        return __('Add Dashboard Tile');
    }

    public function getFormActionUrl()
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }
        return $this->getUrl('*/*/save');
    }
}
