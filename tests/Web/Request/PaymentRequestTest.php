<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\Request\PaymentRequest;
use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;

test('toArray returns correct form fields', function () {
    $request = new PaymentRequest(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        encoded: 'dGVzdA==',
        checksum: 'abc123',
        environment: Environment::Development,
    );

    $fields = $request->toArray();

    expect($fields['PAGE'])->toBe('paylogin')
        ->and($fields['ENCODED'])->toBe('dGVzdA==')
        ->and($fields['CHECKSUM'])->toBe('abc123')
        ->and($fields)->not->toHaveKey('LANG');
});

test('toArray includes URL_OK and URL_CANCEL when set', function () {
    $request = new PaymentRequest(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        encoded: 'dGVzdA==',
        checksum: 'abc123',
        environment: Environment::Development,
        urlOk: 'https://example.com/ok',
        urlCancel: 'https://example.com/cancel',
    );

    $fields = $request->toArray();

    expect($fields['URL_OK'])->toBe('https://example.com/ok')
        ->and($fields['URL_CANCEL'])->toBe('https://example.com/cancel');
});

test('toArray includes SIGNATURE when set', function () {
    $request = new PaymentRequest(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        encoded: 'dGVzdA==',
        checksum: 'abc123',
        environment: Environment::Production,
        signature: 'rsasig456',
    );

    expect($request->toArray()['SIGNATURE'])->toBe('rsasig456');
});

test('toArray omits SIGNATURE when null', function () {
    $request = new PaymentRequest(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        encoded: 'dGVzdA==',
        checksum: 'abc123',
        environment: Environment::Development,
    );

    expect($request->toArray())->not->toHaveKey('SIGNATURE');
});

test('getGatewayUrl returns environment URL', function () {
    $dev = new PaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80',
        expirationDate: '01.08.2026', encoded: 'x', checksum: 'y',
        environment: Environment::Development,
    );
    $prod = new PaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80',
        expirationDate: '01.08.2026', encoded: 'x', checksum: 'y',
        environment: Environment::Production,
    );

    expect($dev->getGatewayUrl())->toBe('https://demo.epay.bg/')
        ->and($prod->getGatewayUrl())->toBe('https://www.epay.bg/');
});

test('getPage returns paylogin', function () {
    $request = new PaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80',
        expirationDate: '01.08.2026', encoded: 'x', checksum: 'y',
        environment: Environment::Development,
    );

    expect($request->getPage())->toBe('paylogin');
});

test('buildDataString creates correct newline-separated format', function () {
    $data = PaymentRequest::buildDataString(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        currency: Currency::EUR,
    );

    expect($data)->toContain('MIN=1000000000')
        ->and($data)->toContain('INVOICE=123456')
        ->and($data)->toContain('AMOUNT=22.80')
        ->and($data)->toContain('EXP_TIME=01.08.2026')
        ->and($data)->toContain('CURRENCY=EUR');
});

test('buildDataString includes optional fields when set', function () {
    $data = PaymentRequest::buildDataString(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        currency: Currency::BGN,
        description: 'Test payment',
        encoding: 'utf-8',
    );

    expect($data)->toContain('DESCR=Test payment')
        ->and($data)->toContain('ENCODING=utf-8');
});

test('buildDataString omits optional fields when null', function () {
    $data = PaymentRequest::buildDataString(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        currency: Currency::EUR,
    );

    expect($data)->not->toContain('DESCR=')
        ->and($data)->not->toContain('ENCODING=');
});

test('buildDataString supports EMAIL instead of MIN', function () {
    $data = PaymentRequest::buildDataString(
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        currency: Currency::EUR,
        email: 'merchant@test.bg',
    );

    expect($data)->toContain('EMAIL=merchant@test.bg')
        ->and($data)->not->toContain('MIN=');
});

test('buildDataString includes DISCOUNT when set', function () {
    $data = PaymentRequest::buildDataString(
        min: '1000000000',
        invoice: '123456',
        amount: '22.80',
        expirationDate: '01.08.2026',
        currency: Currency::EUR,
        discount: ['411111,422222:20.00', '433333:19.50'],
    );

    expect($data)->toContain('DISCOUNT=411111,422222:20.00')
        ->and($data)->toContain('DISCOUNT=433333:19.50');
});
