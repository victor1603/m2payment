<?php

namespace CodeCustom\Payments\Api\CallBack;

interface PrivatBankCallBackInterface
{

    /**
     * @api
     * @return mixed
     */
    public function callback();
}