<?php
return [
    'default' => 'main',
    'merchants' => [
        'main' => [
            'merchant_id' => env('EPAY_MERCHANT_ID'),
            'secret' => env('EPAY_SECRET'),
            'environment' => env('EPAY_ENVIRONMENT', 'production'),
            'currency' => env('EPAY_CURRENCY', 'EUR'),
            'signing_method' => env('EPAY_SIGNING_METHOD', 'hmac'),
            'private_key' => env('EPAY_PRIVATE_KEY'),
            'private_key_passphrase' => env('EPAY_PRIVATE_KEY_PASSPHRASE'),
            'url_ok' => env('EPAY_URL_OK'),
            'url_cancel' => env('EPAY_URL_CANCEL'),
            'notification_url' => env('EPAY_NOTIFICATION_URL'),
        ],
    ],
];
