<?php
namespace Mmsbuilder\Connector\Controller\Token;

class GetVersion extends \Magento\Framework\App\Action\Action
{
   
    private $resultJsonFactory;
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $array = [];
        try {
            $objectData = \Magento\Framework\App\objectData::getInstance();
            $productMetadata = $objectData->get('Magento\Framework\App\ProductMetadataInterface');
            $version = $productMetadata->getVersion();
            $array['version']  = $version;
            $result->setData(['status' => 'success', 'message' => $array]);
            return $result;
        } catch (\Exception $e) {
            $result->setData(['status' => 'error', 'message' => __($e->getMessage())]);
            return $result;
        }
    }
}
