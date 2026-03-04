<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use MageOS\RMA\Api\AttachmentRepositoryInterface;
use MageOS\RMA\Api\Data\AttachmentInterface;
use MageOS\RMA\Api\Data\AttachmentInterfaceFactory;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Attachment\CollectionFactory as AttachmentCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Psr\Log\LoggerInterface;

class AttachmentService
{
    const string BASE_TMP_PATH = 'rma/tmp';
    const string BASE_PATH = 'rma/attachments';

    protected WriteInterface $varDirectory;

    /**
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param ModuleConfig $moduleConfig
     * @param AttachmentInterfaceFactory $attachmentFactory
     * @param AttachmentRepositoryInterface $attachmentRepository
     * @param AttachmentCollectionFactory $attachmentCollectionFactory
     * @param Mime $mime
     * @param LoggerInterface $logger
     */
    public function __construct(
        Filesystem $filesystem,
        protected readonly UploaderFactory $uploaderFactory,
        protected readonly ModuleConfig $moduleConfig,
        protected readonly AttachmentInterfaceFactory $attachmentFactory,
        protected readonly AttachmentRepositoryInterface $attachmentRepository,
        protected readonly AttachmentCollectionFactory $attachmentCollectionFactory,
        protected readonly Mime $mime,
        protected readonly LoggerInterface $logger
    ) {
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    /**
     * @param string $fileId
     * @return array
     * @throws LocalizedException
     */
    public function uploadToTmp(string $fileId): array
    {
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions($this->moduleConfig->getAllowedAttachmentExtensions());
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $tmpPath = $this->varDirectory->getAbsolutePath(self::BASE_TMP_PATH);
        $result = $uploader->save($tmpPath);

        if (!$result) {
            throw new LocalizedException(__('File cannot be saved to the temporary folder.'));
        }

        $fileSize = (int)($result['size'] ?? 0);
        $maxBytes = $this->moduleConfig->getMaxAttachmentFileSizeBytes();

        if ($fileSize > $maxBytes) {
            $fullPath = $tmpPath . '/' . $result['file'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            throw new LocalizedException(
                __('File exceeds the maximum allowed size of %1 MB.', $this->moduleConfig->getMaxAttachmentFileSize())
            );
        }

        return [
            'file' => $result['file'],
            'name' => $result['name'] ?? $result['file'],
            'size' => $fileSize,
            'type' => $result['type'] ?? '',
            'tmp_path' => self::BASE_TMP_PATH . '/' . $result['file'],
        ];
    }

    /**
     * @param int $rmaId
     * @param array $tmpFiles
     * @param int|null $commentId
     * @return AttachmentInterface[]
     * @throws LocalizedException
     */
    public function moveFromTmpAndSave(int $rmaId, array $tmpFiles, ?int $commentId = null): array
    {
        $maxFiles = $this->moduleConfig->getMaxAttachmentFiles();
        $saved = [];

        foreach (array_slice($tmpFiles, 0, $maxFiles) as $tmpFile) {
            $fileName = $tmpFile['file'] ?? '';
            if ($fileName === '') {
                continue;
            }

            $sourcePath = self::BASE_TMP_PATH . '/' . $fileName;
            $destDir = self::BASE_PATH . '/' . $rmaId;
            $destPath = $destDir . '/' . $fileName;

            if (!$this->varDirectory->isExist($sourcePath)) {
                continue;
            }

            $this->varDirectory->create($destDir);
            $this->varDirectory->renameFile($sourcePath, $destPath);

            $absolutePath = $this->varDirectory->getAbsolutePath($destPath);
            $mimeType = $this->mime->getMimeType($absolutePath);

            $attachment = $this->attachmentFactory->create();
            $attachment->setRmaId($rmaId);
            $attachment->setCommentId($commentId);
            $attachment->setFileName($tmpFile['name'] ?? $fileName);
            $attachment->setFilePath($destPath);
            $attachment->setFileSize((int)($tmpFile['size'] ?? 0));
            $attachment->setMimeType($mimeType);

            $this->attachmentRepository->save($attachment);
            $saved[] = $attachment;
        }

        return $saved;
    }

    /**
     * @param int $rmaId
     * @return AttachmentInterface[]
     */
    public function getByRmaId(int $rmaId): array
    {
        $collection = $this->attachmentCollectionFactory->create();
        $collection->addFieldToFilter(AttachmentInterface::RMA_ID, $rmaId);
        $collection->setOrder(AttachmentInterface::CREATED_AT, 'ASC');

        return $collection->getItems();
    }

    /**
     * @param int $commentId
     * @return AttachmentInterface[]
     */
    public function getByCommentId(int $commentId): array
    {
        $collection = $this->attachmentCollectionFactory->create();
        $collection->addFieldToFilter(AttachmentInterface::COMMENT_ID, $commentId);
        $collection->setOrder(AttachmentInterface::CREATED_AT, 'ASC');

        return $collection->getItems();
    }

    /**
     * @param AttachmentInterface $attachment
     * @return string
     */
    public function getAbsolutePath(AttachmentInterface $attachment): string
    {
        return $this->varDirectory->getAbsolutePath($attachment->getFilePath());
    }

    /**
     * @param AttachmentInterface $attachment
     * @return void
     * @throws LocalizedException
     */
    public function deleteAttachment(AttachmentInterface $attachment): void
    {
        $filePath = $attachment->getFilePath();

        if ($this->varDirectory->isExist($filePath)) {
            $this->varDirectory->delete($filePath);
        }

        $this->attachmentRepository->delete($attachment);
    }

    /**
     * @param string $json
     * @param int $rmaId
     * @param int|null $commentId
     * @return void
     * @throws LocalizedException
     */
    public function saveFromJson(string $json, int $rmaId, ?int $commentId = null): void
    {
        if ($json === '' || $json === '[]') {
            return;
        }

        $tmpFiles = json_decode($json, true);
        if (!is_array($tmpFiles) || empty($tmpFiles)) {
            return;
        }

        $this->moveFromTmpAndSave($rmaId, $tmpFiles, $commentId);
    }

    /**
     * @param int $bytes
     * @return string
     */
    public function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * @param AttachmentInterface $attachment
     * @return array
     */
    public function toArray(AttachmentInterface $attachment): array
    {
        return [
            'entity_id' => $attachment->getEntityId(),
            'rma_id' => $attachment->getRmaId(),
            'comment_id' => $attachment->getCommentId(),
            'file_name' => $attachment->getFileName(),
            'file_size' => $attachment->getFileSize(),
            'file_size_label' => $this->formatFileSize((int)$attachment->getFileSize()),
            'mime_type' => $attachment->getMimeType(),
            'created_at' => $attachment->getCreatedAt(),
        ];
    }
}
