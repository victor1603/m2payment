<?php

namespace CodeCustom\Payments\Cron;

use \Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use CodeCustom\Payments\Model\Hold\LiqPay as LiqPayHold;
use CodeCustom\Payments\Model\Hold\PrivatBank as PrivatBankHold;
use CodeCustom\Payments\Model\LiqPay;
use CodeCustom\Payments\Model\PbPartsPayment;
use CodeCustom\Payments\Model\PbInstantInstallment;
use CodeCustom\Payments\Helper\Config\LiqPayConfig;
use CodeCustom\Payments\Helper\Config\PrivatBankConfig;

class Scheduler
{

    /**
     * @var CollectionFactory
     */
    protected $order;

    /**
     * @var LiqPayConfig
     */
    protected $liqPayHold;

    /**
     * @var LiqPayConfig
     */
    protected $liqPayConfig;

    /**
     * @var PrivatBankConfig
     */
    protected $privatBankHold;

    /**
     * @var PrivatBankConfig
     */
    protected $privatBankConfig;

    /**
     * Scheduler constructor.
     * @param CollectionFactory $order
     */
    public function __construct(
        CollectionFactory $order,
        LiqPayHold $liqPayHold,
        PrivatBankHold $privatBankHold,
        LiqPayConfig $liqPayConfig,
        PrivatBankConfig $privatBankConfig
    )
    {
        $this->order = $order;
        $this->liqPayHold = $liqPayHold;
        $this->privatBankHold = $privatBankHold;
        $this->liqPayConfig = $liqPayConfig;
        $this->privatBankConfig = $privatBankConfig;
    }

    /**
     * @var $order \Magento\Sales\Model\Order
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function holdChecker()
    {
        $orderCollection = $this->order->create();
        $statuses = 0;
        if ($this->liqPayConfig->getConfirmHoldStatus()) {
            $orderCollection
                ->addFieldToFilter('status',
                    ['eq' => $this->liqPayConfig->getConfirmHoldStatus()]);
            $statuses++;
        }
        if ($this->privatBankConfig->getConfirmHoldStatus(PbPartsPayment::METHOD_CODE)) {
            $orderCollection
                ->addFieldToFilter('status',
                    ['eq' => $this->privatBankConfig->getConfirmHoldStatus(PbPartsPayment::METHOD_CODE)]);
            $statuses++;
        }
        if ($this->privatBankConfig->getConfirmHoldStatus(PbInstantInstallment::METHOD_CODE)) {
            $orderCollection
                ->addFieldToFilter('status',
                    ['eq' => $this->privatBankConfig->getConfirmHoldStatus(PbInstantInstallment::METHOD_CODE)]);
            $statuses++;
        }

        if ($statuses == 0) {
            return false;
        }


        if ($orderCollection && $orderCollection->getSize()) {
            foreach ($orderCollection as $order) {
                if ($order && $order->getId) {
                    switch ($order->getPayment()->getMethod()) {
                        case LiqPay::METHOD_CODE:
                            $this->liqPayHold->execute($order);
                            break;
                        case PbPartsPayment::METHOD_CODE:
                            $this->privatBankHold->execute($order);
                            break;
                        case PbInstantInstallment::METHOD_CODE:
                            $this->privatBankHold->execute($order);
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        return null;
    }
}