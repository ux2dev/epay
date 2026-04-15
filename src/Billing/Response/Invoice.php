<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Response;

final readonly class Invoice
{
    public function __construct(
        public string $idn, public int $amount, public string $shortDesc,
        public \DateTimeImmutable $validTo, public ?string $longDesc = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = ['IDN' => $this->idn, 'AMOUNT' => (string) $this->amount, 'SHORTDESC' => $this->shortDesc, 'VALIDTO' => $this->validTo->format('Ymd')];
        if ($this->longDesc !== null) { $data['LONGDESC'] = $this->longDesc; }
        return $data;
    }
}
