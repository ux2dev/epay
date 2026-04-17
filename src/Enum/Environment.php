<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Enum;

enum Environment: string
{
    case Development = 'development';
    case Production = 'production';

    public function gatewayUrl(): string
    {
        return match ($this) {
            self::Development => 'https://demo.epay.bg/',
            self::Production => 'https://www.epay.bg/',
        };
    }

    public function oneTouchApiUrl(): string
    {
        return match ($this) {
            self::Development => 'https://demo.epay.bg/xdev/api',
            self::Production => 'https://www.epay.bg/xdev/api',
        };
    }

    public function oneTouchMobileUrl(): string
    {
        return match ($this) {
            self::Development => 'https://demo.epay.bg/xdev/mobile/api',
            self::Production => 'https://www.epay.bg/xdev/mobile/api',
        };
    }
}
