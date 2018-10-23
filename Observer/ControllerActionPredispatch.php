<?php
namespace Mmsbuilder\Connector\Observer;

use \Magento\Framework\Event\Observer;
use \Magento\Framework\Event\ObserverInterface;

class ControllerActionPredispatch implements ObserverInterface
{
    const XML_SECURE_KEY        = 'magentomobileshop/secure/key';
    const ACTIVATION_URL        = 'https://www.magentomobileshop.com/user/mss_verifiy';
    const TRNS_EMAIL            = 'trans_email/ident_general/email';
    const XML_SECURE_KEY_STATUS = 'magentomobileshop/key/status';
    private $logger;
    private $response;
    public function __construct(
        \Psr\Log\LoggerInterface $loggerInterface,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Backend\App\Action $action,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\Resolver $resolver,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Response\Http $response,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {

        $this->logger                = $loggerInterface;
        $this->urlInterface          = $urlInterface;
        $this->action                = $action;
        $this->coreRegistry          = $coreRegistry;
        $this->scopeConfig           = $scopeConfig;
        $this->resolver              = $resolver;
        $this->resourceConfig        = $resourceConfig;
        $this->storeManager          = $storeManager;
        $this->cacheTypeList         = $cacheTypeList;
        $this->response              = $response;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->responseFactory       = $responseFactory;
        $this->messageManager        = $messageManager;
    }
    private $codes = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

    private function decode($input)
    {
        $codes = $this->codes;
        try {
            if ($input == null) {
                $message = "INPUT IS NULL";
                $result->setData($message);
                return $result;
            }
        } catch (\Exception $e) {
            if (isset($e->xdebug_message)) {
                $message = $e->xdebug_message;
            } else {
                $message = $e->getMessage();
            }
            $result->setData($message);
            return $result;
        }
        try {
            if (!empty($input) % 4 != 0) {
                $message = "INVALID BASE64 STRING";
                $result->setData($message);
                return $result;
            }
        } catch (\Exception $e) {
            if (isset($e->xdebug_message)) {
                $message = $e->xdebug_message;
            } else {
                $message = $e->getMessage();
            }
            $result->setData($message);
            return $result;
        }
        $decoded[] = ((strlen($input) * 3) / 4) - (strrpos($input, '=') > 0 ?
            (strlen($input) - strrpos($input, '=')) : 0);
        $inChars = str_split($input);
        $count = count($inChars);
        $j       = 0;
        $b       = [];
        for ($i = 0; $i < $count; $i += 4) {
            $b[0]          = strpos($codes, $inChars[$i]);
            $b[1]          = strpos($codes, $inChars[$i + 1]);
            $b[2]          = strpos($codes, $inChars[$i + 2]);
            $b[3]          = strpos($codes, $inChars[$i + 3]);
            $decoded[$j++] = (($b[0] << 2) | ($b[1] >> 4));
            if ($b[2] < 64) {
                $decoded[$j++] = (($b[1] << 4) | ($b[2] >> 2));
                if ($b[3] < 64) {
                    $decoded[$j++] = (($b[2] << 6) | $b[3]);
                }
            }
        }
        $decodedstr = '';
        $count_decode = count($decoded);
        for ($i = 0; $i < $count_decode; $i++) {
            $decodedstr .= htmlspecialchars_decode($decoded[$i]);
        }
        return $decodedstr;
    }

    private function encode($in)
    {
        $codes = $this->codes;
        $inlen = strlen($in);
        $in    = str_split($in);
        $out   = '';
        $b     = '';
        for ($i = 0; $i < $inlen; $i += 3) {
            $b = (ord($in[$i]) & 0xFC) >> 2;
            $out .= ($codes[$b]);
            $b = (ord($in[$i]) & 0x03) << 4;
            if ($i + 1 < $inlen) {
                $b |= (ord($in[$i + 1]) & 0xF0) >> 4;
                $out .= ($codes[$b]);
                $b = (ord($in[$i + 1]) & 0x0F) << 2;
                if ($i + 2 < $inlen) {
                    $b |= (ord($in[$i + 2]) & 0xC0) >> 6;
                    $out .= ($codes[$b]);
                    $b = ord($in[$i + 2]) & 0x3F;
                    $out .= ($codes[$b]);
                } else {
                    $out .= ($codes[$b]);
                    $out .= ('=');
                }
            } else {
                $out .= ($codes[$b]);
                $out .= ("==");
            }
        }

        return $out;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $event             = $observer->getEvent();
        $adminsession      = \Magento\Security\Model\AdminSessionInfo::LOGGED_IN;
        $url               = $this->urlInterface->getCurrentUrl();
        $objectData        = \Magento\Framework\App\ObjectManager::getInstance();
        $this->request     = $objectData->create('\Magento\Framework\App\Request\Http');
        $decode            = $this->request->getParam('mms_id');
        $mssAppData        = '';
        $this->authSession = $objectData->create('\Magento\Backend\Model\Auth\Session');
        $this->coreSession = $objectData->create('\Magento\Backend\Model\Session');

        if ($decode and !$this->coreRegistry->registry('mms_app_data')) {
            $param = $this->decode($decode);
            $this->coreRegistry->register('mms_app_data', $param);
            $mssAppData = $this->coreRegistry->registry('mms_app_data');
        }
        $current = $this->scopeConfig->getValue('magentomobileshop/secure/key');

        if (!$this->scopeConfig->getValue(self::XML_SECURE_KEY) and $adminsession) {
            $static_url = 'https://www.magentomobileshop.com/user/buildApp?key_info=';
            $email      = base64_encode($this->scopeConfig->getValue(self::TRNS_EMAIL));
            $url        = base64_encode($this->storeManager->getStore()->getBaseUrl());
            $key        = base64_encode('email=' . $email . '&url=' . $url);

            $href = $static_url . $key;
            $this->messageManager->addNotice(__('Magentomobileshop
                extension is not activated yet, <a href="' . $href . '">
                Click here</a> to activate your extension.'));
        }

        if ((!$current) and $adminsession and $mssAppData != '') {
            if ((!$current)) {
                $str        = self::ACTIVATION_URL;
                $url        = $str . '?mms_id=';
                $final_url  = $url . '' . $mssAppData;
                $final_urls = $str;
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY, $mssAppData, 'default', 0);
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY_STATUS, '1', 'default', 0);
                $lang                             = $this->resolver->getLocale();
                $mssData                          = [];
                $mssData[0]['mms_id']             = base64_encode($mssAppData);
                $mssData[0]['default_store_name'] = $this->storeManager->getStore()->getCode();
                $mssData[0]['default_store_id']   = $this->storeManager
                    ->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
                $mssData[0]['default_view_id']        = $this->storeManager->getStore()->getId();
                $mssData[0]['default_store_currency'] = $this->storeManager->getStore()->getCurrentCurrencyCode();
                $mssData[0]['language']               = $lang;
                $mssData[0]['status']                 = 'true';
                $this->cacheTypeList->cleanType('config');
                $this->coreSession->setAppDatas($mssData[0]);
                $this->coreRegistry->unregister('mms_app_data');
                $customerBeforeAuthUrl = $this->urlInterface
                    ->getUrl('mmsbuilder_connector/system_connector/index');
                $this->responseFactory->create()->setRedirect($customerBeforeAuthUrl)->sendResponse();
            } elseif ($current != '' and $adminsession->isLoggedIn() and $decode != '') {
                $str        = self::ACTIVATION_URL;
                $url        = $str . '?mms_id=';
                $final_url  = $url . '' . $mssAppData;
                $final_urls = $str;
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY, $mssAppData);
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY_STATUS, '1');
                $mssData[0]['mms_id']             = base64_encode($mssAppData);
                $mssData[0]['default_store_name'] = $this->storeManager->getStore()->getCode();
                $mssData[0]['default_store_id']   = $this->storeManager
                    ->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
                $mssData[0]['default_view_id']        = $this->storeManager->getStore()->getId();
                $mssData[0]['default_store_currency'] = $this->storeManager->getStore()->getCurrentCurrencyCode();
                $mssData[0]['status']                 = 'true';

                $this->cacheTypeList->cleanType('config');
                $this->coreSession->setAppDatas($mssData[0]);
                $this->coreRegistry->unregister('mms_app_data');
                $customerBeforeAuthUrl = $this->urlInterface
                    ->getUrl('mmsbuilder_connector/system_connector/index');
                $this->responseFactory->create()->setRedirect($customerBeforeAuthUrl)->sendResponse();
            }
        }
    }
}
