<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Enum;

enum PaymentStatus: string
{
    case Paid = 'PAID';
    case Denied = 'DENIED';
    case Expired = 'EXPIRED';
}
