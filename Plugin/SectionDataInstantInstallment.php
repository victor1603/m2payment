<?php

namespace CodeCustom\Payments\Plugin;

use Magento\Checkout\CustomerData\Cart;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use Magento\Checkout\Helper\Cart as CartHelper;
use CodeCustom\Payments\Helper\PbInstantInstallment\Data;
use CodeCustom\Payments\Model\PbInstantInstallment;
use CodeCustom\Payments\Helper\PbInstantInstallment\Validate;

class SectionDataInstantInstallment
{
    /** Method config
     * @var Config
     */
    protected $_methodHelper;

    /** Cart helper
     * @var CartHelper
     */
    protected $_cartHelper;

    /** Helper
     * @var Validate
     */
    protected $_validateHelper;

    /** Data helper
     * @var Data
     */
    protected $_dataHelper;

    public function __construct(
        PrivatBankConfig $methodHelper,
        CartHelper $cartHelper,
        Data $dataHelper,
        Validate $_validateHelper
    )
    {
        $this->_dataHelper = $dataHelper;
        $this->_cartHelper = $cartHelper;
        $this->_methodHelper = $methodHelper;
        $this->_validateHelper = $_validateHelper;
    }

    public function afterGetSectionData(
        Cart $subject,
        $result
    )
    {
        if($this->_methodHelper->isActive(PbInstantInstallment::METHOD_CODE)) {
            $result = $this->addInstantData($result);
        }
        return $result;
    }

    /** Adding instant payment data
     * @param $data
     * @return array
     */
    public function addInstantData($data)
    {
        if(is_array($data) && array_key_exists('items', $data)) {
            $items = $data['items'];
            if(!empty($items)) {
                $quote = $this->_cartHelper->getQuote();
                $valid = $this->_validateHelper->validateQuoteItems($quote);
                if(!is_integer($valid)) {
                    $collectedData = $this->collectData($quote);
                    $data[PbInstantInstallment::METHOD_CODE] = [
                        'data' => $collectedData,
                        'error' => false
                    ];
                }else {
                    $data[PbInstantInstallment::METHOD_CODE] = [
                        'data' => false,
                        'error' => $this->_validateHelper->getErrorStringByCode($valid)
                    ];
                }
            }
        }else{
            $data[PbInstantInstallment::METHOD_CODE] = [
                'data' => false,
                'error' => false
            ];
        }
        return $data;
    }

    /** Collecting data from cart product
     * @param $quote
     * @return bool|mixed
     */
    public function collectData($quote)
    {
        $result = [];
        if($quote) {
            $items = $quote->getItems();
            $attrCode = PbInstantInstallment::ATTRIBUTE_TERM_CODE;
            foreach ($items as $item) {
                if($item->getProductType() !== Validate::FREE_GIFT_TYPE) {
                    $optionData = $item->getOptionByCode(PbInstantInstallment::METHOD_CODE);
                    if($optionData) {
                        $data = $optionData->getData();
                        $value = array_key_exists('value', $data) ? $data['value'] : false;
                        $value = $value ? json_decode($value, true) : false;
                        $result[] = [
                            'price' => (int)$item->getProduct()->getFinalPrice() * $item->getQty(),
                            'max_term' => (int)$this->_dataHelper->getAttributeValue($item->getProductId(), $attrCode),
                            'item' => $item,
                            PbInstantInstallment::ATTRIBUTE_TERM_CODE => (int)$value[PbInstantInstallment::ATTRIBUTE_TERM_CODE]
                        ];
                    }else if($this->_dataHelper->getAttributeValue($item->getProductId(), $attrCode)) {
                        $result[] = [
                            'price' => (int)$item->getProduct()->getFinalPrice() * $item->getQty(),
                            'max_term' => (int)$this->_dataHelper->getAttributeValue($item->getProductId(), $attrCode),
                            'item' => $item,
                            PbInstantInstallment::ATTRIBUTE_TERM_CODE => 0,
                        ];
                    }
                }
            }
        }
        return $this->generateSatisfiedData($result);
    }

    /** Validating similar prices and terms - creating result data based on validated
     * @param $preparedData
     * @return bool|mixed
     */
    private function generateSatisfiedData($preparedData)
    {
        if($preparedData) {
            $highestPrice = $this->_dataHelper->getHigherIntValue($preparedData, 'price');
            $samePrices = $this->_dataHelper
                ->getItemsWithSameValue($preparedData, 'price', $highestPrice['price']);
            if(count($samePrices) === 1) {
                $paymentData = $this->_dataHelper->generateJsonInstantData($samePrices);
            }else {
                $highestTerm[] = $this->_dataHelper->getHigherIntValue($samePrices, 'max_term');
                $paymentData = $this->_dataHelper->generateJsonInstantData($highestTerm);
            }
            return $paymentData;
        }
        return false;
    }
}