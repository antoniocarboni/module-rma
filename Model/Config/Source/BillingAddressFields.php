<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Config\Source;

use Magento\Customer\Model\ResourceModel\Address\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class BillingAddressFields implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];

        $collection = $this->attributeCollectionFactory->create();
        $collection->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $code = $attribute->getAttributeCode();
            $label = $attribute->getFrontendLabel() ?: $code;
            $options[] = [
                'value' => $code,
                'label' => $label . ' (' . $code . ')',
            ];
        }

        return $options;
    }
}
