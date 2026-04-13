<?php

declare(strict_types=1);

use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Enum\PaymentStatus;
use Ux2Dev\Epay\Enum\SigningMethod;
use Ux2Dev\Epay\Enum\TransactionType;

test('Currency has BGN, EUR, USD', function () {
    expect(Currency::BGN->value)->toBe('BGN')
        ->and(Currency::EUR->value)->toBe('EUR')
        ->and(Currency::USD->value)->toBe('USD')
        ->and(Currency::cases())->toHaveCount(3);
});

test('Environment has correct gateway URLs', function () {
    expect(Environment::Development->gatewayUrl())->toBe('https://demo.epay.bg/')
        ->and(Environment::Production->gatewayUrl())->toBe('https://www.epay.bg/')
        ->and(Environment::Development->oneTouchBaseUrl())->toBe('https://demo.epay.bg/xdev/api')
        ->and(Environment::Production->oneTouchBaseUrl())->toBe('https://www.epay.bg/xdev/api');
});

test('PaymentStatus has Paid, Denied, Expired', function () {
    expect(PaymentStatus::Paid->value)->toBe('PAID')
        ->and(PaymentStatus::Denied->value)->toBe('DENIED')
        ->and(PaymentStatus::Expired->value)->toBe('EXPIRED')
        ->and(PaymentStatus::cases())->toHaveCount(3);
});

test('SigningMethod has HmacSha1 and Rsa', function () {
    expect(SigningMethod::HmacSha1->value)->toBe('hmac_sha1')
        ->and(SigningMethod::Rsa->value)->toBe('rsa')
        ->and(SigningMethod::cases())->toHaveCount(2);
});

test('TransactionType has all types', function () {
    expect(TransactionType::Payment->value)->toBe('paylogin')
        ->and(TransactionType::CreditPayDirect->value)->toBe('credit_paydirect')
        ->and(TransactionType::cases())->toHaveCount(2);
});
