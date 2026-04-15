<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Web\Request;

interface RequestInterface
{
    public function getPage(): string;
    public function getGatewayUrl(): string;
    /** @return array<string, string> */
    public function toArray(): array;
}
