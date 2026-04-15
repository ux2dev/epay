<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\Notification\NotificationHandler;
use Ux2Dev\Epay\Web\Notification\NotificationResult;
use Ux2Dev\Epay\Enum\PaymentStatus;
use Ux2Dev\Epay\Signing\HmacSigner;
use Ux2Dev\Epay\Exception\InvalidResponseException;

function buildNotificationPost(string $data, string $secret): array
{
    $encoded = base64_encode($data);
    $checksum = hash_hmac('sha1', $encoded, $secret);
    return ['ENCODED' => $encoded, 'CHECKSUM' => $checksum];
}

test('handles single PAID notification', function () {
    $secret = 'testsecret';
    $handler = new NotificationHandler(new HmacSigner($secret));
    $post = buildNotificationPost("INVOICE=123456:STATUS=PAID:PAY_TIME=20260413123000:STAN=591535:BCODE=A1B2C3", $secret);
    $result = $handler->handle($post);

    expect($result)->toBeInstanceOf(NotificationResult::class)
        ->and($result->items())->toHaveCount(1);
    $item = $result->items()[0];
    expect($item->invoice)->toBe('123456')
        ->and($item->status)->toBe(PaymentStatus::Paid)
        ->and($item->stan)->toBe('591535')
        ->and($item->bcode)->toBe('A1B2C3')
        ->and($item->payTime)->toBeInstanceOf(DateTimeImmutable::class);
});

test('handles DENIED notification', function () {
    $secret = 'testsecret';
    $handler = new NotificationHandler(new HmacSigner($secret));
    $post = buildNotificationPost("INVOICE=123457:STATUS=DENIED", $secret);
    $result = $handler->handle($post);
    $item = $result->items()[0];
    expect($item->invoice)->toBe('123457')
        ->and($item->status)->toBe(PaymentStatus::Denied)
        ->and($item->payTime)->toBeNull()
        ->and($item->stan)->toBeNull();
});

test('handles EXPIRED notification', function () {
    $secret = 'testsecret';
    $handler = new NotificationHandler(new HmacSigner($secret));
    $post = buildNotificationPost("INVOICE=123458:STATUS=EXPIRED", $secret);
    $result = $handler->handle($post);
    expect($result->items()[0]->status)->toBe(PaymentStatus::Expired);
});

test('handles multiple invoices in single callback', function () {
    $secret = 'testsecret';
    $handler = new NotificationHandler(new HmacSigner($secret));
    $data = "INVOICE=123456:STATUS=PAID:PAY_TIME=20260413123000:STAN=591535:BCODE=A1B2C3\n"
        . "INVOICE=123457:STATUS=DENIED\n"
        . "INVOICE=123458:STATUS=EXPIRED";
    $post = buildNotificationPost($data, $secret);
    $result = $handler->handle($post);
    expect($result->items())->toHaveCount(3)
        ->and($result->items()[0]->status)->toBe(PaymentStatus::Paid)
        ->and($result->items()[1]->status)->toBe(PaymentStatus::Denied)
        ->and($result->items()[2]->status)->toBe(PaymentStatus::Expired);
});

test('handles notification with discount fields', function () {
    $secret = 'testsecret';
    $handler = new NotificationHandler(new HmacSigner($secret));
    $post = buildNotificationPost(
        "INVOICE=123456:STATUS=PAID:PAY_TIME=20260413123000:STAN=591535:BCODE=A1B2C3:AMOUNT=20.00:BIN=411111",
        $secret,
    );
    $result = $handler->handle($post);
    $item = $result->items()[0];
    expect($item->amount)->toBe('20.00')->and($item->bin)->toBe('411111');
});

test('throws InvalidResponseException on checksum mismatch', function () {
    $handler = new NotificationHandler(new HmacSigner('testsecret'));
    $handler->handle([
        'ENCODED' => base64_encode('INVOICE=123456:STATUS=PAID'),
        'CHECKSUM' => 'tampered_checksum_value_here_1234',
    ]);
})->throws(InvalidResponseException::class, 'CHECKSUM verification failed');

test('throws InvalidResponseException on missing ENCODED', function () {
    $handler = new NotificationHandler(new HmacSigner('testsecret'));
    $handler->handle(['CHECKSUM' => 'abc']);
})->throws(InvalidResponseException::class, 'Missing ENCODED');

test('throws InvalidResponseException on missing CHECKSUM', function () {
    $handler = new NotificationHandler(new HmacSigner('testsecret'));
    $handler->handle(['ENCODED' => 'abc']);
})->throws(InvalidResponseException::class, 'Missing CHECKSUM');
