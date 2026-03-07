<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Exception;

abstract class AbstractLookupInlineEdit extends Action implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param object $entityRepository
     * @param JsonFactory $jsonFactory
     * @param string $entityLabel
     */
    public function __construct(
        Context $context,
        protected readonly object $entityRepository,
        protected readonly JsonFactory $jsonFactory,
        protected readonly string $entityLabel = ''
    ) {
        parent::__construct($context);
    }

    /**
     * @return string[]
     */
    protected function getImmutableFields(): array
    {
        return [];
    }

    /**
     * @return Json|ResultInterface|ResponseInterface
     */
    public function execute(): Json|ResultInterface|ResponseInterface
    {
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        if ($this->getRequest()->getParam('isAjax')) {
            $postItems = $this->getRequest()->getParam('items', []);

            if (empty($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
                foreach (array_keys($postItems) as $entityId) {
                    try {
                        $entity = $this->entityRepository->get((int)$entityId);
                        $itemData = $postItems[$entityId];

                        foreach ($this->getImmutableFields() as $field) {
                            unset($itemData[$field]);
                        }

                        $entity->setData(array_merge($entity->getData(), $itemData));
                        $this->entityRepository->save($entity);
                    } catch (Exception $e) {
                        $messages[] = '[' . $this->entityLabel . ' ID: ' . $entityId . '] ' . $e->getMessage();
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error,
        ]);
    }
}
