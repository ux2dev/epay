<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
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

function mockClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    return new Client(['handler' => HandlerStack::create($mock)]);
}

function makeOtClient(Client $http): OneTouchClient
{
    $config = new MerchantConfig(merchantId: 'TESTAPP01', secret: 'testsecret', environment: Environment::Development);
    return new OneTouchClient($config, $http);
}

test('getAuthorizationUrl returns correct URL', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->getAuthorizationUrl(deviceId: 'user@test.bg', key: 'uniquekey123');
    expect($url)->toContain('https://demo.epay.bg/xdev/api')->and($url)->toContain('APPID=TESTAPP01')->and($url)->toContain('DEVICEID=user%40test.bg')->and($url)->toContain('KEY=uniquekey123');
});

test('getAuthorizationUrl includes optional device info', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->getAuthorizationUrl(deviceId: 'user@test.bg', key: 'key123', deviceName: 'iPhone', os: 'iOS');
    expect($url)->toContain('DEVICE_NAME=iPhone')->and($url)->toContain('OS=iOS');
});

test('getCode returns CodeResponse', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'code' => 'mycode']))]);
    $r = makeOtClient($http)->getCode(deviceId: 'user@test.bg', key: 'key123');
    expect($r)->toBeInstanceOf(CodeResponse::class)->and($r->status)->toBe('OK')->and($r->code)->toBe('mycode');
});

test('getToken returns TokenResponse', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'TOKEN' => 'tok123', 'EXPIRES' => 1720188520, 'KIN' => '9999', 'USERNAME' => 'user1', 'REALNAME' => 'Ivan']))]);
    $r = makeOtClient($http)->getToken(deviceId: 'user@test.bg', code: 'mycode');
    expect($r)->toBeInstanceOf(TokenResponse::class)->and($r->token)->toBe('tok123')->and($r->kin)->toBe('9999');
});

test('invalidateToken sends request', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK']))]);
    makeOtClient($http)->invalidateToken(deviceId: 'user@test.bg', token: 'tok123');
    expect(true)->toBeTrue();
});

test('getUserInfo returns UserInfoResponse', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'GSM' => '0888123456', 'PIC' => 'pic1', 'REAL_NAME' => 'Ivan', 'ID' => '123', 'KIN' => '9999', 'EMAIL' => 'ivan@test.bg']))]);
    $r = makeOtClient($http)->getUserInfo(deviceId: 'user@test.bg', token: 'tok123');
    expect($r)->toBeInstanceOf(UserInfoResponse::class)->and($r->kin)->toBe('9999')->and($r->paymentInstruments)->toBe([]);
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
    expect($r)->toBeInstanceOf(PaymentResponse::class)->and($r->id)->toBe('pay1');
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

test('createNoRegPaymentUrl returns URL', function () {
    $client = makeOtClient(mockClient([]));
    $url = $client->createNoRegPaymentUrl(deviceId: 'user@test.bg', amount: 2280, recipient: '8888', recipientType: 'KIN', description: 'Test', reason: 'fee', show: 'KIN');
    expect($url)->toContain('https://demo.epay.bg/xdev/api')->and($url)->toContain('APPID=TESTAPP01')->and($url)->toContain('AMOUNT=2280');
});

test('getNoRegPaymentStatus returns NoRegPaymentResponse', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'OK', 'payment' => ['STATE' => 3, 'STATE_TEXT' => 'Success', 'NO' => '789', 'paid_with' => ['CARD_TYPE' => 'VISA', 'CARD_TYPE_DESCR' => 'Visa', 'CARD_TYPE_COUNTRY' => 'BG']]]))]);
    $r = makeOtClient($http)->getNoRegPaymentStatus(deviceId: 'user@test.bg', paymentId: 'pay1');
    expect($r)->toBeInstanceOf(NoRegPaymentResponse::class)->and($r->state)->toBe(3);
});

test('throws on error response', function () {
    $http = mockClient([new Response(200, [], json_encode(['status' => 'ERR', 'error' => 'Invalid token']))]);
    makeOtClient($http)->getCode(deviceId: 'user@test.bg', key: 'key123');
})->throws(EpayException::class);
