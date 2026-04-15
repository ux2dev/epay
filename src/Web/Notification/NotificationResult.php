<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Notification;

final class NotificationResult
{
    /** @param NotificationItem[] $items */
    public function __construct(private readonly array $items) {}

    /** @return NotificationItem[] */
    public function items(): array { return $this->items; }

    public function toHttpResponse(): string
    {
        $lines = [];
        foreach ($this->items as $item) {
            $status = $item->getResponseStatus();
            if ($status !== null) {
                $lines[] = "INVOICE={$item->invoice}:STATUS={$status}";
            }
        }
        return implode("\n", $lines);
    }
}
