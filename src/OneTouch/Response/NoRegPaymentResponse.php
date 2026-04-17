<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class NoRegPaymentResponse
{
    public function __construct(public int $state, public ?string $stateText = null, public ?string $no = null, public ?string $token = null, public ?PaidWith $paidWith = null, public ?PaymentInstrument $paymentInstrument = null) {}
    public static function fromArray(array $data): self {
        $p = $data['payment'];
        $paidWith = isset($p['paid_with']) ? PaidWith::fromArray($p['paid_with']) : null;
        $instrument = isset($p['payment_instrument']) ? PaymentInstrument::fromArray($p['payment_instrument']) : null;
        return new self(state: (int)$p['STATE'], stateText: $p['STATE_TEXT'] ?? null, no: $p['NO'] ?? null, token: $p['TOKEN'] ?? null, paidWith: $paidWith, paymentInstrument: $instrument);
    }
}
