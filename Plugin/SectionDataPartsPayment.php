<?php
/**
 * Created by PhpStorm.
 * User: kharidas
 * Date: 28.03.19
 * Time: 12:42
 */

namespace CodeCustom\Payments\Plugin;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Model\ProductFactory;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use CodeCustom\Payments\Model\PbPartsPayment;

class SectionDataPartsPayment
{
    /**
     * @var CartHelper
     */
    protected $_cartHelper;

    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     * @var ProductFactory
     */
    protected $_model;

    /**
     * @var Config
     */
    protected $_methodConfig;

    public function __construct(
        CartHelper $cartHelper,
        CheckoutSession $checkoutSession,
        ProductFactory $productModel,
        PrivatBankConfig $methodConfig
    )
    {
        $this->_methodConfig = $methodConfig;
        $this->_model = $productModel;
        $this->_checkoutSession = $checkoutSession;
        $this->_cartHelper = $cartHelper;
    }

    public function afterGetSectionData(
        Cart $subject,
        $result
    )
    {
        if($this->_methodConfig->isActive()) {
            $result = $this->modifyPartPayments($result);
        }
        return $result;
    }

    /**
     * @param $data
     * @return array
     */
    private function modifyPartPayments($data)
    {
        if(is_array($data) && array_key_exists('items', $data)) {
            $items = $data['items'];
            $result = array();
            if(!empty($items)) {
                $productType = false;
                $nonTermed = false;
                foreach ($items as $item) {
                    $quote = $this->_cartHelper->getQuote();
                    $quoteItem = $quote->getItemById($item['item_id']);
                    $options = $quoteItem->getOptions();
                    $partPaymentData = false;
                    foreach ($options as $option) {
                        if($option->getCode() === PbPartsPayment::METHOD_CODE) {
                            $partPaymentData = $option->getValue();
                        }
                    }
                    if($partPaymentData) {
                        $data['has_part_payment'] = true;
                        $item['has_part_payment_data'] = true;
                        $item['part_payment_data'] = $partPaymentData;
                    }else if($this->isPartsPaymentAvailable($item)){
                        $productType = true;
                        $item['part_payment_data'] = false;
                        $item['has_part_payment_data'] = true;
                        $data['has_part_payment'] = true;
                    } else {
                        $item['part_payment_data'] = false;
                        $item['has_part_payment_data'] = false;
                        $data['has_part_payment'] = false;
                    }
                    if(!$nonTermed) {
                        $nonTermed = !$this->isTermed($item);
                    }
                    array_push($result, $item);
                }
                $data['part_payment_' . PbPartsPayment::ATTRIBUTE_TERM_CODE] = $this->getPartPaymentData($productType);
                if($nonTermed) {
                    $nonTermed = __('Some1 of this products aren\'t available for parts payment');
                }
                $data['part_payment_message'] = $nonTermed;
            }
            $data['items'] = $result;
            return $data;
        }
        return $data;
    }

    /** Has product with available parts payment rules
     * @param $item
     * @return bool
     */
    private function isPartsPaymentAvailable($item)
    {
        $product = $this->_model->create()->load($item['product_id']);
        if($product && $product->getData(PbPartsPayment::ATTRIBUTE_TERM_CODE)) {
            return true;
        }
        return false;
    }

    /**
     * @param $item
     * @return bool
     */
    private function isTermed($item)
    {
        $product = $this->_model->create()->load($item['product_id']);
        if($item['product_type'] == 'freegift'){
            return true;
        }
        return !!$product->getData(PbPartsPayment::ATTRIBUTE_TERM_CODE);
    }

    /** Data for slider init
     * @param bool $productType
     * @return array|bool
     */
    private function getPartPaymentData($productType = false)
    {
        $result = array();
        $quote = $this->_cartHelper->getQuote();
        $items = $quote->getItems();
        foreach ($items as $item) {
            $type = $item->getOptionByCode('product_type');
            $freeGift = $type ? $type->getValue() === 'freegift' ? true : false : false;
            if(!$freeGift) {
                if ($productType) {
                    $product = $this->_model->create()->load($item['product_id']);
                    $term = $product->getData(PbPartsPayment::ATTRIBUTE_TERM_CODE);
                    if ($term) {
                        array_push($result, [
                            'price' => $item->getPrice(),
                            PbPartsPayment::ATTRIBUTE_TERM_CODE => (int)$term,
                            'item' => $item
                        ]);
                    }
                } else {
                    $option = $item->getOptionByCode(PbPartsPayment::METHOD_CODE);
                    if ($option) {
                        $creditTerm = json_decode($option->getValue())->defined_term;
                        array_push($result, [
                            'price' => $item->getPrice(),
                            PbPartsPayment::ATTRIBUTE_TERM_CODE => (int)$creditTerm,
                            'item' => $item
                        ]);
                    }
                }
            }
        }
        $result = $this->aggregateFinalResult($result, $productType);
        return $result;
    }

    /** Final result
     * @param $result
     * @param bool $productType
     * @return bool|false|string
     */
    private function aggregateFinalResult($result, $productType = false)
    {
        if (!empty($result)) {
            $higherPrice = $this->getHigherIntValue($result, 'price');
            $samePriceItems = $this->getItemsWithSameValue($result, 'price', $higherPrice['price']);
            if (count($samePriceItems) === 1) {
                return $this->createJsonRange($higherPrice, $productType);
            } else {
                $creditItem = $this->getHigherIntValue($samePriceItems, PbPartsPayment::ATTRIBUTE_TERM_CODE);
                return $this->createJsonRange($creditItem, $productType);
            }
        }
        return false;
    }

    /**
     * @param $dataArr
     * @param bool $productType
     * @return false|string
     */
    private function createJsonRange($dataArr, $productType = false)
    {
        $result['user_defined'] = $productType ? false : $dataArr[PbPartsPayment::ATTRIBUTE_TERM_CODE];
        $product = $dataArr['item']->getProduct();
        $limit = $this->getLimit($product);
        $attribute = $product->getAttributes()[PbPartsPayment::ATTRIBUTE_TERM_CODE];
        $options = $attribute->getOptions();
        $product_range = array();
        foreach ($options as $option) {
            $value = $option->getValue();
            if($value && $value <= $limit) {
                array_push($product_range, $value);
            }
        }
        $result[PbPartsPayment::ATTRIBUTE_TERM_CODE . '_range'] = $product_range;
        $result['user_defined'] = in_array($result['user_defined'], $product_range) ? $result['user_defined'] : false;
        return json_encode($result);
    }

    /**
     * @param $product
     * @return mixed
     */
    private function getLimit($product)
    {
        $model = $this->_model->create()->load($product->getId());
        return $model->getData(PbPartsPayment::ATTRIBUTE_TERM_CODE);
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
            if ($item[$key] > $result) {
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
}
