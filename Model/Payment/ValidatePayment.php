<?php

namespace CodeCustom\Payments\Model\Payment;

use CodeCustom\Payments\Api\ValidationInterface;
use Magento\Quote\Model\QuoteFactory;
use CodeCustom\Payments\Model\PbInstantInstallment;
use CodeCustom\Payments\Helper\PbInstantInstallment\Validate as ValidateInstant;

class ValidatePayment implements ValidationInterface
{
    /**
     * @var QuoteFactory
     */
    protected $_quoteFactory;

    /**
     * @var Validate
     */
    protected $_validateHelper;

    /** Instant method validate helper
     * @var ValidateInstant
     */
    protected $_validateHelperInstant;

    public function __construct(
        QuoteFactory $quoteFactory,
        ValidateInstant $validateHelperInstant
    )
    {
        $this->_validateHelperInstant = $validateHelperInstant;
        $this->_quoteFactory = $quoteFactory;
    }

    /**
     * Returns validation result
     *
     * @api
     * @param string $payment Payment data.
     * @return string Result response.
     */
    public function validate($payment)
    {
        $data = json_decode($payment, true);
        if($data && array_key_exists('quote_id', $data) && array_key_exists('payment_data', $data)) {
            $quote = $this->getQuote($data['quote_id']);
            if(!empty($quote->getData())) {
                $validationResult = $this->_validateHelperInstant->validatePlaceOrderData($data, $quote);
                $result = $this->generateResult($validationResult, 'Error in validation place order');
            }else {
                $result = $this->generateResult( 1, 'Quote data is empty');
            }
        }else {
            $result = $this->generateResult(1, 'Error no quote ID or payment data or $data is empty');
        }
        return (string)$result;
    }

    /**
     * @param $data
     * @return array
     */
    protected function generateResult($error = false, $message = false)
    {
        if(!is_integer($error)) {
            $result = [
                'success' => true,
                'message' => false
            ];
        }else {
            if (!$message) {
                $message = $this->_validateHelperInstant->getErrorStringByCode($error);
            }
            $result = [
                'success' => false,
                'message' => $message
            ];
        }
        return json_encode($result);
    }



    /** Quote model by id
     * @param $quote_id
     * @return mixed
     */
    protected function getQuote($quote_id)
    {
        return $this->_quoteFactory->create()->load($quote_id);
    }


}
