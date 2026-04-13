<?php
declare(strict_types=1);
use Ux2Dev\Epay\OneTouch\Response\TokenResponse;
use Ux2Dev\Epay\OneTouch\Response\CodeResponse;
use Ux2Dev\Epay\OneTouch\Response\UserInfoResponse;
use Ux2Dev\Epay\OneTouch\Response\PaymentInstrument;
use Ux2Dev\Epay\OneTouch\Response\PaidWith;
use Ux2Dev\Epay\OneTouch\Response\PaymentResponse;
use Ux2Dev\Epay\OneTouch\Response\NoRegPaymentResponse;

test('TokenResponse fromArray', function () {
    $r = TokenResponse::fromArray(['status' => 'OK', 'TOKEN' => 'abc123', 'EXPIRES' => 1720188520, 'KIN' => '9999', 'USERNAME' => 'user1', 'REALNAME' => 'Ivan Ivanov']);
    expect($r->token)->toBe('abc123')->and($r->expires)->toBe(1720188520)->and($r->kin)->toBe('9999')->and($r->username)->toBe('user1')->and($r->realName)->toBe('Ivan Ivanov');
});

test('CodeResponse fromArray OK', function () {
    $r = CodeResponse::fromArray(['status' => 'OK', 'code' => 'mycode123']);
    expect($r->status)->toBe('OK')->and($r->code)->toBe('mycode123');
});

test('CodeResponse pending', function () {
    $r = CodeResponse::fromArray(['status' => 'PENDING']);
    expect($r->status)->toBe('PENDING')->and($r->code)->toBeNull();
});

test('PaymentInstrument fromArray', function () {
    $pi = PaymentInstrument::fromArray(['ID' => 'pin1', 'VERIFIED' => 1, 'BALANCE' => 50000, 'TYPE' => 1, 'NAME' => 'Visa ****1234', 'EXPIRES' => '12/28']);
    expect($pi->id)->toBe('pin1')->and($pi->verified)->toBe(1)->and($pi->balance)->toBe(50000)->and($pi->type)->toBe(1)->and($pi->name)->toBe('Visa ****1234')->and($pi->expires)->toBe('12/28');
});

test('UserInfoResponse without instruments', function () {
    $r = UserInfoResponse::fromArray(['status' => 'OK', 'GSM' => '0888123456', 'PIC' => 'pic1', 'REAL_NAME' => 'Ivan', 'ID' => '123', 'KIN' => '9999', 'EMAIL' => 'ivan@test.bg']);
    expect($r->gsm)->toBe('0888123456')->and($r->realName)->toBe('Ivan')->and($r->kin)->toBe('9999')->and($r->paymentInstruments)->toBe([]);
});

test('UserInfoResponse with instruments', function () {
    $r = UserInfoResponse::fromArray([
        'status' => 'OK', 'GSM' => '0888123456', 'PIC' => 'pic1', 'REAL_NAME' => 'Ivan', 'ID' => '123', 'KIN' => '9999', 'EMAIL' => 'ivan@test.bg',
        'PAYMENT_INSTRUMENTS' => [['ID' => 'pin1', 'VERIFIED' => 1, 'BALANCE' => 50000, 'TYPE' => 1, 'NAME' => 'Visa', 'EXPIRES' => '12/28']],
    ]);
    expect($r->paymentInstruments)->toHaveCount(1)->and($r->paymentInstruments[0])->toBeInstanceOf(PaymentInstrument::class);
});

test('PaymentResponse fromArray full', function () {
    $r = PaymentResponse::fromArray(['status' => 'OK', 'payment' => ['ID' => 'pay1', 'STATE' => 3, 'STATE_TEXT' => 'Success', 'NO' => '123456', 'AMOUNT' => 2280, 'TAX' => 50, 'TOTAL' => 2330]]);
    expect($r->id)->toBe('pay1')->and($r->state)->toBe(3)->and($r->stateText)->toBe('Success')->and($r->no)->toBe('123456')->and($r->amount)->toBe(2280);
});

test('PaymentResponse fromArray init only', function () {
    $r = PaymentResponse::fromArray(['status' => 'OK', 'payment' => ['ID' => 'pay1']]);
    expect($r->id)->toBe('pay1')->and($r->state)->toBeNull()->and($r->no)->toBeNull();
});

test('PaidWith fromArray', function () {
    $pw = PaidWith::fromArray(['CARD_TYPE' => 'VISA', 'CARD_TYPE_DESCR' => 'Visa Classic', 'CARD_TYPE_COUNTRY' => 'BG']);
    expect($pw->cardType)->toBe('VISA')->and($pw->cardTypeDescr)->toBe('Visa Classic')->and($pw->cardTypeCountry)->toBe('BG');
});

test('NoRegPaymentResponse with paidWith', function () {
    $r = NoRegPaymentResponse::fromArray(['status' => 'OK', 'payment' => ['STATE' => 3, 'STATE_TEXT' => 'Success', 'NO' => '789', 'paid_with' => ['CARD_TYPE' => 'MC', 'CARD_TYPE_DESCR' => 'Mastercard', 'CARD_TYPE_COUNTRY' => 'BG']]]);
    expect($r->state)->toBe(3)->and($r->no)->toBe('789')->and($r->paidWith)->toBeInstanceOf(PaidWith::class)->and($r->paymentInstrument)->toBeNull();
});

test('NoRegPaymentResponse with paymentInstrument', function () {
    $r = NoRegPaymentResponse::fromArray(['status' => 'OK', 'payment' => ['STATE' => 3, 'STATE_TEXT' => 'Success', 'NO' => '789', 'payment_instrument' => ['ID' => 'pin1', 'VERIFIED' => 1, 'BALANCE' => 0, 'TYPE' => 1, 'NAME' => 'Visa', 'EXPIRES' => '12/28']]]);
    expect($r->paymentInstrument)->toBeInstanceOf(PaymentInstrument::class)->and($r->paidWith)->toBeNull();
});
