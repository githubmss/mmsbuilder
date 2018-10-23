<?php
namespace Mmsbuilder\Connector\Setup;

use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * EAV setup factory.
     *
     * @var EavSetupFactory
     */
    private $eavSetupFactory;
    private $categorySetupFactory;

    /**
     * Init.
     *
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory, Config $eavConfig)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig       = $eavConfig;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        /** @var EavSetup $eavSetup */
        if (version_compare($context->getVersion(), '2.5.1', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->addAttribute(
                \Magento\Customer\Model\Customer::ENTITY,
                'mss_profile_image_customer',
                [
                    'type'       => 'text',
                    'label'      => 'App Profile Mss',
                    'input'      => 'textarea',
                    'required'   => false,
                    'default'    => '0',
                    'sort_order' => 100,
                    'system'     => false,
                    'position'   => 100,
                ]
            );
            $sampleAttribute = $this->eavConfig
                ->getAttribute(\Magento\Customer\Model\Customer::ENTITY, 'mss_profile_image_customer');
            $sampleAttribute->setData(
                'used_in_forms',
                ['']
            );
            $sampleAttribute->save();
        }
    }
}
