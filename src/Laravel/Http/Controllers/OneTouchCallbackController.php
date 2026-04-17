<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ux2Dev\Epay\Laravel\EpayManager;
use Ux2Dev\Epay\Laravel\Events\NoRegPaymentCallback;
use Ux2Dev\Epay\Laravel\Events\OneTouchAuthorizationCallback;

final class OneTouchCallbackController
{
    public function handle(Request $request, EpayManager $epay): Response
    {
        /** @var array<string, string> $params */
        $params = $request->query();

        $ret = $params['ret'] ?? '';
        $authok = $params['authok'] ?? '';

        if ($ret !== 'authok' || $authok !== '1') {
            return new Response('Authorization failed', 400, ['Content-Type' => 'text/plain']);
        }

        $deviceId = $params['deviceid'] ?? '';
        $merchant = $epay->getCurrentMerchant();

        // NoReg card payment callback: ePay echoes back the `ID` we set in
        // createNoRegPaymentUrl as lowercase `id`. Auth callbacks have no `id`.
        if (isset($params['id'])) {
            event(new NoRegPaymentCallback(
                paymentId: $params['id'],
                deviceId: $deviceId,
                params: $params,
                merchant: $merchant,
            ));
        } else {
            event(new OneTouchAuthorizationCallback(
                deviceId: $deviceId,
                params: $params,
                merchant: $merchant,
            ));
        }

        return new Response('', 204);
    }
}
