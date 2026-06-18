<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Customer;

use MageOS\RMA\Service\OrderEligibility;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class Create implements HttpGetActionInterface
{
    /**
     * @param PageFactory $resultPageFactory
     * @param RedirectFactory $resultRedirectFactory
     * @param CustomerSession $customerSession
     * @param OrderEligibility $orderEligibility
     * @param StoreManagerInterface $storeManager
     * @param MessageManagerInterface $messageManager
     */
    public function __construct(
        protected readonly PageFactory $resultPageFactory,
        protected readonly RedirectFactory $resultRedirectFactory,
        protected readonly CustomerSession $customerSession,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly MessageManagerInterface $messageManager
    ) {
    }

    /**
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $storeId = (int)$this->storeManager->getStore()->getId();
        $customerGroupId = (int)$this->customerSession->getCustomerGroupId();

        if (!$this->orderEligibility->isCustomerGroupAllowed($customerGroupId, $storeId)) {
            $this->messageManager->addErrorMessage(
                __('You are not allowed to submit return requests.')
            );
            return $this->resultRedirectFactory->create()->setPath('customer/account');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Request Return'));

        $navigationBlock = $resultPage->getLayout()->getBlock('customer_account_navigation');
        if ($navigationBlock) {
            $navigationBlock->setActive('rma/customer/history');
        }

        return $resultPage;
    }
}
