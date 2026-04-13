<?php
declare(strict_types=1);
use Ux2Dev\Epay\Billing\Enum\BillingStatus;
use Ux2Dev\Epay\Billing\Enum\BillingRequestType;
use Ux2Dev\Epay\Billing\Enum\BillingPaymentType;

test('BillingStatus has all codes', function () {
    expect(BillingStatus::Success->value)->toBe('00')
        ->and(BillingStatus::InvalidAmount->value)->toBe('13')
        ->and(BillingStatus::InvalidSubscriber->value)->toBe('14')
        ->and(BillingStatus::NoObligation->value)->toBe('62')
        ->and(BillingStatus::Unavailable->value)->toBe('80')
        ->and(BillingStatus::InvalidChecksum->value)->toBe('93')
        ->and(BillingStatus::Duplicate->value)->toBe('94')
        ->and(BillingStatus::GeneralError->value)->toBe('96')
        ->and(BillingStatus::cases())->toHaveCount(8);
});

test('BillingRequestType has CHECK, BILLING, DEPOSIT', function () {
    expect(BillingRequestType::Check->value)->toBe('CHECK')
        ->and(BillingRequestType::Billing->value)->toBe('BILLING')
        ->and(BillingRequestType::Deposit->value)->toBe('DEPOSIT')
        ->and(BillingRequestType::cases())->toHaveCount(3);
});

test('BillingPaymentType has BILLING, PARTIAL, DEPOSIT', function () {
    expect(BillingPaymentType::Billing->value)->toBe('BILLING')
        ->and(BillingPaymentType::Partial->value)->toBe('PARTIAL')
        ->and(BillingPaymentType::Deposit->value)->toBe('DEPOSIT')
        ->and(BillingPaymentType::cases())->toHaveCount(3);
});
