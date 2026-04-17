<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Ux2Dev\Epay\Laravel\EpayServiceProvider;
use Ux2Dev\Epay\Laravel\Events\PaymentDenied;

beforeEach(function () {
    $this->app['config']->set('epay.routes.enabled', true);
    (new EpayServiceProvider($this->app))->boot();
});
use Ux2Dev\Epay\Laravel\Events\PaymentExpired;
use Ux2Dev\Epay\Laravel\Events\PaymentReceived;

function buildWebNotifPost(string $data, string $secret): array
{
    $encoded = base64_encode($data);
    $checksum = hash_hmac('sha1', $encoded, $secret);
    return ['ENCODED' => $encoded, 'CHECKSUM' => $checksum];
}

test('dispatches PaymentReceived for PAID notification and responds with ACK', function () {
    Event::fake();

    $post = buildWebNotifPost('INVOICE=123456:STATUS=PAID:PAY_TIME=20260413123000:STAN=591535:BCODE=A1B2C3', 'testsecret');
    $response = $this->post('/epay/notify', $post);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('INVOICE=123456:STATUS=OK');

    Event::assertDispatched(PaymentReceived::class, function (PaymentReceived $event) {
        return $event->item->invoice === '123456' && $event->merchant === 'main';
    });
});

test('dispatches PaymentDenied for DENIED notification', function () {
    Event::fake();

    $post = buildWebNotifPost('INVOICE=111:STATUS=DENIED', 'testsecret');
    $this->post('/epay/notify', $post)->assertOk();

    Event::assertDispatched(PaymentDenied::class, fn (PaymentDenied $event) => $event->item->invoice === '111');
});

test('dispatches PaymentExpired for EXPIRED notification', function () {
    Event::fake();

    $post = buildWebNotifPost('INVOICE=222:STATUS=EXPIRED', 'testsecret');
    $this->post('/epay/notify', $post)->assertOk();

    Event::assertDispatched(PaymentExpired::class, fn (PaymentExpired $event) => $event->item->invoice === '222');
});

test('normalizes lowercase POST keys to uppercase', function () {
    Event::fake();

    $post = buildWebNotifPost('INVOICE=333:STATUS=PAID', 'testsecret');
    $lowercased = ['encoded' => $post['ENCODED'], 'checksum' => $post['CHECKSUM']];

    $this->post('/epay/notify', $lowercased)->assertOk();

    Event::assertDispatched(PaymentReceived::class);
});

test('returns 400 on invalid CHECKSUM', function () {
    Event::fake();

    $response = $this->post('/epay/notify', [
        'ENCODED' => base64_encode('INVOICE=444:STATUS=PAID'),
        'CHECKSUM' => 'deadbeef',
    ]);

    $response->assertStatus(400);
    Event::assertNotDispatched(PaymentReceived::class);
});
