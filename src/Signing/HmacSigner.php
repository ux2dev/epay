<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Signing;

final readonly class HmacSigner implements SignerInterface
{
    public function __construct(
        private string $secret,
    ) {}

    public function sign(string $data): string
    {
        return hash_hmac('sha1', $data, $this->secret);
    }

    public function verify(string $data, string $signature): bool
    {
        $expected = $this->sign($data);

        return hash_equals($expected, $signature);
    }
}
