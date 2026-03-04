<?php

declare(strict_types=1);

namespace MageOS\RMA\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class GenericEntityActions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param string $editUrlPath
     * @param string $deleteUrlPath
     * @param string $entityLabel
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        protected UrlInterface $urlBuilder,
        protected string $editUrlPath = '',
        protected string $deleteUrlPath = '',
        protected string $entityLabel = '',
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }

            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl($this->editUrlPath, [
                        'entity_id' => $item['entity_id'],
                    ]),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl($this->deleteUrlPath, [
                        'entity_id' => $item['entity_id'],
                    ]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete %1', $this->entityLabel),
                        'message' => __('Are you sure you want to delete this %1?', $this->entityLabel),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
