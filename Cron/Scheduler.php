<?php

namespace CodeCustom\Payments\Cron;

use \CodeCustom\Payments\Model\ResourceModel\Order;
use CodeCustom\Payments\Model\LiqPay;
use CodeCustom\Payments\Model\PbPartsPayment;
use CodeCustom\Payments\Model\PbInstantInstallment;
use CodeCustom\Payments\Helper\Config\LiqPayConfig;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;
use CodeCustom\Payments\Sdk\LiqPay as LiqaPaySdk;
use CodeCustom\Payments\Sdk\PrivatBank as PrivatBankSdk;
use CodeCustom\Payments\Helper\Logger;

class Scheduler
{

    /**
     * @var CollectionFactory
     */
    protected $order;

    /**
     * @var LiqPayConfig
     */
    protected $liqPaySdk;

    /**
     * @var LiqPayConfig
     */
    protected $liqPayConfig;

    /**
     * @var PrivatBankConfig
     */
    protected $privatBankSdk;

    /**
     * @var PrivatBankConfig
     */
    protected $privatBankConfig;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Scheduler constructor.
     * @param CollectionFactory $order
     */
    public function __construct(
        Order $order,
        LiqaPaySdk $liqPaySdk,
        PrivatBankSdk $privatBankSdk,
        LiqPayConfig $liqPayConfig,
        PrivatBankConfig $privatBankConfig,
        Logger $logger
    )
    {
        $this->order = $order;
        $this->liqPaySdk = $liqPaySdk;
        $this->privatBankSdk = $privatBankSdk;
        $this->liqPayConfig = $liqPayConfig;
        $this->privatBankConfig = $privatBankConfig;
        $this->logger = $logger;
    }

    /**
     * @var $order \Magento\Sales\Model\Order
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function holdChecker()
    {
        $filterData = [
            LiqPay::METHOD_CODE => $this->liqPayConfig->getConfirmHoldStatus(),
            PbPartsPayment::METHOD_CODE => $this->privatBankConfig->getConfirmHoldStatus(PbPartsPayment::METHOD_CODE),
            PbInstantInstallment::METHOD_CODE => $this->privatBankConfig->getConfirmHoldStatus(PbInstantInstallment::METHOD_CODE)
        ];
        $orderCollection = $this->order->getOrdersByPaymentAndStatus($filterData);
        $logger = $this->logger->create('cron_payment_log', 'cron_check');
        $logger->info('Get product collection');
        if ($orderCollection && $orderCollection->getSize()) {
            $logger->info('Get product collection size = ' . $orderCollection->getSize());
            foreach ($orderCollection as $order) {
                if ($order && $order->getId()) {
                    $logger->info('Working with order: ' . $order->getIncrementId() . ' method: ' . $order->getPayment()->getMethod());
                    switch ($order->getPayment()->getMethod()) {
                        case LiqPay::METHOD_CODE:
                            $this->liqPaySdk->holdConfirm($order, $logger);
                            break;
                        case PbPartsPayment::METHOD_CODE:
                        case PbInstantInstallment::METHOD_CODE:
                            $this->privatBankSdk->holdConfirm($order, $logger);
                            break;
                    }
                }
            }
        }
        return null;
    }
}