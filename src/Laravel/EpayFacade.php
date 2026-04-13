<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static EpayManager merchant(string $name)
 * @method static \Ux2Dev\Epay\Web\WebClient web()
 * @method static \Ux2Dev\Epay\Billing\BillingHandler billing()
 * @method static \Ux2Dev\Epay\OneTouch\OneTouchClient oneTouch()
 * @method static \Ux2Dev\Epay\Config\MerchantConfig getConfig()
 *
 * @see \Ux2Dev\Epay\Laravel\EpayManager
 */
class EpayFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EpayManager::class;
    }
}
