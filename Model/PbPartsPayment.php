<?php


namespace CodeCustom\Payments\Model;

use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Payment\Model\Method\AbstractMethod;

class PbPartsPayment extends AbstractMethod
{
    const METHOD_CODE = 'parts_payment';
    const SHIPPING_METHOD = 'freeshipping_freeshipping';
    const ATTRIBUTE_TERM_CODE = 'pp_term';

    protected $_code = self::METHOD_CODE;

    protected $_liqPay;

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
    protected $_supportedCurrencyCodes;
    protected $configHelper;

    /**
     * @var \Magento\Framework\UrlInterface
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

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\UrlInterface $urlBuider,
        \CodeCustom\Payments\Sdk\LiqPay $liqPay,
        PrivatBankConfig $configHelper,
        CheckoutSession $checkoutSession,
        ProductFactory $productModel,
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

        $this->configHelper = $configHelper;
        $this->_liqPay = $liqPay;
        $this->_supportedCurrencyCodes = $liqPay->getSupportedCurrencies();
        $this->_minOrderTotal = $this->getConfigData('min_order_total');
        $this->_urlBuilder = $urlBuider;
    }

    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $billing = $order->getBillingAddress();
        try {
            $payment->setTransactionId('liqpay-' . $order->getId())->setIsTransactionClosed(0);
            return $this;
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->configHelper->isActive()) {
            return false;
        }
        $this->_minOrderTotal = $this->getConfigData('min_order_total');
        if ($quote && $quote->getBaseGrandTotal() < $this->_minOrderTotal) {
            return false;
        }
        if($this->_validateShippingMethod($quote)) {
            return false;
        }
        if(!$this->validateProductPrices($quote)) {
            return false;
        }
        if(!$this->hasPartPaymentItems($quote)) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    /** Validate product prices & product prices summary
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return bool
     */
    public function validateProductPrices($quote = null)
    {
        if (is_null($quote)) {
            $quote = $this->_checkoutSession->getQuote();
        }
        if($quote) {
            $priceLimit = $this->configHelper->getMinProductPrice();
            $items = $quote->getItems();
            if (is_null($items)) {
                $items = $quote->getAllVisibleItems();
            }
            if($items) {
                $sum = 0;
                foreach ($items as $item) {
                    $product = $this->_productModel->create()->load($item->getProductId());
                    if(1 || $product->getData(self::ATTRIBUTE_TERM_CODE)) {
                        $sum += ((int)$product->getFinalPrice()) * $item->getQty();
                    }
                }
                if($sum >= $priceLimit) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return bool
     */
    public function hasPartPaymentItems($quote = null)
    {
        if (is_null($quote)) {
            $quote = $this->_checkoutSession->getQuote();
        }
        if($quote) {
            $items = $quote->getItems();
            if (is_null($items)) {
                $items = $quote->getAllVisibleItems();
            }
            if($items && !empty($items)) {
                $result = false;
                $nonTermed = false;
                foreach($items as $item) {
                    $product = $this->_productModel->create()->load($item->getProductId());
                    if($product->getData(self::ATTRIBUTE_TERM_CODE)) {
                        $result = true;
                    }else {
                        $nonTermed = true;
                    }
                    $options = $item->getOptions();
                    if(is_array($options) && !empty($options)) {
                        foreach ($options as $option) {
                            if($option->getCode() === self::METHOD_CODE) {
                                $result = true;
                            }
                        }
                        if(!$result) {
                            $nonTermed = true;
                        }
                    }
                }
                //$result = $nonTermed ? false : $result;
                return $result;
            }
        }
        return false;
    }

    /**
     * @param $quote
     * @return bool
     */
    public function _validateShippingMethod($quote)
    {
        if($quote) {
            $address = $quote->getShippingAddress();
            if($address) {
                return $address->getShippingMethod() === self::SHIPPING_METHOD;
            }
        }
        return false;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return bool
     */
    public function isAllItemsAvailable($quote = null)
    {
        if (is_null($quote)) {
            $quote = $this->_checkoutSession->getQuote();
        }
        if($quote) {
            $items = $quote->getItems();
            if (is_null($items)) {
                $items = $quote->getAllVisibleItems();
            }
            if($items && !empty($items)) {
                foreach($items as $item) {
                    $product = $this->_productModel->create()->load($item->getProductId());
                    if(!$product->getData('max_credit_term')) {
                        return false;
                    }

                }
                return true;
            }
        }
        return false;
    }
}