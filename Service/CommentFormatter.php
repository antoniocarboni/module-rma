<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use MageOS\RMA\Api\Data\CommentInterface;
use MageOS\RMA\Model\ResourceModel\Comment\CollectionFactory;

class CommentFormatter
{
    /**
     * @param AttachmentService $attachmentService
     * @param CollectionFactory $commentCollectionFactory
     */
    public function __construct(
        protected readonly AttachmentService $attachmentService,
        protected readonly CollectionFactory $commentCollectionFactory
    ) {
    }

    /**
     * @param int $rmaId
     * @param bool $visibleOnly
     * @param bool $includeVisibility
     * @param int $afterId
     * @return array
     */
    public function buildList(
        int $rmaId,
        bool $visibleOnly = false,
        bool $includeVisibility = false,
        int $afterId = 0
    ): array {
        $collection = $this->commentCollectionFactory->create();
        $collection->addFieldToFilter('rma_id', $rmaId);

        if ($visibleOnly) {
            $collection->addFieldToFilter('is_visible_to_customer', 1);
        }

        if ($afterId > 0) {
            $collection->addFieldToFilter('entity_id', ['gt' => $afterId]);
        }

        $collection->setOrder('created_at', 'ASC');

        return array_values(array_map(
            fn($comment) => $this->toArray($comment, $includeVisibility),
            $collection->getItems()
        ));
    }

    /**
     * @param CommentInterface $comment
     * @param bool $includeVisibility
     * @return array
     */
    public function toArray(CommentInterface $comment, bool $includeVisibility = false): array
    {
        $data = [
            'entity_id' => $comment->getEntityId(),
            'author_type' => $comment->getAuthorType(),
            'author_name' => $comment->getAuthorName(),
            'comment' => $comment->getComment(),
            'created_at' => $comment->getCreatedAt(),
        ];

        if ($includeVisibility) {
            $data['is_visible_to_customer'] = (bool)$comment->getIsVisibleToCustomer();
        }

        $commentId = (int)$comment->getEntityId();
        if ($commentId) {
            $data['attachments'] = $this->formatAttachments($commentId);
        }

        return $data;
    }

    /**
     * @param int $commentId
     * @return array
     */
    protected function formatAttachments(int $commentId): array
    {
        return array_values(array_map(
            [$this->attachmentService, 'toArray'],
            $this->attachmentService->getByCommentId($commentId)
        ));
    }
}
