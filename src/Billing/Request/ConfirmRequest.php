<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Request;
use Ux2Dev\Epay\Billing\Enum\BillingPaymentType;

final readonly class ConfirmRequest
{
    public function __construct(
        public string $idn,
        public string $merchantId,
        public string $tid,
        public \DateTimeImmutable $date,
        public int $total,
        public BillingPaymentType $type,
        public ?string $invoices = null,
    ) {}
}
