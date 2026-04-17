<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Events;

final readonly class NoRegPaymentCallback
{
    public function __construct(
        public string $paymentId,
        public string $deviceId,
        /** @var array<string, string> */
        public array $params,
        public string $merchant,
    ) {}
}
