<?php

namespace CodeCustom\Payments\Api\CallBack;

interface LiqPayCallBackInterface
{

    /**
     * @api
     * @return mixed
     */
    public function callback();
}