<?php

declare(strict_types=1);

namespace MageOS\RMA\Model;

use MageOS\RMA\Api\RMARepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;

abstract class AbstractRmaManagement
{
    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @param int $rmaId
     * @throws NoSuchEntityException
     */
    protected function validateRmaExists(int $rmaId): void
    {
        $this->rmaRepository->get($rmaId);
    }

    /**
     * @param int $rmaId
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchCriteriaInterface
     */
    protected function buildScopedSearchCriteria(
        int $rmaId,
        SearchCriteriaInterface $searchCriteria
    ): SearchCriteriaInterface {
        $this->searchCriteriaBuilder->addFilter('rma_id', $rmaId);

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = array_filter(
                $filterGroup->getFilters() ?? [],
                fn($filter) => $filter->getField() !== 'rma_id'
            );

            if (!empty($filters)) {
                $this->searchCriteriaBuilder->addFilters($filters);
            }
        }

        if ($searchCriteria->getSortOrders()) {
            foreach ($searchCriteria->getSortOrders() as $sortOrder) {
                $this->searchCriteriaBuilder->addSortOrder(
                    $sortOrder->getField(),
                    $sortOrder->getDirection()
                );
            }
        }

        if ($searchCriteria->getPageSize()) {
            $this->searchCriteriaBuilder->setPageSize($searchCriteria->getPageSize());
        }

        if ($searchCriteria->getCurrentPage()) {
            $this->searchCriteriaBuilder->setCurrentPage($searchCriteria->getCurrentPage());
        }

        return $this->searchCriteriaBuilder->create();
    }
}
