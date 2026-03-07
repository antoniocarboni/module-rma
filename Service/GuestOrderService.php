<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Helper\Guest as GuestHelper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;

class GuestOrderService
{
    /**
     * @param GuestHelper $guestHelper
     * @param Registry $registry
     */
    public function __construct(
        protected readonly GuestHelper $guestHelper,
        protected readonly Registry $registry
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return OrderInterface|ResultInterface
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     * @throws LocalizedException
     */
    public function loadValidOrder(RequestInterface $request): OrderInterface|ResultInterface
    {
        $result = $this->guestHelper->loadValidOrder($request);

        if ($result instanceof ResultInterface) {
            return $result;
        }

        $order = $this->registry->registry('current_order');

        if (!$order instanceof OrderInterface) {
            throw new LocalizedException(__('Invalid guest order session.'));
        }

        return $order;
    }
}
