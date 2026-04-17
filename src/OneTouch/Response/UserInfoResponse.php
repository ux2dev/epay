<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class UserInfoResponse
{
    /** @param PaymentInstrument[] $paymentInstruments */
    public function __construct(public ?string $gsm, public ?string $pic, public ?string $realName, public ?string $id, public ?string $kin, public ?string $email, public array $paymentInstruments = []) {}
    public static function fromArray(array $data): self {
        $instruments = isset($data['PAYMENT_INSTRUMENTS']) ? array_map(fn (array $pi) => PaymentInstrument::fromArray($pi), $data['PAYMENT_INSTRUMENTS']) : [];
        return new self(gsm: $data['GSM'] ?? null, pic: $data['PIC'] ?? null, realName: $data['REAL_NAME'] ?? null, id: $data['ID'] ?? null, kin: $data['KIN'] ?? null, email: $data['EMAIL'] ?? null, paymentInstruments: $instruments);
    }
}
