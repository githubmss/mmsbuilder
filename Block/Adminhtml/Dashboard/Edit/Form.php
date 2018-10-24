<?php
namespace Mmsbuilder\Connector\Block\Adminhtml\Dashboard\Edit;

class Form extends \Magento\Backend\Block\Widget\Form\Generic
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Cms\Model\Wysiwyg\Config $wysiwygConfig,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollection,
        \Mmsbuilder\Connector\Model\Status $options,
        array $data = []
    ) {
        $this->_options            = $options;
        $this->_wysiwygConfig      = $wysiwygConfig;
        $this->_categoryCollection = $categoryCollection;
        $this->_storeManager       = $context->getStoreManager();
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function _prepareForm()
    {
        $dateFormat = $this->_localeDate->getDateFormat(\IntlDateFormatter::SHORT);
        $model      = $this->_coreRegistry->registry('row_data');

        $form = $this->_formFactory->create(
            ['data' => [
                'id'      => 'edit_form',
                'enctype' => 'multipart/form-data',
                'action'  => $this->getData('action'),
                'method'  => 'post',
            ],
            ]
        );

        $form->setHtmlIdPrefix('mssslider_');
        if ($model->getBannerId()) {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Edit Tile'), 'class' => 'fieldset-wide']
            );
            $fieldset->addField('banner_id', 'hidden', ['name' => 'banner_id']);
        } else {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Add Tile'), 'class' => 'fieldset-wide']
            );
        }

        $fieldset->addField(
            'tile_tittle',
            'text',
            [
                'name'     => 'tile_tittle',
                'label'    => __('Tile Title'),
                'required' => true,
            ]
        );

        $fieldset->addField(
            'id',
            'hidden',
            [
                'name'  => 'id',
                'label' => __('Id'),
            ]
        );

        $fieldset->addField(
            'tile_type',
            'select',
            [
                'name'     => 'tile_type',
                'label'    => __('Tile Type'),
                'class'    => 'tile_type',
                'required' => true,
                'values'   => [
                    ''  => 'Please Select..',
                    '1' => 'Category',
                    '2' => 'Banner',
                    '3' => 'Promotional'],
            ]
        );

        $fieldset->addField(
            'banner_type',
            'select',
            [
                'name'   => 'banner_type',
                'class'  => 'banner_type',
                'label'  => __('Banner Type'),
                'values' => [
                    '0' => 'small',
                    '1' => 'medium',
                    '2' => 'large'],
            ]
        );

        // Get all the categories that in the database
        $optionArray = $this->getCategoryCollection();

        $fieldset->addField(
            'category_display_id',
            'select',
            [
                'name'     => 'category_display_id',
                'class'    => 'category_display_id',
                'label'    => __('Categories'),
                'title'    => __('Categories'),
                'values'   => $optionArray,
                'disabled' => false,

            ]
        );

        $fieldset->addField(
            'category_display',
            'select',
            [
                'name'   => 'category_display',
                'label'  => __('Category Display'),
                'class'  => 'category_display',
                'values' => [
                    '0' => 'Grid',
                    '1' => 'List',
                ],
            ]
        );

        $fieldset->addField(
            'promotion_display',
            'select',
            [
                'name'   => 'promotion_display',
                'label'  => __('Promotional Display'),
                'class'  => 'promotion_display',
                'values' => [
                    '0' => 'Grid',
                    '1' => 'List',
                ],
            ]
        );

        $fieldset->addField(
            'promotion_display_id',
            'select',
            [
                'name'   => 'promotion_display_id',
                'label'  => __('Promotional Display Id'),
                'class'  => 'promotion_display_id',
                'values' => [
                    '0' => 'Top',
                    '1' => 'New',
                    '2' => 'Sale',
                ],
            ]
        );

        $fieldset->addField(
            'banner_name',
            'image',
            [
                'name'     => 'banner_name',
                'title'    => __('Banner Image'),
                'label'    => __('Banner Image'),
                'class'    => 'thumbnail banner_name',
                'index'    => 'image',
                'renderer' => '\Mmsbuilder\Connector\Block\Adminhtml\Grid\Renderer\LogoImage',

            ]
        );

        $fieldset->addField(
            'display_start_date',
            'date',
            [
                'name'        => 'display_start_date',
                'label'       => __('Display Start Date'),
                'title'       => __('Display Start Date'),
                'required'    => false,
                'class'       => 'display_start_date',
                'singleClick' => true,
                'date_format' => 'yyyy-MM-dd',
                'time'        => false,

            ]
        );

        $fieldset->addField(
            'display_end_date',
            'date',
            [
                'name'        => 'display_end_date',
                'label'       => __('Display End Date'),
                'title'       => __('Display End Date'),
                'required'    => false,
                'class'       => 'display_end_date',
                'singleClick' => true,
                'date_format' => 'yyyy-MM-dd',
                'time'        => false,
            ]
        );

        $fieldset->addField(
            'status',
            'select',
            [
                'name'     => 'status',
                'label'    => __('Status'),
                'required' => true,
                'values'   => [
                    ''  => 'Please Select..',
                    '1' => 'Enable',
                    '0' => 'Disable'],
            ]
        );

        $fieldset->addField(
            'position',
            'text',
            [
                'name'  => 'position',
                'label' => __('Position'),
            ]
        );
        $form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    public function getCategoryCollection()
    {
        $collection = $this->_categoryCollection->create()
            ->addAttributeToSelect('*')
            ->setStore($this->_storeManager->getStore())
            ->addAttributeToFilter('is_active', '1');

        $result = [];
        foreach ($collection as $subKey => $subValue) {
            $result[$subValue->getId()] = $subValue->getName();
        }

        return $result;
    }
}
