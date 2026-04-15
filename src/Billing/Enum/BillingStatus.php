<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Enum;

enum BillingStatus: string
{
    case Success = '00';
    case InvalidAmount = '13';
    case InvalidSubscriber = '14';
    case NoObligation = '62';
    case Unavailable = '80';
    case InvalidChecksum = '93';
    case Duplicate = '94';
    case GeneralError = '96';
}
