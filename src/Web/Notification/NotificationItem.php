<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Notification;

use Ux2Dev\Epay\Enum\PaymentStatus;

class NotificationItem
{
    private ?string $responseStatus = null;

    public function __construct(
        public readonly string $invoice,
        public readonly PaymentStatus $status,
        public readonly ?\DateTimeImmutable $payTime = null,
        public readonly ?string $stan = null,
        public readonly ?string $bcode = null,
        public readonly ?string $amount = null,
        public readonly ?string $bin = null,
    ) {}

    public function acknowledge(): void { $this->responseStatus = 'OK'; }
    public function reject(): void { $this->responseStatus = 'ERR'; }
    public function notFound(): void { $this->responseStatus = 'NO'; }
    public function getResponseStatus(): ?string { return $this->responseStatus; }
}
