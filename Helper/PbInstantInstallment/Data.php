<?php


namespace CodeCustom\Payments\Helper\PbInstantInstallment;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use CodeCustom\Payments\Model\Product\Data as DataModel;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig as PrivatBankConfig;
use CodeCustom\Payments\Model\PbInstantInstallment;

class Data extends AbstractHelper
{
    /**
     * @var ModelData
     */
    protected $_modelData;

    /**
     * @var Config
     */
    protected $_methodConfig;

    public function __construct(
        Context $context,
        PrivatBankConfig $methodConfig,
        DataModel $_modelData
    )
    {
        $this->_methodConfig = $methodConfig;
        $this->_modelData = $_modelData;
        parent::__construct($context);
    }

    /** Options array range
     * @param $product
     * @param $code
     * @return array
     */
    public function _generateOptionsArrayRange($product, $code)
    {
        $result = [];
        if($product) {
            $attributes = $product->getAttributes();
            if(!empty($attributes) && array_key_exists($code, $attributes)) {
                $limit = $product->getData($code);
                $options = $attributes[$code]->getOptions();
                foreach ($options as $option) {
                    $value = $option->getValue();
                    if($value && $value <= (int)$limit) {
                        array_push($result, $value);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param $product_id
     * @param $attrCode
     * @return bool
     */
    public function getAttributeValue($product_id, $attrCode = false)
    {
        return $this->_modelData->getProductCreditTermValue($product_id, $attrCode);
    }

    /** Higher int value from array
     * @param $array
     * @param $key
     * @return bool
     */
    public function getHigherIntValue($array, $key)
    {
        $result = 0;
        $resItem = false;
        foreach ($array as $item) {
            if ($item[$key] >= $result) {
                $result = $item[$key];
                $resItem = $item;
            }
        }
        return $resItem;
    }

    /** items with same value
     * @param $array
     * @param $key
     * @param $value
     * @return array
     */
    public function getItemsWithSameValue($array, $key, $value)
    {
        $result = array();
        foreach ($array as $item) {
            if($item[$key] == $value) {
                array_push($result, $item);
            }
        }
        return $result;
    }


    public function generateJsonInstantData($data)
    {
        if($data && !empty($data)) {
            $item = array_shift($data);
            $model = $this->_modelData->getProductModel($item['item']->getProductId());
            if($model) {
                $attrCode = PbInstantInstallment::ATTRIBUTE_TERM_CODE;
                $range = $this->_generateOptionsArrayRange($model, $attrCode);
                $user_defined = $item[PbInstantInstallment::ATTRIBUTE_TERM_CODE];
                $user_defined = in_array($user_defined, $range) ? $user_defined : false;
                $resultData = [
                    PbInstantInstallment::ATTRIBUTE_TERM_CODE => $user_defined,
                    'credit_range' => $range
                ];
                return $resultData;
            }
        }
        return false;
    }

    /** Correct deposit
     * @param $deposit
     * @param $grand_total
     * @return int
     */
    public function getCorrectedDeposit($deposit, $grand_total)
    {
        $max_summary = (int)$this->_methodConfig->getMaxCreditSummary();
        $diff = $grand_total - $max_summary;
        if( $diff > 0) {
            if($deposit < $diff) {
                $deposit = $diff;
            }
        }
        return $deposit;
    }
}