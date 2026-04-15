<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Tests\Laravel;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ux2Dev\Epay\Laravel\EpayServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [EpayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('epay.default', 'main');
        $app['config']->set('epay.merchants.main', [
            'merchant_id' => '1000000000',
            'secret' => 'testsecret',
            'environment' => 'development',
            'currency' => 'EUR',
            'signing_method' => 'hmac',
            'private_key' => null,
            'private_key_passphrase' => null,
        ]);
    }
}
