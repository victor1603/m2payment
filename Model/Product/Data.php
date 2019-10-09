<?php

namespace CodeCustom\Payments\Model\Product;

use Magento\Catalog\Model\ProductFactory;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;

class Data
{
    /**
     * @var ProductFactory
     */
    protected $_productFactory;

    public function __construct(
        ProductFactory $productFactory
    )
    {
        $this->_productFactory = $productFactory;
    }

    /** Is product credit value is defined
     * @param $product_id
     * @param $attrCode
     * @return bool
     */
    public function getProductCreditTermValue($product_id, $attrCode = false)
    {
        $model = $this->_productFactory->create()
            ->load($product_id);
        if ($model) {
            if ($attrCode) {
                return $model->getData($attrCode);
            } else {
                return $model->getData('test');
            }
        }
        return false;
    }

    /**
     * @param $product_id
     * @return mixed
     */
    public function getProductModel($product_id)
    {
        return $this->_productFactory->create()
            ->load($product_id);
    }
}