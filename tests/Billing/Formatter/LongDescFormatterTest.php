<?php

declare(strict_types=1);

use Ux2Dev\Epay\Billing\Formatter\LongDescFormatter;

test('encodes newlines', function () {
    expect(LongDescFormatter::encode("Line 1\nLine 2"))->toBe('Line 1\nLine 2');
});

test('encodes tabs', function () {
    expect(LongDescFormatter::encode("Col1\tCol2"))->toBe('Col1\tCol2');
});

test('encodes dash separator', function () {
    expect(LongDescFormatter::encode("--------"))->toBe('\$');
});

test('decodes \\n to newlines', function () {
    expect(LongDescFormatter::decode('Line 1\nLine 2'))->toBe("Line 1\nLine 2");
});

test('decodes \\t to tab', function () {
    expect(LongDescFormatter::decode('Col1\tCol2'))->toBe("Col1\tCol2");
});

test('decodes \\$ to 8 dashes', function () {
    expect(LongDescFormatter::decode('\$'))->toBe('--------');
});

test('round trip encode then decode', function () {
    $original = "Line 1\nLine 2\n--------\nCol1\tCol2";
    expect(LongDescFormatter::decode(LongDescFormatter::encode($original)))->toBe($original);
});

test('validates line length does not exceed 110', function () {
    LongDescFormatter::validate(str_repeat('x', 111));
})->throws(\Ux2Dev\Epay\Exception\ConfigurationException::class, 'exceeds 110 characters');

test('validates passes for 110 char line', function () {
    LongDescFormatter::validate(str_repeat('x', 110));
    expect(true)->toBeTrue();
});

test('validates multi-line with one long line fails', function () {
    LongDescFormatter::validate("Short line\n" . str_repeat('x', 111));
})->throws(\Ux2Dev\Epay\Exception\ConfigurationException::class, 'exceeds 110 characters');
