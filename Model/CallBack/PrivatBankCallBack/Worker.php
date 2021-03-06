<?php

namespace CodeCustom\Payments\Model\CallBack\PrivatBankCallBack;

use \Magento\Sales\Model\Order;
use \CodeCustom\Payments\Sdk\PrivatBank;
use \Magento\Sales\Model\Service\InvoiceService;
use \Magento\Framework\DB\Transaction;
use \Magento\Sales\Model\Order\Invoice;
use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class Worker
{
    protected $_invoiceService;

    protected $_transaction;

    protected $_invoice;

    protected $_orderRepository;

    protected $_transactionBuilder;

    public $history = null;

    public function __construct(
        InvoiceService $_invoiceService,
        Transaction $_transaction,
        Invoice $_invoice,
        OrderRepositoryInterface $_orderRepository,
        BuilderInterface $_transactionBuilder
    )
    {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction = $_transaction;
        $this->_invoice = $_invoice;
        $this->_orderRepository = $_orderRepository;
        $this->_transactionBuilder = $_transactionBuilder;
    }

    public function execute(Order $order, $decodedData = array())
    {
        $status = isset($decodedData['paymentState']) ? $decodedData['paymentState'] : null;
        $transactionId = $order->getIncrementId();
        $state = $order->getState();
        $invoice = false;
        $this->history[] = __("Callback from PB, order: %1 status: %2", $order->getIncrementId(), $status);
        switch ($status) {
            case PrivatBank::STATUS_FAIL:
                $this->saveInvoice($order, $transactionId, PrivatBank::INVOICE_STATE_HOLD_ERROR);
                $state = \Magento\Sales\Model\Order::STATE_CANCELED;
                $this->history[] = __("Error payment");
                break;
            case PrivatBank::STATUS_CANCELED:
                $this->saveInvoice($order, $transactionId, PrivatBank::INVOICE_STATE_HOLD_ERROR);
                $state = \Magento\Sales\Model\Order::STATE_CANCELED;
                $this->history[] = __("Error payment");
                break;
            case PrivatBank::STATUS_SUCCESS:
                $invoice = $this->saveInvoice($order, $transactionId, PrivatBank::INVOICE_STATE_HOLD_PAID);
                $this->history[] = __("Payment completed successfully");
                $state = null;
                break;
            case PrivatBank::STATUS_CLIENT_WAIT:
                $this->history[] = __("Waiting for customer payment");
                break;
            case PrivatBank::STATUS_LOCKED:
                $invoice = $this->saveInvoice($order, $transactionId, PrivatBank::INVOICE_STATE_HOLD_WAIT);
                $this->history[] = __("The payment has been confirmed by the customer and is awaiting confirmation by the store.");
                break;
            case PrivatBank::STATUS_CREATED:
                break;
            case PrivatBank::STATUS_OTP_WAITING:
                break;
            case PrivatBank::STATUS_PP_CREATION:
                break;
            default:
                break;
        }
        if ($invoice) {
            $this->createTransaction($order,
                [
                    'id' => $transactionId,
                    'order_id' => $order->getIncrementId()
                ]
            );
        }
        $this->saveOrder($state, $order);
    }

    /**
     * @param Order $order
     * @param $transactionId
     * @param $invoiceState
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function saveInvoice(Order $order, $transactionId, $invoiceState)
    {
        $this->history[] = __("Creating invoice with transaction ID: %1 and state: %2", $transactionId, $invoiceState);
        if ($order->canInvoice()) {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->register()->pay();
            $invoice->setState($invoiceState);
            if ($transactionId && !is_null($transactionId)) {
                $invoice->setTransactionId($transactionId);
            }
            $transactionSave = $this->_transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder()
                );
            $transactionSave->save();
        } else {
            $invoiceCollection = $order->getInvoiceCollection();
            if (isset($invoiceCollection->getData()[0])) {
                $invoice = $invoiceCollection->getData()[0];
            }
            if (isset($invoice['increment_id'])) {
                $invoiceData = $this->_invoice->loadByIncrementId($invoice['increment_id']);
                $invoiceData->setState($invoiceState);
                if ($transactionId && !is_null($transactionId)) {
                    $invoiceData->setTransactionId($transactionId);
                }
                $invoiceData->save();
            }
        }

        return true;
    }

    /**
     * @param Order|null $order
     * @param array $paymentData
     * @return bool
     * @throws \Exception
     */
    public function createTransaction(Order $order = null, $paymentData = array())
    {
        try {
            $this->history[] = __("Creating transaction with ID: %1", $paymentData['id']);
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentData['id']);
            $payment->setTransactionId($paymentData['id']);
            $payment->setMethod($order->getPayment()->getMethod());
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
            );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['id'])
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
                )
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return  $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->history[] = __("Error transaction with ID: %1 not created", $paymentData['id']);
        }

        return true;
    }

    /**
     * @param $state
     * @param Order $order
     * @param array $history
     * @throws \Exception
     */
    public function saveOrder($state, Order $order, $history = [])
    {
        if ($this->history) {
            $history += $this->history;
        }

        if (count($history)) {
            $order->addStatusHistoryComment(implode(' ', $history))
                ->setIsCustomerNotified(true);
        }

        if ($state) {
            $order->setState($state);
            $order->setStatus($state);
            $order->save();
        }
        $this->_orderRepository->save($order);
    }

}