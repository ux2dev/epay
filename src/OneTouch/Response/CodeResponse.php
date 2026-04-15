<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class CodeResponse
{
    public function __construct(public string $status, public ?string $code = null) {}
    public static function fromArray(array $data): self { return new self(status: $data['status'], code: $data['code'] ?? null); }
}
