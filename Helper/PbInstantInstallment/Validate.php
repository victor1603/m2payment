<?php


namespace CodeCustom\Payments\Helper\PbInstantInstallment;

use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use CodeCustom\Payments\Model\PbInstantInstallment;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use CodeCustom\Payments\Api\ValidationInterface;
use Magento\Catalog\Model\ProductFactory;
use CodeCustom\Payments\Helper\PbInstantInstallment\Data;
use Magento\Checkout\Model\Session as CheckoutSession;

class Validate extends AbstractHelper
{
    /**
     * Product gift type
     */
    const FREE_GIFT_TYPE = 'freegift';

    /**
     * Instant payment param
     */
    const INSTANT_PAYMENT_PARAM = 'instant_payment';

    /**
     * Instant payment term param
     */
    const INSTANT_PAYMENT_TERM_PARAM = 'instant_payment_term';

    /**
     * Add request qty param
     */
    const INSTANT_PAYMENT_QTY_PARAM = 'instant_payment_qty';

    /**
     * Incorrect place order data
     */
    const INCORRECT_PLACE_ORDER_DATA = 1;

    /**
     * Incorrect term defined
     */
    const INCORRECT_TERM = 2;

    /**
     * Incorrect data price
     */
    const INCORRECT_PRICE = 3;

    /**
     * All items should be available for Instant Payment
     */
    const ALL_ITEMS_AVAILABLE_ERROR = 4;

    /** Method config
     * @var Config
     */
    protected $_methodHelper;

    /**
     * @var ProductFactory
     */
    protected $_productFactory;

    /** Data helper
     * @var Data
     */
    protected $_dataHelper;

    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    public function __construct(
        Context $context,
        PrivatBankConfig $methodHelper,
        Data $dataHelper,
        ProductFactory $productFactory,
        CheckoutSession $checkoutSession
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->_dataHelper = $dataHelper;
        $this->_productFactory = $productFactory;
        $this->_methodHelper = $methodHelper;
        parent::__construct($context);
    }

    /**
     * @param $quote
     * @return bool
     */
    public function validateQuoteItems($quote = false)
    {
        $quote = $quote ? $quote : $this->_checkoutSession->getQuote();
        if ($quote) {
            $items = $quote->getItems();
            $result = true;
            if ($items) {
                if (!$this->_anyWithPayment($items)) return false;
                $gType = self::FREE_GIFT_TYPE;
                foreach ($items as $item) {
                    $option = $item->getOptionByCode('product_type');
                    if ($item->getProductType() !== $gType || !(!$option || $option->getValue() === $gType)) {
                        $model = $this->getProductModel($item->getProductId());
                        if ($result) {
                            $result = !!$model->getData(PbInstantInstallment::ATTRIBUTE_TERM_CODE);
                        } elseif (!$result) {
                            break;
                        }
                    }
                }
            }
            return $result ? $result : self::ALL_ITEMS_AVAILABLE_ERROR;
        }
        return false;
    }

    /** Items
     * @param $items
     * @return bool
     */
    protected function _anyWithPayment($items)
    {
        $result = false;
        foreach ($items as $item) {
            $option = $item->getOptionByCode('product_type');
            if (!$option || ($option && $option->getValeu() !== 'freegift')) {
                $model = $this->getProductModel($item->getProductId());
                if (!$result) {
                    $result = !!$model->getData(PbInstantInstallment::ATTRIBUTE_TERM_CODE);
                } elseif ($result) {
                    break;
                }
            }
        }
        return $result;
    }


    /** Error string
     * @param $code
     * @return string
     */
    public function getErrorStringByCode($code = false)
    {
        switch ($code) {
            case self::INCORRECT_PLACE_ORDER_DATA :
                $result = __('Incorrect place order data');
                break;
            case self::INCORRECT_TERM :
                $result = __('Incorrect term data');
                break;
            case self::INCORRECT_PRICE :
                $result = __('Incorrect price');
                break;
            case self::ALL_ITEMS_AVAILABLE_ERROR :
                $result = __('Some of this products aren\'t available for instant payment');
                break;
            default :
                $result = __('An error occurred');
                break;
        }
        return $result;
    }



    /** Product model by id
     * @param $id
     * @return mixed
     */
    public function getProductModel($id)
    {
        return $this->_productFactory->create()->load($id);
    }

    /** Place order data validation
     * @param $data
     * @param $quote
     * @return bool|int
     */
    public function validatePlaceOrderData($data, $quote)
    {
        try {
            if(array_key_exists('payment_data', $data)) {
                $paymentData = json_decode($data['payment_data'], true);
                if(array_key_exists('additional_data', $paymentData)) {
                    $additionalData = $paymentData['additional_data'];
                    if(array_key_exists(PbInstantInstallment::ATTRIBUTE_TERM_CODE, $additionalData)) {
                        $term = $additionalData[PbInstantInstallment::ATTRIBUTE_TERM_CODE];
                        if(!$term || !array_key_exists('range', $data)) {
                            return self::INCORRECT_TERM;
                        }else if($term && !in_array($term, $data['range'])) {
                            return self::INCORRECT_TERM;
                        }
                    }else{
                        return self::INCORRECT_TERM;
                    }
                    if(array_key_exists('ii_price', $additionalData)) {
                        $grandTotal = (int)$quote->getGrandTotal();
                        $limit = (int)$this->_methodHelper->getMinProductPrice();
                        if($grandTotal < $limit) {
                            return self::INCORRECT_PLACE_ORDER_DATA;
                        }
                    }else {
                        return self::INCORRECT_PRICE;
                    }
                    return true;
                }else{
                    return self::INCORRECT_PLACE_ORDER_DATA;
                }
            }else {
                return self::INCORRECT_PLACE_ORDER_DATA;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}