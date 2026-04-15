<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Request;

use Ux2Dev\Epay\Enum\Environment;

final readonly class DirectPaymentRequest implements RequestInterface
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
        public string $lang = 'bg',
    ) {}

    public function getPage(): string
    {
        return 'credit_paydirect';
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
            'LANG' => $this->lang,
            'ENCODED' => $this->encoded,
            'CHECKSUM' => $this->checksum,
        ];

        if ($this->signature !== null) { $data['SIGNATURE'] = $this->signature; }
        if ($this->urlOk !== null) { $data['URL_OK'] = $this->urlOk; }
        if ($this->urlCancel !== null) { $data['URL_CANCEL'] = $this->urlCancel; }

        return $data;
    }
}
