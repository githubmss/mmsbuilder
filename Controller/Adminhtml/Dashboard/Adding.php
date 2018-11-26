<?php
namespace  Mmsbuilder\Connector\Controller\Adminhtml\Dashboard;

use Magento\Framework\Controller\ResultFactory;

class Adding extends \Magento\Backend\App\Action
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry
    ) {
        parent::__construct($context);
        $this->_coreRegistry = $coreRegistry;
    }
    public function execute()
    {
        
        $rowId   = (int)$this->getRequest()->getParam('id');
        $objectData = \Magento\Framework\App\ObjectManager::getInstance();
        $rowData = $objectData->create('Mmsbuilder\Connector\Model\Dashboard');
        if ($rowId) {
            $rowData = $rowData->load($rowId);
            $rowName = $rowData->getName();
            if (!$rowData->getId()) {
                $this->messageManager->addError(__('row data no longer exist.'));
                $this->_redirect('mmsbuilder_connector/dashboard/index');
                return;
            }
        }
        $this->_coreRegistry->register('row_data', $rowData);
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $title      = $rowId ? _('Edit Dashboard Tile') . $rowName : _('Add Dashboard Tile');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
