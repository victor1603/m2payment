<?php


namespace CodeCustom\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use CodeCustom\Payments\Model\PbPartsPayment as Payment;

class PbPartsPayment extends Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * @var LayoutFactory
     */
    protected $_layoutFactory;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        PrivatBankConfig $helper,
        LayoutFactory $layoutFactory
    )
    {
        parent::__construct($context);
        $this->_helper = $helper;
        $this->_checkoutSession = $checkoutSession;
        $this->_layoutFactory = $layoutFactory;
    }

    public function execute()
    {

    }
}