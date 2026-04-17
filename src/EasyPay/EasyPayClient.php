<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\EasyPay;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\EasyPay\Response\EasyPayCodeResponse;
use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Signing\HmacSigner;
use Ux2Dev\Epay\Web\Request\PaymentRequest;

/**
 * Server-to-server client for generating EasyPay access codes.
 *
 * Calls `<gateway>/ezp/reg_bill.cgi` and returns the 10-digit code the
 * customer takes to any EasyPay cash desk. Response is plain text in
 * windows-1251 with KEY=VALUE lines.
 */
final class EasyPayClient
{
    private readonly HmacSigner $signer;

    public function __construct(
        private readonly MerchantConfig $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        $this->signer = new HmacSigner($config->getSecret());
    }

    public function createCode(
        string $invoice,
        string $amount,
        string $expirationDate,
        ?string $email = null,
        ?string $description = null,
        ?string $encoding = 'utf-8',
        ?Currency $currency = null,
    ): EasyPayCodeResponse {
        $data = PaymentRequest::buildDataString(
            invoice: $invoice,
            amount: $amount,
            expirationDate: $expirationDate,
            currency: $currency ?? $this->config->currency,
            min: $this->config->merchantId,
            email: $email,
            description: $description,
            encoding: $encoding,
        );

        $encoded = base64_encode($data);
        $checksum = $this->signer->sign($encoded);

        $url = $this->config->environment->gatewayUrl() . 'ezp/reg_bill.cgi?'
            . http_build_query(['ENCODED' => $encoded, 'CHECKSUM' => $checksum]);

        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        $body = (string) $response->getBody();
        $parsed = $this->parsePlainText($body);

        return EasyPayCodeResponse::fromParsed($parsed);
    }

    /** @return array<string, string> */
    private function parsePlainText(string $body): array
    {
        // reg_bill.cgi returns plain text in windows-1251.
        $utf8 = @iconv('windows-1251', 'UTF-8//IGNORE', $body);
        $text = $utf8 === false ? $body : $utf8;

        $result = [];
        foreach (preg_split('/\r?\n/', trim($text)) as $line) {
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $result[trim($key)] = trim($value);
        }
        return $result;
    }
}
