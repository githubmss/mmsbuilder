<?php
namespace Mmsbuilder\Connector\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class About extends Field
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        array $data = []
    ) {
        $this->_curl = $curl;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
     
    public function render(AbstractElement $elements)
    {
        $url="http://magentomobileshop.com/wp-content/magentomobiledata/about_us.html";
        $this->_curl->get($url);
        $response = $this->_curl->getBody();
        $form = $elements->getForm();
        $html = $response;
        return $html;
    }
}
