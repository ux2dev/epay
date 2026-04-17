<?php
declare(strict_types=1);
use Ux2Dev\Epay\Laravel\EpayManager;
use Ux2Dev\Epay\Web\WebClient;
use Ux2Dev\Epay\Billing\BillingHandler;
use Ux2Dev\Epay\OneTouch\OneTouchClient;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Exception\ConfigurationException;

beforeEach(function () {
    $this->config = [
        'default' => 'main',
        'merchants' => [
            'main' => ['merchant_id' => '1000000000', 'secret' => 'testsecret', 'environment' => 'development', 'currency' => 'EUR', 'signing_method' => 'hmac', 'private_key' => null, 'private_key_passphrase' => null],
            'building_2' => ['merchant_id' => '2000000000', 'secret' => 'othersecret', 'environment' => 'production', 'currency' => 'BGN', 'signing_method' => 'hmac', 'private_key' => null, 'private_key_passphrase' => null],
        ],
    ];
});

test('merchant returns EpayManager for named merchant', function () {
    expect((new EpayManager($this->config))->merchant('building_2'))->toBeInstanceOf(EpayManager::class);
});
test('web returns WebClient for default', function () {
    expect((new EpayManager($this->config))->web())->toBeInstanceOf(WebClient::class);
});
test('web returns WebClient for named merchant', function () {
    expect((new EpayManager($this->config))->merchant('building_2')->web())->toBeInstanceOf(WebClient::class);
});
test('billing returns BillingHandler', function () {
    expect((new EpayManager($this->config))->billing())->toBeInstanceOf(BillingHandler::class);
});
test('oneTouch returns OneTouchClient', function () {
    expect((new EpayManager($this->config))->oneTouch())->toBeInstanceOf(OneTouchClient::class);
});
test('getConfig returns MerchantConfig for default', function () {
    $config = (new EpayManager($this->config))->getConfig();
    expect($config)->toBeInstanceOf(MerchantConfig::class)->and($config->merchantId)->toBe('1000000000');
});
test('getConfig returns MerchantConfig for named', function () {
    expect((new EpayManager($this->config))->merchant('building_2')->getConfig()->merchantId)->toBe('2000000000');
});
test('throws on unknown merchant', function () {
    (new EpayManager($this->config))->merchant('nonexistent')->web();
})->throws(ConfigurationException::class, 'Merchant "nonexistent" is not configured');
test('caches WebClient instances', function () {
    $m = new EpayManager($this->config);
    expect($m->web())->toBe($m->web());
});
test('different merchants return different clients', function () {
    $m = new EpayManager($this->config);
    expect($m->web())->not->toBe($m->merchant('building_2')->web());
});

test('getCurrentMerchant returns default when no merchant() call', function () {
    expect((new EpayManager($this->config))->getCurrentMerchant())->toBe('main');
});

test('getCurrentMerchant returns named merchant after merchant() call', function () {
    expect((new EpayManager($this->config))->merchant('building_2')->getCurrentMerchant())->toBe('building_2');
});

test('billingInitUsing stores resolver and returns same instance', function () {
    $m = new EpayManager($this->config);
    $resolver = fn () => null;
    expect($m->billingInitUsing($resolver))->toBe($m)
        ->and($m->getBillingInitResolver())->toBe($resolver);
});

test('billingConfirmUsing stores resolver and returns same instance', function () {
    $m = new EpayManager($this->config);
    $resolver = fn () => null;
    expect($m->billingConfirmUsing($resolver))->toBe($m)
        ->and($m->getBillingConfirmResolver())->toBe($resolver);
});

test('billing resolvers default to null', function () {
    $m = new EpayManager($this->config);
    expect($m->getBillingInitResolver())->toBeNull()
        ->and($m->getBillingConfirmResolver())->toBeNull();
});
