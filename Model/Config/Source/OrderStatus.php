<?php

namespace CodeCustom\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class OrderStatus implements ArrayInterface
{

    protected $statusCollectionFactory;

    public function __construct(
        CollectionFactory $statusCollectionFactory
    )
    {
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    public function toOptionArray()
    {
        $options = [['label' => 'Choose status', 'value' => 0]];
        $options += $this->statusCollectionFactory->create()->toOptionArray();
        return !empty($options) ? $options : [['label' => 'Choose status', 'value' => 0]];
    }

}