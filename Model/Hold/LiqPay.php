<?php


namespace CodeCustom\Payments\Model\Hold;

use CodeCustom\Payments\Helper\Config\LiqPayConfig;

class LiqPay
{

    protected $liqPayConfig;

    public function __construct(
        LiqPayConfig $liqPayConfig
    )
    {
        $this->liqPayConfig = $liqPayConfig;
    }

    public function execute($order)
    {

    }
}