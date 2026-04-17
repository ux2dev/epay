<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ux2Dev\Epay\OneTouch\OneTouchClient;
use Ux2Dev\Epay\OneTouch\Response\TokenResponse;
use Ux2Dev\Epay\OneTouch\Response\CodeResponse;
use Ux2Dev\Epay\OneTouch\Response\UserInfoResponse;
use Ux2Dev\Epay\OneTouch\Response\PaymentResponse;
use Ux2Dev\Epay\OneTouch\Response\NoRegPaymentResponse;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Exception\EpayException;

function mockClient(array $responses, array &$history = []): Client
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    return new Client(['handler' => $stack]);
}

function makeOtClient(Client $http): OneTouchClient
{
    $config = new MerchantConfig(merchantId: 'TESTAPP01', secret: 'testsecret', environment: Environment::Development);
    $factory = new HttpFactory();
    return new OneTouchClient($config, $http, $factory, $factory);
}

test('getAuthorizationUrl returns correct URL', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->getAuthorizationUrl(deviceId: 'user@test.bg', key: 'uniquekey123');
    expect($url)
        ->toContain('https://demo.epay.bg/xdev/mobile/api/start')
        ->and($url)->toContain('APPID=TESTAPP01')
        ->and($url)->toContain('DEVICEID=user%40test.bg')
        ->and($url)->toContain('KEY=uniquekey123');
});

test('getAuthorizationUrl includes optional device info', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->getAuthorizationUrl(deviceId: 'user@test.bg', key: 'key123', deviceName: 'iPhone', os: 'iOS');
    expect($url)->toContain('DEVICE_NAME=iPhone')->and($url)->toContain('OS=iOS');
});

test('getCode hits double-/api/ path and returns CodeResponse', function () {
    $history = [];
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'code' => 'mycode']))], $history);
    $r = makeOtClient($http)->getCode(deviceId: 'user@test.bg', key: 'key123');
    expect($r)->toBeInstanceOf(CodeResponse::class)->and($r->code)->toBe('mycode');
    expect((string) $history[0]['request']->getUri())->toContain('/xdev/api/api/code/get')
        ->and((string) $history[0]['request']->getUri())->toContain('APPCHECK=');
});

test('getToken hits double-/api/ path and returns TokenResponse', function () {
    $history = [];
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'TOKEN' => 'tok123', 'EXPIRES' => 1720188520, 'KIN' => '9999', 'USERNAME' => 'user1', 'REALNAME' => 'Ivan']))], $history);
    $r = makeOtClient($http)->getToken(deviceId: 'user@test.bg', code: 'mycode');
    expect($r->token)->toBe('tok123');
    expect((string) $history[0]['request']->getUri())->toContain('/xdev/api/api/token/get');
});

test('invalidateToken hits double-/api/ path', function () {
    $history = [];
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK']))], $history);
    makeOtClient($http)->invalidateToken(deviceId: 'user@test.bg', token: 'tok123');
    expect((string) $history[0]['request']->getUri())->toContain('/xdev/api/api/token/invalidate');
});

test('getUserInfo hits single-/api/ path and returns UserInfoResponse', function () {
    $history = [];
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'GSM' => '0888123456', 'PIC' => 'pic1', 'REAL_NAME' => 'Ivan', 'ID' => '123', 'KIN' => '9999', 'EMAIL' => 'ivan@test.bg']))], $history);
    $r = makeOtClient($http)->getUserInfo(deviceId: 'user@test.bg', token: 'tok123');
    expect($r->kin)->toBe('9999')->and($r->paymentInstruments)->toBe([]);
    expect((string) $history[0]['request']->getUri())->toContain('/xdev/api/user/info')
        ->and((string) $history[0]['request']->getUri())->not->toContain('/api/api/user/info');
});

test('getUserInfo with payment instruments', function () {
    $http = mockClient([new Response(200, [], json_encode([
        'status' => 'OK', 'GSM' => '0888123456', 'PIC' => 'pic1', 'REAL_NAME' => 'Ivan', 'ID' => '123', 'KIN' => '9999', 'EMAIL' => 'ivan@test.bg',
        'PAYMENT_INSTRUMENTS' => [['ID' => 'pin1', 'VERIFIED' => 1, 'BALANCE' => 50000, 'TYPE' => 1, 'NAME' => 'Visa', 'EXPIRES' => '12/28']],
    ]))]);
    $r = makeOtClient($http)->getUserInfo(deviceId: 'user@test.bg', token: 'tok123', withPaymentInstruments: true);
    expect($r->paymentInstruments)->toHaveCount(1);
});

test('initPayment returns PaymentResponse with ID', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'payment' => ['ID' => 'pay1']]))]);
    $r = makeOtClient($http)->initPayment(deviceId: 'user@test.bg', token: 'tok123');
    expect($r->id)->toBe('pay1');
});

