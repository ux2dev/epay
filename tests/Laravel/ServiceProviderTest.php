<?php
declare(strict_types=1);
use Ux2Dev\Epay\Laravel\EpayManager;
use Ux2Dev\Epay\Laravel\EpayFacade;
use Ux2Dev\Epay\Web\WebClient;

test('EpayManager is bound as singleton', function () {
    $m1 = $this->app->make(EpayManager::class);
    $m2 = $this->app->make(EpayManager::class);
    expect($m1)->toBeInstanceOf(EpayManager::class)->and($m1)->toBe($m2);
});

test('EpayManager is bound under epay alias', function () {
    expect($this->app->make('epay'))->toBeInstanceOf(EpayManager::class);
});

test('facade resolves to EpayManager', function () {
    expect(EpayFacade::web())->toBeInstanceOf(WebClient::class);
});

test('config is published', function () {
    $config = $this->app['config']->get('epay');
    expect($config)->toBeArray()->and($config)->toHaveKey('default')->and($config)->toHaveKey('merchants');
});

test('routes are not registered when epay.routes.enabled is false', function () {
    $names = collect($this->app['router']->getRoutes())->map(fn ($r) => $r->getName())->filter();
    expect($names->contains('epay.notify'))->toBeFalse()
        ->and($names->contains('epay.onetouch.callback'))->toBeFalse();
});

test('routes are registered when epay.routes.enabled is true', function () {
    // Routes are registered in boot(); reboot the provider with enabled=true.
    $this->refreshApplication();
    $this->app['config']->set('epay.routes.enabled', true);
    $this->app->register(\Ux2Dev\Epay\Laravel\EpayServiceProvider::class, true);
    $this->app->boot();

    $names = collect($this->app['router']->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
    expect($names)->toContain('epay.notify', 'epay.billing.init', 'epay.billing.confirm', 'epay.onetouch.callback');
});

test('route prefix is configurable', function () {
    $this->refreshApplication();
    $this->app['config']->set('epay.routes.enabled', true);
    $this->app['config']->set('epay.routes.prefix', 'payments');
    $this->app->register(\Ux2Dev\Epay\Laravel\EpayServiceProvider::class, true);
    $this->app->boot();

    $route = collect($this->app['router']->getRoutes())->first(fn ($r) => $r->getName() === 'epay.onetouch.callback');
    expect($route)->not->toBeNull()->and($route->uri())->toBe('payments/callback');
});
