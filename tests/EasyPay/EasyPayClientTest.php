<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\EasyPay\EasyPayClient;
use Ux2Dev\Epay\EasyPay\Response\EasyPayCodeResponse;
use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;

function mockEasyPayClient(array $responses, array &$history = []): EasyPayClient
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $http = new Client(['handler' => $stack]);
    $config = new MerchantConfig(merchantId: '1000000000', secret: 'testsecret', environment: Environment::Development);
    $factory = new HttpFactory();
    return new EasyPayClient($config, $http, $factory, $factory);
}

test('createCode sends GET to ezp/reg_bill.cgi with ENCODED and CHECKSUM', function () {
    $history = [];
    $body = "IDN=1234567890\r\nSTATUS=00";
    $http = mockEasyPayClient([new Response(200, [], $body)], $history);

    $result = $http->createCode(
        invoice: 'INV001',
        amount: '10.00',
        expirationDate: '20.04.2026',
    );

    expect($result)->toBeInstanceOf(EasyPayCodeResponse::class)
        ->and($result->idn)->toBe('1234567890')
        ->and($result->isSuccess())->toBeTrue();

    $uri = (string) $history[0]['request']->getUri();
    expect($uri)->toContain('https://demo.epay.bg/ezp/reg_bill.cgi')
        ->and($uri)->toContain('ENCODED=')
        ->and($uri)->toContain('CHECKSUM=');
});

test('createCode returns error response', function () {
    $body = "ERR=INVALID_AMOUNT\r\nMESSAGE=Amount too low";
    $client = mockEasyPayClient([new Response(200, [], $body)]);

    $result = $client->createCode(invoice: 'INV002', amount: '0.01', expirationDate: '20.04.2026');

    expect($result->isSuccess())->toBeFalse()
        ->and($result->error)->toBe('INVALID_AMOUNT')
        ->and($result->errorMessage)->toBe('Amount too low')
        ->and($result->idn)->toBeNull();
});

test('createCode parses windows-1251 response', function () {
    $body = iconv('UTF-8', 'windows-1251', "IDN=9876543210\r\nSTATUS=00\r\nMESSAGE=Успешно");
    $client = mockEasyPayClient([new Response(200, [], $body)]);

    $result = $client->createCode(invoice: 'INV003', amount: '5.00', expirationDate: '20.04.2026');

    expect($result->idn)->toBe('9876543210')
        ->and($result->isSuccess())->toBeTrue();
});

test('createCode passes optional parameters', function () {
    $history = [];
    $client = mockEasyPayClient([new Response(200, [], "IDN=1111111111\r\nSTATUS=00")], $history);

    $client->createCode(
        invoice: 'INV004',
        amount: '25.50',
        expirationDate: '25.04.2026',
        email: 'test@example.com',
        description: 'Test payment',
        currency: Currency::BGN,
    );

    $uri = (string) $history[0]['request']->getUri();
    expect($uri)->toContain('ENCODED=');

    // Decode the ENCODED param to verify contents
    parse_str(parse_url($uri, PHP_URL_QUERY), $params);
    $decoded = base64_decode($params['ENCODED']);
    expect($decoded)->toContain('EMAIL=test@example.com')
        ->and($decoded)->toContain('DESCR=Test payment')
        ->and($decoded)->toContain('CURRENCY=BGN')
        ->and($decoded)->toContain('INVOICE=INV004')
        ->and($decoded)->toContain('AMOUNT=25.50');
});

test('createCode uses GET method', function () {
    $history = [];
    $client = mockEasyPayClient([new Response(200, [], "IDN=0000000000")], $history);

    $client->createCode(invoice: 'INV005', amount: '1.00', expirationDate: '20.04.2026');

    expect($history[0]['request']->getMethod())->toBe('GET');
});

test('createCode CHECKSUM verifies against ENCODED', function () {
    $history = [];
    $client = mockEasyPayClient([new Response(200, [], "IDN=0000000000")], $history);

    $client->createCode(invoice: 'INV006', amount: '1.00', expirationDate: '20.04.2026');

    parse_str(parse_url((string) $history[0]['request']->getUri(), PHP_URL_QUERY), $params);
    $expected = hash_hmac('sha1', $params['ENCODED'], 'testsecret');
    expect($params['CHECKSUM'])->toBe($expected);
});
