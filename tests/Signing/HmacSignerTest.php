<?php

declare(strict_types=1);

use Ux2Dev\Epay\Signing\HmacSigner;

beforeEach(function () {
    $this->signer = new HmacSigner('testsecret');
});

test('sign returns lowercase hex string', function () {
    $result = $this->signer->sign('test data');

    expect($result)->toMatch('/^[a-f0-9]{40}$/')
        ->and(strlen($result))->toBe(40);
});

test('sign produces correct HMAC-SHA1', function () {
    $expected = hash_hmac('sha1', 'test data', 'testsecret');

    expect($this->signer->sign('test data'))->toBe($expected);
});

test('sign and verify round trip', function () {
    $data = 'MIN=1000000000\nINVOICE=123456\nAMOUNT=22.80';
    $signature = $this->signer->sign($data);

    expect($this->signer->verify($data, $signature))->toBeTrue();
});

test('verify rejects tampered data', function () {
    $signature = $this->signer->sign('original data');

    expect($this->signer->verify('tampered data', $signature))->toBeFalse();
});

test('verify rejects tampered signature', function () {
    $data = 'test data';
    $this->signer->sign($data);

    expect($this->signer->verify($data, 'deadbeef' . str_repeat('0', 32)))->toBeFalse();
});

test('sign is deterministic', function () {
    $data = 'same input';

    expect($this->signer->sign($data))->toBe($this->signer->sign($data));
});

test('different secrets produce different signatures', function () {
    $other = new HmacSigner('othersecret');
    $data = 'test data';

    expect($this->signer->sign($data))->not->toBe($other->sign($data));
});

test('verify uses constant-time comparison', function () {
    $data = 'test data';
    $signature = $this->signer->sign($data);

    $tampered = substr($signature, 0, -1) . ($signature[-1] === 'a' ? 'b' : 'a');

    expect($this->signer->verify($data, $signature))->toBeTrue()
        ->and($this->signer->verify($data, $tampered))->toBeFalse();
});
