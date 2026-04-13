<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class PaymentResponse
{
    public function __construct(public string $id, public ?int $state = null, public ?string $stateText = null, public ?string $no = null, public ?int $amount = null, public ?int $tax = null, public ?int $total = null) {}
    public static function fromArray(array $data): self {
        $p = $data['payment'];
        return new self(id: $p['ID'], state: $p['STATE'] ?? null, stateText: $p['STATE_TEXT'] ?? null, no: $p['NO'] ?? null, amount: $p['AMOUNT'] ?? null, tax: $p['TAX'] ?? null, total: $p['TOTAL'] ?? null);
    }
}
