<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Item\CollectionFactory as RmaItemCollectionFactory;
use MageOS\RMA\Service\OrderEligibility;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderEligibilityTest extends TestCase
{
    private ModuleConfig&MockObject $moduleConfig;
    private RmaItemCollectionFactory&MockObject $rmaItemCollectionFactory;
    private OrderCollectionFactory&MockObject $orderCollectionFactory;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private TimezoneInterface&MockObject $timezone;
    private StoreManagerInterface&MockObject $storeManager;
    private ProductRepositoryInterface&MockObject $productRepository;

    protected function setUp(): void
    {
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->rmaItemCollectionFactory = $this->createMock(RmaItemCollectionFactory::class);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);

        // Safe defaults: disable new restrictions so existing tests are unaffected
        $this->moduleConfig->method('getRestrictedBillingFields')->willReturn([]);
        $this->moduleConfig->method('getProductDisableAttribute')->willReturn('');
        $this->moduleConfig->method('getProductReturnableAttribute')->willReturn('');
    }

    private function createService(array $stubbedMethods = []): OrderEligibility
    {
        if (empty($stubbedMethods)) {
            return new OrderEligibility(
                $this->moduleConfig,
                $this->rmaItemCollectionFactory,
                $this->orderCollectionFactory,
                $this->orderRepository,
                $this->timezone,
                $this->storeManager,
                $this->productRepository
            );
        }

        return $this->getMockBuilder(OrderEligibility::class)
            ->setConstructorArgs([
                $this->moduleConfig,
                $this->rmaItemCollectionFactory,
                $this->orderCollectionFactory,
                $this->orderRepository,
                $this->timezone,
                $this->storeManager,
                $this->productRepository,
            ])
            ->onlyMethods($stubbedMethods)
            ->getMock();
    }

    private function createOrder(int $storeId = 1, string $status = 'complete', string $createdAt = ''): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getStatus')->willReturn($status);
        $order->method('getCreatedAt')->willReturn($createdAt ?: date('Y-m-d H:i:s'));
        $order->method('getEntityId')->willReturn(100);

        return $order;
    }

    private function createOrderItem(
        ?int $parentItemId,
        string $productType,
        int $itemId,
        int $qtyOrdered,
        string $name = 'Product',
        string $sku = 'SKU-001',
        int $productId = 0
    ): OrderItemInterface&MockObject {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getParentItemId')->willReturn($parentItemId);
        $item->method('getProductType')->willReturn($productType);
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);
        $item->method('getName')->willReturn($name);
        $item->method('getSku')->willReturn($sku);
        $item->method('getProductId')->willReturn($productId);

        return $item;
    }

    // -------------------------------------------------------------------------
    // isOrderEligible
    // -------------------------------------------------------------------------

    public function testIsOrderEligibleReturnsFalseWhenModuleDisabled(): void
    {
        $this->moduleConfig->method('isEnabled')->with(1)->willReturn(false);
        $service = $this->createService(['getEligibleItems']);

        $this->assertFalse($service->isOrderEligible($this->createOrder()));
    }

    public function testIsOrderEligibleReturnsFalseWhenStatusNotAllowed(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);

        $order = $this->createOrder(status: 'pending');
        $service = $this->createService(['getEligibleItems']);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsFalseWhenOutsideReturnPeriod(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);

        $order = $this->createOrder(status: 'complete', createdAt: '2020-01-01 00:00:00');
        $service = $this->createService(['getEligibleItems']);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsFalseWhenNoEligibleItems(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->with($order)->willReturn([]);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueWhenAllConditionsMet(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->moduleConfig->method('getRestrictedCustomerGroups')->willReturn([]);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->with($order)->willReturn([
            ['order_item_id' => 1, 'name' => 'Product', 'sku' => 'SKU-1', 'qty_ordered' => 2, 'qty_already_requested' => 0, 'qty_available' => 2],
        ]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueWhenReturnPeriodIsUnlimited(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);

        $order = $this->createOrder(status: 'complete', createdAt: '2010-01-01 00:00:00');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->willReturn([['order_item_id' => 1]]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    // -------------------------------------------------------------------------
    // isBillingAllowed (via isOrderEligible)
    // -------------------------------------------------------------------------

    public function testIsOrderEligibleReturnsFalseWhenRestrictedBillingFieldIsFilled(): void
    {
        // Fresh mock to avoid setUp stub shadowing
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->moduleConfig->method('getRestrictedCustomerGroups')->willReturn([]);
        $this->moduleConfig->method('getRestrictedBillingFields')->willReturn(['vat_id']);
        $this->moduleConfig->method('getProductDisableAttribute')->willReturn('');
        $this->moduleConfig->method('getProductReturnableAttribute')->willReturn('');

        $billing = $this->createMock(OrderAddress::class);
        $billing->method('getData')->with('vat_id')->willReturn('IT12345678901');

        $order = $this->createOrder(status: 'complete');
        $order->method('getBillingAddress')->willReturn($billing);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueWhenRestrictedBillingFieldIsEmpty(): void
    {
        // Fresh mock to avoid setUp stub shadowing
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->moduleConfig->method('getRestrictedCustomerGroups')->willReturn([]);
        $this->moduleConfig->method('getRestrictedBillingFields')->willReturn(['vat_id']);
        $this->moduleConfig->method('getProductDisableAttribute')->willReturn('');
        $this->moduleConfig->method('getProductReturnableAttribute')->willReturn('');

        $billing = $this->createMock(OrderAddress::class);
        $billing->method('getData')->with('vat_id')->willReturn('');

        $order = $this->createOrder(status: 'complete');
        $order->method('getBillingAddress')->willReturn($billing);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->willReturn([['order_item_id' => 1]]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueWhenNoBillingFieldsConfigured(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->moduleConfig->method('getRestrictedCustomerGroups')->willReturn([]);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->willReturn([['order_item_id' => 1]]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    // -------------------------------------------------------------------------
    // getEligibleItems
    // -------------------------------------------------------------------------

    public function testGetEligibleItemsSkipsChildItems(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: 5, productType: 'simple', itemId: 10, qtyOrdered: 1),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsVirtualProducts(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'virtual', itemId: 10, qtyOrdered: 1),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsDownloadableProducts(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'downloadable', itemId: 10, qtyOrdered: 1),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsItemsWithNoAvailableQty(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([10 => 2]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsDeductsAlreadyRequestedQty(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 3, name: 'Shirt', sku: 'SHIRT-L'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([10 => 1]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['qty_already_requested']);
        $this->assertSame(2, $result[0]['qty_available']);
    }

    public function testGetEligibleItemsReturnsCorrectStructure(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2, name: 'Blue Hat', sku: 'HAT-BL'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame([
            'order_item_id' => 10,
            'name' => 'Blue Hat',
            'sku' => 'HAT-BL',
            'qty_ordered' => 2,
            'qty_already_requested' => 0,
            'qty_available' => 2,
        ], $result[0]);
    }

    public function testGetEligibleItemsIncludesConfigurableProducts(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'configurable', itemId: 20, qtyOrdered: 1, name: 'T-Shirt', sku: 'TSHIRT-M'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(20, $result[0]['order_item_id']);
    }

    public function testGetEligibleItemsFiltersMultipleItemTypes(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 1, qtyOrdered: 2, name: 'Book', sku: 'BOOK-1'),
            $this->createOrderItem(parentItemId: null, productType: 'virtual', itemId: 2, qtyOrdered: 1),
            $this->createOrderItem(parentItemId: 1, productType: 'simple', itemId: 3, qtyOrdered: 1),
            $this->createOrderItem(parentItemId: null, productType: 'downloadable', itemId: 4, qtyOrdered: 1),
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 5, qtyOrdered: 1, name: 'Pen', sku: 'PEN-1'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['order_item_id']);
        $this->assertSame(5, $result[1]['order_item_id']);
    }

    // -------------------------------------------------------------------------
    // getEligibleItems — product_disable_attribute
    // -------------------------------------------------------------------------

    public function testGetEligibleItemsSkipsItemsWithRmaDisabled(): void
    {
        // Fresh mock: setUp defaults would shadow willReturn('rma_disable')
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('getProductReturnableAttribute')->willReturn('');
        $this->moduleConfig->method('getProductDisableAttribute')->willReturn('rma_disable');
        $this->moduleConfig->method('getRestrictedBillingFields')->willReturn([]);

        $product = $this->createMock(Product::class);
        $product->method('getData')->with('rma_disable')->willReturn(1);
        $this->productRepository->method('getById')->willReturn($product);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 1, productId: 99),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsIncludesItemsWithRmaNotDisabled(): void
    {
        // Fresh mock
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('getProductReturnableAttribute')->willReturn('');
        $this->moduleConfig->method('getProductDisableAttribute')->willReturn('rma_disable');
        $this->moduleConfig->method('getRestrictedBillingFields')->willReturn([]);

        $product = $this->createMock(Product::class);
        $product->method('getData')->with('rma_disable')->willReturn(0);
        $this->productRepository->method('getById')->willReturn($product);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 1, name: 'Book', sku: 'BOOK-1', productId: 99),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);
        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['order_item_id']);
    }

    // -------------------------------------------------------------------------
    // areAllItemsRmaDisabled
    // -------------------------------------------------------------------------

    public function testAreAllItemsRmaDisabledReturnsTrueWhenAllDisabled(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->with('rma_disable')->willReturn(1);
        $this->productRepository->method('getById')->willReturn($product);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 1, qtyOrdered: 1, productId: 10),
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 2, qtyOrdered: 1, productId: 11),
        ]);

        $service = $this->createService();
        $this->assertTrue($service->areAllItemsRmaDisabled($order, 'rma_disable'));
    }

    public function testAreAllItemsRmaDisabledReturnsFalseWhenOneIsNotDisabled(): void
    {
        $productDisabled = $this->createMock(Product::class);
        $productDisabled->method('getData')->with('rma_disable')->willReturn(1);

        $productEnabled = $this->createMock(Product::class);
        $productEnabled->method('getData')->with('rma_disable')->willReturn(0);

        $productMap = [10 => $productDisabled, 11 => $productEnabled];
        $this->productRepository->method('getById')
            ->willReturnCallback(fn($id) => $productMap[$id]);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 1, qtyOrdered: 1, productId: 10),
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 2, qtyOrdered: 1, productId: 11),
        ]);

        $service = $this->createService();
        $this->assertFalse($service->areAllItemsRmaDisabled($order, 'rma_disable'));
    }

    public function testAreAllItemsRmaDisabledReturnsFalseWhenNoCandidates(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'virtual', itemId: 1, qtyOrdered: 1),
        ]);

        $service = $this->createService();
        $this->assertFalse($service->areAllItemsRmaDisabled($order, 'rma_disable'));
    }

    public function testAreAllItemsRmaDisabledSkipsChildAndVirtualItems(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->with('rma_disable')->willReturn(1);
        $this->productRepository->method('getById')->willReturn($product);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: 5,    productType: 'simple', itemId: 1, qtyOrdered: 1, productId: 10),  // child → skipped
            $this->createOrderItem(parentItemId: null, productType: 'virtual', itemId: 2, qtyOrdered: 1),                // virtual → skipped
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 3, qtyOrdered: 1, productId: 11), // disabled
        ]);

        $service = $this->createService();
        $this->assertTrue($service->areAllItemsRmaDisabled($order, 'rma_disable'));
    }
}
