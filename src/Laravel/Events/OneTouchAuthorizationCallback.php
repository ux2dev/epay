<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Events;

final readonly class OneTouchAuthorizationCallback
{
    public function __construct(
        public string $deviceId,
        /** @var array<string, string> */
        public array $params,
        public string $merchant,
    ) {}
}
