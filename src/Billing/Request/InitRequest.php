<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Request;
use Ux2Dev\Epay\Billing\Enum\BillingRequestType;

final readonly class InitRequest
{
    public function __construct(
        public string $idn,
        public string $merchantId,
        public BillingRequestType $type,
        public ?string $tid = null,
        public ?int $total = null,
    ) {}
}
