<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\Notification\NotificationItem;
use Ux2Dev\Epay\Web\Notification\NotificationResult;
use Ux2Dev\Epay\Enum\PaymentStatus;

test('NotificationItem holds parsed data for PAID', function () {
    $item = new NotificationItem(
        invoice: '123456', status: PaymentStatus::Paid,
        payTime: new DateTimeImmutable('2026-04-13 12:30:00'), stan: '591535', bcode: 'A1B2C3',
    );
    expect($item->invoice)->toBe('123456')
        ->and($item->status)->toBe(PaymentStatus::Paid)
        ->and($item->payTime)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($item->stan)->toBe('591535')
        ->and($item->bcode)->toBe('A1B2C3')
        ->and($item->amount)->toBeNull()
        ->and($item->bin)->toBeNull();
});

test('NotificationItem holds data for DENIED', function () {
    $item = new NotificationItem(invoice: '123457', status: PaymentStatus::Denied);
    expect($item->invoice)->toBe('123457')
        ->and($item->status)->toBe(PaymentStatus::Denied)
        ->and($item->payTime)->toBeNull()
        ->and($item->stan)->toBeNull();
});

test('NotificationItem holds discount data', function () {
    $item = new NotificationItem(
        invoice: '123456', status: PaymentStatus::Paid,
        payTime: new DateTimeImmutable('2026-04-13'), stan: '591535', bcode: 'A1B2C3',
        amount: '20.00', bin: '411111',
    );
    expect($item->amount)->toBe('20.00')->and($item->bin)->toBe('411111');
});

test('acknowledge sets response to OK', function () {
    $item = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    $item->acknowledge();
    expect($item->getResponseStatus())->toBe('OK');
});

test('reject sets response to ERR', function () {
    $item = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    $item->reject();
    expect($item->getResponseStatus())->toBe('ERR');
});

test('notFound sets response to NO', function () {
    $item = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    $item->notFound();
    expect($item->getResponseStatus())->toBe('NO');
});

test('default response status is null', function () {
    $item = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    expect($item->getResponseStatus())->toBeNull();
});

test('NotificationResult collects items', function () {
    $item1 = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    $item2 = new NotificationItem(invoice: '123457', status: PaymentStatus::Denied);
    $item1->acknowledge();
    $item2->notFound();
    $result = new NotificationResult([$item1, $item2]);
    expect($result->items())->toHaveCount(2);
});

test('NotificationResult toHttpResponse generates correct format', function () {
    $item1 = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    $item2 = new NotificationItem(invoice: '123457', status: PaymentStatus::Denied);
    $item3 = new NotificationItem(invoice: '123458', status: PaymentStatus::Expired);
    $item1->acknowledge();
    $item2->reject();
    $item3->notFound();
    $result = new NotificationResult([$item1, $item2, $item3]);
    expect($result->toHttpResponse())->toBe("INVOICE=123456:STATUS=OK\nINVOICE=123457:STATUS=ERR\nINVOICE=123458:STATUS=NO");
});

test('NotificationResult toHttpResponse skips items without response', function () {
    $item1 = new NotificationItem(invoice: '123456', status: PaymentStatus::Paid);
    $item2 = new NotificationItem(invoice: '123457', status: PaymentStatus::Denied);
    $item1->acknowledge();
    $result = new NotificationResult([$item1, $item2]);
    expect($result->toHttpResponse())->toBe("INVOICE=123456:STATUS=OK");
});
