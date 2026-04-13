<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Config;

use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Enum\SigningMethod;
use Ux2Dev\Epay\Exception\ConfigurationException;

final readonly class MerchantConfig
{
    public function __construct(
        public string $merchantId,
        private string $secret,
        public Environment $environment,
        public Currency $currency = Currency::EUR,
        public SigningMethod $signingMethod = SigningMethod::HmacSha1,
        private ?string $privateKey = null,
        private ?string $privateKeyPassphrase = null,
    ) {
        if ($merchantId === '') {
            throw new ConfigurationException('merchantId must not be empty');
        }

        if ($secret === '') {
            throw new ConfigurationException('secret must not be empty');
        }

        if ($signingMethod === SigningMethod::Rsa && $privateKey === null) {
            throw new ConfigurationException('privateKey is required when signingMethod is Rsa');
        }

        if ($privateKey !== null) {
            $testKey = openssl_pkey_get_private($privateKey, $privateKeyPassphrase ?? '');
            if ($testKey === false) {
                throw new ConfigurationException(
                    'privateKey is not a valid PEM private key (or passphrase is wrong)'
                );
            }
        }
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getPrivateKey(): ?string
    {
        return $this->privateKey;
    }

    public function getPrivateKeyPassphrase(): ?string
    {
        return $this->privateKeyPassphrase;
    }

    public function __debugInfo(): array
    {
        return [
            'merchantId' => $this->merchantId,
            'secret' => $this->secret !== '' ? '[REDACTED]' : '',
            'environment' => $this->environment,
            'currency' => $this->currency,
            'signingMethod' => $this->signingMethod,
            'privateKey' => $this->privateKey !== null ? '[REDACTED]' : null,
            'privateKeyPassphrase' => $this->privateKeyPassphrase !== null ? '[REDACTED]' : null,
        ];
    }

    public function __serialize(): array
    {
        throw new \LogicException(
            'MerchantConfig must not be serialized as it contains secret key material'
        );
    }

    public function __unserialize(array $data): void
    {
        throw new \LogicException(
            'MerchantConfig must not be unserialized'
        );
    }
}
