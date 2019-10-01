<?php


namespace CodeCustom\Payments\Setup;

use CodeCustom\Payments\Model\PbInstantInstallment;
use CodeCustom\Payments\Model\PbPartsPayment;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class Uninstall implements UninstallInterface
{
    protected $eavSetupFactory;
    public function __construct(
        EavSetupFactory $eavSetupFactory
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote_payment'),
            'part_payment_term'
        );
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote_payment'),
            'part_payment_price'
        );
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote_payment'),
            'credit_payment_deposit'
        );
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote_payment'),
            'credit_payment_overpay'
        );

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, PbPartsPayment::ATTRIBUTE_TERM_CODE);
        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, PbInstantInstallment::ATTRIBUTE_TERM_CODE);
        $setup->endSetup();
    }
}