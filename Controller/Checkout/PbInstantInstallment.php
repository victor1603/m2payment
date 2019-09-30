<?php


namespace CodeCustom\Payments\Controller\Checkout;

use CodeCustom\Payments\Sdk\PrivatBank;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use CodeCustom\Payments\Model\PbInstantInstallment as Payment;

class PbInstantInstallment extends Action
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

    /**
     * @var SdkPartsPayment
     */
    protected $sdkPartsPayment;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        PrivatBankConfig $helper,
        LayoutFactory $layoutFactory,
        PrivatBank $sdkPartsPayment
    )
    {
        parent::__construct($context);
        $this->_helper = $helper;
        $this->_checkoutSession = $checkoutSession;
        $this->_layoutFactory = $layoutFactory;
        $this->sdkPartsPayment = $sdkPartsPayment;
    }

    public function execute()
    {
        try {
            if (!$this->_helper->isActive(Payment::METHOD_CODE)) {
                throw new \Exception(__('Payment is not allow.'));
            }
            $order = $this->getCheckoutSession()->getLastRealOrder();
            if (!($order && $order->getId())) {
                throw new \Exception(__('Order not found'));
            }
            $partsCount = 2;
            if ($this->getRequest()->getParam(Payment::ATTRIBUTE_TERM_CODE)) {
                $partsCount = (int)$this->getRequest()->getParam(Payment::ATTRIBUTE_TERM_CODE);
            }
            if ($order->getPayment()->getMethod() == Payment::METHOD_CODE) {
                $urlToPayment = $this->sdkPartsPayment->getPartsPaymentToken($order, $partsCount, $this->_url);
                $data = $urlToPayment;
            } else {
                throw new \Exception('Order payment method is not parts_payment');
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong, please try again later'));
            $this->_helper->getLogger()->critical($e);
            $this->getCheckoutSession()->restoreQuote();
            $data = [
                'status' => 'error',
                'redirect' => $this->_url->getUrl('checkout/cart'),
            ];
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($data);
        return $result;
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }
}