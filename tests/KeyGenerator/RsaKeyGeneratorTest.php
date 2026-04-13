<?php

declare(strict_types=1);

use Ux2Dev\Epay\KeyGenerator\RsaKeyGenerator;
use Ux2Dev\Epay\KeyGenerator\KeyResult;
use Ux2Dev\Epay\Exception\SigningException;

test('generates valid RSA key pair', function () {
    $result = RsaKeyGenerator::generate();

    expect($result)->toBeInstanceOf(KeyResult::class)
        ->and($result->privateKey)->toContain('-----BEGIN PRIVATE KEY-----')
        ->and($result->publicKey)->toContain('-----BEGIN PUBLIC KEY-----');
});

test('generated keys can sign and verify', function () {
    $result = RsaKeyGenerator::generate();

    $key = openssl_pkey_get_private($result->privateKey);
    openssl_sign('test', $signature, $key, OPENSSL_ALGO_SHA256);

    $pubKey = openssl_pkey_get_public($result->publicKey);
    $valid = openssl_verify('test', $signature, $pubKey, OPENSSL_ALGO_SHA256);

    expect($valid)->toBe(1);
});

test('generates encrypted key with passphrase', function () {
    $result = RsaKeyGenerator::generate(passphrase: 'mypassword');

    expect($result->privateKey)->toContain('ENCRYPTED')
        ->and(openssl_pkey_get_private($result->privateKey, 'mypassword'))->not->toBeFalse();
});

test('custom key bits', function () {
    $result = RsaKeyGenerator::generate(keyBits: 4096);

    $key = openssl_pkey_get_private($result->privateKey);
    $details = openssl_pkey_get_details($key);

    expect($details['bits'])->toBe(4096);
});

test('default key bits is 2048', function () {
    $result = RsaKeyGenerator::generate();

    $key = openssl_pkey_get_private($result->privateKey);
    $details = openssl_pkey_get_details($key);

    expect($details['bits'])->toBe(2048);
});

test('throws on key bits below 2048', function () {
    RsaKeyGenerator::generate(keyBits: 1024);
})->throws(SigningException::class, 'Key size must be at least 2048 bits');

test('saveToDirectory writes files', function () {
    $result = RsaKeyGenerator::generate();
    $dir = sys_get_temp_dir() . '/epay_test_' . uniqid();
    mkdir($dir);

    $result->saveToDirectory($dir);

    expect(file_exists($dir . '/epay_private.key'))->toBeTrue()
        ->and(file_exists($dir . '/epay_public.key'))->toBeTrue()
        ->and(file_get_contents($dir . '/epay_private.key'))->toBe($result->privateKey)
        ->and(file_get_contents($dir . '/epay_public.key'))->toBe($result->publicKey);

    unlink($dir . '/epay_private.key');
    unlink($dir . '/epay_public.key');
    rmdir($dir);
});
