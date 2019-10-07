<?php

namespace CodeCustom\Payments\Helper\Config;

use CodeCustom\Payments\Sdk\PrivatBank;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class PrivatBankConfig extends AbstractHelper
{
    const CONFIG_FIELDS_PP = [
        'XML_PATH_ACTIVE' => 'payment/parts_payment/active',
        'XML_PATH_TITLE' => 'payment/parts_payment/title',
        'XML_PATH_CHECKOUT_ERROR' => 'payment/parts_payment/checkout_error',
        'XML_PATH_MIN_PRICE' => 'payment/parts_payment/min_price',
        'XML_PATH_STORE_ID' => 'payment/parts_payment/store_id',
        'XML_PATH_STORE_PASSWORD' => 'payment/parts_payment/store_password',
        'XML_PATH_SCHEME' => 'payment/parts_payment/scheme',
        'XML_PATH_RECIPIENT_ID' => 'payment/parts_payment/recipient_id',
        'XML_PATH_MERCHANT_TYPE' => 'payment/parts_payment/merchant_type',
        'XML_PATH_SAND_BOX' => 'payment/parts_payment/sand_box',
        'XML_PATH_SAND_BOX_STORE_ID' => 'payment/parts_payment/sand_box_store_id',
        'XML_PATH_SAND_BOX_STORE_PASSWORD' => 'payment/parts_payment/sand_box_store_password',
        'XML_PATH_SAND_BOX_ORDER_PREFIX' => 'payment/parts_payment/sand_box_prefix',
        'XML_PATH_RESPONSE_URL' => 'payment/parts_payment/response_url',
        'XML_PATH_REDIRECT_URL' => 'payment/parts_payment/redirect_url',
        'XML_PATH_DEV_MODE' => 'payment/parts_payment/dev_mode',
        'XML_PATH_SHIPP' => 'payment/parts_payment/active_shipping',
        'XML_PATH_PAYMENT_TYPE' => 'payment/parts_payment/payment_type',
        'XML_PATH_CUSTOM_API_URL' => 'codecustom/parts_payment/api_url',
        'XML_PATH_CUSTOM_CHECKOUT_URL' => 'codecustom/parts_payment/checkout_url',
        'XML_PATH_CUSTOM_CONFIRM_URL' => 'codecustom/parts_payment/confirm_url',
        'XML_PATH_CUSTOM_CHECK_ST_URL' => 'codecustom/parts_payment/check_status_url',
        'XML_PATH_CUSTOM_HOLD_CONFIRM_STATUS' => 'codecustom/parts_payment/hold_confirm_order_status'
    ];

    const CONFIG_FIELDS_II = [
        'XML_PATH_ACTIVE' => 'payment/instant_installment/active',
        'XML_PATH_TITLE' => 'payment/instant_installment/title',
        'XML_PATH_CHECKOUT_ERROR' => 'payment/instant_installment/checkout_error',
        'XML_PATH_MIN_PRICE' => 'payment/instant_installment/min_price',
        'XML_PATH_STORE_ID' => 'payment/instant_installment/store_id',
        'XML_PATH_STORE_PASSWORD' => 'payment/instant_installment/store_password',
        'XML_PATH_SCHEME' => 'payment/instant_installment/scheme',
        'XML_PATH_RECIPIENT_ID' => 'payment/instant_installment/recipient_id',
        'XML_PATH_MERCHANT_TYPE' => 'payment/instant_installment/merchant_type',
        'XML_PATH_SAND_BOX' => 'payment/instant_installment/sand_box',
        'XML_PATH_SAND_BOX_STORE_ID' => 'payment/instant_installment/sand_box_store_id',
        'XML_PATH_SAND_BOX_STORE_PASSWORD' => 'payment/instant_installment/sand_box_store_password',
        'XML_PATH_SAND_BOX_ORDER_PREFIX' => 'payment/instant_installment/sand_box_prefix',
        'XML_PATH_RESPONSE_URL' => 'payment/instant_installment/response_url',
        'XML_PATH_REDIRECT_URL' => 'payment/instant_installment/redirect_url',
        'XML_PATH_DEV_MODE' => 'payment/instant_installment/dev_mode',
        'XML_PATH_SHIPP' => 'payment/instant_installment/active_shipping',
        'XML_PATH_PAYMENT_TYPE' => 'payment/instant_installment/payment_type',
        'XML_PATH_CUSTOM_API_URL' => 'codecustom/instant_installment/api_url',
        'XML_PATH_CUSTOM_CHECKOUT_URL' => 'codecustom/instant_installment/checkout_url',
        'XML_PATH_CUSTOM_CONFIRM_URL' => 'codecustom/instant_installment/confirm_url',
        'XML_PATH_CUSTOM_CHECK_ST_URL' => 'codecustom/instant_installment/check_status_url',
        'XML_PATH_CUSTOM_HOLD_CONFIRM_STATUS' => 'codecustom/instant_installment/hold_confirm_order_status',
    ];

    /**
     * @var array
     */
    protected $instant_installment = self::CONFIG_FIELDS_II;

    /**
     * @var array
     */
    protected $parts_payment = self::CONFIG_FIELDS_PP;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * PrivatBankConfig constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
)
    {
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    /** Getting system configuration by field path
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigValue($field, $storeId = null)
    {
        $storeId = $storeId ? $storeId : $this->getSiteStoreId();
        $ttt = $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
        return $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    /** Current store id
     * @return int|null
     */
    public function getSiteStoreId()
    {
        try {
            $storeId = $this->_storeManager->getStore()->getId();
            return $storeId;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Is enabled
     * @return mixed
     */
    public function isActive($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_ACTIVE']);
    }

    /** Config min product price
     * @return int|mixed
     */
    public function getMinProductPrice($paymentCode = 'parts_payment')
    {
        $price = $this->getConfigValue($this->$paymentCode['XML_PATH_MIN_PRICE']);
        $price = is_numeric($price) ? (int)$price : $this->_defaultMinPrice;
        return $price;
    }

    /**
     * @return array|bool
     */

    public function getActiveMethods($paymentCode = 'parts_payment')
    {
        $string = $this->getConfigValue($this->$paymentCode['XML_PATH_SHIPP']);
        if(is_string($string)) {
            $arr = explode(',', $string);
            return !empty($arr) ? $arr : false;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getStoreId($paymentCode = 'parts_payment')
    {
        if ($this->isSandBox($paymentCode)) {
            return $this->sandBoxStoreID($paymentCode);
        }
        return $this->getConfigValue($this->$paymentCode['XML_PATH_STORE_ID']);
    }

    /**
     * @return string
     */
    public function getStorePassword($paymentCode = 'parts_payment')
    {
        if ($this->isSandBox($paymentCode)) {
            return $this->sandBoxPassword($paymentCode);
        }
        return $this->getConfigValue($this->$paymentCode['XML_PATH_STORE_PASSWORD']);
    }

    /**
     * @return string
     */
    public function getScheme($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_SCHEME']);
    }

    /**
     * @return string
     */
    public function getRecipientId($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_RECIPIENT_ID']);
    }

    /**
     * @return string
     */
    public function getMerchantType($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_MERCHANT_TYPE']);
    }

    /**
     * @return string
     */
    public function isSandBox($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_SAND_BOX']);
    }

    /**
     * @return string
     */
    public function sandBoxStoreID($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_SAND_BOX_STORE_ID']);
    }

    /**
     * @return string
     */
    public function sandBoxPassword($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_SAND_BOX_STORE_PASSWORD']);
    }

    /**
     * @return string
     */
    public function getOrderPrefix($paymentCode = 'parts_payment')
    {
        return  $this->getConfigValue($this->$paymentCode['XML_PATH_SAND_BOX_ORDER_PREFIX']);
    }

    /**
     * @param $orderIncrementId
     * @return string
     */
    public function getPaymentOrderId($orderIncrementId, $paymentCode = 'parts_payment')
    {
        if ($this->isSandBox($paymentCode)) {
            return $this->getOrderPrefix($paymentCode) . $orderIncrementId;
        }
        return $orderIncrementId;
    }

    /**
     * @return string
     */
    public function getResponseUrl($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_RESPONSE_URL']);
    }

    /**
     * @return string
     */
    public function getRedirectUrl($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_REDIRECT_URL']);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    public function checkCallbackSignature($responseSignature, $orderId, $state, $message, $paymentCode = 'parts_payment')
    {
        $signature = base64_encode(sha1($this->getStorePassword($paymentCode) .
            $this->getStoreId($paymentCode) . $orderId . $state .
            $message . $this->getStorePassword($paymentCode),
            true));

        if ($signature == $responseSignature) {
            return true;
        }

        return false;
    }

    public function getConfirmSignature($orderId, $paymentCode = 'parts_payment')
    {
        return base64_encode(sha1($this->getStorePassword($paymentCode) .
            $this->getStoreId($paymentCode) .
            $orderId . $this->getStorePassword($paymentCode),
            true));
    }

    public function isDevModeEnabled($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_DEV_MODE']);
    }

    public function getPaymentType($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_PAYMENT_TYPE']);
    }

    public function getApiUrl($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_PAYMENT_TYPE']);
    }

    public function getCheckoutUrl($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_PAYMENT_TYPE']);
    }

    public function getConfirmUrl($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_PAYMENT_TYPE']);
    }

    public function getCheckStatusUrl($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_PAYMENT_TYPE']);
    }

    public function getConfirmHoldStatus($paymentCode = 'parts_payment')
    {
        return $this->getConfigValue($this->$paymentCode['XML_PATH_PAYMENT_TYPE']);
    }

    public function getApiUrlByType($paymentCode = 'parts_payment')
    {
        $resultURL = null;
        switch ($this->getPaymentType($paymentCode)) {
            case PrivatBank::STANDART_PB_ACTION:
                break;

            case PrivatBank::HOLD_PB_ACTION:
                break;
        }
        return $resultURL;
    }
}