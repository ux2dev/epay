<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Enum;

enum SigningMethod: string
{
    case HmacSha1 = 'hmac_sha1';
    case Rsa = 'rsa';
}
