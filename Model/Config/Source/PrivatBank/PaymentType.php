<?php

namespace CodeCustom\Payments\Model\Config\Source\PrivatBank;

use Magento\Framework\Option\ArrayInterface;
use Magento\Shipping\Model\Config;
use CodeCustom\Payments\Sdk\PrivatBank;

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
            ['label' => __('Standart payment'), 'value' => PrivatBank::STANDART_PB_ACTION],
            ['label' => __('Hold payment'),     'value' => PrivatBank::HOLD_PB_ACTION]
        ];
    }
}