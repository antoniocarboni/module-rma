<?php

declare(strict_types=1);

namespace MageOS\RMA\Block\Adminhtml\Rma\Edit;

use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Block\Trait\AttachmentConfigTrait;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Service\AttachmentService;
use MageOS\RMA\Service\CommentFormatter;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Comments extends Template
{
    use AttachmentConfigTrait;

    /**
     * @var string
     */
    protected $_template = 'MageOS_RMA::rma/edit/comments.phtml';

    /**
     * @param Context $context
     * @param RMARepositoryInterface $rmaRepository
     * @param CommentFormatter $commentFormatter
     * @param AttachmentService $attachmentService
     * @param ModuleConfig $moduleConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly CommentFormatter $commentFormatter,
        protected readonly AttachmentService $attachmentService,
        protected readonly ModuleConfig $moduleConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return int
     */
    public function getRmaId(): int
    {
        return (int)$this->getRequest()->getParam('entity_id');
    }

    /**
     * @return bool
     */
    public function isEditMode(): bool
    {
        return $this->getRmaId() > 0;
    }

    /**
     * @return array
     */
    public function getComments(): array
    {
        $rmaId = $this->getRmaId();
        if (!$rmaId) {
            return [];
        }

        return $this->commentFormatter->buildList($rmaId, includeVisibility: true);
    }

    /**
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('rma/comment/save');
    }

    /**
     * @return string
     */
    public function getLoadListUrl(): string
    {
        return $this->getUrl('rma/comment/loadList');
    }

    /**
     * @return string
     */
    public function getUploadUrl(): string
    {
        return $this->getUrl('rma/attachment/upload');
    }

    /**
     * @return string
     */
    public function getDownloadUrl(): string
    {
        return $this->getUrl('rma/attachment/download');
    }

    /**
     * @return string
     */
    public function getDeleteUrl(): string
    {
        return $this->getUrl('rma/attachment/delete');
    }

    /**
     * @return array
     */
    public function getAttachments(): array
    {
        $rmaId = $this->getRmaId();
        if (!$rmaId) {
            return [];
        }

        return array_map(
            [$this->attachmentService, 'toArray'],
            $this->attachmentService->getByRmaId($rmaId)
        );
    }

    /**
     * @param int $attachmentId
     * @return string
     */
    public function getAttachmentDownloadUrl(int $attachmentId): string
    {
        return $this->getUrl('rma/attachment/download', ['id' => $attachmentId]);
    }
}
