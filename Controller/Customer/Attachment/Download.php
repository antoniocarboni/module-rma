<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Customer\Attachment;

use MageOS\RMA\Api\AttachmentRepositoryInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Service\AttachmentService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Exception;

class Download implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $resultRedirectFactory
     * @param FileFactory $fileFactory
     * @param CustomerSession $customerSession
     * @param AttachmentRepositoryInterface $attachmentRepository
     * @param RMARepositoryInterface $rmaRepository
     * @param AttachmentService $attachmentService
     * @param MessageManagerInterface $messageManager
     */
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly RedirectFactory $resultRedirectFactory,
        protected readonly FileFactory $fileFactory,
        protected readonly CustomerSession $customerSession,
        protected readonly AttachmentRepositoryInterface $attachmentRepository,
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly AttachmentService $attachmentService,
        protected readonly MessageManagerInterface $messageManager
    ) {
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        try {
            $attachment = $this->attachmentRepository->get((int)$this->request->getParam('id'));
            $rma = $this->rmaRepository->get($attachment->getRmaId());

            if ((int)$rma->getCustomerId() !== (int)$this->customerSession->getCustomerId()) {
                $this->messageManager->addErrorMessage(__('Not authorized.'));
                return $this->resultRedirectFactory->create()->setPath('rma/customer/history');
            }

            return $this->attachmentService->createDownloadResponse($attachment, $this->fileFactory);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('Attachment not found.'));
        } catch (Exception) {
            $this->messageManager->addErrorMessage(__('Could not download the file.'));
        }

        return $this->resultRedirectFactory->create()->setPath('rma/customer/history');
    }
}
