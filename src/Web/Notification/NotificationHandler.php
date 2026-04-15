<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Notification;

use Ux2Dev\Epay\Enum\PaymentStatus;
use Ux2Dev\Epay\Exception\InvalidResponseException;
use Ux2Dev\Epay\Signing\HmacSigner;

final readonly class NotificationHandler
{
    public function __construct(
        private HmacSigner $signer,
    ) {}

    /** @param array<string, string> $postData */
    public function handle(array $postData): NotificationResult
    {
        if (!isset($postData['ENCODED'])) {
            throw new InvalidResponseException('Missing ENCODED field in notification', $postData);
        }
        if (!isset($postData['CHECKSUM'])) {
            throw new InvalidResponseException('Missing CHECKSUM field in notification', $postData);
        }

        $encoded = $postData['ENCODED'];
        $checksum = $postData['CHECKSUM'];

        if (!$this->signer->verify($encoded, $checksum)) {
            throw new InvalidResponseException('CHECKSUM verification failed', $postData);
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new InvalidResponseException('Failed to decode ENCODED payload', $postData);
        }

        $lines = array_filter(explode("\n", $decoded), fn (string $line) => $line !== '');
        $items = [];

        foreach ($lines as $line) {
            $items[] = $this->parseLine($line);
        }

        return new NotificationResult($items);
    }

    private function parseLine(string $line): NotificationItem
    {
        $parts = [];
        foreach (explode(':', $line) as $segment) {
            $eqPos = strpos($segment, '=');
            if ($eqPos !== false) {
                $key = substr($segment, 0, $eqPos);
                $value = substr($segment, $eqPos + 1);
                $parts[$key] = $value;
            }
        }

        $status = PaymentStatus::from($parts['STATUS']);
        $payTime = null;

        if (isset($parts['PAY_TIME'])) {
            $payTime = \DateTimeImmutable::createFromFormat('YmdHis', $parts['PAY_TIME']);
            if ($payTime === false) {
                $payTime = null;
            }
        }

        return new NotificationItem(
            invoice: $parts['INVOICE'],
            status: $status,
            payTime: $payTime,
            stan: $parts['STAN'] ?? null,
            bcode: $parts['BCODE'] ?? null,
            amount: $parts['AMOUNT'] ?? null,
            bin: $parts['BIN'] ?? null,
        );
    }
}
