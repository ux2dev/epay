<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Signing;

interface SignerInterface
{
    public function sign(string $data): string;

    public function verify(string $data, string $signature): bool;
}
