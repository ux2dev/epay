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
