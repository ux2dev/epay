<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Enum;

enum BillingPaymentType: string
{
    case Billing = 'BILLING';
    case Partial = 'PARTIAL';
    case Deposit = 'DEPOSIT';
}
