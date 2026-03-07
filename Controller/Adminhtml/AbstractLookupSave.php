<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Exception;

abstract class AbstractLookupSave extends Action implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param object $entityRepository
     * @param object $entityFactory
     * @param DataPersistorInterface $dataPersistor
     * @param string $entityName
     * @param string $persistorKey
     */
    public function __construct(
        Context $context,
        protected readonly object $entityRepository,
        protected readonly object $entityFactory,
        protected readonly DataPersistorInterface $dataPersistor,
        protected readonly string $entityName = '',
        protected readonly string $persistorKey = ''
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
     * @return ResultInterface|ResponseInterface|Redirect
     */
    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = (int)($data['entity_id'] ?? 0);

        if ($id) {
            try {
                $model = $this->entityRepository->get($id);
            } catch (LocalizedException) {
                $this->messageManager->addErrorMessage(
                    __('This %1 no longer exists.', $this->entityName)
                );
                return $resultRedirect->setPath('*/*/');
            }

            foreach ($this->getImmutableFields() as $field) {
                unset($data[$field]);
            }
        } else {
            $model = $this->entityFactory->create();
        }

        $model->setData(array_merge($model->getData(), $data));

        try {
            $this->entityRepository->save($model);
            $this->messageManager->addSuccessMessage(
                __('You saved the %1.', $this->entityName)
            );
            $this->dataPersistor->clear($this->persistorKey);

            if ($this->getRequest()->getParam('back') === 'continue') {
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getEntityId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the %1.', $this->entityName)
            );
        }

        $this->dataPersistor->set($this->persistorKey, $data);

        return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
    }
}
