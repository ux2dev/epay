<?php

declare(strict_types=1);

namespace Ux2Dev\Epay\Exception;

class InvalidResponseException extends EpayException
{
    private const SENSITIVE_KEYS = ['CHECKSUM', 'ENCODED', 'SIGNATURE', 'TOKEN'];

    /** @var array<string, mixed> */
    private readonly array $responseData;

    public function __construct(
        string $message,
        array $responseData = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        foreach (self::SENSITIVE_KEYS as $key) {
            if (isset($responseData[$key])) {
                $responseData[$key] = '[REDACTED]';
            }
        }

        $this->responseData = $responseData;
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, mixed> */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
