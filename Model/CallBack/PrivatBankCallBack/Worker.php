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

        return true;
    }
}