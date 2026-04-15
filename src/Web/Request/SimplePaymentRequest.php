<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Request;

use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Exception\ConfigurationException;

final readonly class SimplePaymentRequest implements RequestInterface
{
    public function __construct(
        public string $min,
        public string $invoice,
        public string $total,
        public Environment $environment,
        public ?string $description = null,
        public ?string $encoding = null,
        public ?string $urlOk = null,
        public ?string $urlCancel = null,
    ) {
        if ($min === '') { throw new ConfigurationException('min must not be empty'); }
        if ($invoice === '') { throw new ConfigurationException('invoice must not be empty'); }
        if ((float) $total <= 0.01) { throw new ConfigurationException('total must be greater than 0.01'); }
    }

    public function getPage(): string { return 'paylogin'; }
    public function getGatewayUrl(): string { return $this->environment->gatewayUrl(); }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = ['PAGE' => $this->getPage(), 'MIN' => $this->min, 'INVOICE' => $this->invoice, 'TOTAL' => $this->total];
        if ($this->description !== null) { $data['DESCR'] = $this->description; }
        if ($this->encoding !== null) { $data['ENCODING'] = $this->encoding; }
        if ($this->urlOk !== null) { $data['URL_OK'] = $this->urlOk; }
        if ($this->urlCancel !== null) { $data['URL_CANCEL'] = $this->urlCancel; }
        return $data;
    }
}
