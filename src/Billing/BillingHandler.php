<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing;

use Ux2Dev\Epay\Billing\Enum\BillingPaymentType;
use Ux2Dev\Epay\Billing\Enum\BillingRequestType;
use Ux2Dev\Epay\Billing\Request\ConfirmRequest;
use Ux2Dev\Epay\Billing\Request\InitRequest;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Exception\InvalidResponseException;
use Ux2Dev\Epay\Signing\HmacSigner;

final class BillingHandler
{
    private readonly HmacSigner $signer;

    public function __construct(private readonly MerchantConfig $config)
    {
        $this->signer = new HmacSigner($config->getSecret());
    }

    /** @param array<string, string> $queryParams */
    public function parseInitRequest(array $queryParams): InitRequest
    {
        $this->verifyChecksum($queryParams);
        return new InitRequest(
            idn: $queryParams['IDN'],
            merchantId: $queryParams['MERCHANTID'],
            type: BillingRequestType::from($queryParams['TYPE']),
            tid: $queryParams['TID'] ?? null,
            total: isset($queryParams['TOTAL']) ? (int) $queryParams['TOTAL'] : null,
        );
    }

    /** @param array<string, string> $queryParams */
    public function parseConfirmRequest(array $queryParams): ConfirmRequest
    {
        $this->verifyChecksum($queryParams);
        $date = \DateTimeImmutable::createFromFormat('YmdHis', $queryParams['DATE']);
        return new ConfirmRequest(
            idn: $queryParams['IDN'],
            merchantId: $queryParams['MERCHANTID'],
            tid: $queryParams['TID'],
            date: $date ?: new \DateTimeImmutable(),
            total: (int) $queryParams['TOTAL'],
            type: BillingPaymentType::from($queryParams['TYPE']),
            invoices: $queryParams['INVOICES'] ?? null,
        );
    }

    /** @param array<string, string> $queryParams */
    private function verifyChecksum(array $queryParams): void
    {
        if (!isset($queryParams['CHECKSUM'])) {
            throw new InvalidResponseException('Missing CHECKSUM field', $queryParams);
        }
        $checksum = $queryParams['CHECKSUM'];
        $params = $queryParams;
        unset($params['CHECKSUM']);
        ksort($params);
        $data = implode("\n", array_map(
            fn (string $key, string $value) => "{$key}{$value}",
            array_keys($params), array_values($params),
        ));
        if (!$this->signer->verify($data, $checksum)) {
            throw new InvalidResponseException('CHECKSUM verification failed', $queryParams);
        }
    }
}
