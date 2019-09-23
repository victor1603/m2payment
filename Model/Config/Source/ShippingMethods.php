<?php


namespace CodeCustom\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Shipping\Model\Config;

class ShippingMethods implements ArrayInterface
{
    /** Shipping config
     * @var Config
     */
    protected $_shippingConfig;

    public function __construct(
        Config $shippingConfig
    )
    {
        $this->_shippingConfig = $shippingConfig;
    }

    /** Options
     * @return array
     */
    public function toOptionArray()
    {
        $methods = $this->getMethodsArray();
        return $methods;
    }

    /** methods array
     * @return array
     */
    public function getMethodsArray()
    {
        $methods[] = [
            'value' => '0',
            'label' => __('Please select...')
        ];
        $carriers = $this->_shippingConfig->getActiveCarriers();
        foreach ($carriers as $carrierCode => $model) {
            $allowedMethods = $model->getAllowedMethods();
            foreach ($allowedMethods as $key => $methodName) {
                $methods[] = [
                    'value' => $carrierCode.'_'.$key,
                    'label' => $methodName
                ];
            }
        }
        return $methods;
    }
}
