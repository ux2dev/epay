<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\KeyGenerator;

use Ux2Dev\Epay\Exception\SigningException;

final class RsaKeyGenerator
{
    public static function generate(
        int $keyBits = 2048,
        ?string $passphrase = null,
    ): KeyResult {
        if ($keyBits < 2048) {
            throw new SigningException('Key size must be at least 2048 bits');
        }

        $key = openssl_pkey_new([
            'private_key_bits' => $keyBits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new SigningException('Failed to generate RSA key pair: ' . openssl_error_string());
        }

        $privateKeyPem = '';
        $cipher = $passphrase !== null ? 'aes-256-cbc' : null;
        $options = $cipher ? ['encrypt_key_cipher' => $cipher] : [];
        $exported = openssl_pkey_export($key, $privateKeyPem, $passphrase, $options);

        if ($exported === false) {
            throw new SigningException('Failed to export private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new SigningException('Failed to extract public key: ' . openssl_error_string());
        }

        return new KeyResult($privateKeyPem, $details['key']);
    }
}
