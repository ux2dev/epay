<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\OneTouch;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Exception\EpayException;
use Ux2Dev\Epay\OneTouch\Response\CodeResponse;
use Ux2Dev\Epay\OneTouch\Response\NoRegPaymentResponse;
use Ux2Dev\Epay\OneTouch\Response\PaymentResponse;
use Ux2Dev\Epay\OneTouch\Response\TokenResponse;
use Ux2Dev\Epay\OneTouch\Response\UserInfoResponse;
use Ux2Dev\Epay\Signing\HmacSigner;

final class OneTouchClient
{
    private readonly string $apiUrl;

    private readonly string $mobileUrl;

    private readonly HmacSigner $signer;

    public function __construct(
        private readonly MerchantConfig $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        $this->apiUrl = $config->environment->oneTouchApiUrl();
        $this->mobileUrl = $config->environment->oneTouchMobileUrl();
        $this->signer = new HmacSigner($config->getSecret());
    }

    public function getAuthorizationUrl(
        string $deviceId,
        string $key,
        ?int $userType = null,
        ?string $deviceName = null,
        ?string $brand = null,
        ?string $os = null,
        ?string $model = null,
        ?string $osVersion = null,
        ?string $phone = null,
    ): string {
        $params = [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'KEY' => $key,
        ];

        if ($userType !== null) {
            $params['UTYPE'] = (string) $userType;
        }
        if ($deviceName !== null) {
            $params['DEVICE_NAME'] = $deviceName;
        }
        if ($brand !== null) {
            $params['BRAND'] = $brand;
        }
        if ($os !== null) {
            $params['OS'] = $os;
        }
        if ($model !== null) {
            $params['MODEL'] = $model;
        }
        if ($osVersion !== null) {
            $params['OS_VERSION'] = $osVersion;
        }
        if ($phone !== null) {
            $params['PHONE'] = $phone;
        }

        return $this->mobileUrl . '/start?' . http_build_query($params);
    }

    public function getCode(string $deviceId, string $key): CodeResponse
    {
        $data = $this->get('/api/code/get', [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'KEY' => $key,
        ]);

        return CodeResponse::fromArray($data);
    }

    public function getToken(string $deviceId, string $code): TokenResponse
    {
        $data = $this->get('/api/token/get', [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'CODE' => $code,
        ]);

        return TokenResponse::fromArray($data);
    }

    public function invalidateToken(string $deviceId, string $token): void
    {
        $this->get('/api/token/invalidate', [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'TOKEN' => $token,
        ]);
    }

    public function getUserInfo(
        string $deviceId,
        string $token,
        bool $withPaymentInstruments = false,
    ): UserInfoResponse {
        $params = [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'TOKEN' => $token,
        ];

        if ($withPaymentInstruments) {
            $params['PINS'] = '1';
        }

        $data = $this->get('/user/info', $params);

        return UserInfoResponse::fromArray($data);
    }

    public function initPayment(string $deviceId, string $token): PaymentResponse
    {
        $data = $this->post('/payment/init', [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'TOKEN' => $token,
            'TYPE' => 'send',
        ]);

        return PaymentResponse::fromArray($data);
    }

    public function checkPayment(
        string $deviceId,
        string $token,
        string $paymentId,
        int $amount,
        string $recipient,
        string $recipientType,
        string $description,
        string $reason,
        string $paymentInstrumentId,
        string $show,
    ): PaymentResponse {
        $data = $this->post('/payment/check', $this->paymentParams(
            $deviceId, $token, $paymentId, $amount, $recipient,
            $recipientType, $description, $reason, $paymentInstrumentId, $show,
        ));

        return PaymentResponse::fromArray($data);
    }

    public function sendPayment(
        string $deviceId,
        string $token,
        string $paymentId,
        int $amount,
        string $recipient,
        string $recipientType,
        string $description,
        string $reason,
        string $paymentInstrumentId,
        string $show,
    ): PaymentResponse {
        $data = $this->post('/payment/send/user', $this->paymentParams(
            $deviceId, $token, $paymentId, $amount, $recipient,
            $recipientType, $description, $reason, $paymentInstrumentId, $show,
        ));

        return PaymentResponse::fromArray($data);
    }

