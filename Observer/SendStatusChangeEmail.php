<?php

declare(strict_types=1);

namespace MageOS\RMA\Observer;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Email\SenderInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Exception;

class SendStatusChangeEmail implements ObserverInterface
{
    /**
     * @param SenderInterface $sender
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly SenderInterface $sender,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $rma = $observer->getData('rma');

        if (!$rma instanceof RMAInterface) {
            return;
        }

        try {
            $this->sender->sendCustomerStatusChangeEmail($rma, (int)$observer->getData('new_status_id'));
        } catch (Exception $e) {
            $this->logger->error('RMA: Failed to send status change email', [
                'rma_id' => $rma->getEntityId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
