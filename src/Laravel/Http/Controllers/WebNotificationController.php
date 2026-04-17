<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ux2Dev\Epay\Enum\PaymentStatus;
use Ux2Dev\Epay\Exception\InvalidResponseException;
use Ux2Dev\Epay\Laravel\EpayManager;
use Ux2Dev\Epay\Laravel\Events\PaymentDenied;
use Ux2Dev\Epay\Laravel\Events\PaymentExpired;
use Ux2Dev\Epay\Laravel\Events\PaymentReceived;

final class WebNotificationController
{
    public function handle(Request $request, EpayManager $epay): Response
    {
        // ePay sends param names in lowercase; WebClient expects uppercase.
        $normalized = array_change_key_case($request->post(), CASE_UPPER);

        try {
            $result = $epay->web()->handleNotification($normalized);
        } catch (InvalidResponseException $e) {
            return new Response('Invalid notification', 400, ['Content-Type' => 'text/plain']);
        }

        $merchant = $epay->getCurrentMerchant();

        foreach ($result->items() as $item) {
            match ($item->status) {
                PaymentStatus::Paid => event(new PaymentReceived($item, $merchant)),
                PaymentStatus::Denied => event(new PaymentDenied($item, $merchant)),
                PaymentStatus::Expired => event(new PaymentExpired($item, $merchant)),
                default => null,
            };
            $item->acknowledge();
        }

        return new Response($result->toHttpResponse(), 200, ['Content-Type' => 'text/plain']);
    }
}
