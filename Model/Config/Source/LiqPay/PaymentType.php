<?php

namespace CodeCustom\Payments\Model\Config\Source\LiqPay;

use Magento\Framework\Option\ArrayInterface;
use Magento\Shipping\Model\Config;
use CodeCustom\Payments\Sdk\LiqPay;

class PaymentType implements ArrayInterface
{

    /** Options
     * @return array
     */
    public function toOptionArray()
    {
        $paymentTypes = $this->getPaymentTypes();
        return $paymentTypes;
    }

    public function getPaymentTypes()
    {
        return [
            ['label' => __('Standart payment'), 'value' => LiqPay::STANDART_LIQPAY_ACTION],
            ['label' => __('Hold payment'),     'value' => LiqPay::HOLD_LIQPAY_ACTION]
        ];
    }
}