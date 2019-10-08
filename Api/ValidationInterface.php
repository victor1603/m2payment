<?php

namespace CodeCustom\Payments\Api;

interface ValidationInterface
{
    /**
     * Returns validation result
     *
     * @api
     * @param string $payment Payment data.
     * @return string Result response.
     */
    public function validate($payment);
}
