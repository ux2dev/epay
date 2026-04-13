<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Billing\Formatter;

use Ux2Dev\Epay\Exception\ConfigurationException;

final class LongDescFormatter
{
    public static function encode(string $text): string
    {
        $text = str_replace('--------', '\$', $text);
        $text = str_replace("\t", '\t', $text);
        $text = str_replace("\n", '\n', $text);
        return $text;
    }

    public static function decode(string $encoded): string
    {
        $text = str_replace('\n', "\n", $encoded);
        $text = str_replace('\t', "\t", $text);
        $text = str_replace('\$', '--------', $text);
        return $text;
    }

    public static function validate(string $text): void
    {
        foreach (explode("\n", $text) as $line) {
            if (mb_strlen($line) > 110) {
                throw new ConfigurationException('LONGDESC line exceeds 110 characters');
            }
        }
    }
}
