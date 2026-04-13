<?php
declare(strict_types=1);
use Ux2Dev\Epay\Billing\Response\Invoice;
use Ux2Dev\Epay\Billing\Response\InitResponse;
use Ux2Dev\Epay\Billing\Response\ConfirmResponse;

test('Invoice holds data', function () {
    $inv = new Invoice(idn: '12345.001', amount: 5000, shortDesc: 'Taksa vhod', validTo: new DateTimeImmutable('2026-05-01'));
    expect($inv->idn)->toBe('12345.001')->and($inv->amount)->toBe(5000)->and($inv->shortDesc)->toBe('Taksa vhod');
});

test('Invoice toArray formats correctly', function () {
    $inv = new Invoice(idn: '12345.001', amount: 5000, shortDesc: 'Taksa vhod', validTo: new DateTimeImmutable('2026-05-01'), longDesc: 'Detailed info');
    $arr = $inv->toArray();
    expect($arr['IDN'])->toBe('12345.001')->and($arr['AMOUNT'])->toBe('5000')->and($arr['VALIDTO'])->toBe('20260501')->and($arr['LONGDESC'])->toBe('Detailed info');
});

test('InitResponse::success returns STATUS 00 with full data', function () {
    $r = InitResponse::success(idn: '12345', shortDesc: 'Ivan Ivanov, ap. 12', amount: 8000, validTo: new DateTimeImmutable('2026-05-01'));
    $json = json_decode($r->toJson(), true);
    expect($json['STATUS'])->toBe('00')->and($json['IDN'])->toBe('12345')->and($json['SHORTDESC'])->toBe('Ivan Ivanov, ap. 12')->and($json['AMOUNT'])->toBe('8000')->and($json['VALIDTO'])->toBe('20260501');
});

test('InitResponse::success includes LONGDESC when set', function () {
    $r = InitResponse::success(idn: '12345', shortDesc: 'Ivan', amount: 8000, validTo: new DateTimeImmutable('2026-05-01'), longDesc: "Line 1\nLine 2");
    $json = json_decode($r->toJson(), true);
    expect($json['LONGDESC'])->toBe("Line 1\nLine 2");
});

test('InitResponse::success includes invoices', function () {
    $r = InitResponse::success(
        idn: '12345', shortDesc: 'Ivan', amount: 8000, validTo: new DateTimeImmutable('2026-05-01'),
        invoices: [
            new Invoice('12345.001', 5000, 'Taksa vhod', new DateTimeImmutable('2026-05-01')),
            new Invoice('12345.002', 3000, 'Taksa asansor', new DateTimeImmutable('2026-05-01')),
        ],
    );
    $json = json_decode($r->toJson(), true);
    expect($json['INVOICES'])->toHaveCount(2)->and($json['INVOICES'][0]['IDN'])->toBe('12345.001')->and($json['INVOICES'][1]['AMOUNT'])->toBe('3000');
});

test('InitResponse::noObligation returns STATUS 62', function () {
    $json = json_decode(InitResponse::noObligation('12345')->toJson(), true);
    expect($json['STATUS'])->toBe('62');
});

test('InitResponse::invalidSubscriber returns STATUS 14', function () {
    $json = json_decode(InitResponse::invalidSubscriber('12345')->toJson(), true);
    expect($json['STATUS'])->toBe('14');
});

test('InitResponse::unavailable returns STATUS 80', function () {
    $json = json_decode(InitResponse::unavailable()->toJson(), true);
    expect($json['STATUS'])->toBe('80');
});

test('InitResponse::error returns STATUS 96', function () {
    $json = json_decode(InitResponse::error()->toJson(), true);
    expect($json['STATUS'])->toBe('96');
});

test('InitResponse::invalidAmount returns STATUS 13', function () {
    $json = json_decode(InitResponse::invalidAmount()->toJson(), true);
    expect($json['STATUS'])->toBe('13');
});

test('ConfirmResponse::success returns STATUS 00', function () {
    $json = json_decode(ConfirmResponse::success()->toJson(), true);
    expect($json['STATUS'])->toBe('00');
});

test('ConfirmResponse::duplicate returns STATUS 94', function () {
    $json = json_decode(ConfirmResponse::duplicate()->toJson(), true);
    expect($json['STATUS'])->toBe('94');
});

test('ConfirmResponse::error returns STATUS 96', function () {
    $json = json_decode(ConfirmResponse::error()->toJson(), true);
    expect($json['STATUS'])->toBe('96');
});

test('ConfirmResponse::invalidChecksum returns STATUS 93', function () {
    $json = json_decode(ConfirmResponse::invalidChecksum()->toJson(), true);
    expect($json['STATUS'])->toBe('93');
});
