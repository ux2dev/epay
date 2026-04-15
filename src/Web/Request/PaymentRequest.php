<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Request;

use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;

final readonly class PaymentRequest implements RequestInterface
{
    public function __construct(
        public string $min,
        public string $invoice,
        public string $amount,
        public string $expirationDate,
        public string $encoded,
        public string $checksum,
        public Environment $environment,
        public ?string $signature = null,
        public ?string $urlOk = null,
        public ?string $urlCancel = null,
    ) {}

    public function getPage(): string
    {
        return 'paylogin';
    }

    public function getGatewayUrl(): string
    {
        return $this->environment->gatewayUrl();
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = [
            'PAGE' => $this->getPage(),
            'ENCODED' => $this->encoded,
            'CHECKSUM' => $this->checksum,
        ];

        if ($this->signature !== null) {
            $data['SIGNATURE'] = $this->signature;
        }
        if ($this->urlOk !== null) {
            $data['URL_OK'] = $this->urlOk;
        }
        if ($this->urlCancel !== null) {
            $data['URL_CANCEL'] = $this->urlCancel;
        }

        return $data;
    }

    /**
     * @param string[] $discount
     */
    public static function buildDataString(
        string $invoice,
        string $amount,
        string $expirationDate,
        Currency $currency,
        ?string $min = null,
        ?string $email = null,
        ?string $description = null,
        ?string $encoding = null,
        ?array $discount = null,
    ): string {
        $lines = [];

        if ($min !== null) {
            $lines[] = "MIN={$min}";
        }
        if ($email !== null) {
            $lines[] = "EMAIL={$email}";
        }

        $lines[] = "INVOICE={$invoice}";
        $lines[] = "AMOUNT={$amount}";
        $lines[] = "EXP_TIME={$expirationDate}";
        $lines[] = "CURRENCY={$currency->value}";

        if ($description !== null) {
            $lines[] = "DESCR={$description}";
        }
        if ($encoding !== null) {
            $lines[] = "ENCODING={$encoding}";
        }
        if ($discount !== null) {
            foreach ($discount as $rule) {
                $lines[] = "DISCOUNT={$rule}";
            }
        }

        return implode("\n", $lines);
    }
}
