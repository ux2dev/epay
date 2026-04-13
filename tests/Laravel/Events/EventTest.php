<?php
declare(strict_types=1);
use Ux2Dev\Epay\Laravel\Events\PaymentReceived;
use Ux2Dev\Epay\Laravel\Events\PaymentDenied;
use Ux2Dev\Epay\Laravel\Events\PaymentExpired;
use Ux2Dev\Epay\Laravel\Events\BillingObligationChecked;
use Ux2Dev\Epay\Laravel\Events\BillingPaymentConfirmed;
use Ux2Dev\Epay\Web\Notification\NotificationItem;
use Ux2Dev\Epay\Billing\Request\InitRequest;
use Ux2Dev\Epay\Billing\Request\ConfirmRequest;
use Ux2Dev\Epay\Billing\Enum\BillingRequestType;
use Ux2Dev\Epay\Billing\Enum\BillingPaymentType;
use Ux2Dev\Epay\Enum\PaymentStatus;

test('PaymentReceived holds NotificationItem', function () {
    $item = new NotificationItem(invoice: '123', status: PaymentStatus::Paid);
    $event = new PaymentReceived($item, 'main');
    expect($event->item)->toBe($item)->and($event->merchant)->toBe('main');
});
test('PaymentDenied holds NotificationItem', function () {
    $item = new NotificationItem(invoice: '123', status: PaymentStatus::Denied);
    $event = new PaymentDenied($item, 'main');
    expect($event->item)->toBe($item);
});
test('PaymentExpired holds NotificationItem', function () {
    $item = new NotificationItem(invoice: '123', status: PaymentStatus::Expired);
    $event = new PaymentExpired($item, 'main');
    expect($event->item)->toBe($item);
});
test('BillingObligationChecked holds InitRequest', function () {
    $request = new InitRequest(idn: '12345', merchantId: '0000334', type: BillingRequestType::Check);
    $event = new BillingObligationChecked($request, 'main');
    expect($event->request)->toBe($request)->and($event->merchant)->toBe('main');
});
test('BillingPaymentConfirmed holds ConfirmRequest', function () {
    $request = new ConfirmRequest(idn: '12345', merchantId: '0000334', tid: '20260413121650591535700020', date: new DateTimeImmutable('2026-04-13'), total: 16600, type: BillingPaymentType::Billing);
    $event = new BillingPaymentConfirmed($request, 'main');
    expect($event->request)->toBe($request);
});
