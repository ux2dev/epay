<?php
declare(strict_types=1);

namespace Ux2Dev\Epay\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ux2Dev\Epay\Billing\Response\ConfirmResponse;
use Ux2Dev\Epay\Billing\Response\InitResponse;
use Ux2Dev\Epay\Exception\InvalidResponseException;
use Ux2Dev\Epay\Laravel\EpayManager;
use Ux2Dev\Epay\Laravel\Events\BillingObligationChecked;
use Ux2Dev\Epay\Laravel\Events\BillingPaymentConfirmed;

final class BillingController
{
    public function init(Request $request, EpayManager $epay): Response
    {
        $resolver = $epay->getBillingInitResolver();
        if ($resolver === null) {
            throw new \LogicException(
                'No billing init resolver registered. Call Epay::billingInitUsing(fn(InitRequest $r) => InitResponse::success(...)) in a service provider.'
            );
        }

        try {
            $initRequest = $epay->billing()->parseInitRequest($request->query());
        } catch (InvalidResponseException $e) {
            return $this->json(InitResponse::error()->toJson(), 400);
        }

        event(new BillingObligationChecked($initRequest, $epay->getCurrentMerchant()));

        /** @var InitResponse $response */
        $response = $resolver($initRequest);

        return $this->json($response->toJson());
    }

    public function confirm(Request $request, EpayManager $epay): Response
    {
        $resolver = $epay->getBillingConfirmResolver();
        if ($resolver === null) {
            throw new \LogicException(
                'No billing confirm resolver registered. Call Epay::billingConfirmUsing(fn(ConfirmRequest $r) => ConfirmResponse::success()) in a service provider.'
            );
        }

        try {
            $confirmRequest = $epay->billing()->parseConfirmRequest($request->query());
        } catch (InvalidResponseException $e) {
            return $this->json(ConfirmResponse::error()->toJson(), 400);
        }

        /** @var ConfirmResponse $response */
        $response = $resolver($confirmRequest);

        event(new BillingPaymentConfirmed($confirmRequest, $epay->getCurrentMerchant()));

        return $this->json($response->toJson());
    }

    private function json(string $body, int $status = 200): Response
    {
        return new Response($body, $status, ['Content-Type' => 'application/json']);
    }
}
