<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- None --')]];

        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('frontend_input', 'boolean');
        $collection->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $label = $attribute->getFrontendLabel() ?: $attribute->getAttributeCode();
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $label . ' (' . $attribute->getAttributeCode() . ')',
            ];
        }

        return $options;
    }
}
