<?php

namespace CodeCustom\Payments\Block\LiqPay;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use CodeCustom\Payments\Sdk\LiqPay;
use CodeCustom\Payments\Helper\Config\LiqPayConfig;

class SubmitForm extends Template
{
    protected $_order = null;

    /* @var $_liqPay LiqPay */
    protected $_liqPay;

    /* @var $_helper Helper */
    protected $_helper;

    public function __construct(
        Template\Context $context,
        LiqPay $liqPay,
        LiqPayConfig $helper,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_liqPay = $liqPay;
        $this->_helper = $helper;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        if ($this->_order === null) {
            throw new \Exception('Order is not set');
        }
        return $this->_order;
    }

    public function setOrder(Order $order)
    {
        $this->_order = $order;
    }

    protected function _loadCache()
    {
        return false;
    }

    protected function _toHtml()
    {
        $order = $this->getOrder();
        $html = $this->_liqPay->cnb_form(array(
            'action' => $this->_helper->getPaymentType(),
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'description' => $this->_helper->getLiqPayDescription($order),
            'order_id' => $order->getIncrementId(),
        ));
        return $html;
    }

    public function getHtml()
    {
        return $this->_toHtml();
    }
}