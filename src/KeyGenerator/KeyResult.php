<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\KeyGenerator;

final readonly class KeyResult
{
    public function __construct(
        public string $privateKey,
        public string $publicKey,
    ) {}

    public function saveToDirectory(string $directory): void
    {
        $directory = rtrim($directory, '/');

        file_put_contents($directory . '/epay_private.key', $this->privateKey);
        file_put_contents($directory . '/epay_public.key', $this->publicKey);
    }
}
