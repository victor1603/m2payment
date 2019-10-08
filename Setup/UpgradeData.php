<?php

namespace CodeCustom\Payments\Setup;

use CodeCustom\Payments\Model\PbInstantInstallment;
use CodeCustom\Payments\Model\PbPartsPayment;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;

/**
 * Upgrade Data script
 */
class UpgradeData implements UpgradeDataInterface
{
    private $eavSetupFactory;
    protected $_attributeRepository;
    protected $attrOptionCollectionFactory;
    protected $optionManagement;
    protected $optionFactory;

    public function __construct(
        EavSetupFactory $eavSetupFactory,
        \Magento\Eav\Model\AttributeRepository $_attributeRepository,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attrOptionCollectionFactory,
        \Magento\Eav\Api\AttributeOptionManagementInterface $optionManagement,
        \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionInterfaceFactory

    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->_attributeRepository = $_attributeRepository;
        $this->attrOptionCollectionFactory = $attrOptionCollectionFactory;
        $this->optionFactory = $optionInterfaceFactory;
        $this->optionManagement = $optionManagement;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        /**
         * Add quote fields for some option in Parts Payments or Instant InstallMent
         */
        /*$setup->getConnection()->addColumn(
            $setup->getTable('quote_payment'),
            'part_payment_term',
            [
                'type' => 'text',
                'nullable' => true  ,
                'comment' => 'Parts Payment Data',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote_payment'),
            'part_payment_price',
            [
                'type' => 'text',
                'nullable' => true  ,
                'comment' => 'Parts Payment Data',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote_payment'),
            'credit_payment_deposit',
            [
                'type' => 'text',
                'nullable' => true,
                'comment' => 'Credit First Deposit',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote_payment'),
            'credit_payment_overpay',
            [
                'type' => 'text',
                'nullable' => true,
                'comment' => 'Credit Overpay Price',
            ]
        );*/

        /**
         * Add new Product attributes for change Term of Parts Payment Or Instant Installment
         */

        /*$eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            PbPartsPayment::ATTRIBUTE_TERM_CODE,
            [
                'group' => 'General',
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Parts payment term',
                'input' => 'select',
                'class' => '',
                'source' => 'CodeCustom\Payments\Model\Config\Attribute\Options',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '1',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );

        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            PbInstantInstallment::ATTRIBUTE_TERM_CODE,
            [
                'group' => 'General',
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Instant installment term',
                'input' => 'select',
                'class' => '',
                'source' => 'CodeCustom\Payments\Model\Config\Attribute\Options',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '1',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );*/

        $setup->endSetup();
    }
}