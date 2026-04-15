<?php
declare(strict_types=1);
use Ux2Dev\Epay\Billing\Request\InitRequest;
use Ux2Dev\Epay\Billing\Request\ConfirmRequest;
use Ux2Dev\Epay\Billing\Enum\BillingRequestType;
use Ux2Dev\Epay\Billing\Enum\BillingPaymentType;

test('InitRequest holds CHECK data', function () {
    $r = new InitRequest(idn: '12345', merchantId: '0000334', type: BillingRequestType::Check);
    expect($r->idn)->toBe('12345')->and($r->type)->toBe(BillingRequestType::Check)->and($r->tid)->toBeNull()->and($r->total)->toBeNull();
});

test('InitRequest holds BILLING data with TID', function () {
    $r = new InitRequest(idn: '12345', merchantId: '0000334', type: BillingRequestType::Billing, tid: '20260413121650591535700020');
    expect($r->type)->toBe(BillingRequestType::Billing)->and($r->tid)->toBe('20260413121650591535700020');
});

test('InitRequest holds DEPOSIT data with TOTAL', function () {
    $r = new InitRequest(idn: '12345', merchantId: '0000334', type: BillingRequestType::Deposit, tid: '20260413121650591535700020', total: 16600);
    expect($r->type)->toBe(BillingRequestType::Deposit)->and($r->total)->toBe(16600);
});

test('ConfirmRequest holds BILLING data', function () {
    $r = new ConfirmRequest(idn: '12345', merchantId: '0000334', tid: '20260413121650591535700020', date: new DateTimeImmutable('2026-04-13 18:12:26'), total: 16600, type: BillingPaymentType::Billing);
    expect($r->idn)->toBe('12345')->and($r->total)->toBe(16600)->and($r->type)->toBe(BillingPaymentType::Billing)->and($r->invoices)->toBeNull();
});

test('ConfirmRequest holds multi-invoice data', function () {
    $r = new ConfirmRequest(idn: '12345', merchantId: '0000334', tid: '20260413121650591535700020', date: new DateTimeImmutable('2026-04-13'), total: 7800, type: BillingPaymentType::Billing, invoices: '12345.001,12345.002');
    expect($r->invoices)->toBe('12345.001,12345.002');
});

test('ConfirmRequest holds PARTIAL type', function () {
    $r = new ConfirmRequest(idn: '12345', merchantId: '0000334', tid: '20260413121650591535700020', date: new DateTimeImmutable('2026-04-13'), total: 100, type: BillingPaymentType::Partial);
    expect($r->type)->toBe(BillingPaymentType::Partial);
});

test('ConfirmRequest holds DEPOSIT type', function () {
    $r = new ConfirmRequest(idn: '12345', merchantId: '0000334', tid: '20260413121650591535700020', date: new DateTimeImmutable('2026-04-13'), total: 5000, type: BillingPaymentType::Deposit);
    expect($r->type)->toBe(BillingPaymentType::Deposit);
});
