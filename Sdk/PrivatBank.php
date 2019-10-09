<?php

namespace CodeCustom\Payments\Sdk;

use CodeCustom\Payments\Model\PbPartsPayment;
use CodeCustom\Payments\Sdk\Core\PrivatBankSdk;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use \Magento\Quote\Model\Quote;
use CodeCustom\Payments\Model\CallBack\PrivatBankCallBack\Worker;

class PrivatBank extends PrivatBankSdk
{
    const VERSION = '3';
    const TEST_MODE_SURFIX_DELIM = '-';

    const LOGGER_DIRECTORY_PARTS_PAYMENT_CHECKOUT = 'parts_payment';

    const STATUS_SUCCESS            = 'SUCCESS';
    const STATUS_CANCELED           = 'CANCELED';
    const STATUS_CREATED            = 'CREATED';
    const STATUS_FAIL               = 'FAIL';
    const STATUS_CLIENT_WAIT        = 'CLIENT_WAIT';
    const STATUS_OTP_WAITING        = 'OTP_WAITING';
    const STATUS_PP_CREATION        = 'PP_CREATION';
    const STATUS_LOCKED             = 'LOCKED';
    const STATUS_SANDBOX            = 'sandbox';
    const STATUS_PENDING            = 'pending';

    const STANDART_PB_ACTION        = 'pay';
    const HOLD_PB_ACTION            = 'hold';

    const INVOICE_STATE_HOLD_WAIT      = 1;
    const INVOICE_STATE_HOLD_PAID      = 2;
    const INVOICE_STATE_HOLD_ERROR     = 3;

    protected $_helper;

    protected $_quote;

    protected $worker;

    public function __construct(
        PrivatBankConfig $helper,
        Quote $quote,
        Worker $worker
    )
    {
        $this->_helper = $helper;
        $this->_quote = $quote;
        $this->worker = $worker;
    }

    /**
     * @return \Perspective\ PartsPayment\Helper\Config
     */
    public function getHelper()
    {
        return $this->_helper;
    }

    /**
     * @return array
     */
    public function getSupportedCurrencies()
    {
        return $this->_supportedCurrencies;
    }

    public function getCheckoutUrl($paymentCode)
    {
        if (!$paymentCode) {
            throw new \Exception('No payment method');
        }

        return $this->_helper->getPaymentURL($paymentCode);
    }


