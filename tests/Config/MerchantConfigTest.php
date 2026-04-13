<?php

declare(strict_types=1);

use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Enum\SigningMethod;
use Ux2Dev\Epay\Exception\ConfigurationException;

beforeEach(function () {
    $this->privateKey = file_get_contents(__DIR__ . '/../fixtures/test_private_key.pem');
});

test('creates valid config with defaults', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
    );

    expect($config->merchantId)->toBe('1000000000')
        ->and($config->environment)->toBe(Environment::Development)
        ->and($config->currency)->toBe(Currency::EUR)
        ->and($config->signingMethod)->toBe(SigningMethod::HmacSha1);
});

test('creates config with RSA signing', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Production,
        signingMethod: SigningMethod::Rsa,
        privateKey: $this->privateKey,
    );

    expect($config->signingMethod)->toBe(SigningMethod::Rsa)
        ->and($config->getPrivateKey())->toBe($this->privateKey);
});

test('creates config with encrypted RSA key', function () {
    $encryptedKey = file_get_contents(__DIR__ . '/../fixtures/test_private_key_encrypted.pem');

    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::Rsa,
        privateKey: $encryptedKey,
        privateKeyPassphrase: 'testpass',
    );

    expect($config->getPrivateKey())->toBe($encryptedKey)
        ->and($config->getPrivateKeyPassphrase())->toBe('testpass');
});

test('secret is accessible via getter', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
    );

    expect($config->getSecret())->toBe('mysecretword');
});

test('throws on empty merchantId', function () {
    new MerchantConfig(
        merchantId: '',
        secret: 'mysecretword',
        environment: Environment::Development,
    );
})->throws(ConfigurationException::class, 'merchantId must not be empty');

test('throws on empty secret', function () {
    new MerchantConfig(
        merchantId: '1000000000',
        secret: '',
        environment: Environment::Development,
    );
})->throws(ConfigurationException::class, 'secret must not be empty');

test('throws when RSA signing requires privateKey', function () {
    new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::Rsa,
    );
})->throws(ConfigurationException::class, 'privateKey is required when signingMethod is Rsa');

test('throws on invalid privateKey PEM', function () {
    new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::Rsa,
        privateKey: 'not-a-valid-pem-key',
    );
})->throws(ConfigurationException::class, 'privateKey is not a valid PEM private key');

test('throws on wrong passphrase for encrypted key', function () {
    $encryptedKey = file_get_contents(__DIR__ . '/../fixtures/test_private_key_encrypted.pem');

    new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::Rsa,
        privateKey: $encryptedKey,
        privateKeyPassphrase: 'wrongpassword',
    );
})->throws(ConfigurationException::class, 'privateKey is not a valid PEM private key');

test('debugInfo redacts secret and privateKey', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::Rsa,
        privateKey: $this->privateKey,
        privateKeyPassphrase: 'testpass',
    );

    $debug = $config->__debugInfo();

    expect($debug['secret'])->toBe('[REDACTED]')
        ->and($debug['privateKey'])->toBe('[REDACTED]')
        ->and($debug['privateKeyPassphrase'])->toBe('[REDACTED]')
        ->and($debug['merchantId'])->toBe('1000000000')
        ->and($debug['environment'])->toBe(Environment::Development);
});

test('debugInfo shows null for unset optional fields', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
    );

    $debug = $config->__debugInfo();

    expect($debug['privateKey'])->toBeNull()
        ->and($debug['privateKeyPassphrase'])->toBeNull();
});

test('serialize throws LogicException', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
    );

    serialize($config);
})->throws(\LogicException::class, 'MerchantConfig must not be serialized');

test('unserialize throws LogicException', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
    );

    $config->__unserialize([]);
})->throws(\LogicException::class, 'MerchantConfig must not be unserialized');

test('HMAC signing does not require privateKey', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::HmacSha1,
    );

    expect($config->getPrivateKey())->toBeNull();
});

test('privateKey accepted for HMAC signing without error', function () {
    $config = new MerchantConfig(
        merchantId: '1000000000',
        secret: 'mysecretword',
        environment: Environment::Development,
        signingMethod: SigningMethod::HmacSha1,
        privateKey: $this->privateKey,
    );

    expect($config->getPrivateKey())->toBe($this->privateKey);
});
