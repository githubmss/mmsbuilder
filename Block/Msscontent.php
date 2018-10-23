<?php
namespace Mmsbuilder\Connector\Block;

/**
 * Msscontent block
 */
class Msscontent extends \Magento\Framework\View\Element\Template
{
    public function getContent()
    {
        ?>
    <div style="text-align:left">
    <img src="<?php echo $this->getViewFileUrl('Mmsbuilder_Connector::images/magento_logo.png'); ?>" />
    </div>
        <h1 style="text-align:center; font-size: 10px !important;"><a href="#">
        This demo is for MagentoMobileShop Connector extension for Magento2</a></h1>
        <h1 style="text-align:right; font-size: 10px !important;"><a href="#">Let's Get Started for FREE!!</a></h1>

    <?php
    }
}
