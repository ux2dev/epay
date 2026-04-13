<?php

declare(strict_types=1);

use Ux2Dev\Epay\Exception\EpayException;
use Ux2Dev\Epay\Exception\ConfigurationException;
use Ux2Dev\Epay\Exception\SigningException;
use Ux2Dev\Epay\Exception\InvalidResponseException;

test('exception hierarchy extends RuntimeException', function () {
    expect(new EpayException())->toBeInstanceOf(RuntimeException::class)
        ->and(new ConfigurationException())->toBeInstanceOf(EpayException::class)
        ->and(new SigningException())->toBeInstanceOf(EpayException::class)
        ->and(new InvalidResponseException('test'))->toBeInstanceOf(EpayException::class);
});

test('InvalidResponseException redacts sensitive fields', function () {
    $exception = new InvalidResponseException('bad response', [
        'CHECKSUM' => 'abc123secret',
        'ENCODED' => 'base64data',
        'SIGNATURE' => 'rsasig',
        'TOKEN' => 'usertoken',
        'INVOICE' => '12345',
        'STATUS' => 'PAID',
    ]);

    $data = $exception->getResponseData();

    expect($data['CHECKSUM'])->toBe('[REDACTED]')
        ->and($data['ENCODED'])->toBe('[REDACTED]')
        ->and($data['SIGNATURE'])->toBe('[REDACTED]')
        ->and($data['TOKEN'])->toBe('[REDACTED]')
        ->and($data['INVOICE'])->toBe('12345')
        ->and($data['STATUS'])->toBe('PAID');
});

test('InvalidResponseException stores message', function () {
    $exception = new InvalidResponseException('checksum mismatch', ['foo' => 'bar']);

    expect($exception->getMessage())->toBe('checksum mismatch')
        ->and($exception->getResponseData())->toBe(['foo' => 'bar']);
});

test('InvalidResponseException handles empty data', function () {
    $exception = new InvalidResponseException('error');

    expect($exception->getResponseData())->toBe([]);
});
