<?php
namespace  Mmsbuilder\Connector\Controller\Adminhtml\Dashboard;

class Index extends \Magento\Backend\App\Action
{
    private $resultPageFactory = false;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('App Dashboard Configuration')));
        return $resultPage;
    }
}
