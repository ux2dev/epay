<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ux2Dev\Epay\Laravel\Http\Controllers\BillingController;
use Ux2Dev\Epay\Laravel\Http\Controllers\OneTouchCallbackController;
use Ux2Dev\Epay\Laravel\Http\Controllers\WebNotificationController;

$config = config('epay.routes', []);
$prefix = (string) ($config['prefix'] ?? 'epay');
$middleware = (array) ($config['middleware'] ?? []);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    Route::post('notify', [WebNotificationController::class, 'handle'])->name('epay.notify');
    Route::get('billing/init', [BillingController::class, 'init'])->name('epay.billing.init');
    Route::get('billing/confirm', [BillingController::class, 'confirm'])->name('epay.billing.confirm');
    Route::get('callback', [OneTouchCallbackController::class, 'handle'])->name('epay.onetouch.callback');
});
