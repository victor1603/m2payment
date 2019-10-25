<?php

namespace CodeCustom\Payments\Model;

use CodeCustom\Payments\Sdk\PrivatBank;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validator\Exception;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use \Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use CodeCustom\Payments\Helper\PbInstantInstallment\Validate;

class PbInstantInstallment extends AbstractMethod
{
    const METHOD_CODE = 'instant_installment';
    const ATTRIBUTE_TERM_CODE = 'ii_term';

    protected $_sdk;
    protected $_code = self::METHOD_CODE;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseForMultishipping = false;
    protected $_canUseInternal = true;
    protected $_isInitializeNeeded = true;
    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canUseCheckout = true;
    protected $_minOrderTotal = 0;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     * @var ProductFactory
     */
    protected $_productModel;

    /**
     * @var array
     */
    protected $_supportedCurrencyCodes;

    /** Validate helper
     * @var Validate
     */
    protected $_validateHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuider,
        PrivatBank $privatBank,
        PrivatBankConfig $configHelper,
        CheckoutSession $checkoutSession,
        ProductFactory $productModel,
        Validate $_validateHelper,
        array $data = array()
    )
    {
        $this->_productModel = $productModel;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );
        $this->_validateHelper = $_validateHelper;
        $this->configHelper = $configHelper;
        $this->_sdk = $privatBank;
        $this->_supportedCurrencyCodes = $privatBank->getSupportedCurrencies();
        $this->_minOrderTotal = $this->getConfigData('min_order_total');
        $this->_urlBuilder = $urlBuider;
    }

    /**
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|AbstractMethod
     * @throws Exception
     */
    public function capture(InfoInterface $payment, $amount)
    {
        /** @var Order $order */
        $order = $payment->getOrder();
        try {
            $payment->setTransactionId('liqpay-' . $order->getId())->setIsTransactionClosed(0);
            return $this;
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            throw new Exception(__('Payment capturing error.'));
        }
    }

    /** Is method available
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if (!$this->configHelper->isActive(self::METHOD_CODE)) {
            return false;
        }
        $this->_minOrderTotal = $this->getConfigData('min_order_total');
        if ($quote && $quote->getBaseGrandTotal() < $this->_minOrderTotal) {
            return false;
        }
        if($quote && !$this->_validateHelper->validateQuoteItems($quote)) {
            return false;
        }
        if(!$quote && !$this->_validateHelper->validateQuoteItems()) {
            return false;
        }
        if($quote && !$this->_validateShipping($quote)) {
            return false;
        }
        if($quote && !$this->_validateGrandTotal($quote)) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    /** Grand total comparison with min price limit
     * @param $quote
     * @return bool
     */
    public function _validateGrandTotal($quote)
    {
        $grandTotal = (int)$quote->getGrandTotal();
        $minPrice = (int)$this->configHelper->getMinProductPrice(self::METHOD_CODE);
        return $grandTotal >= $minPrice;
    }

    /** Validate shipping methods
     * @param $quote
     * @return bool
     */
    public function _validateShipping($quote)
    {
        if($quote) {
            $address = $quote->getShippingAddress();
            if($address) {
                $method = $address->getShippingMethod();
                $availableMethods = $this->configHelper->getActiveMethods(self::METHOD_CODE);
                if(is_array($availableMethods) && !empty($availableMethods)) {
                    return in_array($method, $availableMethods);
                }
            }
        }
        return false;
    }
}