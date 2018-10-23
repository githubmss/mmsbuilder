<?php

namespace Mmsbuilder\Connector\Controller\products;

class ProductReviews extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Review\Model\ReviewFactory $reviewFactory,
        \Magento\Review\Model\RatingFactory $ratingFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_reviewFactory    = $reviewFactory;
        $this->_ratingFactory    = $ratingFactory;
        $this->_storeManager     = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $context->getRequest();
    }

    public function execute()
    {

        $productId       = $this->request->getParam('productid');
        $productTitle    = $this->request->getParam('producttitle');
        $productDetail   = $this->request->getParam('productdetail');
        $objectData      = \Magento\Framework\App\objectData::getInstance();
        $customerSession = $objectData->get('Magento\Customer\Model\Session');
        if ($customerSession->isLoggedIn()) {
            $customerId = $customerSession->getCustomerId();

            $_review = $objectData->get("Magento\Review\Model\Review")
                ->setEntityPkValue($productId) //product Id
                ->setStatusId(\Magento\Review\Model\Review::STATUS_PENDING) // approved
                ->setTitle($productTitle)
                ->setDetail($productDetail)
                ->setEntityId(1)
                ->setStoreId(1)
                ->setStores(1)
                ->setCustomerId($customerId) //get dynamically here
                ->setNickname($customerSession->getCustomer()->getName())
                ->save();

            $ratingOptions = [
                '1' => '1',
                '2' => '1',
                '3' => '1',
                '4' => '1',
            ];

            foreach ($ratingOptions as $ratingId => $optionIds) {
                $objectData->get("Magento\Review\Model\Rating")
                    ->setRatingId($ratingId)
                    ->setReviewId($_review->getId())
                    ->addOptionVote($optionIds, $productId);
            }
            $result = $this->resultJsonFactory->create();
            $_review->aggregate();
            $result->setData(['status' => 'success', 'message' => 'Rating has been saved success !!!!!!!!!']);
            return $result;
        }
    }
}
