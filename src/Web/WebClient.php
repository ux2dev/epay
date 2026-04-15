<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web;

use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\SigningMethod;
use Ux2Dev\Epay\Exception\ConfigurationException;
use Ux2Dev\Epay\Signing\HmacSigner;
use Ux2Dev\Epay\Signing\RsaSigner;
use Ux2Dev\Epay\Web\Notification\NotificationHandler;
use Ux2Dev\Epay\Web\Notification\NotificationResult;
use Ux2Dev\Epay\Web\Request\BankTransferRequest;
use Ux2Dev\Epay\Web\Request\DirectPaymentRequest;
use Ux2Dev\Epay\Web\Request\PaymentRequest;
use Ux2Dev\Epay\Web\Request\SimplePaymentRequest;

final class WebClient
{
    private readonly HmacSigner $hmacSigner;
    private readonly ?RsaSigner $rsaSigner;
    private readonly NotificationHandler $notificationHandler;

    public function __construct(
        private readonly MerchantConfig $config,
    ) {
        $this->hmacSigner = new HmacSigner($config->getSecret());

        if ($config->signingMethod === SigningMethod::Rsa && $config->getPrivateKey() !== null) {
            $privKey = openssl_pkey_get_private($config->getPrivateKey(), $config->getPrivateKeyPassphrase() ?? '');
            $details = openssl_pkey_get_details($privKey);
            $this->rsaSigner = new RsaSigner($config->getPrivateKey(), $details['key'], $config->getPrivateKeyPassphrase());
        } else {
            $this->rsaSigner = null;
        }

        $this->notificationHandler = new NotificationHandler($this->hmacSigner);
    }

    public function createPaymentRequest(
        string $invoice,
        string $amount,
        string $expirationDate,
        ?string $description = null,
        ?string $encoding = null,
        ?string $email = null,
        ?array $discount = null,
        ?string $urlOk = null,
        ?string $urlCancel = null,
    ): PaymentRequest {
        $this->validateInvoice($invoice);
        $this->validateAmount($amount);
        $this->validateDescription($description);

        $min = $email === null ? $this->config->merchantId : null;

        $dataString = PaymentRequest::buildDataString(
            invoice: $invoice, amount: $amount, expirationDate: $expirationDate,
            currency: $this->config->currency, min: $min, email: $email,
            description: $description, encoding: $encoding, discount: $discount,
        );

        $encoded = base64_encode($dataString);
        $checksum = $this->hmacSigner->sign($encoded);
        $signature = $this->rsaSigner?->sign($encoded);

        return new PaymentRequest(
            min: $min ?? '', invoice: $invoice, amount: $amount,
            expirationDate: $expirationDate, encoded: $encoded, checksum: $checksum,
            environment: $this->config->environment, signature: $signature,
            urlOk: $urlOk, urlCancel: $urlCancel,
        );
    }

    public function createDirectPaymentRequest(
        string $invoice,
        string $amount,
        string $expirationDate,
        string $lang = 'bg',
        ?string $description = null,
        ?string $encoding = null,
        ?string $email = null,
        ?array $discount = null,
        ?string $urlOk = null,
        ?string $urlCancel = null,
    ): DirectPaymentRequest {
        $this->validateInvoice($invoice);
        $this->validateAmount($amount);
        $this->validateDescription($description);

        $min = $email === null ? $this->config->merchantId : null;

        $dataString = PaymentRequest::buildDataString(
            invoice: $invoice, amount: $amount, expirationDate: $expirationDate,
            currency: $this->config->currency, min: $min, email: $email,
            description: $description, encoding: $encoding, discount: $discount,
        );

        $encoded = base64_encode($dataString);
        $checksum = $this->hmacSigner->sign($encoded);
        $signature = $this->rsaSigner?->sign($encoded);

        return new DirectPaymentRequest(
            min: $min ?? '', invoice: $invoice, amount: $amount,
            expirationDate: $expirationDate, encoded: $encoded, checksum: $checksum,
            environment: $this->config->environment, signature: $signature,
            urlOk: $urlOk, urlCancel: $urlCancel, lang: $lang,
        );
    }

    public function createBankTransferRequest(
        string $merchant, string $iban, string $bic, string $total,
        string $statement, string $pstatement,
        ?string $urlOk = null, ?string $urlCancel = null,
    ): BankTransferRequest {
        return new BankTransferRequest(
            merchant: $merchant, iban: $iban, bic: $bic, total: $total,
            statement: $statement, pstatement: $pstatement,
            environment: $this->config->environment, urlOk: $urlOk, urlCancel: $urlCancel,
        );
    }

    public function createSimplePaymentRequest(
        string $invoice, string $total,
        ?string $description = null, ?string $encoding = null,
        ?string $urlOk = null, ?string $urlCancel = null,
    ): SimplePaymentRequest {
        return new SimplePaymentRequest(
            min: $this->config->merchantId, invoice: $invoice, total: $total,
            environment: $this->config->environment, description: $description,
            encoding: $encoding, urlOk: $urlOk, urlCancel: $urlCancel,
        );
    }

    /** @param array<string, string> $postData */
    public function handleNotification(array $postData): NotificationResult
    {
        return $this->notificationHandler->handle($postData);
    }

    private function validateInvoice(string $invoice): void
    {
        if ($invoice === '') {
            throw new ConfigurationException('invoice must not be empty');
        }
    }

    private function validateAmount(string $amount): void
    {
        if ((float) $amount <= 0.01) {
            throw new ConfigurationException('amount must be greater than 0.01');
        }
    }

    private function validateDescription(?string $description): void
    {
        if ($description !== null && mb_strlen($description) > 100) {
            throw new ConfigurationException('description must not exceed 100 characters');
        }
    }
}
