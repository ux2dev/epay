<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Events;

use Ux2Dev\Epay\Billing\Request\InitRequest;

final readonly class BillingObligationChecked
{
    public function __construct(
        public InitRequest $request,
        public string $merchant,
    ) {}
}
