<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Exception;

abstract class AbstractLookupDelete extends Action implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param object $entityRepository
     * @param string $entityName
     */
    public function __construct(
        Context $context,
        protected readonly object $entityRepository,
        protected readonly string $entityName = ''
    ) {
        parent::__construct($context);
    }

    /**
     * @param object $entity
     * @return bool
     */
    protected function isProtected(object $entity): bool
    {
        return false;
    }

    /**
     * @param object $entity
     * @return string
     */
    protected function getProtectedMessage(object $entity): string
    {
        return '';
    }

    /**
     * @return ResultInterface|ResponseInterface|Redirect
     */
    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('entity_id');

        if (!$id) {
            $this->messageManager->addErrorMessage(
                __('We can\'t find the %1 to delete.', $this->entityName)
            );
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $entity = $this->entityRepository->get($id);

            if ($this->isProtected($entity)) {
                $this->messageManager->addErrorMessage($this->getProtectedMessage($entity));
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }

            $this->entityRepository->delete($entity);
            $this->messageManager->addSuccessMessage(
                __('You deleted the %1.', $this->entityName)
            );
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}
