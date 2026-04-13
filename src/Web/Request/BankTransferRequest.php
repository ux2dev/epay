<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Request;

use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Exception\ConfigurationException;

final readonly class BankTransferRequest implements RequestInterface
{
    public function __construct(
        public string $merchant,
        public string $iban,
        public string $bic,
        public string $total,
        public string $statement,
        public string $pstatement,
        public Environment $environment,
        public ?string $urlOk = null,
        public ?string $urlCancel = null,
    ) {
        if ($merchant === '') {
            throw new ConfigurationException('merchant must not be empty');
        }
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{4,30}$/', $iban)) {
            throw new ConfigurationException('IBAN format is invalid');
        }
        if (!preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
            throw new ConfigurationException('BIC format is invalid');
        }
        if ((float) $total <= 0.01) {
            throw new ConfigurationException('total must be greater than 0.01');
        }
        if (!preg_match('/^\d{6}$/', $pstatement)) {
            throw new ConfigurationException('pstatement must be exactly 6 digits');
        }
    }

    public function getPage(): string { return 'paylogin'; }
    public function getGatewayUrl(): string { return $this->environment->gatewayUrl(); }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = [
            'PAGE' => $this->getPage(),
            'MERCHANT' => $this->merchant,
            'IBAN' => $this->iban,
            'BIC' => $this->bic,
            'TOTAL' => $this->total,
            'STATEMENT' => $this->statement,
            'PSTATEMENT' => $this->pstatement,
        ];
        if ($this->urlOk !== null) { $data['URL_OK'] = $this->urlOk; }
        if ($this->urlCancel !== null) { $data['URL_CANCEL'] = $this->urlCancel; }
        return $data;
    }
}