test('sendPayment returns PaymentResponse with state', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'payment' => ['ID' => 'pay1', 'STATE' => 3, 'STATE_TEXT' => 'Success', 'NO' => '123456', 'AMOUNT' => 2280, 'TAX' => 50, 'TOTAL' => 2330]]))]);
    $r = makeOtClient($http)->sendPayment(deviceId: 'user@test.bg', token: 'tok123', paymentId: 'pay1', amount: 2280, recipient: '8888', recipientType: 'KIN', description: 'Test', reason: 'fee', paymentInstrumentId: 'pin1', show: 'KIN');
    expect($r->state)->toBe(3)->and($r->no)->toBe('123456');
});

test('getPaymentStatus returns PaymentResponse', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'payment' => ['ID' => 'pay1', 'STATE' => 3, 'STATE_TEXT' => 'Done', 'NO' => '123456']]))]);
    $r = makeOtClient($http)->getPaymentStatus(deviceId: 'user@test.bg', token: 'tok123', paymentId: 'pay1');
    expect($r->state)->toBe(3);
});

test('createNoRegPaymentUrl returns mobile URL with CHECKSUM', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->createNoRegPaymentUrl(
        deviceId: 'user@test.bg',
        id: 'order-1',
        amount: 2280,
        recipient: '8888',
        recipientType: 'KIN',
        description: 'Test',
        reason: 'fee',
    );
    expect($url)
        ->toContain('https://demo.epay.bg/xdev/mobile/api/payment/noreg/send')
        ->and($url)->toContain('APPID=TESTAPP01')
        ->and($url)->toContain('AMOUNT=2280')
        ->and($url)->toContain('ID=order-1')
        ->and($url)->toContain('CHECKSUM=');
});

test('createNoRegPaymentUrl with saveCard includes SAVECARD=1', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->createNoRegPaymentUrl(
        deviceId: 'user@test.bg',
        id: 'order-1',
        amount: 100,
        recipient: '8888',
        recipientType: 'KIN',
        description: 'Test',
        reason: 'fee',
        saveCard: true,
    );
    expect($url)->toContain('SAVECARD=1');
});

test('getNoRegPaymentStatus hits double-/api/ path with CHECKSUM signing', function () {
    $history = [];
    $http = mockClient([new Response(200, [], json_encode([
        'status' => 'OK',
        'payment' => ['STATE' => 3, 'STATE_TEXT' => 'Success', 'NO' => '789', 'TOKEN' => 'reuse-tok', 'paid_with' => ['CARD_TYPE' => 'VISA', 'CARD_TYPE_DESCR' => 'Visa', 'CARD_TYPE_COUNTRY' => 'BG']],
    ]))], $history);

    $r = makeOtClient($http)->getNoRegPaymentStatus(
        deviceId: 'user@test.bg',
        paymentId: 'pay1',
        amount: 2280,
        recipient: '8888',
        recipientType: 'KIN',
        description: 'Test',
        reason: 'fee',
    );

    expect($r)->toBeInstanceOf(NoRegPaymentResponse::class)
        ->and($r->state)->toBe(3)
        ->and($r->token)->toBe('reuse-tok');

    $uri = (string) $history[0]['request']->getUri();
    expect($uri)->toContain('/xdev/api/api/payment/noreg/send/status')
        ->and($uri)->toContain('CHECKSUM=')
        ->and($uri)->not->toContain('APPCHECK=')
        ->and($uri)->toContain('AMOUNT=2280')
        ->and($uri)->toContain('RCPT=8888')
        ->and($uri)->toContain('RCPT_TYPE=KIN')
        ->and($uri)->toContain('DESCRIPTION=Test')
        ->and($uri)->toContain('REASON=fee');
});

test('getNoRegPaymentStatus parses nullable fields', function () {
    $http = mockClient([new Response(200, [], json_encode([
        'status' => 'OK',
        'payment' => ['STATE' => 2],
    ]))]);

    $r = makeOtClient($http)->getNoRegPaymentStatus(
        deviceId: 'd', paymentId: 'p', amount: 1, recipient: 'r', recipientType: 'KIN', description: 'x', reason: 'y',
    );

    expect($r->state)->toBe(2)
        ->and($r->stateText)->toBeNull()
        ->and($r->no)->toBeNull()
        ->and($r->token)->toBeNull()
        ->and($r->paidWith)->toBeNull()
        ->and($r->paymentInstrument)->toBeNull();
});

test('throws on error response', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'ERR', 'error' => 'Invalid token']))]);
    makeOtClient($http)->getCode(deviceId: 'user@test.bg', key: 'key123');
})->throws(EpayException::class);
