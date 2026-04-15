<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\IdnGenerator;

use Ux2Dev\Epay\Exception\ConfigurationException;

final class IdnGenerator
{
    public static function generate(string $prefix, string $subscriberId): string
    {
        $idn = $prefix . $subscriberId;
        self::validate($idn);

        return $idn;
    }

    public static function padded(string $prefix, int $subscriberId, int $length): string
    {
        $subscriberPart = str_pad((string) $subscriberId, $length - strlen($prefix), '0', STR_PAD_LEFT);
        $idn = $prefix . $subscriberPart;
        self::validate($idn);

        return $idn;
    }

    /**
     * @return array{prefix: string, subscriberId: string}
     */
    public static function parse(string $idn, int $prefixLength): array
    {
        return [
            'prefix' => substr($idn, 0, $prefixLength),
            'subscriberId' => substr($idn, $prefixLength),
        ];
    }

    public static function validate(string $idn): void
    {
        if ($idn === '') {
            throw new ConfigurationException('IDN must not be empty');
        }

        if (strlen($idn) > 64) {
            throw new ConfigurationException('IDN must not exceed 64 characters');
        }

        if (!preg_match('/^\d+$/', $idn)) {
            throw new ConfigurationException('IDN must contain only digits');
        }
    }
}