    public function getPaymentStatus(
        string $deviceId,
        string $token,
        string $paymentId,
    ): PaymentResponse {
        $data = $this->post('/payment/send/status', [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'TOKEN' => $token,
            'ID' => $paymentId,
        ]);

        return PaymentResponse::fromArray($data);
    }

    public function createNoRegPaymentUrl(
        string $deviceId,
        string $id,
        int $amount,
        string $recipient,
        string $recipientType,
        string $description,
        string $reason,
        bool $saveCard = false,
    ): string {
        $params = [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'ID' => $id,
            'AMOUNT' => (string) $amount,
            'RCPT' => $recipient,
            'RCPT_TYPE' => $recipientType,
            'DESCRIPTION' => $description,
            'REASON' => $reason,
        ];

        if ($saveCard) {
            $params['SAVECARD'] = '1';
        }

        $params['CHECKSUM'] = $this->generateChecksum($params);

        return $this->mobileUrl . '/payment/noreg/send?' . http_build_query($params);
    }

    public function getNoRegPaymentStatus(
        string $deviceId,
        string $paymentId,
        int $amount,
        string $recipient,
        string $recipientType,
        string $description,
        string $reason,
    ): NoRegPaymentResponse {
        // NoReg status signs with CHECKSUM (trailing newline) over the same
        // set of params used at create, not APPCHECK. Path is double-`/api/`.
        $params = [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'ID' => $paymentId,
            'AMOUNT' => (string) $amount,
            'RCPT' => $recipient,
            'RCPT_TYPE' => $recipientType,
            'DESCRIPTION' => $description,
            'REASON' => $reason,
        ];
        $params['CHECKSUM'] = $this->generateChecksum($params);

        $url = $this->apiUrl . '/api/payment/noreg/send/status?' . http_build_query($params);
        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);
        $data = $this->parseResponse($response);

        return NoRegPaymentResponse::fromArray($data);
    }

    /** @return array<string, string> */
    private function paymentParams(
        string $deviceId,
        string $token,
        string $paymentId,
        int $amount,
        string $recipient,
        string $recipientType,
        string $description,
        string $reason,
        string $paymentInstrumentId,
        string $show,
    ): array {
        return [
            'APPID' => $this->config->merchantId,
            'DEVICEID' => $deviceId,
            'TOKEN' => $token,
            'ID' => $paymentId,
            'AMOUNT' => (string) $amount,
            'RCPT' => $recipient,
            'RCPT_TYPE' => $recipientType,
            'DESCRIPTION' => $description,
            'REASON' => $reason,
            'PINS' => $paymentInstrumentId,
            'SHOW' => $show,
        ];
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $params): array
    {
        $params['APPCHECK'] = $this->generateAppcheck($params);
        $url = $this->apiUrl . $path . '?' . http_build_query($params);
        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        return $this->parseResponse($response);
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $params): array
    {
        $params['APPCHECK'] = $this->generateAppcheck($params);
        $url = $this->apiUrl . $path;
        $body = $this->streamFactory->createStream(http_build_query($params));
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
        $response = $this->httpClient->sendRequest($request);

        return $this->parseResponse($response);
    }

    /** @return array<string, mixed> */
    private function parseResponse(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (($data['status'] ?? '') !== 'OK') {
            throw new EpayException(
                'ePay One Touch error: ' . ($data['error'] ?? $data['status'] ?? 'unknown')
            );
        }

        return $data;
    }

    private function generateAppcheck(array $params): string
    {
        ksort($params);
        $data = implode("\n", array_map(
            fn(string $key, string $value) => "{$key}{$value}",
            array_keys($params),
            array_values($params),
        ));

        return $this->signer->sign($data);
    }

    /** NoReg CHECKSUM uses trailing newline per ePay docs. */
    private function generateChecksum(array $params): string
    {
        ksort($params);
        $data = implode("\n", array_map(
            fn(string $key, string $value) => "{$key}{$value}",
            array_keys($params),
            array_values($params),
        )) . "\n";

        return $this->signer->sign($data);
    }
}
