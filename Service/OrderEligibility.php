<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Item\CollectionFactory as RmaItemCollectionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class OrderEligibility
{
    /**
     * @param ModuleConfig $moduleConfig
     * @param RmaItemCollectionFactory $rmaItemCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param TimezoneInterface $timezone
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        protected readonly ModuleConfig $moduleConfig,
        protected readonly RmaItemCollectionFactory $rmaItemCollectionFactory,
        protected readonly OrderCollectionFactory $orderCollectionFactory,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly TimezoneInterface $timezone,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Determines whether an order is eligible for an RMA request.
     *
     * An order is eligible when all of the following conditions are met:
     * - the RMA module is enabled for the order's store
     * - the order status is among the configured allowed statuses
     * - the order was placed within the configured return period
     * - the customer group associated with the order is not restricted
     * - the order contains at least one eligible item
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isOrderEligible(OrderInterface $order): bool
    {
        $storeId = (int)$order->getStoreId();

        if (!$this->moduleConfig->isEnabled($storeId)) {
            return false;
        }

        $allowedStatuses = $this->moduleConfig->getAllowedOrderStatuses($storeId);
        if (!in_array($order->getStatus(), $allowedStatuses, true)) {
            return false;
        }

        if (!$this->isWithinReturnPeriod($order)) {
            return false;
        }

        if (!$this->isCustomerGroupAllowed((int)$order->getCustomerGroupId(), $storeId)) {
            return false;
        }

        return !empty($this->getEligibleItems($order));
    }

    /**
     * Determines whether the given customer group is allowed to submit RMA requests.
     *
     * Returns true when no restricted groups are configured (open to all) or when
     * the provided group ID is not present in the restricted groups list.
     *
     * @param int $customerGroupId
     * @param int $storeId
     * @return bool
     */
    public function isCustomerGroupAllowed(int $customerGroupId, int $storeId): bool
    {
        $restrictedGroups = $this->moduleConfig->getRestrictedCustomerGroups($storeId);

        if (empty($restrictedGroups)) {
            return true;
        }

        return !in_array($customerGroupId, $restrictedGroups, true);
    }

    /**
     * Returns the order items that are eligible for return.
     *
     * An item is eligible when all of the following conditions are met:
     * - it is not a child item (bundle component, configurable variant)
     * - its product type is neither virtual nor downloadable
     * - when a returnable attribute is configured, the product's attribute value is truthy
     * - the available quantity (ordered minus already requested) is greater than zero
     *
     * @param OrderInterface $order
     * @return array<int, array{
     *     order_item_id: int,
     *     name: string,
     *     sku: string,
     *     qty_ordered: int,
     *     qty_already_requested: int,
     *     qty_available: int
     * }>
     */
    public function getEligibleItems(OrderInterface $order): array
    {
        $orderId = (int)$order->getEntityId();
        $storeId = (int)$order->getStoreId();
        $alreadyRequested = $this->getAlreadyRequestedQty($orderId);
        $returnableAttributeCode = $this->moduleConfig->getProductReturnableAttribute($storeId);

        $items = [];
        foreach ($order->getItems() as $orderItem) {
            if ($orderItem->getParentItemId()) {
                continue;
            }

            $productType = $orderItem->getProductType();
            if (in_array($productType, ['virtual', 'downloadable'], true)) {
                continue;
            }

            if ($returnableAttributeCode !== ''
                && !$this->isProductReturnable((int)$orderItem->getProductId(), $returnableAttributeCode)
            ) {
                continue;
            }

            $orderItemId = (int)$orderItem->getItemId();
            $qtyOrdered = (int)$orderItem->getQtyOrdered();
            $qtyAlreadyRequested = $alreadyRequested[$orderItemId] ?? 0;
            $qtyAvailable = $qtyOrdered - $qtyAlreadyRequested;

            if ($qtyAvailable <= 0) {
                continue;
            }

            $items[] = [
                'order_item_id' => $orderItemId,
                'name' => $orderItem->getName(),
                'sku' => $orderItem->getSku(),
                'qty_ordered' => $qtyOrdered,
                'qty_already_requested' => $qtyAlreadyRequested,
                'qty_available' => $qtyAvailable,
            ];
        }

        return $items;
    }

    /**
     * @param int $customerId
     * @param int $storeId
     * @return Collection
     * @throws NoSuchEntityException
     */
    public function getCustomerEligibleOrders(int $customerId, int $storeId): Collection
    {
        $allowedStatuses = $this->moduleConfig->getAllowedOrderStatuses($storeId);
        $returnPeriod = $this->moduleConfig->getReturnPeriod($storeId);

        $storeIds = $this->getStoreIdsForWebsite($storeId);

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('store_id', ['in' => $storeIds]);

        if (!empty($allowedStatuses)) {
            $collection->addFieldToFilter('status', ['in' => $allowedStatuses]);
        }

        if ($returnPeriod > 0) {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$returnPeriod} days"));
            $collection->addFieldToFilter('created_at', ['gteq' => $cutoffDate]);
        }
        $collection->setOrder('created_at', 'desc');

        return $collection;
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    protected function isWithinReturnPeriod(OrderInterface $order): bool
    {
        $storeId = (int)$order->getStoreId();
        $returnPeriod = $this->moduleConfig->getReturnPeriod($storeId);

        if ($returnPeriod <= 0) {
            return true;
        }

        $orderDate = strtotime($order->getCreatedAt());
        $cutoffDate = strtotime("-{$returnPeriod} days");

        return $orderDate >= $cutoffDate;
    }

    /**
     * @param int $storeId
     * @return int[]
     * @throws NoSuchEntityException
     */
    protected function getStoreIdsForWebsite(int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $websiteId = (int)$store->getWebsiteId();
        $storeIds = array_map(
            fn($s) => (int)$s->getId(),
            array_filter(
                $this->storeManager->getStores(),
                fn($s) => (int)$s->getWebsiteId() === $websiteId
            )
        );

        return $storeIds ?: [$storeId];
    }

    /**
     * @param int $orderId
     * @return array<int, int>
     */
    public function getAlreadyRequestedQty(int $orderId): array
    {
        $collection = $this->rmaItemCollectionFactory->create();

        $collection->getSelect()->join(
            ['rma' => $collection->getTable('rma_entity')],
            'main_table.rma_id = rma.entity_id',
            []
        )->where('rma.order_id = ?', $orderId);

        $result = [];
        foreach ($collection as $item) {
            $orderItemId = (int)$item->getData('order_item_id');
            $qty = (int)$item->getData('qty_requested');
            $result[$orderItemId] = ($result[$orderItemId] ?? 0) + $qty;
        }

        return $result;
    }

    /**
     * Determines whether a product is eligible for return based on a configurable Yes/No attribute.
     *
     * The product is considered returnable when:
     * - the product cannot be loaded (does not restrict by default)
     * - the attribute is not set or its value is null/empty (no explicit restriction)
     * - the attribute value is truthy (explicitly marked as returnable)
     *
     * @param int $productId
     * @param string $attributeCode
     * @return bool
     */
    private function isProductReturnable(int $productId, string $attributeCode): bool
    {
        try {
            $product = $this->productRepository->getById($productId);
            $value = $product->getData($attributeCode);

            // Attribute not set on this product: do not restrict
            if ($value === null || $value === '') {
                return true;
            }

            return (bool)$value;
        } catch (NoSuchEntityException) {
            // Product not found: do not restrict
            return true;
        }
    }
}
