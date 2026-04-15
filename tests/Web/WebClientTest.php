<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\WebClient;
use Ux2Dev\Epay\Web\Request\PaymentRequest;
use Ux2Dev\Epay\Web\Request\DirectPaymentRequest;
use Ux2Dev\Epay\Web\Request\BankTransferRequest;
use Ux2Dev\Epay\Web\Request\SimplePaymentRequest;
use Ux2Dev\Epay\Web\Notification\NotificationResult;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Enum\PaymentStatus;
use Ux2Dev\Epay\Enum\SigningMethod;
use Ux2Dev\Epay\Exception\ConfigurationException;

beforeEach(function () {
    $this->config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'testsecret',
        environment: Environment::Development,
    );
    $this->web = new WebClient($this->config);
});

test('createPaymentRequest returns PaymentRequest with valid ENCODED and CHECKSUM', function () {
    $request = $this->web->createPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
    );

    expect($request)->toBeInstanceOf(PaymentRequest::class)
        ->and($request->encoded)->not->toBeEmpty()
        ->and($request->checksum)->toMatch('/^[a-f0-9]{40}$/')
        ->and($request->getGatewayUrl())->toBe('https://demo.epay.bg/');

    $decoded = base64_decode($request->encoded);
    expect($decoded)->toContain('MIN=1000000000')
        ->and($decoded)->toContain('INVOICE=123456')
        ->and($decoded)->toContain('AMOUNT=22.80')
        ->and($decoded)->toContain('EXP_TIME=01.08.2026')
        ->and($decoded)->toContain('CURRENCY=EUR');

    $expectedChecksum = hash_hmac('sha1', $request->encoded, 'testsecret');
    expect($request->checksum)->toBe($expectedChecksum);
});

test('createPaymentRequest with all optional fields', function () {
    $request = $this->web->createPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        description: 'Test payment', encoding: 'utf-8',
        urlOk: 'https://ok.com', urlCancel: 'https://cancel.com',
    );

    $decoded = base64_decode($request->encoded);
    expect($decoded)->toContain('DESCR=Test payment')
        ->and($decoded)->toContain('ENCODING=utf-8');
    $fields = $request->toArray();
    expect($fields['URL_OK'])->toBe('https://ok.com')
        ->and($fields['URL_CANCEL'])->toBe('https://cancel.com');
});

test('createPaymentRequest with email instead of MIN', function () {
    $request = $this->web->createPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        email: 'merchant@test.bg',
    );

    $decoded = base64_decode($request->encoded);
    expect($decoded)->toContain('EMAIL=merchant@test.bg')
        ->and($decoded)->not->toContain('MIN=');
});

test('createPaymentRequest with RSA signing includes SIGNATURE', function () {
    $privateKey = file_get_contents(__DIR__ . '/../fixtures/test_private_key.pem');
    $config = new MerchantConfig(
        merchantId: '1000000000', secret: 'testsecret', environment: Environment::Development,
        signingMethod: SigningMethod::Rsa, privateKey: $privateKey,
    );
    $web = new WebClient($config);
    $request = $web->createPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
    );

    expect($request->signature)->not->toBeNull()
        ->and($request->checksum)->toMatch('/^[a-f0-9]{40}$/');
});

test('createDirectPaymentRequest returns DirectPaymentRequest', function () {
    $request = $this->web->createDirectPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026', lang: 'en',
    );

    expect($request)->toBeInstanceOf(DirectPaymentRequest::class)
        ->and($request->toArray()['PAGE'])->toBe('credit_paydirect')
        ->and($request->toArray()['LANG'])->toBe('en')
        ->and($request->checksum)->toMatch('/^[a-f0-9]{40}$/');
});

test('createBankTransferRequest returns BankTransferRequest', function () {
    $request = $this->web->createBankTransferRequest(
        merchant: 'Test Company', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD',
        total: '22.80', statement: 'Monthly fee', pstatement: '123456',
    );

    expect($request)->toBeInstanceOf(BankTransferRequest::class)
        ->and($request->toArray()['MERCHANT'])->toBe('Test Company');
});

test('createSimplePaymentRequest returns SimplePaymentRequest', function () {
    $request = $this->web->createSimplePaymentRequest(
        invoice: '123456', total: '22.80',
    );

    expect($request)->toBeInstanceOf(SimplePaymentRequest::class)
        ->and($request->toArray()['MIN'])->toBe('1000000000');
});

test('throws on empty invoice', function () {
    $this->web->createPaymentRequest(invoice: '', amount: '22.80', expirationDate: '01.08.2026');
})->throws(ConfigurationException::class, 'invoice must not be empty');

test('throws on invalid amount', function () {
    $this->web->createPaymentRequest(invoice: '123456', amount: '0.00', expirationDate: '01.08.2026');
})->throws(ConfigurationException::class, 'amount must be greater than 0.01');

test('throws on description over 100 chars', function () {
    $this->web->createPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        description: str_repeat('x', 101),
    );
})->throws(ConfigurationException::class, 'description must not exceed 100 characters');

test('handleNotification parses valid callback', function () {
    $data = "INVOICE=123456:STATUS=PAID:PAY_TIME=20260413123000:STAN=591535:BCODE=A1B2C3";
    $encoded = base64_encode($data);
    $checksum = hash_hmac('sha1', $encoded, 'testsecret');

    $result = $this->web->handleNotification(['ENCODED' => $encoded, 'CHECKSUM' => $checksum]);

    expect($result)->toBeInstanceOf(NotificationResult::class)
        ->and($result->items())->toHaveCount(1)
        ->and($result->items()[0]->status)->toBe(PaymentStatus::Paid);
});

test('HMAC mode has no SIGNATURE', function () {
    $request = $this->web->createPaymentRequest(
        invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
    );
    expect($request->signature)->toBeNull()
        ->and($request->toArray())->not->toHaveKey('SIGNATURE');
});