    /**
     * @param $postData
     * @param $url
     * @return array
     */
    protected function sendPost($postData, $url, $paymentCode)
    {
        $curl = curl_init();

        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "cache-control: no-cache"
            ),
        );

        if (!$this->_helper->isDevModeEnabled($paymentCode)) {
            $curlOptions[CURLOPT_PROXY] = "proxy_url";
            $curlOptions[CURLOPT_PROXYPORT] = 3128;
            $curlOptions[CURLOPT_HTTPPROXYTUNNEL] = 1;
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        return ['response' => $response, 'err' => $err];
    }


    /**
     * @param $order
     * @param $partsCount
     * @param $url
     * @param null $overideHelper
     * @return array
     */
    public function getPartsPaymentToken($order, $partsCount, $url)
    {
        $paymentCode = $order->getPayment()->getMethod();
        $loggerHelper = $this->getObjectManager('\CodeCustom\Payments\Helper\Logger');
        $logger = $loggerHelper->create('pp', self::LOGGER_DIRECTORY_PARTS_PAYMENT_CHECKOUT);
        $postData = $this->getPostData($order, $partsCount);

        $logger->info('Try to send cURL to ' . $this->_checkout_url . ' with params:');
        $this->logPostData($logger, $postData);
        $logger->info('Now we send cURL');

        $curlResult = $this->sendPost($postData, $this->_checkout_url, $paymentCode);

        $response = $curlResult['response'];
        $err = $curlResult['err'];

        if ($err) {
            $logger->info('response parts payment to order: ' .
                $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode) .
                ' returning with error: ' . $err);
            $result = [
                'status' => 'error',
                'redirect' => $url->getUrl($this->_checkout_fail_redirect_url)
            ];

            if ($response) {
                $logger->info('response parts payment to order: ' .
                    $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode) .
                    ' returning with response: ' . $response);
            }else{
                $logger->info('response parts payment to order: ' .
                    $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode) .
                    ' returning with no response');
            }

        } else {
            try {
                $xmlToArr = new \SimpleXMLElement($response);
            }catch (\Exception $e) {
                $xmlToArr = (object)['state' => 'ERROR'];
            }

            if ($xmlToArr->state == 'SUCCESS' && isset($xmlToArr->token)) {

                foreach ($xmlToArr as $key => $value) {
                    $logger->info('response parts payment to order: ' .
                        $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode) .
                        ' returning with response key: ' . $key . ' and value: ' .$value );
                }
                $result = [
                    'status' => 'success',
                    'redirect' => $this->_checkout_redirect_url . $xmlToArr->token
                ];
            } else {
                $logger->info('response parts payment to order: ' .
                    $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode) .
                    ' returning with response: ' . $response);
                if (isset($xmlToArr->state) && $xmlToArr->state) {
                    foreach ($xmlToArr as $key => $value) {
                        $logger->info('response parts payment to order: ' .
                            $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode) .
                            ' returning with response key: ' . $key . ' and value: ' .$value );
                    }
                }
                $result = [
                    'status' => 'error',
                    'redirect' => $url->getUrl($this->_checkout_fail_redirect_url)
                ];
            }
        }

        return $result;
    }

    /**
     * @param $logger
     * @param array $postData
     * @return bool
     */
    protected function logPostData($logger, $postData = [])
    {
        if (!empty($postData)) {
            foreach ($postData as $pKey => $pValue) {
                if (is_array($pValue)) {
                    $logger->info('add array ' . $pKey);
                    $this->logPostData($logger, $pValue);
                }else{
                    $logger->info('POST key: ' . $pKey . ', value: ' . $pValue);
                }
            }
        }
        return true;
    }

    /**
     * @param $order
     * @param $partsCount
     * @return array
     */
    protected function getPostData($order, $partsCount)
    {
        $paymentCode = $order->getPayment()->getMethod();
        $result = [];
        $result['storeId'] = $this->_helper->getStoreId($paymentCode);
        $result['orderId'] = $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode);
        $result['amount'] = $order->getGrandTotal();
        $result['partsCount'] = $partsCount;
        $result['merchantType'] = $this->_helper->getMerchantType($paymentCode);
        $result['products'] = $this->getProducts($order);
        $result['responseUrl'] = $this->_helper->getResponseUrl($paymentCode);
        $result['redirectUrl'] = $this->_helper->getRedirectUrl($paymentCode);
        $result['signature'] = $this->getPartPaymentsSignature($order, $result['amount'], $result['partsCount']);

        return $result;
    }

    /**
     * @param $order
     * @param int $amount
     * @param int $partsCount
     * @return string
     */
    protected function getPartPaymentsSignature($order, $amount = 0, $partsCount = 0)
    {
        $paymentCode = $order->getPayment()->getMethod();
        $signature = [
            $this->_helper->getStorePassword($paymentCode),
            $this->_helper->getStoreId($paymentCode),
            $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode),
            (string)($amount * 100),
            $partsCount,
            $this->_helper->getMerchantType($paymentCode),
            $this->_helper->getResponseUrl($paymentCode),
            $this->_helper->getRedirectUrl($paymentCode),
            $this->getProductsLine($order),
            $this->_helper->getStorePassword($paymentCode)
        ];

        return $this->calcSignature($signature);
    }

    /**
     * @param $array
     * @return string
     */
    private function calcSignature($array)
    {
        $signature = '';
        foreach ($array as $item) {
            $signature .= $item;
        }
        return base64_encode(sha1($signature, true));
    }

    /**
     * @param $order
     * @return string
     */
    protected function getProductsLine($order)
    {
        $productstring = '';
        if ($order->getItems()) {
            $shippingLine = $this->getShipmentLine($order);
            if ($shippingLine) {
                $productstring .= $shippingLine;
            }
            foreach ($order->getItems() as $item) {
                $productstring .= (string)$item->getSku();
                $productstring .= (int)$item->getQtyOrdered();
                $productstring .= $this->calcDiscount($item, $order) * 100; //$item->getPrice() * 100;
            }
        }

        return $productstring;
    }

    /**
     * @param $order
     * @return array
     */
    protected function getProducts($order)
    {
        $result = [];
        if ($order->getItems()) {
            $shippingBlock = $this->getShipmentAmountBlock($order);
            if ($shippingBlock) {
                $result[] = $shippingBlock;
            }
            foreach ($order->getItems() as $item) {
                $result [] = [
                    'name' => (string)$item->getSku(),
                    'count' => (int)$item->getQtyOrdered(),
                    'price' => (int)$this->calcDiscount($item, $order) //(int)$item->getPrice()
                ];
            }
        }
        return $result;
    }

    /**
     * @param $order
     * @return array|bool
     */
    protected function getShipmentAmountBlock($order)
    {
        $price = (int)$order->getData('shipping_amount') ? (int)$order->getData('shipping_amount') : '';
        if ($price) {
            return ['name' => 'shipping', 'count' => 1, 'price' => $price];
        }
        return false;
    }

    /**
     * @param $order
     * @return bool|string
     */
    protected function getShipmentLine($order)
    {
        $price = (int)$order->getData('shipping_amount') ? (int)$order->getData('shipping_amount') : '';
        if ($price) {
            return 'shipping1'.$price * 100;
        }
        return false;
    }

    /**
     * @param $order
     * @param null $overideHelper
     * @return mixed|\SimpleXMLElement
     */

    public function confirmPayment($order)
    {
        $paymentCode = $order->getPayment()->getMethod();
        $ordeID = $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode);
        $postData = [
            'storeId' => $this->_helper->getStoreId($paymentCode),
            'orderId' => $ordeID,
            'signature' => $this->_helper->getConfirmSignature($ordeID, $paymentCode)

        ];

        $curlResult = $this->sendPost($postData, $this->_confirm_url, $paymentCode);
        $response = $curlResult['response'];
        $err = $curlResult['err'];

        if ($err) {
            $result = $response;
        } else {
            try {
                $jsonDecode = json_decode($response);
                $result = $jsonDecode;
            } catch (\Exception $e) {
                $result = ['error' => $e->getMessage()];
            }
            if(!$result) {
                try {
                    $result = new \SimpleXMLElement($response);
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
            }
        }

        return $result;
    }

    /**
     * @param $order
     * @param null $overideHelper
     * @return mixed|\SimpleXMLElement
     */
    public function getPaymentStatus($order)
    {
        $paymentCode = $order->getPayment()->getMethod();
        $ordeID = $this->_helper->getPaymentOrderId($order->getIncrementId(), $paymentCode);
        $postData = [
            'storeId' => $this->_helper->getStoreId($paymentCode),
            'orderId' => $ordeID,
            'showRefund' => 'true',
            'signature' =>  $this->_helper->getConfirmSignature($ordeID, $paymentCode)
        ];
        $curlResult = $this->sendPost($postData, $this->_check_status_url, $paymentCode);
        $response = $curlResult['response'];
        $err = $curlResult['err'];

        if ($err) {
            $result = $response;
        } else {
            $jsonDecode = json_decode($response);
            $result = $jsonDecode;
            if(!$result) {
                $result = new \SimpleXMLElement($response);
            }
        }

        return $result;
    }

    /**
     * @param $class
     * @return mixed
     */
    private function getObjectManager($class)
    {
        $_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        return $_objectManager->create($class);
    }

    /**
     * @param $item
     * @param $order
     * @return float|int
     */
    public function getFreeGiftPrice($item, $order)
    {
        $finalPrice = $item->getPrice() - $item->getDiscountAmount() / $item->getQtyOrdered();
        $quote_id = $order->getQuoteId();
        $quote = $this->_quote->loadByIdWithoutStore($quote_id);
        $quote_item_id = $item->getQuoteItemId();

        if($quote_item_id && $quote->getItemById($quote_item_id)){
            $quote_product=$quote->getItemById($item->getQuoteItemId())->getProduct();
        }

        if(isset($quote_product)&& !empty($quote_product->getCustomOption('has_freegift')) && $quote_product->getCustomOption('has_freegift')->getData('value')>0){
            $freegift_key = $quote_product->getCustomOption('freegift_key')->getData('value');
            $gift_price=0;

            foreach ($quote->getItemsCollection()->getItems() as $quote_item){
                if(
                    isset($quote_item->getOptionsByCode('product_type')['product_type'])&&
                    isset($quote_item->getOptionsByCode('product_type')['freegift_key'])&&
                    $quote_item->getOptionsByCode('product_type')['product_type']->getData('value') =='freegift'&&
                    $quote_item->getOptionsByCode('freegift_key')['freegift_key']->getData('value')==$freegift_key){
                    $gift_price+=$quote_item->getProduct()->getPrice();
                }
            }
            return ($finalPrice - $gift_price);
        }else{
            return $finalPrice;
        }
    }

    /**
     * @param $item
     * @param $order
     * @return float|int
     */
    public function calcDiscount($item, $order){
        if($item->getProductType()=="freegift"){
            $finalPrice = $item->getBaseOriginalPrice();
        }else{
            $finalPrice = $this->getFreeGiftPrice($item, $order);
        }
        return $finalPrice;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function holdConfirm(\Magento\Sales\Model\Order $order)
    {
        $checkPayment = $this->getPaymentStatus($order);
        if ($checkPayment->paymentState == PrivatBank::STATUS_SUCCESS || $checkPayment->paymentState == PrivatBank::STATUS_CANCELED) {
            return false;
        }

        $result = $this->confirmPayment($order);
        if (isset($result->state) && $result->state == PrivatBank::STATUS_SUCCESS) {
            $this->worker->saveInvoice($order,
                $order->getIncrementId(),
                PrivatBank::INVOICE_STATE_HOLD_PAID);
            return true;
        }
        return false;
    }
}