<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Ux2Dev\Epay\Laravel\EpayServiceProvider;
use Ux2Dev\Epay\Laravel\Events\NoRegPaymentCallback;

beforeEach(function () {
    $this->app['config']->set('epay.routes.enabled', true);
    (new EpayServiceProvider($this->app))->boot();
});
use Ux2Dev\Epay\Laravel\Events\OneTouchAuthorizationCallback;

test('dispatches NoRegPaymentCallback when id is present', function () {
    Event::fake();

    $response = $this->get('/epay/callback?' . http_build_query([
        'ret' => 'authok',
        'authok' => '1',
        'deviceid' => 'dev-1',
        'id' => 'nr_abc123',
    ]));

    $response->assertNoContent();

    Event::assertDispatched(NoRegPaymentCallback::class, function (NoRegPaymentCallback $event) {
        return $event->paymentId === 'nr_abc123'
            && $event->deviceId === 'dev-1'
            && $event->merchant === 'main'
            && $event->params['id'] === 'nr_abc123';
    });
    Event::assertNotDispatched(OneTouchAuthorizationCallback::class);
});

test('dispatches OneTouchAuthorizationCallback when id is absent', function () {
    Event::fake();

    $response = $this->get('/epay/callback?' . http_build_query([
        'ret' => 'authok',
        'authok' => '1',
        'deviceid' => 'dev-1',
    ]));

    $response->assertNoContent();

    Event::assertDispatched(OneTouchAuthorizationCallback::class, function (OneTouchAuthorizationCallback $event) {
        return $event->deviceId === 'dev-1' && $event->merchant === 'main';
    });
    Event::assertNotDispatched(NoRegPaymentCallback::class);
});

test('returns 400 when ret is not authok', function () {
    Event::fake();

    $response = $this->get('/epay/callback?ret=fail&authok=0');

    $response->assertStatus(400);
    Event::assertNotDispatched(NoRegPaymentCallback::class);
    Event::assertNotDispatched(OneTouchAuthorizationCallback::class);
});

test('returns 400 when authok is not 1', function () {
    $response = $this->get('/epay/callback?ret=authok&authok=0');
    $response->assertStatus(400);
});

test('returns 400 when ret and authok missing', function () {
    $response = $this->get('/epay/callback');
    $response->assertStatus(400);
});
