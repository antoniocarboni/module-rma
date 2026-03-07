<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Exception\NoSuchEntityException;

abstract class AbstractLookupEdit extends AbstractLookupController implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param object $entityRepository
     * @param string $entityName
     * @param string $menuId
     * @param string $breadcrumbLabel
     */
    public function __construct(
        Context $context,
        protected readonly PageFactory $resultPageFactory,
        protected readonly object $entityRepository,
        protected readonly string $entityName = '',
        protected readonly string $menuId = '',
        protected readonly string $breadcrumbLabel = ''
    ) {
        parent::__construct($context);
    }

    /**
     * @return string
     */
    protected function getMenuId(): string
    {
        return $this->menuId;
    }

    /**
     * @return string
     */
    protected function getBreadcrumbLabel(): string
    {
        return $this->breadcrumbLabel;
    }

    /**
     * @return Page|ResultInterface|ResponseInterface|Redirect
     */
    public function execute(): Page|ResultInterface|ResponseInterface|Redirect
    {
        $id = (int)$this->getRequest()->getParam('entity_id');

        if ($id) {
            try {
                $entity = $this->entityRepository->get($id);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(
                    __('This %1 no longer exists.', $this->entityName)
                );
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage);

        $resultPage->getConfig()->getTitle()->prepend(
            isset($entity) ? __('Edit %1: %2', $this->entityName, $entity->getLabel()) : __('New %1', $this->entityName)
        );

        return $resultPage;
    }
}
