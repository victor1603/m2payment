<?php

namespace CodeCustom\Payments\Plugin\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\PlaceOrder as PlaseOrderResolve;
use Magento\Sales\Model\Order;
use CodeCustom\Payments\Helper\Config\LiqPayConfig;
use CodeCustom\Payments\Sdk\LiqPay;

class PlaceOrder
{
    /**
     * @var PlaseOrderResolve
     */
    protected $placeOrderResolve;

    /**
     * @var Order
     */
    protected $orderModel;

    /**
     * @var LiqPayConfig
     */
    protected $liqpayHelper;

    /**
     * @var LiqPay
     */
    protected $liqpaySdk;

    public function __construct(
        PlaseOrderResolve $placeOrderResolve,
        Order $orderModel,
        LiqPayConfig $liqpayHelper,
        LiqPay $liqpaySdk
    )
    {
        $this->placeOrderResolve = $placeOrderResolve;
        $this->orderModel = $orderModel;
        $this->liqpayHelper = $liqpayHelper;
        $this->liqpaySdk = $liqpaySdk;
    }

    /**
     * @param ResolverInterface $subject
     * @param $resolvedValue
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed
     * @throws \Exception
     */
    public function afterResolve(
        ResolverInterface $subject,
        $resolvedValue,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        if (!$resolvedValue['order']) {
            throw new GraphQlInputException(__("Order not created"));
        }

        $orderId = $resolvedValue['order']['order_number'];

        try {
            $order = $this->orderModel->loadByIncrementId($orderId);
            $url = $this->liqpayPaymentLogic($order);
            $resolvedValue['order']['payment_extension_data']['redirect_url'] = $url;
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
        return $resolvedValue;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    protected function liqpayPaymentLogic($order)
    {
        if (!$this->liqpayHelper->isEnabled()) {
            throw new \Exception(__('Payment is not allow.'));
        }

        if ($this->liqpayHelper->checkOrderIsLiqPayPayment($order)) {

            $url = $this->liqpaySdk->getRedirectUrl(
                [
                    'action' => $this->liqpayHelper->getPaymentType(),
                    'amount' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode(),
                    'description' => $this->liqpayHelper->getLiqPayDescription($order),
                    'order_id' => $order->getIncrementId(),
                ]
            );

        } else {
            $url = null;
        }

        return $url;
    }
}
