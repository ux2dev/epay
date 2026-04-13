<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Enum;

enum TransactionType: string
{
    case Payment = 'paylogin';
    case CreditPayDirect = 'credit_paydirect';
}
