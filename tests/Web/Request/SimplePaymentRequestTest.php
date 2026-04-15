<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\Request\SimplePaymentRequest;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Exception\ConfigurationException;

test('toArray returns required fields', function () {
    $request = new SimplePaymentRequest(min: '1000000000', invoice: '123456', total: '22.80', environment: Environment::Development);
    $fields = $request->toArray();
    expect($fields['PAGE'])->toBe('paylogin')
        ->and($fields['MIN'])->toBe('1000000000')
        ->and($fields['INVOICE'])->toBe('123456')
        ->and($fields['TOTAL'])->toBe('22.80');
});

test('toArray includes optional fields when set', function () {
    $request = new SimplePaymentRequest(
        min: '1000000000', invoice: '123456', total: '22.80', environment: Environment::Development,
        description: 'Test payment', encoding: 'utf-8', urlOk: 'https://ok.com', urlCancel: 'https://cancel.com',
    );
    $fields = $request->toArray();
    expect($fields['DESCR'])->toBe('Test payment')
        ->and($fields['ENCODING'])->toBe('utf-8')
        ->and($fields['URL_OK'])->toBe('https://ok.com')
        ->and($fields['URL_CANCEL'])->toBe('https://cancel.com');
});

test('toArray omits optional fields when null', function () {
    $request = new SimplePaymentRequest(min: '1000000000', invoice: '123456', total: '22.80', environment: Environment::Development);
    $fields = $request->toArray();
    expect($fields)->not->toHaveKey('DESCR')->and($fields)->not->toHaveKey('ENCODING');
});

test('throws on empty min', function () {
    new SimplePaymentRequest(min: '', invoice: '123456', total: '22.80', environment: Environment::Development);
})->throws(ConfigurationException::class, 'min must not be empty');

test('throws on empty invoice', function () {
    new SimplePaymentRequest(min: '1000000000', invoice: '', total: '22.80', environment: Environment::Development);
})->throws(ConfigurationException::class, 'invoice must not be empty');

test('throws on total <= 0.01', function () {
    new SimplePaymentRequest(min: '1000000000', invoice: '123456', total: '0.00', environment: Environment::Development);
})->throws(ConfigurationException::class, 'total must be greater than 0.01');

test('has no ENCODED or CHECKSUM fields', function () {
    $request = new SimplePaymentRequest(min: '1000000000', invoice: '123456', total: '22.80', environment: Environment::Development);
    $fields = $request->toArray();
    expect($fields)->not->toHaveKey('ENCODED')->and($fields)->not->toHaveKey('CHECKSUM');
});
