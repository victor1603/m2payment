<?php

namespace CodeCustom\Payments\Sdk;
use \CodeCustom\Payments\Sdk\Core\LiqPaySdk;
use \CodeCustom\Payments\Helper\Config\LiqPayConfig;

class LiqPay extends LiqPaySdk
{
    const VERSION = '3';
    const TEST_MODE_SURFIX_DELIM = '-';
    const STATUS_SUCCESS           = 'success';
    const STATUS_WAIT_COMPENSATION = 'wait_compensation';
    const STATUS_PROCESSING  = 'processing';
    const STATUS_PENDING  = 'pending';
    const STATUS_FAILURE     = 'failure';
    const STATUS_ERROR       = 'error';
    const STATUS_WAIT_SECURE = 'wait_secure';
    const STATUS_WAIT_ACCEPT = 'wait_accept';
    const STATUS_WAIT_CARD   = 'wait_card';
    const STATUS_SANDBOX     = 'sandbox';
    const STATUS_HOLD_WAIT        = 'hold_wait';
    const INVOICE_STATE      = [self::STATUS_HOLD_WAIT => 1, self::STATUS_SUCCESS => 2, self::STATUS_ERROR => 3, self::STATUS_FAILURE=> 3];
    const INVOICE_STATE_HOLD_WAIT      = 1;
    const INVOICE_STATE_HOLD_PAID      = 2;
    const INVOICE_STATE_HOLD_ERROR     = 3;
    const STANDART_LIQPAY_ACTION = 'pay';
    const HOLD_LIQPAY_ACTION = 'hold';

    protected $_liqPayConfig;

    public function __construct(
        LiqPayConfig $liqPayConfig
    )
    {
        $this->_liqPayConfig = $liqPayConfig;
        if ($liqPayConfig->isEnabled()) {
            $publicKey = $liqPayConfig->getPublicKey();
            $privateKey = $liqPayConfig->getPrivateKey();
            parent::__construct($publicKey, $privateKey);
        }
    }

    protected function prepareParams($params)
    {
        if (!isset($params['sandbox'])) {
            $params['sandbox'] = (int)$this->_liqPayConfig->isTestMode();
        }
        if (!isset($params['version'])) {
            $params['version'] = static::VERSION;
        }
        if (isset($params['order_id']) && $this->_liqPayConfig->isTestMode()) {
            $surfix = $this->_liqPayConfig->getTestOrderSurfix();
            if (!empty($surfix)) {
                $params['order_id'] .= self::TEST_MODE_SURFIX_DELIM . $surfix;
            }
        }
        $params['paytypes']='card';
        return $params;
    }

    public function getHelper()
    {
        return $this->_liqPayConfig;
    }

    public function getSupportedCurrencies()
    {
        return $this->_supportedCurrencies;
    }

    public function api($path, $params = array(), $timeout = 5)
    {
        $params = $this->prepareParams($params);
        return parent::api($path, $params, $timeout);
    }

    public function cnb_form($params)
    {
        $params = $this->prepareParams($params);
        return parent::cnb_form($params);
    }

    public function getDecodedData($data)
    {
        return json_decode(base64_decode($data), true, 1024);
    }

    public function checkSignature($signature, $data)
    {
        $privateKey = $this->_liqPayConfig->getPrivateKey();
        $generatedSignature = base64_encode(sha1($privateKey . $data . $privateKey, 1));
        return $signature == $generatedSignature;
    }

    public function holdConfirm(\Magento\Sales\Model\Order $order)
    {
        $result = null;
        if ($order && $order->getId()) {
            $result = $this->api('request',
                [
                    'action'        => $this->_liqPayConfig->getHoldAction(),
                    'version'       => $this->_liqPayConfig->getVersion(),
                    'amount'        => $order->getGrandTotal(),
                    'order_id'      => $this->_liqPayConfig->getBankOrderId($order->getIncrementId())
                ]);
        }
        return $result;
    }
}