<?php


namespace CodeCustom\Payments\Model\ResourceModel;

use \Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class Order
{

    /**
     * @var string
     */
    protected $paymentMethodTable = 'sales_order_payment';

    /**
     * @var CollectionFactory
     */
    protected $orderFactory;

    /**
     * Order constructor.
     * @param CollectionFactory $orderFactory
     */
    public function __construct(
        CollectionFactory $orderFactory
    )
    {
        $this->orderFactory = $orderFactory;
    }

    /**
     * @param array $filterData = []
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection|null
     */
    public function getOrdersByPaymentAndStatus($filterData = [])
    {
        $result = null;
        $conditions = 0;
        if (!empty($filterData)) {
            $orderCollection = $this->orderFactory->create();
            $orderCol = $orderCollection->getSelect()
            ->join(
                ["sop" => $this->paymentMethodTable],
                'main_table.entity_id = sop.parent_id',
                array('method')
            );
            foreach ($filterData as $paymentMethod => $status) {
                if ($paymentMethod && $status) {
                    $orderCol->orWhere('main_table.status = ?', $status)
                        ->where('sop.method = ?', $paymentMethod);
                    $conditions ++;
                }
            }
            if ($conditions > 0) {
                $result = $orderCollection;
            }
        }

        return $result;
    }
}