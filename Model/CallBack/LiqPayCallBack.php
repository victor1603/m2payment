<?php

namespace CodeCustom\Payments\Model\CallBack;

use CodeCustom\Payments\Api\CallBack\LiqPayCallbackInterface;
use Magento\Sales\Model\Order;
use CodeCustom\Payments\Sdk\LiqPay;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use CodeCustom\Payments\Helper\Config\LiqPayConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order\Invoice;
use CodeCustom\Payments\Model\CallBack\LipPayCallback\Worker;

class LiqPayCallBack implements LiqPayCallbackInterface
{

    protected $_order;

    protected $_orderRepository;

    protected $_invoiceService;

    protected $_transaction;

    protected $_liqpayConfig;

    protected $_liqpaySdk;

    protected $_request;

    protected $_invoice;

    protected $_worker;

    public function __construct(
        Order $order,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        LiqPayConfig $helper,
        LiqPay $liqPay,
        RequestInterface $request,
        Invoice $invoice,
        Worker $worker
    )
    {
        $this->_order = $order;
        $this->_liqpaySdk = $liqPay;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_liqpayConfig = $helper;
        $this->_request = $request;
        $this->_invoice = $invoice;
        $this->_worker = $worker;
    }

    public function callback()
    {
        $post = $this->_request->getParams();
        if (!(isset($post['data']) && isset($post['signature']))) {
            $this->_liqpayConfig->getLogger()->error(__('In the response from LiqPay server there are no POST parameters "data" and "signature"'));
            return null;
        }
        $data = $post['data'];
        $receivedSignature = $post['signature'];
        $decodedData = $this->_liqpaySdk->getDecodedData($data);
        $orderId = $decodedData['order_id'] ?? null;
        $receivedPublicKey = $decodedData['public_key'] ?? null;
        $status = $decodedData['status'] ?? null;

        try {
            $order = $this->getOrder($status, $orderId);
            if (!($order && $order->getId() && $this->_liqpayConfig->checkOrderIsLiqPayPayment($order))) {
                return null;
            }

            if (!$this->_liqpayConfig->securityOrderCheck($data, $receivedPublicKey, $receivedSignature)) {
                $order->addStatusHistoryComment(__('LiqPay security check failed!'));
                $this->_orderRepository->save($order);
                return null;
            }
            /**
             * execute the worker class
             */
            $this->_worker->execute($order, $decodedData);

        } catch (\Exception $e) {
            $this->_liqpayConfig->getLogger()->critical($e);
        }

        return null;
    }

    protected function getOrder($status, $orderId)
    {
        if ($status == LiqPay::STATUS_SANDBOX) {
            $testOrderSurfix = $this->_liqpayConfig->getTestOrderSurfix();
            if (!empty($testOrderSurfix)) {
                $testOrderSurfix = LiqPay::TEST_MODE_SURFIX_DELIM . $testOrderSurfix;
                if (strlen($testOrderSurfix) < strlen($orderId)
                    && substr($orderId, -strlen($testOrderSurfix)) == $testOrderSurfix
                ) {
                    $orderId = substr($orderId, 0, strlen($orderId) - strlen($testOrderSurfix));
                }
            }
        }
        return $this->_order->loadByIncrementId($orderId);
    }
}