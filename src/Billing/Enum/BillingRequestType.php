<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Enum;

enum BillingRequestType: string
{
    case Check = 'CHECK';
    case Billing = 'BILLING';
    case Deposit = 'DEPOSIT';
}
