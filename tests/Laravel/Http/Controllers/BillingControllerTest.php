<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Ux2Dev\Epay\Laravel\EpayServiceProvider;
use Ux2Dev\Epay\Billing\Request\ConfirmRequest;

beforeEach(function () {
    $this->app['config']->set('epay.routes.enabled', true);
    (new EpayServiceProvider($this->app))->boot();
});
use Ux2Dev\Epay\Billing\Request\InitRequest;
use Ux2Dev\Epay\Billing\Response\ConfirmResponse;
use Ux2Dev\Epay\Billing\Response\InitResponse;
use Ux2Dev\Epay\Laravel\EpayManager;
use Ux2Dev\Epay\Laravel\Events\BillingObligationChecked;
use Ux2Dev\Epay\Laravel\Events\BillingPaymentConfirmed;

function billingChecksumForController(array $params, string $secret): string
{
    return hash_hmac('sha1', \Ux2Dev\Epay\Billing\BillingHandler::buildChecksumData($params), $secret);
}

test('init invokes resolver, fires event, returns its JSON', function () {
    Event::fake();

    $this->app->make(EpayManager::class)->billingInitUsing(function (InitRequest $req) {
        expect($req->idn)->toBe('12345');
        return InitResponse::noObligation($req->idn);
    });

    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'CHECK'];
    $params['CHECKSUM'] = billingChecksumForController($params, 'testsecret');

    $response = $this->get('/epay/billing/init?' . http_build_query($params));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('STATUS', '62');

    Event::assertDispatched(BillingObligationChecked::class);
});

test('init returns 400 on invalid CHECKSUM without firing event', function () {
    Event::fake();

    $this->app->make(EpayManager::class)->billingInitUsing(fn () => InitResponse::noObligation('x'));

    $response = $this->get('/epay/billing/init?IDN=12345&MERCHANTID=0000334&TYPE=CHECK&CHECKSUM=deadbeef');

    $response->assertStatus(400);
    Event::assertNotDispatched(BillingObligationChecked::class);
});

test('init throws LogicException when no resolver is registered', function () {
    $this->withoutExceptionHandling();

    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'CHECK'];
    $params['CHECKSUM'] = billingChecksumForController($params, 'testsecret');

    $this->get('/epay/billing/init?' . http_build_query($params));
})->throws(LogicException::class, 'billingInitUsing');

test('confirm invokes resolver, fires event, returns its JSON', function () {
    Event::fake();

    $this->app->make(EpayManager::class)->billingConfirmUsing(function (ConfirmRequest $req) {
        expect($req->idn)->toBe('12345')->and($req->total)->toBe(16600);
        return ConfirmResponse::success();
    });

    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => '20260413121650591535700020', 'DATE' => '20260413181226', 'TOTAL' => '16600', 'TYPE' => 'BILLING'];
    $params['CHECKSUM'] = billingChecksumForController($params, 'testsecret');

    $response = $this->get('/epay/billing/confirm?' . http_build_query($params));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('STATUS', '00');

    Event::assertDispatched(BillingPaymentConfirmed::class);
});

test('confirm returns 400 on invalid CHECKSUM without firing event', function () {
    Event::fake();

    $this->app->make(EpayManager::class)->billingConfirmUsing(fn () => ConfirmResponse::success());

    $response = $this->get('/epay/billing/confirm?IDN=12345&MERCHANTID=0000334&TID=t&DATE=20260413181226&TOTAL=10&TYPE=BILLING&CHECKSUM=deadbeef');

    $response->assertStatus(400);
    Event::assertNotDispatched(BillingPaymentConfirmed::class);
});

test('confirm throws LogicException when no resolver is registered', function () {
    $this->withoutExceptionHandling();

    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => 't', 'DATE' => '20260413181226', 'TOTAL' => '10', 'TYPE' => 'BILLING'];
    $params['CHECKSUM'] = billingChecksumForController($params, 'testsecret');

    $this->get('/epay/billing/confirm?' . http_build_query($params));
})->throws(LogicException::class, 'billingConfirmUsing');
