<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Signing;

use Ux2Dev\Epay\Exception\SigningException;

final class RsaSigner implements SignerInterface
{
    private \OpenSSLAsymmetricKey $privateKeyResource;
    private \OpenSSLAsymmetricKey $publicKeyResource;

    public function __construct(
        string $privateKey,
        string $publicKey,
        ?string $passphrase = null,
    ) {
        $privKey = openssl_pkey_get_private($privateKey, $passphrase ?? '');
        if ($privKey === false) {
            throw new SigningException('Failed to load private key');
        }
        $this->privateKeyResource = $privKey;

        $pubKey = openssl_pkey_get_public($publicKey);
        if ($pubKey === false) {
            throw new SigningException('Failed to load public key');
        }
        $this->publicKeyResource = $pubKey;
    }

    public function sign(string $data): string
    {
        $result = openssl_sign($data, $signature, $this->privateKeyResource, OPENSSL_ALGO_SHA256);
        if ($result === false) {
            throw new SigningException('Failed to sign data: ' . openssl_error_string());
        }

        return bin2hex($signature);
    }

    public function verify(string $data, string $signature): bool
    {
        $binary = @hex2bin($signature);
        if ($binary === false) {
            return false;
        }

        $result = openssl_verify($data, $binary, $this->publicKeyResource, OPENSSL_ALGO_SHA256);
        if ($result === -1) {
            throw new SigningException('Signature verification error: ' . openssl_error_string());
        }

        return $result === 1;
    }
}
