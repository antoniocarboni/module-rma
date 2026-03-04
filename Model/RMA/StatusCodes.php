<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\RMA;

class StatusCodes
{
    const string NEW_REQUEST = 'new_request';
    const string NEED_DETAILS = 'need_details';
    const string APPROVED = 'approved';
    const string REJECTED = 'rejected';
    const string SHIPPED_BY_CUSTOMER = 'shipped_by_customer';
    const string RECEIVED_BY_ADMIN = 'received_by_admin';
    const string CANCELED_BY_CUSTOMER = 'canceled_by_customer';
    const string RESOLVED = 'resolved';
    const array STATUS_EVENT_MAP = [
        self::APPROVED => 'rma_approved_after',
        self::REJECTED => 'rma_rejected_after',
        self::SHIPPED_BY_CUSTOMER => 'rma_shipped_by_customer_after',
        self::RECEIVED_BY_ADMIN => 'rma_received_after',
        self::CANCELED_BY_CUSTOMER => 'rma_canceled_after',
        self::RESOLVED => 'rma_resolved_after',
    ];

    const array PROTECTED_CODES = [
        self::NEW_REQUEST,
        self::APPROVED,
        self::REJECTED,
        self::SHIPPED_BY_CUSTOMER,
        self::RECEIVED_BY_ADMIN,
        self::CANCELED_BY_CUSTOMER,
        self::RESOLVED,
    ];

    /**
     * @param string $code
     * @return bool
     */
    public static function isProtected(string $code): bool
    {
        return in_array($code, self::PROTECTED_CODES, true);
    }
}
