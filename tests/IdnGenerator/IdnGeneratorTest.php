<?php

declare(strict_types=1);

use Ux2Dev\Epay\IdnGenerator\IdnGenerator;
use Ux2Dev\Epay\Exception\ConfigurationException;

test('generate concatenates prefix and subscriberId', function () {
    expect(IdnGenerator::generate('001', '0012'))->toBe('0010012');
});

test('generate with numeric strings', function () {
    expect(IdnGenerator::generate('99', '123'))->toBe('99123');
});

test('padded generates fixed-length IDN', function () {
    expect(IdnGenerator::padded('001', 12, 10))->toBe('0010000012');
});

test('padded pads subscriberId only', function () {
    expect(IdnGenerator::padded('55', 7, 8))->toBe('55000007');
});

test('parse extracts prefix and subscriberId', function () {
    $parts = IdnGenerator::parse('0010000012', 3);

    expect($parts['prefix'])->toBe('001')
        ->and($parts['subscriberId'])->toBe('0000012');
});

test('parse with different prefix length', function () {
    $parts = IdnGenerator::parse('55000007', 2);

    expect($parts['prefix'])->toBe('55')
        ->and($parts['subscriberId'])->toBe('000007');
});

test('validate passes for digits only', function () {
    IdnGenerator::validate('1234567890');
    expect(true)->toBeTrue();
});

test('validate throws on non-digit characters', function () {
    IdnGenerator::validate('ABC123');
})->throws(ConfigurationException::class, 'IDN must contain only digits');

test('validate throws on empty string', function () {
    IdnGenerator::validate('');
})->throws(ConfigurationException::class, 'IDN must not be empty');

test('validate throws on exceeding max length', function () {
    IdnGenerator::validate(str_repeat('1', 65));
})->throws(ConfigurationException::class, 'IDN must not exceed 64 characters');

test('validate passes for max length', function () {
    IdnGenerator::validate(str_repeat('1', 64));
    expect(true)->toBeTrue();
});

test('generate validates result', function () {
    IdnGenerator::generate('ABC', '123');
})->throws(ConfigurationException::class, 'IDN must contain only digits');

test('padded validates result', function () {
    IdnGenerator::padded('AB', 12, 10);
})->throws(ConfigurationException::class, 'IDN must contain only digits');
