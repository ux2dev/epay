<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\EasyPay\Response;

final readonly class EasyPayCodeResponse
{
    public function __construct(
        public ?string $idn = null,
        public ?string $status = null,
        public ?string $error = null,
        public ?string $errorMessage = null,
        /** @var array<string, string> */
        public array $raw = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->idn !== null && $this->error === null;
    }

    /** @param array<string, string> $parsed */
    public static function fromParsed(array $parsed): self
    {
        return new self(
            idn: $parsed['IDN'] ?? null,
            status: $parsed['STATUS'] ?? null,
            error: $parsed['ERR'] ?? null,
            errorMessage: $parsed['ERRM'] ?? ($parsed['MESSAGE'] ?? null),
            raw: $parsed,
        );
    }
}
