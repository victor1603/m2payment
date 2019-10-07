<?php

namespace CodeCustom\Payments\Helper\Config;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use CodeCustom\Payments\Model\LiqPay as LiqPayPayment;

class LiqPayConfig extends AbstractHelper
{

    const XML_PATH_IS_ENABLED               = 'payment/liqpay_payment/active';
    const XML_PATH_PUBLIC_KEY               = 'payment/liqpay_payment/public_key';
    const XML_PATH_PRIVATE_KEY              = 'payment/liqpay_payment/private_key';
    const XML_PATH_TEST_MODE                = 'payment/liqpay_payment/sandbox';
    const XML_PATH_TEST_ORDER_SURFIX        = 'payment/liqpay_payment/sandbox_order_surfix';
    const XML_PATH_DESCRIPTION              = 'payment/liqpay_payment/description';
    const XML_PATH_CALLBACK_SECURITY_CHECK  = 'payment/liqpay_payment/security_check';
    const XML_PATH_PAYMENT_TYPE             = 'payment/liqpay_payment/payment_type';
    const XML_PATH_PAYMENT_API_URL          = 'codecustom/liqpay_payment/api_url';
    const XML_PATH_PAYMENT_CHECKOUT_URL     = 'codecustom/liqpay_payment/checkout_url';
    const XML_PATH_HOLD_CONFIRM_STATUS      = 'codecustom/liqpay_payment/hold_confirm_order_status';

    protected $_paymentHelper;

    public function __construct(
        Context $context,
        PaymentHelper $paymentHelper
    )
    {
        parent::__construct($context);
        $this->_paymentHelper = $paymentHelper;
    }

    public function isEnabled()
    {
        if ($this->scopeConfig->getValue(
            static::XML_PATH_IS_ENABLED,
            ScopeInterface::SCOPE_STORE
        )
        ) {
            if ($this->getPublicKey() && $this->getPrivateKey()) {
                return true;
            } else {
                $this->_logger->error(__('The LiqpayMagento\LiqPay module is turned off, because public or private key is not set'));
            }
        }
        return false;
    }

    public function isTestMode()
    {
        return $this->scopeConfig->getValue(
            static::XML_PATH_TEST_MODE,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function isSecurityCheck()
    {
        return $this->scopeConfig->getValue(
            static::XML_PATH_CALLBACK_SECURITY_CHECK,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getPublicKey()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_PUBLIC_KEY,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getPrivateKey()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_PRIVATE_KEY,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getTestOrderSurfix()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_TEST_ORDER_SURFIX,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getPaymentType()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_PAYMENT_TYPE,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getAPIUrl()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_PAYMENT_API_URL,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getCheckoutUrl()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_PAYMENT_CHECKOUT_URL,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getConfirmHoldStatus()
    {
        return trim($this->scopeConfig->getValue(
            static::XML_PATH_HOLD_CONFIRM_STATUS,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getLiqPayDescription(\Magento\Sales\Api\Data\OrderInterface $order = null)
    {
        $description = trim($this->scopeConfig->getValue(
            static::XML_PATH_DESCRIPTION,
            ScopeInterface::SCOPE_STORE
        ));
        $order_id = $order->getIncrementId();
        if($this->isTestMode()){
            $surfix = $this->getTestOrderSurfix();
            $order_id=$order_id.'-'.$surfix;
        }
        $params = [
            '{order_id}' => $order_id,
        ];
        return strtr($description, $params);
    }

    public function checkOrderIsLiqPayPayment(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);
        return $methodInstance instanceof LiqPayPayment;
    }

    public function securityOrderCheck($data, $receivedPublicKey, $receivedSignature)
    {
        if ($this->isSecurityCheck()) {
            $publicKey = $this->getPublicKey();
            if ($publicKey !== $receivedPublicKey) {
                return false;
            }

            $privateKey = $this->getPrivateKey();
            $generatedSignature = base64_encode(sha1($privateKey . $data . $privateKey, 1));

            return $receivedSignature === $generatedSignature;
        } else {
            return true;
        }
    }

    public function getLogger()
    {
        return $this->_logger;
    }
}