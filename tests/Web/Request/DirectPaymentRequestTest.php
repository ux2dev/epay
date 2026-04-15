<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\Request\DirectPaymentRequest;
use Ux2Dev\Epay\Enum\Environment;

test('getPage returns credit_paydirect', function () {
    $request = new DirectPaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        encoded: 'dGVzdA==', checksum: 'abc123', environment: Environment::Development,
    );
    expect($request->getPage())->toBe('credit_paydirect');
});

test('toArray includes PAGE as credit_paydirect', function () {
    $request = new DirectPaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        encoded: 'dGVzdA==', checksum: 'abc123', environment: Environment::Development,
    );
    expect($request->toArray()['PAGE'])->toBe('credit_paydirect');
});

test('toArray includes LANG when set', function () {
    $request = new DirectPaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        encoded: 'dGVzdA==', checksum: 'abc123', environment: Environment::Development, lang: 'en',
    );
    expect($request->toArray()['LANG'])->toBe('en');
});

test('toArray defaults LANG to bg', function () {
    $request = new DirectPaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        encoded: 'dGVzdA==', checksum: 'abc123', environment: Environment::Development,
    );
    expect($request->toArray()['LANG'])->toBe('bg');
});

test('toArray includes all standard fields', function () {
    $request = new DirectPaymentRequest(
        min: '1000000000', invoice: '123456', amount: '22.80', expirationDate: '01.08.2026',
        encoded: 'dGVzdA==', checksum: 'abc123', environment: Environment::Development,
        signature: 'sig', urlOk: 'https://ok.com', urlCancel: 'https://cancel.com', lang: 'en',
    );
    $fields = $request->toArray();
    expect($fields)->toHaveKeys(['PAGE', 'ENCODED', 'CHECKSUM', 'SIGNATURE', 'URL_OK', 'URL_CANCEL', 'LANG']);
});
