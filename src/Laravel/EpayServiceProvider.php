<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Laravel;

use Illuminate\Support\ServiceProvider;

class EpayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/epay.php', 'epay');

        $this->app->singleton(EpayManager::class, function ($app) {
            return new EpayManager($app['config']->get('epay'));
        });

        $this->app->alias(EpayManager::class, 'epay');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/epay.php' => config_path('epay.php'),
            ], 'epay-config');

            $this->commands([
                Commands\GenerateKeyCommand::class,
                Commands\GenerateObligationsCommand::class,
            ]);
        }
    }
}
