<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Response;
use Ux2Dev\Epay\Billing\Enum\BillingStatus;

final readonly class InitResponse
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data) {}

    /** @param Invoice[] $invoices */
    public static function success(string $idn, string $shortDesc, int $amount, \DateTimeImmutable $validTo, ?string $longDesc = null, ?array $invoices = null): self
    {
        $data = ['STATUS' => BillingStatus::Success->value, 'IDN' => $idn, 'SHORTDESC' => $shortDesc, 'AMOUNT' => (string) $amount, 'VALIDTO' => $validTo->format('Ymd')];
        if ($longDesc !== null) { $data['LONGDESC'] = $longDesc; }
        if ($invoices !== null) { $data['INVOICES'] = array_map(fn (Invoice $inv) => $inv->toArray(), $invoices); }
        return new self($data);
    }

    public static function noObligation(string $idn): self { return new self(['STATUS' => BillingStatus::NoObligation->value, 'IDN' => $idn]); }
    public static function invalidSubscriber(string $idn): self { return new self(['STATUS' => BillingStatus::InvalidSubscriber->value, 'IDN' => $idn]); }
    public static function invalidAmount(): self { return new self(['STATUS' => BillingStatus::InvalidAmount->value]); }
    public static function unavailable(): self { return new self(['STATUS' => BillingStatus::Unavailable->value]); }
    public static function error(): self { return new self(['STATUS' => BillingStatus::GeneralError->value]); }

    public function toJson(): string { return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
}
