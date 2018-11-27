<?php
namespace Mmsbuilder\Connector\Controller\Adminhtml\dashboard;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class Save extends \Magento\Backend\App\Action
{
    const BASE_MEDIA_PATH = 'mss/dashboard/images';
    private $storeManager;
    private $fileUploaderFactory;
    private $fileSystem;
    public function __construct(
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory,
        Action\Context $context,
        Filesystem $fileSystem
    ) {
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->fileSystem          = $fileSystem;
        parent::__construct($context);
    }
    public function execute()
    {
        $data         = $this->getRequest()->getParams();
        $imageRequest = $this->getRequest()->getFiles('banner_name');
        if (!$data) {
            $this->_redirect('mmsbuilder_connector/dashboard/adding');
            return;
        }
        try {
            $objectData = \Magento\Framework\App\ObjectManager::getInstance();
            $rowData    = $objectData->create('Mmsbuilder\Connector\Model\Dashboard');
            if ($imageRequest) {
                if (isset($imageRequest['name'])) {
                    $fileName = $imageRequest['name'];
                } else {
                    $fileName = '';
                }
            } else {
                $fileName = '';
            }
            if ($imageRequest && (!empty($fileName))) {
                try {
                    $uploader = $this->fileUploaderFactory->create(['fileId' => 'banner_name']);
                    $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                    $uploader->setAllowRenameFiles(false);
                    $uploader->setFilesDispersion(false);
                    $path = $this->fileSystem
                        ->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('images/');
                    $result              = $uploader->save($path);
                    $data['banner_name'] = $result['file'];
                } catch (\Exception $e) {
                    if ($e->getCode() == 0) {
                        $this->messageManager->addError($e->getMessage());
                    }
                }
            } else {
                unset($data['banner_name']);
            }
            if (!$data['id']) {
                unset($data['id']);
            }
            $rowData->setData($data);
            if (isset($data['id'])) {
                $rowData->setEntityId($data['id']);
            }
            $rowData->save();
            $this->messageManager->addSuccess(__('Dashboard data has been successfully saved.'));
        } catch (\Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
        }
        $this->_redirect('mmsbuilder_connector/dashboard/index');
    }
}
