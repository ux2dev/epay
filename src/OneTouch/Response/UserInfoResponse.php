<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class UserInfoResponse
{
    /** @param PaymentInstrument[] $paymentInstruments */
    public function __construct(public string $gsm, public string $pic, public string $realName, public string $id, public string $kin, public string $email, public array $paymentInstruments = []) {}
    public static function fromArray(array $data): self {
        $instruments = isset($data['PAYMENT_INSTRUMENTS']) ? array_map(fn (array $pi) => PaymentInstrument::fromArray($pi), $data['PAYMENT_INSTRUMENTS']) : [];
        return new self(gsm: $data['GSM'], pic: $data['PIC'], realName: $data['REAL_NAME'], id: $data['ID'], kin: $data['KIN'], email: $data['EMAIL'], paymentInstruments: $instruments);
    }
}
