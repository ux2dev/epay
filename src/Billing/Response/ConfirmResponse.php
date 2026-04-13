<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\Response;
use Ux2Dev\Epay\Billing\Enum\BillingStatus;

final readonly class ConfirmResponse
{
    private function __construct(private array $data) {}

    public static function success(): self { return new self(['STATUS' => BillingStatus::Success->value]); }
    public static function duplicate(): self { return new self(['STATUS' => BillingStatus::Duplicate->value]); }
    public static function invalidChecksum(): self { return new self(['STATUS' => BillingStatus::InvalidChecksum->value]); }
    public static function error(): self { return new self(['STATUS' => BillingStatus::GeneralError->value]); }

    public function toJson(): string { return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
}
