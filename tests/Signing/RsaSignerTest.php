<?php

declare(strict_types=1);

use Ux2Dev\Epay\Signing\RsaSigner;
use Ux2Dev\Epay\Exception\SigningException;

beforeEach(function () {
    $this->privateKey = file_get_contents(__DIR__ . '/../fixtures/test_private_key.pem');
    $this->publicKey = file_get_contents(__DIR__ . '/../fixtures/test_public_key.pem');
    $this->encryptedKey = file_get_contents(__DIR__ . '/../fixtures/test_private_key_encrypted.pem');
});

test('sign returns hex string', function () {
    $signer = new RsaSigner($this->privateKey, $this->publicKey);
    $result = $signer->sign('test data');

    expect($result)->toMatch('/^[a-f0-9]+$/');
});

test('sign and verify round trip', function () {
    $signer = new RsaSigner($this->privateKey, $this->publicKey);
    $data = 'MIN=1000000000\nINVOICE=123456\nAMOUNT=22.80';
    $signature = $signer->sign($data);

    expect($signer->verify($data, $signature))->toBeTrue();
});

test('verify rejects tampered data', function () {
    $signer = new RsaSigner($this->privateKey, $this->publicKey);
    $signature = $signer->sign('original');

    expect($signer->verify('tampered', $signature))->toBeFalse();
});

test('sign with encrypted key and passphrase', function () {
    $signer = new RsaSigner($this->encryptedKey, $this->publicKey, 'testpass');
    $data = 'test data';
    $signature = $signer->sign($data);

    expect($signer->verify($data, $signature))->toBeTrue();
});

test('throws on invalid private key', function () {
    new RsaSigner('not-a-key', $this->publicKey);
})->throws(SigningException::class, 'Failed to load private key');

test('throws on invalid public key', function () {
    new RsaSigner($this->privateKey, 'not-a-key');
})->throws(SigningException::class, 'Failed to load public key');

test('throws on wrong passphrase', function () {
    new RsaSigner($this->encryptedKey, $this->publicKey, 'wrong');
})->throws(SigningException::class, 'Failed to load private key');

test('verify rejects tampered signature', function () {
    $signer = new RsaSigner($this->privateKey, $this->publicKey);
    $data = 'test data';

    expect($signer->verify($data, 'deadbeef'))->toBeFalse();
});
