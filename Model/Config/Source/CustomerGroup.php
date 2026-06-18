<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Config\Source;

use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model providing customer group options for the RMA restriction multiselect.
 */
class CustomerGroup implements OptionSourceInterface
{
    /**
     * @param GroupCollectionFactory $groupCollectionFactory
     */
    public function __construct(
        protected readonly GroupCollectionFactory $groupCollectionFactory
    ) {
    }

    /**
     * Returns all customer groups as an option array suitable for multiselect fields.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->groupCollectionFactory->create() as $group) {
            $options[] = [
                'value' => $group->getId(),
                'label' => $group->getCustomerGroupCode(),
            ];
        }

        return $options;
    }
}
