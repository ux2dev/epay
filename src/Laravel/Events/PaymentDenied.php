<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Events;

use Ux2Dev\Epay\Web\Notification\NotificationItem;

final readonly class PaymentDenied
{
    public function __construct(
        public NotificationItem $item,
        public string $merchant,
    ) {}
}
