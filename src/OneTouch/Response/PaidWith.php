<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class PaidWith
{
    public function __construct(public string $cardType, public string $cardTypeDescr, public string $cardTypeCountry) {}
    public static function fromArray(array $data): self { return new self(cardType: $data['CARD_TYPE'], cardTypeDescr: $data['CARD_TYPE_DESCR'], cardTypeCountry: $data['CARD_TYPE_COUNTRY']); }
}
