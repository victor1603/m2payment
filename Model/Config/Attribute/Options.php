<?php

namespace CodeCustom\Payments\Model\Config\Attribute;

use Magento\Eav\Model\ResourceModel\Entity\Attribute\OptionFactory;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\TypeFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class Options extends AbstractSource
{
    /**
     * @var AttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var TypeFactory
     */
    protected $eavTypeFactory;

    /**
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * @var CollectionFactory
     */
    protected $attrOptionCollectionFactory;

    /**
     * @param OptionFactory $optionFactory
     */
    public function __construct(
        AttributeFactory $attributeFactory,
        TypeFactory $typeFactory,
        OptionFactory $optionFactory,
        CollectionFactory $attrOptionCollectionFactory
    )
    {
        $this->attributeFactory = $attributeFactory;
        $this->eavTypeFactory = $typeFactory;
        $this->optionFactory = $optionFactory;
        $this->attrOptionCollectionFactory = $attrOptionCollectionFactory;
    }

    /**
     * Get all options
     *
     * @return array
     */

    public function getAllOptions()
    {
        $arr = $this->toArray();
        $this->_options = [];

        if ($arr) {
            foreach ($arr as $key => $value) {
                $key = $key && $key != 0 ? $key : __('Choose');
                $this->_options[] = ['label' => (string)$key, 'value' => (int)$value];
            }
        }

        return $this->_options;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $arr = [0];
        $attributeId = $this->getAttribute()->getId();
        $collection = $this->attrOptionCollectionFactory->create()
            ->setAttributeFilter($attributeId)
            ->setPositionOrder('asc', true)
            ->load();

        if ($collection && $collection->getItems()) {
            foreach ($collection->getItems() as $attribute) {
                if ($attribute && $attribute->getData()) {
                    $arr[$attribute->getValue()] = $attribute->getValue();
                }
            }
            asort($arr);
        }
        return $arr;
    }
}