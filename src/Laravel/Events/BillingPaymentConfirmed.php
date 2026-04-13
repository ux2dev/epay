<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Events;

use Ux2Dev\Epay\Billing\Request\ConfirmRequest;

final readonly class BillingPaymentConfirmed
{
    public function __construct(
        public ConfirmRequest $request,
        public string $merchant,
    ) {}
}
