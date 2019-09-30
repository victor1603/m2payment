<?php

namespace CodeCustom\Payments\Model\CallBack;

use CodeCustom\Payments\Api\CallBack\PrivatBankCallBackInterface;
use Magento\Sales\Model\Order;
use CodeCustom\Payments\Sdk\PrivatBank;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig as Helper;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order\Invoice;
use CodeCustom\Payments\Helper\Logger;
use CodeCustom\Payments\Model\PbPartsPayment as PartsPaymentModel;
use CodeCustom\Payments\Model\PbInstantInstallment as InstantPaymentModel;
use CodeCustom\Payments\Model\CallBack\PrivatBankCallBack\Worker;

class PrivatBankCallBack implements PrivatBankCallBackInterface
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var PartsPayment
     */
    protected $_privatBabk;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * @var Logger
     */
    protected $loggerHelper;

    protected $_worker;


    public function __construct(
        Order $order,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        Helper $helper,
        PrivatBank $privatBank,
        RequestInterface $request,
        Invoice $invoice,
        Logger $loggerHelper,
        Worker $_worker
    )
    {
        $this->_order = $order;
        $this->_privatBabk = $privatBank;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_helper = $helper;
        $this->_request = $request;
        $this->invoice = $invoice;
        $this->loggerHelper = $loggerHelper;
        $this->_worker = $_worker;
    }

    public function callback()
    {
        if ($this->_request->getContent()) {
            $json = $this->_request->getContent();
            $jsonDecode = json_decode($json);

            $decodeData = $jsonDecode;
            if(!$decodeData) {
                $decodeData = new \SimpleXMLElement($json);
            }

            try {
                $storeId = isset($decodeData->storeIdentifier) ? $decodeData->storeIdentifier : null;
                $orderId = isset($decodeData->orderId) ? $decodeData->orderId : null;
                $paymentState = isset($decodeData->paymentState) ? $decodeData->paymentState : null;
                $message = isset($decodeData->message) ? $decodeData->message : null;
                $signature = isset($decodeData->signature) ? $decodeData->signature : null;

                $orderIdSandBox = $orderId ? $orderId : null;
                $order = $this->getOrderCallbackPaymentType($orderId);

                $paymentMethod = null;
                if ($order && $order->getId()) {
                    $paymentMethod = $order->getPayment()->getMethod();
                }

                if (!$this->_helper->checkCallbackSignature((string)$signature, (string)$orderIdSandBox, (string)$paymentState, (string)$message)) {
                    $order->addStatusHistoryComment(__('Parts payment security check failed!'));
                    $this->_orderRepository->save($order);
                    return null;
                }

                $historyMessage = [];
                $state = null;
                $invoice = [];
                $logger = $this->loggerHelper->create('callback_parts_payment', 'test');
                if ($decodeData) {
                    $logger->info('Decoded data in Callback: ');
                    foreach ($decodeData as $k => $v) {
                        $logger->info('key: ' . $k . ' value: ' . $v);
                    }
                }
            } catch (\Exception $e){

            }
        }
    }

    /**
     * check PartsPayment first if order doesnt to exist, check instant_installment
     * @param null $orderId
     * @return bool|void
     */

    protected function getOrderCallbackPaymentType($orderId = null)
    {
        $result = false;
        if ($orderId) {
            if ($this->_helper->isSandBox(PartsPaymentModel::METHOD_CODE)) {
                $orderId = str_replace($this->_helper->getOrderPrefix(PartsPaymentModel::METHOD_CODE), '', $orderId);
            }
            $order = $this->getRealOrder($orderId);
            if ($order && $order->getId()) {
                $result = $order;
            } else {
                if ($this->_helper->isSandBox(InstantPaymentModel::METHOD_CODE)) {
                    $orderId = str_replace($this->_helper->getOrderPrefix(InstantPaymentModel::METHOD_CODE), '', $orderId);
                }
                $order = $this->getRealOrder($orderId);
                if ($order && $order->getId()) {
                    $result = $order;
                } else {
                    $result = false;
                }
            }
        }
        return $result;
    }

    protected function getRealOrder($orderId)
    {
        return $this->_order->loadByIncrementId($orderId);
    }
}