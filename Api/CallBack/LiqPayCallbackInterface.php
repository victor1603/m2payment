<?php

namespace CodeCustom\Payments\Api\CallBack;

interface LiqPayCallbackInterface
{

    /**
     * @api
     * @return mixed
     */
    public function callback();
}