<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class PaymentInstrument
{
    public function __construct(public string $id, public int $verified, public int $balance, public int $type, public string $name, public ?string $expires = null) {}
    public static function fromArray(array $data): self { return new self(id: $data['ID'], verified: $data['VERIFIED'], balance: $data['BALANCE'], type: $data['TYPE'], name: $data['NAME'], expires: $data['EXPIRES'] ?? null); }
}
